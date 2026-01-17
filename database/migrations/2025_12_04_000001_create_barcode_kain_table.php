<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('barcode_kain', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('detail_proses_id');
            $table->string('no_op', 12);
            $table->string('no_partai')->nullable();
            $table->string('barcode')->nullable();
            $table->string('matdok', 10)->nullable();
            $table->double('qty_gi')->nullable();
            $table->foreignId('mesin_id')->constrained('mesins')->restrictOnDelete();
            $table->boolean('cancel')->default(false);
            $table->timestamps();
            $table->foreign('detail_proses_id')->references('id')->on('detail_proses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_kain');
    }
};
