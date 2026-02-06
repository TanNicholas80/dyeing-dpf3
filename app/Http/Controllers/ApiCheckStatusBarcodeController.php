<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Mesin;
use App\Models\Proses;
use App\Models\DetailProses;
use App\Models\BarcodeKain;
use App\Models\BarcodeLa;
use App\Models\BarcodeAux;

class ApiCheckStatusBarcodeController extends Controller
{
    private function assertDeviceToken(Request $request): void
    {
        $expected = env('IOT_DEVICE_TOKEN');
        if (!$expected) {
            // Jika belum diset, jangan blokir (untuk memudahkan awal integrasi).
            return;
        }

        $provided = $request->header('X-DEVICE-TOKEN');
        if (!$provided || !hash_equals((string) $expected, (string) $provided)) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'Unauthorized device',
            ], 401));
        }
    }

    /**
     * Arduino/ESP kirim status mesin (ON/OFF) dari PLC.
     * POST /api/iot/mesin/{mesin}/state
     * Header: X-DEVICE-TOKEN: <token>
     * Body: { "is_on": true, "source": "plc", "ts": "2026-02-03T10:00:00Z" }
     */
    public function updateMesinState(Request $request, Mesin $mesin)
    {
        $this->assertDeviceToken($request);

        $validated = $request->validate([
            'is_on' => 'required|boolean',
            'source' => 'nullable|string|max:50',
            'ts' => 'nullable|string|max:50',
        ]);

        $cacheKey = "iot:mesin:{$mesin->id}:state";
        $isOn = (bool) $validated['is_on'];
        $payload = [
            'is_on' => $isOn,
            'source' => $validated['source'] ?? null,
            'ts' => $validated['ts'] ?? null,
            'server_time' => now()->toIso8601String(),
        ];

        // TTL pendek supaya kalau device mati, status tidak stale terlalu lama
        Cache::put($cacheKey, $payload, now()->addMinutes(2));

        // Update status mesin di database (1 = Hidup/ON, 0 = Mati/OFF)
        $mesin->status = $isOn;
        $mesin->save();

        return response()->json([
            'status' => 'success',
            'mesin_id' => $mesin->id,
            'mesin' => $mesin->jenis_mesin,
            'state' => $payload,
        ]);
    }

    /**
     * Arduino/ESP polling status alarm untuk mesin.
     * GET /api/iot/mesin/{mesin}/alarm
     * Header: X-DEVICE-TOKEN: <token>
     *
     * Response minimal untuk Arduino: mesin_id, alarm_on.
     * Tambahkan ?full=1 untuk response lengkap (debug).
     */
    public function getAlarmStatus(Request $request, Mesin $mesin)
    {
        $this->assertDeviceToken($request);
        $full = filter_var($request->query('full'), FILTER_VALIDATE_BOOLEAN);

        // Ambil state mesin dari cache; jika tidak ada, fallback ke DB (mesins.status)
        $cacheState = Cache::get("iot:mesin:{$mesin->id}:state", null);
        $cacheIsOn = (is_array($cacheState) && array_key_exists('is_on', $cacheState))
            ? (bool) $cacheState['is_on']
            : null;
        $dbIsOn = (bool) $mesin->status;

        // Prioritas: cache (sinyal PLC real-time) -> fallback DB
        $isOn = ($cacheIsOn !== null) ? $cacheIsOn : $dbIsOn;

        // Pilih proses yang paling "aktif":
        // - prioritas proses yang sudah mulai (mulai != null) tapi belum selesai
        // - fallback ke proses antri (mulai null) yang belum selesai
        $proses = Proses::where('mesin_id', $mesin->id)
            ->whereNull('selesai')
            ->orderByRaw('CASE WHEN mulai IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('mulai')
            ->orderBy('order', 'asc')
            ->first();

        $alarmOn = false;

        if ($isOn && $proses && ($proses->jenis ?? null) !== 'Maintenance') {
            $details = DetailProses::where('proses_id', $proses->id)->get(['id', 'no_op', 'roll']);
            $detailIds = $details->pluck('id')->all();

            $kainIncomplete = false;
            foreach ($details as $d) {
                $roll = (int) ($d->roll ?? 0);
                $scanned = BarcodeKain::where('detail_proses_id', $d->id)
                    ->where('cancel', false)
                    ->count();
                $isComplete = ($roll > 0) ? ($scanned >= $roll) : true;
                if (!$isComplete) {
                    $kainIncomplete = true;
                    break;
                }
            }

            $hasLa = !empty($detailIds)
                ? BarcodeLa::whereIn('detail_proses_id', $detailIds)->where('cancel', false)->exists()
                : false;
            $hasAux = !empty($detailIds)
                ? BarcodeAux::whereIn('detail_proses_id', $detailIds)->where('cancel', false)->exists()
                : false;

            $alarmOn = $kainIncomplete || !$hasLa || !$hasAux;
        }

        $minimal = [
            'mesin_id' => $mesin->id,
            'alarm_on' => $alarmOn,
        ];

        if (!$full) {
            return response()->json($minimal);
        }

        // Response lengkap untuk debug
        $stateSource = ($cacheIsOn !== null) ? 'cache' : 'db';
        $state = [
            'source' => $stateSource,
            'cache' => $cacheState,
            'db' => ['status' => $dbIsOn],
        ];

        $reason = !$isOn ? "Mesin OFF" : (!$proses ? 'Tidak ada proses aktif' : (($proses->jenis ?? null) === 'Maintenance' ? 'Maintenance' : ($alarmOn ? 'Barcode belum lengkap' : 'Barcode lengkap')));

        return response()->json(array_merge($minimal, [
            'status' => 'success',
            'mesin' => $mesin->jenis_mesin,
            'is_on' => $isOn,
            'reason' => $reason,
            'state' => $state,
            'proses_id' => $proses?->id,
        ]));
    }
}
