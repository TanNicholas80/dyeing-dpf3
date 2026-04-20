<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use App\Events\MesinCreated;
use App\Events\MesinUpdated;
use App\Events\MesinDeleted;
use App\Services\MesinCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MesinController extends Controller
{
    private function forceAlarmKey(int $mesinId): string
    {
        return "iot:mesin:{$mesinId}:force_alarm_off";
    }

    public function index()
    {
        $mesins = Mesin::all();
        $forceAlarmOffMap = [];
        $lastStatusMap = [];

        // Ambil semua aktivitas update status untuk semua mesin guna efisiensi (sekali query)
        $machineIds = $mesins->pluck('id')->toArray();
        $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', Mesin::class)
            ->whereIn('subject_id', $machineIds)
            ->where('log_name', 'Manajemen Mesin')
            ->where('properties->event_type', 'updated')
            ->orderByDesc('created_at')
            ->get();

        foreach ($mesins as $mesin) {
            $forceAlarmOffMap[$mesin->id] = (bool) Cache::get($this->forceAlarmKey((int) $mesin->id), false);

            $lastStatusMap[$mesin->id] = [
                'nyala' => $mesin->last_on_at ? $mesin->last_on_at->translatedFormat('d-m-Y H:i:s') : '-',
                'mati' => $mesin->last_off_at ? $mesin->last_off_at->translatedFormat('d-m-Y H:i:s') : '-',
            ];
        }

        return view('mesin.index', compact('mesins', 'forceAlarmOffMap', 'lastStatusMap'));
    }

    public function create()
    {
        return view('mesin.create');
    }

    public function store(Request $request, MesinCacheService $mesinCache)
    {
        $request->validate([
            'jenis_mesin' => 'required|unique:mesins,jenis_mesin',
            'status' => 'required|boolean',
        ]);

        $mesin = Mesin::create($request->only('jenis_mesin', 'status'));
        $mesinCache->forgetAll();

        // Broadcast event untuk update real-time di dashboard
        event(new MesinCreated([
            'id' => $mesin->id,
            'jenis_mesin' => $mesin->jenis_mesin,
            'status' => (bool) $mesin->status,
        ]));

        return redirect()->route('mesin.index')->with('success', 'Mesin berhasil ditambahkan.');
    }

    public function edit(Mesin $mesin)
    {
        return view('mesin.edit', compact('mesin'));
    }

    public function update(Request $request, Mesin $mesin, MesinCacheService $mesinCache)
    {
        $request->validate([
            'jenis_mesin' => 'required|unique:mesins,jenis_mesin,' . $mesin->id,
            'status' => 'required|boolean',
        ]);

        $mesin->update($request->only('jenis_mesin', 'status'));
        $mesinCache->forgetAll();

        // Refresh untuk memastikan data terbaru
        $mesin->refresh();

        // Broadcast event untuk update real-time di dashboard
        event(new MesinUpdated([
            'id' => $mesin->id,
            'jenis_mesin' => $mesin->jenis_mesin,
            'status' => (bool) $mesin->status,
        ]));

        return redirect()->route('mesin.index')->with('success', 'Mesin berhasil diperbarui.');
    }

    public function destroy(Mesin $mesin, MesinCacheService $mesinCache)
    {
        $mesinId = $mesin->id;
        $mesin->delete();
        $mesinCache->forgetAll();

        // Broadcast event untuk update real-time di dashboard
        event(new MesinDeleted($mesinId));

        return redirect()->route('mesin.index')->with('success', 'Mesin berhasil dihapus.');
    }

    public function statuses()
    {
        try {
            $mesins = Mesin::all();
            $result = [];
            foreach ($mesins as $mesin) {
                // Logic Auto-Offline: jika tidak ada sinyal > 5 detik
                $isTimeout = true;
                if ($mesin->last_seen_at) {
                    // Gunakan timestamp untuk perbandingan agar aman dari masalah timezone Laravel vs DB
                    $lastSeenTs = $mesin->last_seen_at->getTimestamp();
                    $nowTs = now()->getTimestamp();
                    $isTimeout = ($nowTs - $lastSeenTs) > 30;
                }

                // Paksa status ke Mati (false) jika timeout dan saat ini masih Hidup
                if ($isTimeout && $mesin->status) {
                    $mesin->status = false;
                    $mesin->save();

                    // Broadcast ke dashboard pusher untuk update real-time
                    event(new \App\Events\MesinUpdated([
                        'id' => $mesin->id,
                        'jenis_mesin' => $mesin->jenis_mesin,
                        'status' => false,
                        'auto_offline' => true
                    ]));
                }

                $result[$mesin->id] = [
                    'status' => (bool) $mesin->status,
                    'label' => $mesin->status ? 'Hidup' : 'Mati',
                    'force_alarm_off' => (bool) Cache::get($this->forceAlarmKey((int) $mesin->id), false),
                    'iot_signal' => !$isTimeout,
                    'iot_label' => !$isTimeout ? 'Terhubung' : 'Terputus',
                    'last_on' => $mesin->last_on_at ? $mesin->last_on_at->translatedFormat('d-m-Y H:i:s') : '-',
                    'last_off' => $mesin->last_off_at ? $mesin->last_off_at->translatedFormat('d-m-Y H:i:s') : '-',
                ];
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function toggleForceAlarmOff(Request $request, Mesin $mesin)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'reason' => 'required|string|max:255',
        ]);

        $enabled = (bool) $validated['enabled'];
        $reason = trim((string) ($validated['reason'] ?? ''));
        if ($reason === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Alasan wajib diisi.',
            ], 422);
        }
        $key = $this->forceAlarmKey((int) $mesin->id);
        $metaKey = "iot:mesin:{$mesin->id}:force_alarm_off_meta";

        if ($enabled) {
            Cache::put($key, true, now()->addYears(5));
            // Saat force-off diaktifkan, reset latch agar tidak langsung menyala lagi saat force-off dimatikan.
            Cache::forget("iot:mesin:{$mesin->id}:alarm_latched");
            Cache::put($metaKey, [
                'reason' => $reason,
                'by_user_id' => Auth::id(),
                'by_name' => optional(Auth::user())->nama ?? optional(Auth::user())->username,
                'by_role' => optional(Auth::user())->role,
                'activated_at' => now()->format('Y-m-d H:i:s'),
            ], now()->addYears(5));
        } else {
            $previousMeta = Cache::get($metaKey, []);
            Cache::forget($key);
            Cache::put($metaKey, array_merge((array) $previousMeta, [
                'deactivated_reason' => $reason,
                'deactivated_by_user_id' => Auth::id(),
                'deactivated_by_name' => optional(Auth::user())->nama ?? optional(Auth::user())->username,
                'deactivated_by_role' => optional(Auth::user())->role,
                'deactivated_at' => now()->format('Y-m-d H:i:s'),
            ]), now()->addDays(30));
        }

        // Paksa refresh hasil polling alarm berikutnya.
        Cache::forget("iot:mesin:{$mesin->id}:alarm_result");

        // Catat ke activity log (Spatie)
        try {
            $user = Auth::user();
            $logMessage = $enabled
                ? "Alarm paksa OFF diaktifkan untuk mesin '{$mesin->jenis_mesin}'"
                : "Alarm paksa OFF dinonaktifkan untuk mesin '{$mesin->jenis_mesin}'";

            activity()
                ->useLogName('Manajemen Mesin')
                ->causedBy($user)
                ->performedOn($mesin)
                ->withProperties([
                    'event_type' => 'force_alarm_off_toggle',
                    'mesin_id' => $mesin->id,
                    'jenis_mesin' => $mesin->jenis_mesin,
                    'force_alarm_off' => $enabled,
                    'reason' => $reason,
                ])
                ->log($logMessage);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat activity log force alarm off', [
                'mesin_id' => $mesin->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'mesin_id' => $mesin->id,
            'force_alarm_off' => $enabled,
            'message' => $enabled
                ? 'Mode alarm paksa OFF diaktifkan.'
                : 'Mode alarm paksa OFF dinonaktifkan.',
        ]);
    }
}
