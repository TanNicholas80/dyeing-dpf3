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
                if (!Schema::hasColumn('ticket_details', 'fabric_name')) {
                    $table->string('fabric_name')->nullable()->index();
                }
                if (!Schema::hasColumn('ticket_details', 'customer_name')) {
                    $table->string('customer_name')->nullable()->index();
                }
                if (!Schema::hasColumn('ticket_details', 'color_name')) {
                    $table->string('color_name')->nullable();
                }
                if (!Schema::hasColumn('ticket_details', 'order_no')) {
                    $table->string('order_no')->nullable()->index();
                }
                if (!Schema::hasColumn('ticket_details', 'dyelot_batch')) {
                    $table->string('dyelot_batch')->nullable();
                }
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
                $columns = ['fabric_name', 'customer_name', 'color_name', 'order_no', 'dyelot_batch'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('ticket_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
