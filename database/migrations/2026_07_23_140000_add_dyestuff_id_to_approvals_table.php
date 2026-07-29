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
        Schema::table('approvals', function (Blueprint $table) {
            if (!Schema::hasColumn('approvals', 'dyestuff_id')) {
                $table->foreignId('dyestuff_id')->nullable()->after('auxl_id')->constrained('dye_stuffs')->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            if (Schema::hasColumn('approvals', 'dyestuff_id')) {
                $table->dropForeign(['dyestuff_id']);
                $table->dropColumn('dyestuff_id');
            }
        });
    }
};
