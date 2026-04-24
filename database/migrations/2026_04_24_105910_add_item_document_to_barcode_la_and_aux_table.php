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
            $table->string('item_document', 10)->nullable()->after('matdok');
        });

        Schema::table('barcode_aux', function (Blueprint $table) {
            $table->string('item_document', 10)->nullable()->after('matdok');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barcode_la', function (Blueprint $table) {
            $table->dropColumn('item_document');
        });

        Schema::table('barcode_aux', function (Blueprint $table) {
            $table->dropColumn('item_document');
        });
    }
};
