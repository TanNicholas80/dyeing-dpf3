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
        Schema::create('detail_proses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proses_id')->constrained('proses')->onDelete('cascade');
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
            $table->integer('roll')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_proses');
    }
};
