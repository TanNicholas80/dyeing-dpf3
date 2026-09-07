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
        Schema::create('break_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proses_id')->constrained('proses')->cascadeOnDelete();
            $table->foreignId('mesin_id')->constrained('mesins')->cascadeOnDelete();
            $table->text('alasan');
            $table->dateTime('break_at');
            $table->dateTime('selesai_at')->nullable();
            $table->foreignId('break_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('selesai_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['proses_id', 'break_at']);
            $table->index(['mesin_id', 'break_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_histories');
    }
};
