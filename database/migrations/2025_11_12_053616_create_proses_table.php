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
            $table->string('no_op', 12)->nullable();
            $table->string('item_op')->nullable();
            $table->string('kode_material')->nullable();
            $table->string('konstruksi')->nullable();
            $table->string('no_partai')->nullable();
            $table->string('gramasi')->nullable();
            $table->string('lebar')->nullable();
            $table->string('hfeel')->nullable();
            $table->string('warna')->nullable();
            $table->string('kode_warna')->nullable();
            $table->string('kategori_warna')->nullable();
            $table->double('qty')->nullable();
            $table->unsignedBigInteger('cycle_time')->nullable();
            $table->unsignedBigInteger('cycle_time_actual')->nullable();
            $table->dateTime('mulai')->nullable();
            $table->dateTime('selesai')->nullable();
            $table->unsignedBigInteger('mesin_id')->nullable();
            $table->foreign('mesin_id')->references('id')->on('mesins')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proses');
    }
};
