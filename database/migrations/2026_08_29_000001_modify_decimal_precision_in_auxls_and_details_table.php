<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auxls', function (Blueprint $table) {
            $table->decimal('total_wt', 12, 4)->default(0.0000)->change();
            $table->decimal('volume_litres', 12, 4)->default(0.0000)->change();
        });

        Schema::table('auxl_details', function (Blueprint $table) {
            $table->decimal('konsentrasi', 12, 4)->default(0.0000)->change();
        });
    }

    public function down(): void
    {
        Schema::table('auxls', function (Blueprint $table) {
            $table->decimal('total_wt', 10, 2)->default(0.00)->change();
            $table->decimal('volume_litres', 10, 2)->default(0.00)->change();
        });

        Schema::table('auxl_details', function (Blueprint $table) {
            $table->decimal('konsentrasi', 8, 2)->change();
        });
    }
};
