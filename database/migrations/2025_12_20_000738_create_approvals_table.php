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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proses_id')->nullable();
            $table->unsignedBigInteger('auxl_id')->nullable();
            $table->enum('status', ['pending', 'rejected', 'approved'])->default('pending');
            $table->enum('type', ['FM', 'VP'])->nullable();
            $table->enum('action', ['move_machine', 'edit_cycle_time', 'delete_proses', 'create_reprocess', 'create_aux_reprocess', 'swap_position'])->nullable();
            $table->json('history_data')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->foreign('proses_id')->references('id')->on('proses')->onDelete('cascade');
            $table->foreign('auxl_id')->references('id')->on('auxls')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
