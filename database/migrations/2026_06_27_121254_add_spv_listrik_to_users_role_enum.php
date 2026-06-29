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
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'owner', 'aux', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_shift', 'spv_listrik') DEFAULT 'dashboard'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'owner', 'aux', 'ppic', 'operator', 'dashboard', 'fm', 'vp', 'kepala_ruangan', 'kepala_shift') DEFAULT 'dashboard'");
    }
};
