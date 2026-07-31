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
        if (Schema::hasTable('ticket_details') && !Schema::hasColumn('ticket_details', 'product_name')) {
            Schema::table('ticket_details', function (Blueprint $table) {
                $table->string('product_name')->nullable()->after('product_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ticket_details') && Schema::hasColumn('ticket_details', 'product_name')) {
            Schema::table('ticket_details', function (Blueprint $table) {
                $table->dropColumn('product_name');
            });
        }
    }
};
