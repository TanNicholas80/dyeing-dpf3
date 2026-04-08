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

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $mesins = Mesin::query();
            return \Yajra\DataTables\Facades\DataTables::of($mesins)
                ->addColumn('status_badge', function ($mesin) {
                    $class = $mesin->status ? 'badge-success' : 'badge-secondary';
                    $text = $mesin->status ? 'Hidup' : 'Mati';
                    return '<span class="badge status-badge ' . $class . '" data-id="' . $mesin->id . '">' . $text . '</span>';
                })
                ->addColumn('force_off', function ($mesin) {
                    $isSuperAdmin = strtolower(Auth::user()->role ?? '') === 'super_admin';
                    if (!$isSuperAdmin) return '';

                    $forceOff = (bool) Cache::get($this->forceAlarmKey((int) $mesin->id), false);
                    $checked = $forceOff ? 'checked' : '';
                    $label = $forceOff ? 'ON' : 'OFF';

                    return '
                        <div class="custom-control custom-switch">
                            <input type="checkbox"
                                class="custom-control-input force-alarm-toggle"
                                id="forceAlarm' . $mesin->id . '"
                                data-id="' . $mesin->id . '"
                                data-jenis="' . htmlspecialchars($mesin->jenis_mesin, ENT_QUOTES) . '"
                                ' . $checked . '>
                            <label class="custom-control-label" for="forceAlarm' . $mesin->id . '">' . $label . '</label>
                        </div>
                    ';
                })
                ->addColumn('action', function ($mesin) {
                    $userRole = Auth::user()->role ?? null;
                    $restrictedRoles = ['fm', 'vp', 'ppic', 'owner'];
                    $canManageMesin = !in_array(strtolower($userRole), $restrictedRoles);

                    if (!$canManageMesin) return '';

                    $editUrl = route('mesin.edit', $mesin->id);
                    $deleteUrl = route('mesin.destroy', $mesin->id);

                    return '
                        <a href="' . $editUrl . '" class="btn btn-warning btn-sm mr-2">
                            <i class="fas fa-pen"></i> Edit
                        </a>
                        <button type="button" class="btn btn-danger btn-sm" onclick="showDeleteModal(\'' . $deleteUrl . '\', \'' . htmlspecialchars($mesin->jenis_mesin, ENT_QUOTES) . '\')">
                            <i class="fas fa-trash-alt"></i> Hapus
                        </button>
                    ';
                })
                ->rawColumns(['status_badge', 'force_off', 'action'])
                ->make(true);
        }

        return view('mesin.index');
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
            $mesins = Mesin::all(['id', 'status']);
            $result = [];
            foreach ($mesins as $mesin) {
                $result[$mesin->id] = [
                    'status' => (bool) $mesin->status,
                    'label' => $mesin->status ? 'Hidup' : 'Mati',
                    'force_alarm_off' => (bool) Cache::get($this->forceAlarmKey((int) $mesin->id), false),
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
