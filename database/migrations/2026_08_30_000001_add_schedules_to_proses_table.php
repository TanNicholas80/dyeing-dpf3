<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->json('dye_stuff_schedules')->nullable()->after('qty_dye_stuff');
            $table->json('aux_schedules')->nullable()->after('qty_aux');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->dropColumn(['dye_stuff_schedules', 'aux_schedules']);
        });
    }
};
