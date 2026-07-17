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
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'owner', 'aux', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_shift', 'spv_listrik', 'scm') DEFAULT 'dashboard'");
        } elseif ($driver === 'pgsql') {
            // Drop check constraint in PostgreSQL
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            // Re-add constraint with new value
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['super_admin', 'owner', 'aux', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_shift', 'spv_listrik', 'scm']::text[]))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'owner', 'aux', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_shift', 'spv_listrik') DEFAULT 'dashboard'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['super_admin', 'owner', 'aux', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_shift', 'spv_listrik']::text[]))");
        }
    }
};
