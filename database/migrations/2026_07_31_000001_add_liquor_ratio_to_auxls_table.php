<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auxls', function (Blueprint $table) {
            $table->decimal('liquor_ratio', 8, 2)->default(10.00)->after('step_proses');
        });
    }

    public function down(): void
    {
        Schema::table('auxls', function (Blueprint $table) {
            $table->dropColumn(['liquor_ratio']);
        });
    }
};
