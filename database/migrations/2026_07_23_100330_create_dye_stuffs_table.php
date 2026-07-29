<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_stuffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proses_id')->nullable()->constrained('proses')->onDelete('cascade');
            $table->string('barcode')->unique();
            $table->enum('tipe', ['normal', 'additional'])->default('normal'); // normal = Utama, additional = Topping
            $table->enum('jenis', ['normal', 'reproses', 'perbaikan'])->default('normal');
            $table->decimal('liquor_ratio', 8, 2)->default(10.00); // Angka belakang ratio (misal 6.00 = 1 : 6.0)
            $table->decimal('total_wt', 10, 2)->default(0.00); // Total Qty per partai (Kg)
            $table->decimal('volume_litres', 10, 2)->default(0.00); // Volume (Litres)
            $table->unsignedTinyInteger('step_proses')->nullable(); // Step 1, 2, 3
            $table->string('matdok', 20)->nullable();
            $table->boolean('cancel')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dye_stuffs');
    }
};
