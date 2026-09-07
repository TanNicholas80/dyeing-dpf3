<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('absen_delegasis', function (Blueprint $table) {
            $table->dropUnique('absen_delegasis_role_target_unique');
            $table->date('tanggal')->nullable()->after('id');
            $table->renameColumn('current_shift', 'shift');
        });

        // Update record lama agar tanggal terisi (hari kemarin atau hari ini)
        DB::table('absen_delegasis')
            ->whereNull('tanggal')
            ->update([
                'tanggal' => DB::raw('DATE(created_at)'),
            ]);

        Schema::table('absen_delegasis', function (Blueprint $table) {
            $table->unique(['tanggal', 'shift', 'role_target'], 'absen_delegasis_tanggal_shift_role_unique');
            $table->index(['tanggal', 'role_target'], 'absen_delegasis_tanggal_role_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absen_delegasis', function (Blueprint $table) {
            $table->dropUnique('absen_delegasis_tanggal_shift_role_unique');
            $table->dropIndex('absen_delegasis_tanggal_role_index');
            $table->renameColumn('shift', 'current_shift');
            $table->dropColumn('tanggal');
            $table->unique('role_target', 'absen_delegasis_role_target_unique');
        });
    }
};
