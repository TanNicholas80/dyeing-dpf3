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
        // Update the 0 -> 1 trigger to check for an approved 'pause_proses' approval AFTER NEW.last_off_at
        DB::unprepared("
        DROP TRIGGER IF EXISTS mesin_after_update_status;
        CREATE TRIGGER mesin_after_update_status
        AFTER UPDATE ON mesins
        FOR EACH ROW
        BEGIN
            DECLARE proses_aktif_id BIGINT;
            DECLARE latest_approval_status VARCHAR(50);

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
                    -- Jalankan antrian jika tidak ada yang sedang berjalan
                    UPDATE proses
                    SET mulai = NOW(),
                        updated_at = NOW()
                    WHERE mesin_id = NEW.id
                      AND mulai IS NULL
                      AND selesai IS NULL
                    ORDER BY `order` ASC, id ASC
                    LIMIT 1;
                END IF;
            END IF;
        END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the previous trigger without last_off_at check
        DB::unprepared("
        DROP TRIGGER IF EXISTS mesin_after_update_status;
        CREATE TRIGGER mesin_after_update_status
        AFTER UPDATE ON mesins
        FOR EACH ROW
        BEGIN
            DECLARE proses_aktif_id BIGINT;
            DECLARE latest_approval_status VARCHAR(50);

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
                    SELECT status INTO latest_approval_status
                    FROM approvals
                    WHERE proses_id = proses_aktif_id
                      AND action = 'pause_proses'
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
                    -- Jalankan antrian jika tidak ada yang sedang berjalan
                    UPDATE proses
                    SET mulai = NOW(),
                        updated_at = NOW()
                    WHERE mesin_id = NEW.id
                      AND mulai IS NULL
                      AND selesai IS NULL
                    ORDER BY `order` ASC, id ASC
                    LIMIT 1;
                END IF;
            END IF;
        END;
        ");
    }
};
