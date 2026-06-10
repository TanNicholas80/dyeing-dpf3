<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE approvals MODIFY COLUMN action ENUM('move_machine','edit_cycle_time','delete_proses','create_reprocess','create_aux_reprocess','swap_position','topping_la','topping_aux','pause_proses') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE approvals MODIFY COLUMN action ENUM('move_machine','edit_cycle_time','delete_proses','create_reprocess','create_aux_reprocess','swap_position','topping_la','topping_aux') NOT NULL");
    }
};
