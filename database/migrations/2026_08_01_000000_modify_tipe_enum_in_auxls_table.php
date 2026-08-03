<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('auxls')) {
            DB::statement("ALTER TABLE `auxls` MODIFY COLUMN `tipe` VARCHAR(30) NOT NULL DEFAULT 'normal'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('auxls')) {
            DB::statement("ALTER TABLE `auxls` MODIFY COLUMN `tipe` ENUM('normal', 'additional') NOT NULL DEFAULT 'normal'");
        }
    }
};
