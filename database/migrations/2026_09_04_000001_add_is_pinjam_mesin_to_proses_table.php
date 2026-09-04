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
            $table->boolean('is_pinjam_mesin')->default(false)->after('is_paused');
            $table->text('pinjam_mesin_alasan')->nullable()->after('is_pinjam_mesin');
            $table->timestamp('pinjam_mesin_at')->nullable()->after('pinjam_mesin_alasan');
            $table->foreignId('pinjam_mesin_by')->nullable()->constrained('users')->nullOnDelete()->after('pinjam_mesin_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->dropForeign(['pinjam_mesin_by']);
            $table->dropColumn([
                'is_pinjam_mesin',
                'pinjam_mesin_alasan',
                'pinjam_mesin_at',
                'pinjam_mesin_by',
            ]);
        });
    }
};
