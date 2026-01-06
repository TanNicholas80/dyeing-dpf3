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
        // Trigger untuk memonitor perubahan status mesin
        // Ketika mesin mati (status 1 -> 0): selesaikan proses aktif
        // Ketika mesin hidup (status 0 -> 1): mulai proses berikutnya berdasarkan order
        DB::statement("
            CREATE TRIGGER mesin_after_update_status
            AFTER UPDATE ON mesins
            FOR EACH ROW
            BEGIN
                DECLARE proses_aktif_id BIGINT UNSIGNED;
                DECLARE proses_aktif_mulai DATETIME;
                DECLARE proses_selanjutnya_id BIGINT UNSIGNED;
                DECLARE cycle_time_calc BIGINT UNSIGNED;
                
                -- Hanya jalankan jika status benar-benar berubah
                IF OLD.status != NEW.status THEN
                    
                    -- Kasus 1: Status berubah dari AKTIF (1) menjadi MATI (0)
                    IF OLD.status = 1 AND NEW.status = 0 THEN
                        -- Cari proses yang sedang aktif (sudah mulai tapi belum selesai)
                        -- Urutkan berdasarkan order (jika ada) atau id sebagai fallback
                        SELECT id, mulai INTO proses_aktif_id, proses_aktif_mulai
                        FROM proses
                        WHERE mesin_id = NEW.id
                          AND mulai IS NOT NULL
                          AND selesai IS NULL
                        ORDER BY `order` ASC, id ASC
                        LIMIT 1;
                        
                        -- Jika ada proses aktif, selesaikan proses tersebut
                        IF proses_aktif_id IS NOT NULL THEN
                            -- Hitung cycle_time_actual dalam detik (selesai - mulai)
                            SET cycle_time_calc = TIMESTAMPDIFF(SECOND, proses_aktif_mulai, NOW());
                            
                            -- Pastikan cycle_time_calc tidak negatif
                            IF cycle_time_calc < 0 THEN
                                SET cycle_time_calc = 0;
                            END IF;
                            
                            -- Update proses: set selesai, cycle_time_actual, dan reset order
                            UPDATE proses
                            SET selesai = NOW(),
                                cycle_time_actual = cycle_time_calc,
                                `order` = 0,
                                updated_at = NOW()
                            WHERE id = proses_aktif_id;
                            
                            -- Renumber proses pending yang tersisa (1, 2, 3, ...)
                            -- Menggunakan teknik dengan variabel user untuk renumber
                            SET @row_number = 0;
                            UPDATE proses
                            SET `order` = (@row_number := @row_number + 1),
                                updated_at = NOW()
                            WHERE mesin_id = NEW.id
                              AND mulai IS NULL
                              AND selesai IS NULL
                            ORDER BY `order` ASC, id ASC;
                        END IF;
                    END IF;
                    
                    -- Kasus 2: Status berubah dari MATI (0) menjadi AKTIF (1)
                    IF OLD.status = 0 AND NEW.status = 1 THEN
                        -- Cari proses selanjutnya yang belum dimulai untuk mesin ini
                        -- Prioritas: proses yang belum dimulai, diurutkan berdasarkan order (untuk support reorder)
                        SELECT id INTO proses_selanjutnya_id
                        FROM proses
                        WHERE mesin_id = NEW.id
                          AND mulai IS NULL
                          AND selesai IS NULL
                          AND `order` > 0
                        ORDER BY `order` ASC, id ASC
                        LIMIT 1;
                        
                        -- Jika ada proses selanjutnya, update kolom mulai dan reset order
                        IF proses_selanjutnya_id IS NOT NULL THEN
                            -- Update proses: set mulai dan reset order ke 0
                            UPDATE proses
                            SET mulai = NOW(),
                                `order` = 0,
                                updated_at = NOW()
                            WHERE id = proses_selanjutnya_id;
                            
                            -- Renumber proses pending yang tersisa (1, 2, 3, ...)
                            -- Menggunakan teknik dengan variabel user untuk renumber
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
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS mesin_after_update_status");
    }
};
