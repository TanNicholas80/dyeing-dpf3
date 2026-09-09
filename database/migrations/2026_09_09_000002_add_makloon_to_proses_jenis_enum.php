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
        DB::statement("ALTER TABLE `proses` MODIFY COLUMN `jenis` ENUM('Produksi', 'Maintenance', 'Reproses', 'Proses Makloon', 'Reproses Makloon') NOT NULL DEFAULT 'Produksi'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `proses` MODIFY COLUMN `jenis` ENUM('Produksi', 'Maintenance', 'Reproses') NOT NULL DEFAULT 'Produksi'");
    }
};
