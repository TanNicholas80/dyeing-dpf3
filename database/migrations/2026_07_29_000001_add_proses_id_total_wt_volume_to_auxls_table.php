<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auxls', function (Blueprint $table) {
            $table->foreignId('proses_id')->nullable()->after('id')->constrained('proses')->onDelete('cascade');
            $table->decimal('total_wt', 10, 2)->default(0.00)->after('step_proses');
            $table->decimal('volume_litres', 10, 2)->default(0.00)->after('total_wt');
        });
    }

    public function down(): void
    {
        Schema::table('auxls', function (Blueprint $table) {
            $table->dropForeign(['proses_id']);
            $table->dropColumn(['proses_id', 'total_wt', 'volume_litres']);
        });
    }
};
