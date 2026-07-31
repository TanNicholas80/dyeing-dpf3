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
        Schema::create('ticket_details', function (Blueprint $table) {
            $table->id();
            
            $table->string('id_no')->nullable()->index();
            $table->integer('step_no')->nullable();
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_type')->nullable();
            $table->decimal('target_wt', 15, 4)->nullable();
            $table->decimal('actual_wt', 15, 4)->nullable();
            $table->string('unit')->nullable();
            $table->string('comp_date')->nullable();
            $table->string('comp_time')->nullable();
            $table->string('transfer_state')->nullable();
            $table->string('error_code')->nullable();
            $table->string('machine')->nullable()->index();
            $table->string('tank_no')->nullable();
            $table->string('id_type')->nullable();
            $table->string('product_lot')->nullable();
            $table->string('recipe_code')->nullable();
            $table->decimal('lr', 15, 4)->nullable();
            $table->decimal('fabric_weight', 15, 4)->nullable();
            $table->decimal('volume', 15, 4)->nullable();
            $table->string('recipe_type')->nullable();
            $table->decimal('conc', 15, 4)->nullable();
            $table->string('conc_unit')->nullable();
            $table->text('remark')->nullable();
            $table->string('adjust')->nullable();
            $table->decimal('price', 15, 4)->nullable();
            
            $table->double('res_double1')->nullable();
            $table->double('res_double2')->nullable();
            $table->double('res_double3')->nullable();
            $table->double('res_double4')->nullable();
            
            $table->string('res_string1')->nullable();
            $table->string('res_string2')->nullable();
            $table->string('res_string3')->nullable();
            $table->string('res_string4')->nullable();
            
            $table->string('reweight')->nullable();
            $table->dateTime('dye_weight_time')->nullable();
            $table->string('re_dye')->nullable();
            $table->string('user_code')->nullable();
            $table->string('user_account')->nullable();
            $table->string('batch_no')->nullable()->index();
            $table->bigInteger('record_order')->nullable()->unique();
            $table->string('station')->nullable();
            $table->string('process')->nullable();
            $table->decimal('gravity', 15, 4)->nullable();
            $table->decimal('current_stock', 15, 4)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_details');
    }
};
