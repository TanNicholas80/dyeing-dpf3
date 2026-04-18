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
        Schema::table('mesins', function (Blueprint $table) {
            $table->timestamp('last_on_at')->nullable()->after('last_seen_at');
            $table->timestamp('last_off_at')->nullable()->after('last_on_at');
        });

        // Initial Data Population
        try {
            $mesins = \App\Models\Mesin::all();
            foreach ($mesins as $mesin) {
                $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', \App\Models\Mesin::class)
                    ->where('subject_id', $mesin->id)
                    ->where('log_name', 'Manajemen Mesin')
                    ->where('properties->event_type', 'updated')
                    ->orderByDesc('created_at')
                    ->get();

                $onActivity = $activities->filter(function($act) {
                    $props = $act->properties;
                    return isset($props['after_update']['status']) && ($props['after_update']['status'] == 1 || $props['after_update']['status'] === true);
                })->first();
                
                if ($onActivity) {
                    $mesin->last_on_at = $onActivity->created_at;
                }

                $offActivity = $activities->filter(function($act) {
                    $props = $act->properties;
                    return isset($props['after_update']['status']) && ($props['after_update']['status'] == 0 || $props['after_update']['status'] === false);
                })->first();

                if ($offActivity) {
                    $mesin->last_off_at = $offActivity->created_at;
                }

                if ($onActivity || $offActivity) {
                    $mesin->save();
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Migration populated last_on_off failed: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mesins', function (Blueprint $table) {
            $table->dropColumn(['last_on_at', 'last_off_at']);
        });
    }
};
