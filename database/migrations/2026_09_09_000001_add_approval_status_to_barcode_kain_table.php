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
        Schema::table('barcode_kain', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('approved')->after('cancel');
            $table->string('approval_reject_reason', 255)->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approval_reject_reason');

            $table->index(['barcode', 'approval_status'], 'barcode_kain_bc_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barcode_kain', function (Blueprint $table) {
            $table->dropIndex('barcode_kain_bc_status_idx');
            $table->dropColumn(['approval_status', 'approval_reject_reason', 'approved_at']);
        });
    }
};
