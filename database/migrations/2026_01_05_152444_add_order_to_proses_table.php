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
            $table->unsignedInteger('order')->default(0)->after('mesin_id');
            $table->index(['mesin_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->dropColumn('order');
            $table->dropIndex(['mesin_id', 'order']);
        });
    }
};
