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
        Schema::table('proses', function (Blueprint $table) {
            $table->boolean('is_break')->default(false)->after('is_pinjam_mesin');
            $table->text('break_alasan')->nullable()->after('is_break');
            $table->timestamp('break_at')->nullable()->after('break_alasan');
            $table->foreignId('break_by')->nullable()->constrained('users')->nullOnDelete()->after('break_at');
            $table->unsignedInteger('total_break_seconds')->default(0)->after('break_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->dropForeign(['break_by']);
            $table->dropColumn([
                'is_break',
                'break_alasan',
                'break_at',
                'break_by',
                'total_break_seconds',
            ]);
        });
    }
};
