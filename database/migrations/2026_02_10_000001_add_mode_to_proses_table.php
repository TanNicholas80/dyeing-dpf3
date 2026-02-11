<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mode: greige = block GDA, finish = block FDA. Finish hanya boleh jenis Produksi/Maintenance.
     */
    public function up(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->enum('mode', ['greige', 'finish'])->after('jenis'); // greige | finish
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
