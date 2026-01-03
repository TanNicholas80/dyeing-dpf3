<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('auxls', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->nullable();
            $table->enum('jenis', ['normal', 'reproses', 'perbaikan']);
            $table->string('code');
            $table->string('konstruksi')->nullable();
            $table->string('customer')->nullable();
            $table->string('marketing')->nullable();
            $table->date('date')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('auxl_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auxl_id')->constrained('auxls')->onDelete('cascade');
            $table->string('auxiliary');
            $table->decimal('konsentrasi', 8, 2); // g/L
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('auxl_details');
        Schema::dropIfExists('auxls');
    }
};
