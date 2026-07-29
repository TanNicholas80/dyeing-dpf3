<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_stuff_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dyestuff_id')->constrained('dye_stuffs')->onDelete('cascade');
            $table->string('code', 50)->nullable(); // Kode Obat / Recipe Code
            $table->string('chemical_name'); // Nama Zat Warna / Bahan Kimia
            $table->decimal('konsentrasi', 8, 4)->default(0); // Concentrate / Konsentrasi (%)
            $table->decimal('weight', 10, 3)->default(0); // Weight Hasil Timbangan (Gram / Kg)
            $table->string('unit', 10)->default('g'); // Unit (g / kg / %)
            $table->string('remark')->nullable(); // Catatan / Remark
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dye_stuff_details');
    }
};
