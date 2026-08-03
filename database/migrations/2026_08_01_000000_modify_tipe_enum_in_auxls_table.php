<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('auxls')) {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                DB::statement("ALTER TABLE auxls ALTER COLUMN tipe TYPE VARCHAR(30) USING tipe::text, ALTER COLUMN tipe SET DEFAULT 'normal', ALTER COLUMN tipe SET NOT NULL");
            } else {
                DB::statement("ALTER TABLE auxls MODIFY COLUMN tipe VARCHAR(30) NOT NULL DEFAULT 'normal'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('auxls')) {
            $driver = DB::getDriverName();
            if ($driver === 'pgsql') {
                DB::statement("ALTER TABLE auxls ALTER COLUMN tipe TYPE VARCHAR(30) USING tipe::text, ALTER COLUMN tipe SET DEFAULT 'normal', ALTER COLUMN tipe SET NOT NULL");
            } else {
                DB::statement("ALTER TABLE auxls MODIFY COLUMN tipe VARCHAR(30) NOT NULL DEFAULT 'normal'");
            }
        }
    }
};
