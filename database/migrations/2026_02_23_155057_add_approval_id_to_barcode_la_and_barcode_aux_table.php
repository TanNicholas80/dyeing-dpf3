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
        Schema::table('barcode_la', function (Blueprint $table) {
            $table->foreignId('approval_id')->nullable()->after('cancel')->constrained('approvals')->nullOnDelete();
        });

        Schema::table('barcode_aux', function (Blueprint $table) {
            $table->foreignId('approval_id')->nullable()->after('cancel')->constrained('approvals')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barcode_la', function (Blueprint $table) {
            $table->dropForeign(['approval_id']);
        });

        Schema::table('barcode_aux', function (Blueprint $table) {
            $table->dropForeign(['approval_id']);
        });
    }
};
