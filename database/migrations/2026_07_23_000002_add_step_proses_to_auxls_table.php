<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auxls', function (Blueprint $table) {
            $table->unsignedTinyInteger('step_proses')->nullable()->after('tipe');
        });
    }

    public function down(): void
    {
        Schema::table('auxls', function (Blueprint $table) {
            $table->dropColumn('step_proses');
        });
    }
};
