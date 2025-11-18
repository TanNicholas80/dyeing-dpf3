<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proses', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['Produksi', 'Maintenance', 'Reproses'])->default('Produksi');
            $table->string('no_op', 12);
            $table->string('item_op');
            $table->string('kode_material');
            $table->string('konstruksi');
            $table->string('no_partai');
            $table->string('gramasi');
            $table->string('lebar');
            $table->string('hfeel');
            $table->string('warna');
            $table->string('kode_warna');
            $table->string('kategori_warna');
            $table->double('qty');
            $table->unsignedBigInteger('cycle_time')->nullable();
            $table->unsignedBigInteger('cycle_time_actual')->nullable();
            $table->string('barcode_kain')->nullable();
            $table->string('barcode_la')->nullable();
            $table->string('barcode_aux')->nullable();
            $table->foreignId('mesin_id')->constrained('mesins')->restrictOnDelete();
            $table->dateTime('mulai')->nullable();
            $table->dateTime('selesai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proses');
    }
};
