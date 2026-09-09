<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop existing check constraints on column 'jenis' in table 'proses'
            DB::statement("
                DO $$
                DECLARE
                    r RECORD;
                BEGIN
                    FOR r IN (
                        SELECT tc.constraint_name
                        FROM information_schema.table_constraints tc
                        JOIN information_schema.constraint_column_usage ccu
                          ON tc.constraint_name = ccu.constraint_name
                         AND tc.table_schema = ccu.table_schema
                        WHERE tc.table_name = 'proses'
                          AND ccu.column_name = 'jenis'
                          AND tc.constraint_type = 'CHECK'
                    ) LOOP
                        EXECUTE 'ALTER TABLE \"proses\" DROP CONSTRAINT IF EXISTS ' || quote_ident(r.constraint_name);
                    END LOOP;
                END $$;
            ");

            // Also try dropping default named constraint if exists
            DB::statement('ALTER TABLE "proses" DROP CONSTRAINT IF EXISTS proses_jenis_check');

            // Add new check constraint with Makloon values
            DB::statement("
                ALTER TABLE \"proses\" 
                ADD CONSTRAINT proses_jenis_check 
                CHECK (\"jenis\" IN ('Produksi', 'Maintenance', 'Reproses', 'Proses Makloon', 'Reproses Makloon'))
            ");

            // Ensure default remains 'Produksi'
            DB::statement('ALTER TABLE "proses" ALTER COLUMN "jenis" SET DEFAULT \'Produksi\'');
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE `proses` MODIFY COLUMN `jenis` ENUM('Produksi', 'Maintenance', 'Reproses', 'Proses Makloon', 'Reproses Makloon') NOT NULL DEFAULT 'Produksi'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                DO $$
                DECLARE
                    r RECORD;
                BEGIN
                    FOR r IN (
                        SELECT tc.constraint_name
                        FROM information_schema.table_constraints tc
                        JOIN information_schema.constraint_column_usage ccu
                          ON tc.constraint_name = ccu.constraint_name
                         AND tc.table_schema = ccu.table_schema
                        WHERE tc.table_name = 'proses'
                          AND ccu.column_name = 'jenis'
                          AND tc.constraint_type = 'CHECK'
                    ) LOOP
                        EXECUTE 'ALTER TABLE \"proses\" DROP CONSTRAINT IF EXISTS ' || quote_ident(r.constraint_name);
                    END LOOP;
                END $$;
            ");

            DB::statement('ALTER TABLE "proses" DROP CONSTRAINT IF EXISTS proses_jenis_check');

            DB::statement("
                ALTER TABLE \"proses\" 
                ADD CONSTRAINT proses_jenis_check 
                CHECK (\"jenis\" IN ('Produksi', 'Maintenance', 'Reproses'))
            ");

            DB::statement('ALTER TABLE "proses" ALTER COLUMN "jenis" SET DEFAULT \'Produksi\'');
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE `proses` MODIFY COLUMN `jenis` ENUM('Produksi', 'Maintenance', 'Reproses') NOT NULL DEFAULT 'Produksi'");
        }
    }
};
