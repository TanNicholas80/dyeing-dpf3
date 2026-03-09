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
        // Trigger untuk memonitor perubahan status mesin (PostgreSQL)
        // Ketika mesin mati (status 1 -> 0): selesaikan proses aktif
        // Ketika mesin hidup (status 0 -> 1): mulai proses berikutnya berdasarkan order
        DB::unprepared('
            CREATE OR REPLACE FUNCTION mesin_after_update_status_func()
            RETURNS TRIGGER AS $$
            DECLARE
                proses_aktif_id BIGINT;
                proses_aktif_mulai TIMESTAMP;
                proses_selanjutnya_id BIGINT;
                cycle_time_calc BIGINT;
            BEGIN
                -- Hanya jalankan jika status benar-benar berubah
                IF OLD.status IS DISTINCT FROM NEW.status THEN

                    -- Kasus 1: Status berubah dari AKTIF (1) menjadi MATI (0)
                    IF OLD.status = 1 AND NEW.status = 0 THEN
                        -- Cari proses yang sedang aktif (sudah mulai tapi belum selesai)
                        SELECT id, mulai INTO proses_aktif_id, proses_aktif_mulai
                        FROM proses
                        WHERE mesin_id = NEW.id
                          AND mulai IS NOT NULL
                          AND selesai IS NULL
                        ORDER BY "order" ASC, id ASC
                        LIMIT 1;

                        -- Jika ada proses aktif, selesaikan proses tersebut
                        IF proses_aktif_id IS NOT NULL THEN
                            -- Hitung cycle_time_actual dalam detik (NOW - mulai)
                            cycle_time_calc := GREATEST(0, EXTRACT(EPOCH FROM (NOW() - proses_aktif_mulai))::BIGINT);

                            -- Update proses: set selesai, cycle_time_actual, dan reset order
                            UPDATE proses
                            SET selesai = NOW(),
                                cycle_time_actual = cycle_time_calc,
                                "order" = 0,
                                updated_at = NOW()
                            WHERE id = proses_aktif_id;

                            -- Renumber proses pending yang tersisa (1, 2, 3, ...)
                            WITH numbered AS (
                                SELECT id, ROW_NUMBER() OVER (ORDER BY "order" ASC, id ASC) AS new_order
                                FROM proses
                                WHERE mesin_id = NEW.id
                                  AND mulai IS NULL
                                  AND selesai IS NULL
                            )
                            UPDATE proses p
                            SET "order" = n.new_order, updated_at = NOW()
                            FROM numbered n
                            WHERE p.id = n.id;
                        END IF;
                    END IF;

                    -- Kasus 2: Status berubah dari MATI (0) menjadi AKTIF (1)
                    IF OLD.status = 0 AND NEW.status = 1 THEN
                        -- Cari proses selanjutnya yang belum dimulai untuk mesin ini
                        -- SKIP proses Reproses jika masih pending approval create_reprocess (FM/VP)
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

                        -- Jika ada proses selanjutnya, update kolom mulai dan reset order
                        IF proses_selanjutnya_id IS NOT NULL THEN
                            -- Auto reject approval pending aksi perubahan umum saat proses akan auto-start
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

                            -- Update proses: set mulai dan reset order ke 0
                            UPDATE proses
                            SET mulai = NOW(),
                                "order" = 0,
                                updated_at = NOW()
                            WHERE id = proses_selanjutnya_id;

                            -- Renumber proses pending yang tersisa (1, 2, 3, ...)
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('
            DROP TRIGGER IF EXISTS mesin_after_update_status ON mesins;
            DROP FUNCTION IF EXISTS mesin_after_update_status_func();
        ');
    }
};
