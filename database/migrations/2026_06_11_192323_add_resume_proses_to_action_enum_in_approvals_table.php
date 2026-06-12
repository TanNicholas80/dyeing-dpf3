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
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE approvals MODIFY action ENUM('move_machine', 'edit_cycle_time', 'delete_proses', 'create_reprocess', 'create_aux_reprocess', 'swap_position', 'topping_la', 'topping_aux', 'pause_proses', 'resume_proses')");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE approvals DROP CONSTRAINT IF EXISTS approvals_action_check");
            DB::statement("ALTER TABLE approvals ADD CONSTRAINT approvals_action_check CHECK (action::text = ANY (ARRAY['move_machine'::character varying, 'edit_cycle_time'::character varying, 'delete_proses'::character varying, 'create_reprocess'::character varying, 'create_aux_reprocess'::character varying, 'swap_position'::character varying, 'topping_la'::character varying, 'topping_aux'::character varying, 'pause_proses'::character varying, 'resume_proses'::character varying]::text[]))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE approvals MODIFY action ENUM('move_machine', 'edit_cycle_time', 'delete_proses', 'create_reprocess', 'create_aux_reprocess', 'swap_position', 'topping_la', 'topping_aux', 'pause_proses')");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE approvals DROP CONSTRAINT IF EXISTS approvals_action_check");
            DB::statement("ALTER TABLE approvals ADD CONSTRAINT approvals_action_check CHECK (action::text = ANY (ARRAY['move_machine'::character varying, 'edit_cycle_time'::character varying, 'delete_proses'::character varying, 'create_reprocess'::character varying, 'create_aux_reprocess'::character varying, 'swap_position'::character varying, 'topping_la'::character varying, 'topping_aux'::character varying, 'pause_proses'::character varying]::text[]))");
        }
    }
};
