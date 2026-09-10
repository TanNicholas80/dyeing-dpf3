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
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jadwal', 150)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->time('shift1_start')->default('06:00:00');
            $table->time('shift1_end')->default('18:00:00');
            $table->time('shift2_start')->default('18:00:00');
            $table->time('shift2_end')->default('06:00:00');
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tanggal_mulai', 'tanggal_selesai', 'is_active']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_schedules');
    }
};
