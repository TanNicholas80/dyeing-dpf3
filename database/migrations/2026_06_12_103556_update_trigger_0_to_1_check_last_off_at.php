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

    public function down(): void
    {
        // Revert is complex, skip for now
    }

    private function upMysql(): void
    {
        DB::unprepared("
        DROP TRIGGER IF EXISTS mesin_after_update_status;
        CREATE TRIGGER mesin_after_update_status
        AFTER UPDATE ON mesins
        FOR EACH ROW
        BEGIN
            DECLARE proses_aktif_id BIGINT UNSIGNED DEFAULT NULL;
            DECLARE latest_approval_status VARCHAR(50);
            DECLARE proses_selanjutnya_id BIGINT UNSIGNED DEFAULT NULL;

            IF OLD.status != NEW.status THEN
                -- Jika mesin mati (1 -> 0)
                IF OLD.status = 1 AND NEW.status = 0 THEN
                    SELECT id INTO proses_aktif_id
                    FROM proses
                    WHERE mesin_id = NEW.id
                      AND mulai IS NOT NULL
                      AND selesai IS NULL
                    ORDER BY `order` ASC, id ASC
                    LIMIT 1;

                    IF proses_aktif_id IS NOT NULL THEN
                        UPDATE proses
                        SET is_paused = true,
                            updated_at = NOW()
                        WHERE id = proses_aktif_id;
                    END IF;
                END IF;

                -- Jika mesin nyala (0 -> 1)
                IF OLD.status = 0 AND NEW.status = 1 THEN
                    SELECT id INTO proses_aktif_id
                    FROM proses
                    WHERE mesin_id = NEW.id
                      AND mulai IS NOT NULL
                      AND selesai IS NULL
                    ORDER BY `order` ASC, id ASC
                    LIMIT 1;

                    IF proses_aktif_id IS NOT NULL THEN
                        -- Check if there is an approved 'pause_proses' approval for this process 
                        -- that was created AFTER the machine turned off
                        SELECT status INTO latest_approval_status
                        FROM approvals
                        WHERE proses_id = proses_aktif_id
                          AND action = 'pause_proses'
                          AND created_at >= NEW.last_off_at
                        ORDER BY created_at DESC
                        LIMIT 1;

                        -- Only unpause if the latest approval is approved
                        IF latest_approval_status = 'approved' THEN
                            UPDATE proses
                            SET is_paused = false,
                                mulai = DATE_ADD(mulai, INTERVAL TIMESTAMPDIFF(SECOND, updated_at, NOW()) SECOND),
                                updated_at = NOW()
                            WHERE id = proses_aktif_id;
                        END IF;
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
        ");
    }

    private function upPgsql(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION mesin_after_update_status_func()
            RETURNS TRIGGER AS $$
            DECLARE
                proses_aktif_id BIGINT;
                latest_approval_status VARCHAR(50);
                proses_selanjutnya_id BIGINT;
            BEGIN
                IF OLD.status != NEW.status THEN
                    -- Jika mesin mati (1 -> 0)
                    IF OLD.status = true AND NEW.status = false THEN
                        SELECT id INTO proses_aktif_id
                        FROM proses
                        WHERE mesin_id = NEW.id
                          AND mulai IS NOT NULL
                          AND selesai IS NULL
                        ORDER BY \"order\" ASC, id ASC
                        LIMIT 1;

                        IF proses_aktif_id IS NOT NULL THEN
                            UPDATE proses
                            SET is_paused = true,
                                updated_at = NOW()
                            WHERE id = proses_aktif_id;
                        END IF;
                    END IF;

                    -- Jika mesin nyala (0 -> 1)
                    IF OLD.status = false AND NEW.status = true THEN
                        SELECT id INTO proses_aktif_id
                        FROM proses
                        WHERE mesin_id = NEW.id
                          AND mulai IS NOT NULL
                          AND selesai IS NULL
                        ORDER BY \"order\" ASC, id ASC
                        LIMIT 1;

                        IF proses_aktif_id IS NOT NULL THEN
                            -- Check if there is an approved 'pause_proses' approval for this process
                            -- that was created AFTER the machine turned off
                            SELECT status INTO latest_approval_status
                            FROM approvals
                            WHERE proses_id = proses_aktif_id
                              AND action = 'pause_proses'
                              AND created_at >= NEW.last_off_at
                            ORDER BY created_at DESC
                            LIMIT 1;

                            -- Only unpause if the latest approval is approved
                            IF latest_approval_status = 'approved' THEN
                                UPDATE proses
                                SET is_paused = false,
                                    mulai = mulai + (EXTRACT(EPOCH FROM NOW() - updated_at) * INTERVAL '1 second'),
                                    updated_at = NOW()
                                WHERE id = proses_aktif_id;
                            END IF;
                        ELSE
                            -- TAPI JIKA TIDAK ADA PROSES AKTIF (Mesin benar-benar idle dan menyala kembali)
                            -- Maka jalankan proses selanjutnya otomatis
                            SELECT id INTO proses_selanjutnya_id
                            FROM proses
                            WHERE mesin_id = NEW.id
                              AND mulai IS NULL
                              AND selesai IS NULL
                              AND \"order\" > 0
                              AND NOT EXISTS (
                                  SELECT 1
                                  FROM approvals a
                                  WHERE a.proses_id = proses.id
                                    AND a.status = 'pending'
                                    AND a.action = 'create_reprocess'
                                    AND a.type IN ('FM', 'VP')
                              )
                            ORDER BY \"order\" ASC, id ASC
                            LIMIT 1;

                            IF proses_selanjutnya_id IS NOT NULL THEN
                                -- Auto reject pending approvals lama
                                UPDATE approvals
                                SET status = 'rejected',
                                    note = CASE
                                        WHEN note IS NULL OR note = '' THEN 'Auto rejected by system: proses otomatis berjalan saat mesin ON.'
                                        ELSE note || ' | Auto rejected by system: proses otomatis berjalan saat mesin ON.'
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
                                    \"order\" = 0,
                                    updated_at = NOW()
                                WHERE id = proses_selanjutnya_id;

                                -- PostgreSQL update with subquery for numbering
                                UPDATE proses
                                SET \"order\" = t.new_order,
                                    updated_at = NOW()
                                FROM (
                                    SELECT id, ROW_NUMBER() OVER(ORDER BY \"order\" ASC, id ASC) as new_order
                                    FROM proses
                                    WHERE mesin_id = NEW.id
                                      AND mulai IS NULL
                                      AND selesai IS NULL
                                      AND id != proses_selanjutnya_id
                                ) t
                                WHERE proses.id = t.id;
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
        ");
    }
};
