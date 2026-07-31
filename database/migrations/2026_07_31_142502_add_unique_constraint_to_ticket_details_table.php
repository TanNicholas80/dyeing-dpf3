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
                $table->unique(['id_no', 'step_no', 'product_code'], 'uniq_ticket_step_product');
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
                $table->dropUnique('uniq_ticket_step_product');
            });
        }
    }
};
