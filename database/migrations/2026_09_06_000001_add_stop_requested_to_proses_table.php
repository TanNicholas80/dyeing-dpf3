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
        Schema::table('proses', function (Blueprint $table) {
            $table->timestamp('stop_requested_at')->nullable()->after('note');
            $table->foreignId('stop_requested_by')->nullable()->constrained('users')->nullOnDelete()->after('stop_requested_at');
            $table->string('stop_request_type', 50)->nullable()->after('stop_requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proses', function (Blueprint $table) {
            $table->dropForeign(['stop_requested_by']);
            $table->dropColumn([
                'stop_requested_at',
                'stop_requested_by',
                'stop_request_type',
            ]);
        });
    }
};
