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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // 1. Perluas enum agar menerima kepala_ruangan dan kepala_regu sekaligus
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'owner', 'aux', 'dye_stuff', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_regu', 'kepala_shift', 'spv_listrik', 'scm') DEFAULT 'dashboard'");

            // 2. Update data yang sudah ada di tabel users
            DB::table('users')->where('role', 'kepala_ruangan')->update(['role' => 'kepala_regu']);

            // 3. Finalisasi enum hanya ke kepala_regu
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'owner', 'aux', 'dye_stuff', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_regu', 'kepala_shift', 'spv_listrik', 'scm') DEFAULT 'dashboard'");
        } elseif ($driver === 'pgsql') {
            // 1. Drop check constraint lama di PostgreSQL
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");

            // 2. Update data yang sudah ada di tabel users
            DB::table('users')->where('role', 'kepala_ruangan')->update(['role' => 'kepala_regu']);

            // 3. Pasang kembali constraint dengan kepala_regu
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['super_admin', 'owner', 'aux', 'dye_stuff', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_regu', 'kepala_shift', 'spv_listrik', 'scm']::text[]))");
        }

        // 4. Update data delegasi dan riwayat absen
        if (Schema::hasTable('absen_delegasis')) {
            DB::table('absen_delegasis')
                ->where('role_target', 'kepala_ruangan')
                ->update(['role_target' => 'kepala_regu']);
        }

        if (Schema::hasTable('absen_histories')) {
            DB::table('absen_histories')
                ->where('role_target', 'kepala_ruangan')
                ->update(['role_target' => 'kepala_regu']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'owner', 'aux', 'dye_stuff', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_regu', 'kepala_shift', 'spv_listrik', 'scm') DEFAULT 'dashboard'");

            DB::table('users')->where('role', 'kepala_regu')->update(['role' => 'kepala_ruangan']);

            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'owner', 'aux', 'dye_stuff', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_shift', 'spv_listrik', 'scm') DEFAULT 'dashboard'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");

            DB::table('users')->where('role', 'kepala_regu')->update(['role' => 'kepala_ruangan']);

            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['super_admin', 'owner', 'aux', 'dye_stuff', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_shift', 'spv_listrik', 'scm']::text[]))");
        }

        if (Schema::hasTable('absen_delegasis')) {
            DB::table('absen_delegasis')
                ->where('role_target', 'kepala_regu')
                ->update(['role_target' => 'kepala_ruangan']);
        }

        if (Schema::hasTable('absen_histories')) {
            DB::table('absen_histories')
                ->where('role_target', 'kepala_regu')
                ->update(['role_target' => 'kepala_ruangan']);
        }
    }
};
