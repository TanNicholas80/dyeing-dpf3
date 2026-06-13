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
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared("DROP TRIGGER IF EXISTS mesin_after_update_status ON mesins");
            DB::unprepared("DROP FUNCTION IF EXISTS mesin_after_update_status_func()");
        } else {
            DB::unprepared("DROP TRIGGER IF EXISTS mesin_after_update_status");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration
    }
};
