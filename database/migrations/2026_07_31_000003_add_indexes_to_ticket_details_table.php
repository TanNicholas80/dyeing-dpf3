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
        if (Schema::hasTable('ticket_details')) {
            Schema::table('ticket_details', function (Blueprint $table) {
                // Index composite untuk query grouping & status timbang cepat
                $table->index(['id_no', 'comp_date'], 'idx_ticket_idno_compdate');
                $table->index(['recipe_code', 'machine'], 'idx_ticket_recipe_machine');
            });
        }

        if (Schema::hasTable('barcode_las')) {
            Schema::table('barcode_las', function (Blueprint $table) {
                // Index composite untuk pengecekan status pakai barcode di OP
                $table->index(['barcode', 'cancel'], 'idx_barcodelas_barcode_cancel');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ticket_details')) {
            Schema::table('ticket_details', function (Blueprint $table) {
                $table->dropIndex('idx_ticket_idno_compdate');
                $table->dropIndex('idx_ticket_recipe_machine');
            });
        }

        if (Schema::hasTable('barcode_las')) {
            Schema::table('barcode_las', function (Blueprint $table) {
                $table->dropIndex('idx_barcodelas_barcode_cancel');
            });
        }
    }
};
