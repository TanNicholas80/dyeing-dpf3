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
        Schema::create('absen_delegasis', function (Blueprint $table) {
            $table->id();
            $table->string('role_target', 50)->unique(); // 'kepala_shift' atau 'kepala_ruangan'
            $table->boolean('is_active')->default(true); // true = ON (Hadir/Normal), false = OFF (Absen/Wewenang dialihkan)
            $table->string('current_shift', 20)->default('Shift 1');
            $table->text('keterangan')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('absen_histories', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('shift', 20);
            $table->string('role_target', 50); // 'kepala_shift' atau 'kepala_ruangan'
            $table->string('status', 10); // 'ON' atau 'OFF'
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // FM atau Super Admin
            $table->timestamps();

            $table->index(['tanggal', 'shift']);
            $table->index('role_target');
            $table->index('created_at');
        });

        // Inisialisasi default 2 role dalam kondisi ON (is_active = true)
        DB::table('absen_delegasis')->insert([
            [
                'role_target' => 'kepala_shift',
                'is_active' => true,
                'current_shift' => 'Shift 1',
                'keterangan' => 'Default sistem: Aktif / Hadir',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_target' => 'kepala_ruangan',
                'is_active' => true,
                'current_shift' => 'Shift 1',
                'keterangan' => 'Default sistem: Aktif / Hadir',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absen_histories');
        Schema::dropIfExists('absen_delegasis');
    }
};
