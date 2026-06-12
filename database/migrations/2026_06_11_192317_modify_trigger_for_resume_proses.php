<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->upMysql();
        } elseif ($driver === 'pgsql') {
            $this->upPgsql();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Untuk down, bisa dikembalikan ke logika lama, tapi saya biarkan kosong/minimal 
        // karena ini adalah perombakan besar yang sifatnya permanen untuk business logic.
    }

    private function upMysql(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS mesin_after_update_status');

        DB::statement(<<<'SQL'
CREATE TRIGGER mesin_after_update_status
AFTER UPDATE ON mesins
FOR EACH ROW
BEGIN
    DECLARE proses_aktif_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE proses_selanjutnya_id BIGINT UNSIGNED DEFAULT NULL;

    IF OLD.status != NEW.status THEN

        -- KASUS 1: MESIN OFF (1 -> 0)
        IF OLD.status = 1 AND NEW.status = 0 THEN
            SELECT id INTO proses_aktif_id
            FROM proses
            WHERE mesin_id = NEW.id
              AND mulai IS NOT NULL
              AND selesai IS NULL
            ORDER BY `order` ASC, id ASC
            LIMIT 1;

            -- Jika ada proses aktif, JANGAN diselesaikan, cukup PAUSE (Freeze)
            IF proses_aktif_id IS NOT NULL THEN
                UPDATE proses
                SET is_paused = true,
                    updated_at = NOW()
                WHERE id = proses_aktif_id;
            END IF;
        END IF;

        -- KASUS 2: MESIN ON (0 -> 1)
        IF OLD.status = 0 AND NEW.status = 1 THEN
            
            -- Cek apakah ada proses yang sedang berjalan (aktif) namun ter-pause dari sesi sebelumnya
            SELECT id INTO proses_aktif_id
            FROM proses
            WHERE mesin_id = NEW.id
              AND mulai IS NOT NULL
              AND selesai IS NULL
            ORDER BY `order` ASC, id ASC
            LIMIT 1;

            -- Jika ada proses aktif (ter-pause), otomatis unpause
            IF proses_aktif_id IS NOT NULL THEN
                UPDATE proses
                SET is_paused = false,
                    updated_at = NOW()
                WHERE id = proses_aktif_id;
            ELSE
                -- TAPI JIKA TIDAK ADA PROSES AKTIF (Mesin benar-benar idle dan menyala kembali)
                -- Maka jalankan proses selanjutnya otomatis
                SELECT id INTO proses_selanjutnya_id
                FROM proses
                WHERE mesin_id = NEW.id
                  AND mulai IS NULL
                  AND selesai IS NULL
                  AND `order` > 0
                  AND NOT EXISTS (
                      SELECT 1
                      FROM approvals a
                      WHERE a.proses_id = proses.id
                        AND a.status = 'pending'
                        AND a.action = 'create_reprocess'
                        AND a.type IN ('FM', 'VP')
                  )
                ORDER BY `order` ASC, id ASC
                LIMIT 1;

                IF proses_selanjutnya_id IS NOT NULL THEN
                    -- Auto reject pending approvals lama
                    UPDATE approvals
                    SET status = 'rejected',
                        note = CASE
                            WHEN note IS NULL OR note = '' THEN 'Auto rejected by system: proses otomatis berjalan saat mesin ON.'
                            ELSE CONCAT(note, ' | Auto rejected by system: proses otomatis berjalan saat mesin ON.')
                        END,
                        approved_by = NULL,
                        updated_at = NOW()
                    WHERE proses_id = proses_selanjutnya_id
                      AND status = 'pending'
                      AND type = 'FM'
                      AND action IN ('edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position');

                    -- Jalankan proses
                    UPDATE proses
                    SET mulai = NOW(),
                        `order` = 0,
                        updated_at = NOW()
                    WHERE id = proses_selanjutnya_id;

                    SET @row_number = 0;
                    UPDATE proses
                    SET `order` = (@row_number := @row_number + 1),
                        updated_at = NOW()
                    WHERE mesin_id = NEW.id
                      AND mulai IS NULL
                      AND selesai IS NULL
                      AND id != proses_selanjutnya_id
                    ORDER BY `order` ASC, id ASC;
                END IF;
            END IF;
        END IF;

    END IF;
END;
SQL);
    }

    private function upPgsql(): void
    {
        DB::unprepared('
            CREATE OR REPLACE FUNCTION mesin_after_update_status_func()
            RETURNS TRIGGER AS $$
            DECLARE
                proses_aktif_id BIGINT;
                proses_selanjutnya_id BIGINT;
            BEGIN
                IF OLD.status IS DISTINCT FROM NEW.status THEN

                    -- KASUS 1: MESIN OFF (1 -> 0)
                    IF OLD.status IS TRUE AND NEW.status IS FALSE THEN
                        SELECT id INTO proses_aktif_id
                        FROM proses
                        WHERE mesin_id = NEW.id
                          AND mulai IS NOT NULL
                          AND selesai IS NULL
                        ORDER BY "order" ASC, id ASC
                        LIMIT 1;

                        IF proses_aktif_id IS NOT NULL THEN
                            UPDATE proses
                            SET is_paused = true,
                                updated_at = NOW()
                            WHERE id = proses_aktif_id;
                        END IF;
                    END IF;

                    -- KASUS 2: MESIN ON (0 -> 1)
                    IF OLD.status IS FALSE AND NEW.status IS TRUE THEN
                        SELECT id INTO proses_aktif_id
                        FROM proses
                        WHERE mesin_id = NEW.id
                          AND mulai IS NOT NULL
                          AND selesai IS NULL
                        ORDER BY "order" ASC, id ASC
                        LIMIT 1;

                        -- Jika ada proses aktif (ter-pause), otomatis unpause
                        IF proses_aktif_id IS NOT NULL THEN
                            UPDATE proses
                            SET is_paused = false,
                                updated_at = NOW()
                            WHERE id = proses_aktif_id;
                        ELSE
                            SELECT id INTO proses_selanjutnya_id
                            FROM proses
                            WHERE mesin_id = NEW.id
                              AND mulai IS NULL
                              AND selesai IS NULL
                              AND "order" > 0
                              AND NOT EXISTS (
                                  SELECT 1
                                  FROM approvals a
                                  WHERE a.proses_id = proses.id
                                    AND a.status = \'pending\'
                                    AND a.action = \'create_reprocess\'
                                    AND a.type IN (\'FM\', \'VP\')
                              )
                            ORDER BY "order" ASC, id ASC
                            LIMIT 1;

                            IF proses_selanjutnya_id IS NOT NULL THEN
                                UPDATE approvals
                                SET status = \'rejected\',
                                    note = CASE
                                        WHEN note IS NULL OR note = \'\' THEN \'Auto rejected by system: proses otomatis berjalan saat mesin ON.\'
                                        ELSE note || \' | Auto rejected by system: proses otomatis berjalan saat mesin ON.\'
                                    END,
                                    approved_by = NULL,
                                    updated_at = NOW()
                                WHERE proses_id = proses_selanjutnya_id
                                  AND status = \'pending\'
                                  AND type = \'FM\'
                                  AND action IN (\'edit_cycle_time\', \'delete_proses\', \'move_machine\', \'swap_position\');

                                UPDATE proses
                                SET mulai = NOW(),
                                    "order" = 0,
                                    updated_at = NOW()
                                WHERE id = proses_selanjutnya_id;

                                WITH numbered AS (
                                    SELECT id, ROW_NUMBER() OVER (ORDER BY "order" ASC, id ASC) AS new_order
                                    FROM proses
                                    WHERE mesin_id = NEW.id
                                      AND mulai IS NULL
                                      AND selesai IS NULL
                                      AND id != proses_selanjutnya_id
                                )
                                UPDATE proses p
                                SET "order" = n.new_order, updated_at = NOW()
                                FROM numbered n
                                WHERE p.id = n.id;
                            END IF;
                        END IF;
                    END IF;

                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS mesin_after_update_status ON mesins;
            CREATE TRIGGER mesin_after_update_status
                AFTER UPDATE ON mesins
                FOR EACH ROW
                EXECUTE FUNCTION mesin_after_update_status_func();
        ');
    }
};
