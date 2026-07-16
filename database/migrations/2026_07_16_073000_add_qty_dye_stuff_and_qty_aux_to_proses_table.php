<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->tinyInteger('qty_dye_stuff')->default(1)->after('cycle_time_actual');
            $table->tinyInteger('qty_aux')->default(1)->after('qty_dye_stuff');
        });
    }

    public function down(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->dropColumn(['qty_dye_stuff', 'qty_aux']);
        });
    }
};
