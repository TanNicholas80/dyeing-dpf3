<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Approval;
use App\Models\Mesin;
use App\Models\Proses;
use App\Models\DetailProses;
use App\Models\BarcodeKain;
use App\Models\BarcodeLa;
use App\Models\BarcodeAux;
use App\Events\MesinUpdated;

class ApiCheckStatusBarcodeController extends Controller
{
    private function forceAlarmKey(int $mesinId): string
    {
        return "iot:mesin:{$mesinId}:force_alarm_off";
    }

    private function alarmStateKey(int $mesinId): string
    {
        return "iot:mesin:{$mesinId}:alarm_on_state";
    }

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

        // Mesin OFF: clear latch alarm (alarm akan mati ketika mesin off)
        if (!$isOn) {
            Cache::forget("iot:mesin:{$mesin->id}:alarm_latched");
        }

        // Invalidate cache alarm agar polling berikutnya mendapat status terbaru
        Cache::forget("iot:mesin:{$mesin->id}:alarm_result");
        Cache::forget($this->alarmStateKey((int) $mesin->id));

        // Update status mesin di database (1 = Hidup/ON, 0 = Mati/OFF)
        $mesin->status = $isOn;
        $mesin->save();

        event(new \App\Events\MesinUpdated($mesin));

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
        $latchKey = "iot:mesin:{$mesin->id}:alarm_latched";
        $alarmCacheKey = "iot:mesin:{$mesin->id}:alarm_result";

        // Optimasi: cache response minimal 3 detik untuk Arduino polling
        if (!$full) {
            $cached = Cache::get($alarmCacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        // Ambil state mesin dari cache; jika tidak ada, fallback ke DB (mesins.status)
        $cacheState = Cache::get("iot:mesin:{$mesin->id}:state", null);
        $cacheIsOn = (is_array($cacheState) && array_key_exists('is_on', $cacheState))
            ? (bool) $cacheState['is_on']
            : null;
        $dbIsOn = (bool) $mesin->status;

        // Prioritas: cache (sinyal PLC real-time) -> fallback DB
        $isOn = ($cacheIsOn !== null) ? $cacheIsOn : $dbIsOn;

        // Override super admin: alarm dipaksa OFF tanpa memedulikan rule lain.
        $forceAlarmOff = (bool) Cache::get($this->forceAlarmKey((int) $mesin->id), false);
        if ($forceAlarmOff) {
            Cache::forget($latchKey);
            Cache::put($this->alarmStateKey((int) $mesin->id), false, now()->addMinutes(5));
            $minimal = ['mesin_id' => $mesin->id, 'alarm_on' => false];
            if (!$full) {
                Cache::put($alarmCacheKey, $minimal, now()->addSeconds(3));
                return response()->json($minimal);
            }

            $stateSource = ($cacheIsOn !== null) ? 'cache' : 'db';
            return response()->json([
                'status' => 'success',
                'mesin_id' => $mesin->id,
                'mesin' => $mesin->jenis_mesin,
                'alarm_on' => false,
                'is_on' => $isOn,
                'reason' => 'Alarm dipaksa OFF oleh Super Admin',
                'state' => [
                    'source' => $stateSource,
                    'cache' => $cacheState,
                    'db' => ['status' => $dbIsOn],
                ],
                'force_alarm_off' => true,
                'proses_id' => null,
                'proses_selesai_id' => null,
            ]);
        }

        // Optimasi: early exit saat mesin ON dan alarm latched - skip semua query DB
        if ($isOn) {
            $isLatched = (bool) Cache::get($latchKey, false);
            if ($isLatched) {
                $minimal = ['mesin_id' => $mesin->id, 'alarm_on' => true];
                if (!$full) {
                    Cache::put($alarmCacheKey, $minimal, now()->addSeconds(3));
                    return response()->json($minimal);
                }
            }
        }

        // Pilih proses yang "runnable" untuk alarm:
        // - prioritas proses aktif (mulai != null, selesai null)
        // - fallback ke proses antri yang TIDAK terblokir pending create_reprocess (FM/VP)
        $prosesAktif = Proses::where('mesin_id', $mesin->id)
            ->whereNotNull('mulai')
            ->whereNull('selesai')
            ->orderByDesc('mulai')
            ->orderBy('id', 'asc')
            ->first();

        $hasPendingReprocessApproval = $isOn && Approval::where('status', 'pending')
            ->where('action', 'create_reprocess')
            ->whereIn('type', ['FM', 'VP'])
            ->whereHas('proses', function ($q) use ($mesin) {
                $q->where('mesin_id', $mesin->id)
                    ->whereNull('selesai')
                    ->where('jenis', 'Reproses');
            })
            ->exists();
        $prosesAntriRunnable = Proses::where('mesin_id', $mesin->id)
            ->whereNull('mulai')
            ->whereNull('selesai')
            ->where('order', '>', 0)
            ->whereDoesntHave('approvals', function ($q) {
                $q->where('status', 'pending')
                    ->where('action', 'create_reprocess')
                    ->whereIn('type', ['FM', 'VP']);
            })
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        $proses = $prosesAktif ?: $prosesAntriRunnable;
        $hasNextPlanning = !is_null($prosesAntriRunnable);

        // Proses sebelumnya yang sudah selesai (paling terakhir selesai)
        $prosesSelesai = Proses::where('mesin_id', $mesin->id)
            ->whereNotNull('selesai')
            ->orderByDesc('selesai')
            ->first();

        // Alarm operasional tambahan:
        // 1) Mesin ON tapi tidak ada plan/proses runnable sama sekali
        // 2) Mesin ON dan Reproses masih pending FM/VP, serta tidak ada planning berikutnya yang runnable
        $noPlanOrProses = $isOn && !$proses;
        $pendingReprocessWithoutPlanning = $isOn && $hasPendingReprocessApproval && !$hasNextPlanning && is_null($prosesAktif);

        $alarmOn = false;
        $barcodeIncomplete = false;

        // Cek barcode proses aktif (jika ada dan bukan Maintenance)
        $barcodeIncompleteAktif = $this->checkProsesBarcodeIncomplete($proses);
        // Cek barcode proses sebelumnya yang sudah selesai (kecuali Maintenance)
        $barcodeIncompleteSelesai = $this->checkProsesBarcodeIncomplete($prosesSelesai);

        $barcodeIncomplete = $barcodeIncompleteAktif || $barcodeIncompleteSelesai;

        // Simpan progress untuk response full (dari proses aktif) - hanya saat full=1
        $laRequired = null;
        $laScanned = null;
        $laComplete = null;
        $auxRequired = null;
        $auxScanned = null;
        $auxComplete = null;
        if ($full && $proses && ($proses->jenis ?? null) !== 'Maintenance') {
            [$laRequired, $laScanned, $laComplete, $auxRequired, $auxScanned, $auxComplete] = $this->getProsesBarcodeProgress($proses);
        }

        // Logic alarm baru:
        // - Mesin ON + barcode incomplete -> Alarm ON, latch (tetap ON walau barcode nanti lengkap, sampai mesin OFF)
        // - Mesin OFF -> Alarm mati HANYA jika barcode complete; jika barcode incomplete tetap berbunyi
        if (!$isOn) {
            Cache::forget($latchKey);
            $alarmOn = $barcodeIncomplete;
        } else {
            $operationalAlarm = $noPlanOrProses || $pendingReprocessWithoutPlanning;
            if ($operationalAlarm) {
                $alarmOn = true;
            } else {
                $isLatched = (bool) Cache::get($latchKey, false);
                if ($isLatched) {
                    $alarmOn = true;
                } elseif ($barcodeIncomplete) {
                    $alarmOn = true;
                    Cache::put($latchKey, true, now()->addHours(24));
                } else {
                    $alarmOn = false;
                }
            }
        }

        $minimal = [
            'mesin_id' => $mesin->id,
            'alarm_on' => $alarmOn,
        ];
        Cache::put($this->alarmStateKey((int) $mesin->id), (bool) $alarmOn, now()->addMinutes(5));

        if (!$full) {
            Cache::put($alarmCacheKey, $minimal, now()->addSeconds(3));
            return response()->json($minimal);
        }

        // Response lengkap untuk debug
        $stateSource = ($cacheIsOn !== null) ? 'cache' : 'db';
        $state = [
            'source' => $stateSource,
            'cache' => $cacheState,
            'db' => ['status' => $dbIsOn],
        ];

        $reason = !$isOn
            ? ($alarmOn ? 'Mesin OFF, barcode belum lengkap' : 'Mesin OFF')
            : ($alarmOn
                ? ($noPlanOrProses
                    ? 'Mesin ON tetapi tidak ada plan/proses'
                    : ($pendingReprocessWithoutPlanning
                        ? 'Mesin ON dan Reproses masih menunggu approval FM/VP'
                        : ($barcodeIncompleteSelesai && !$barcodeIncompleteAktif
                            ? 'Proses sebelumnya belum lengkap barcode / alarm latched'
                            : 'Barcode belum lengkap / alarm latched')))
                : (!$proses ? 'Tidak ada proses aktif' : (($proses->jenis ?? null) === 'Maintenance' ? 'Maintenance' : 'Barcode lengkap')));

        $fullPayload = array_merge($minimal, [
            'status' => 'success',
            'mesin' => $mesin->jenis_mesin,
            'is_on' => $isOn,
            'reason' => $reason,
            'state' => $state,
            'proses_id' => $proses?->id,
            'proses_selesai_id' => $prosesSelesai?->id,
            'no_plan_or_proses' => $noPlanOrProses,
            'has_pending_reprocess_approval' => $hasPendingReprocessApproval,
            'has_next_planning' => $hasNextPlanning,
            'pending_reprocess_without_planning' => $pendingReprocessWithoutPlanning,
        ]);

        if ($proses && ($proses->jenis ?? null) !== 'Maintenance' && isset($laComplete, $auxComplete)) {
            $fullPayload['la_progress'] = [
                'required' => $laRequired,
                'scanned' => $laScanned,
                'is_complete' => $laComplete,
            ];
            $fullPayload['aux_progress'] = [
                'required' => $auxRequired,
                'scanned' => $auxScanned,
                'is_complete' => $auxComplete,
            ];
        }

        return response()->json($fullPayload);
    }

    /**
     * Cek apakah barcode proses belum lengkap (GDA/FDA + TD/TA).
     * Kecuali Maintenance: selalu false (tidak perlu barcode).
     *
     * @param Proses|null $proses
     * @return bool true jika barcode incomplete
     */
    private function checkProsesBarcodeIncomplete(?Proses $proses): bool
    {
        if (!$proses || ($proses->jenis ?? null) === 'Maintenance') {
            return false;
        }

        $details = DetailProses::where('proses_id', $proses->id)->get(['id', 'no_op', 'no_partai', 'roll']);
        $mode = $proses->mode ?? 'greige';
        $jenis = $proses->jenis ?? null;

        // Apakah barcode kain (G/F) wajib untuk alarm?
        $requireKain = true;
        if ($jenis === 'Reproses' && $mode === 'greige') {
            $requireKain = false;
        } elseif ($jenis === 'Reproses' && $mode === 'finish') {
            $allDetailSecondOrMore = true;
            foreach ($details as $d) {
                $noOp = $d->no_op ?? '';
                $noPartai = $d->no_partai ?? '';
                if ($noOp === '' || $noPartai === '') {
                    $allDetailSecondOrMore = false;
                    break;
                }
                $countFinishReprosesSelesai = Proses::where('jenis', 'Reproses')
                    ->where('mode', 'finish')
                    ->whereNotNull('selesai')
                    ->where('id', '!=', $proses->id)
                    ->whereHas('details', function ($q) use ($noOp, $noPartai) {
                        $q->where('no_op', $noOp)->where('no_partai', $noPartai);
                    })
                    ->count();
                if ($countFinishReprosesSelesai < 1) {
                    $allDetailSecondOrMore = false;
                    break;
                }
            }
            $requireKain = !$allDetailSecondOrMore;
        }

        $kainIncomplete = false;
        if ($requireKain) {
            $detailIds = $details->pluck('id');
            $scannedCounts = BarcodeKain::whereIn('detail_proses_id', $detailIds)
                ->where('cancel', false)
                ->selectRaw('detail_proses_id, COUNT(*) as cnt')
                ->groupBy('detail_proses_id')
                ->pluck('cnt', 'detail_proses_id');
            foreach ($details as $d) {
                $roll = (int) ($d->roll ?? 0);
                $scanned = (int) ($scannedCounts[$d->id] ?? 0);
                $isComplete = ($roll > 0) ? ($scanned >= $roll) : true;
                if (!$isComplete) {
                    $kainIncomplete = true;
                    break;
                }
            }
        }

        // Validasi LA & AUX: kebutuhan awal (1) + topping yang sudah di-approve (TD/TA)
        $laInitialScanned = BarcodeLa::whereHas('detailProses', fn ($q) => $q->where('proses_id', $proses->id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->exists() ? 1 : 0;
        $laToppingRequired = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->count();
        $laToppingScanned = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->whereHas('barcodeLas', fn ($q) => $q->where('cancel', false))
            ->count();
        $laComplete = ($laInitialScanned + $laToppingScanned) >= (1 + $laToppingRequired);

        $auxInitialScanned = BarcodeAux::whereHas('detailProses', fn ($q) => $q->where('proses_id', $proses->id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->exists() ? 1 : 0;
        $auxToppingRequired = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->count();
        $auxToppingScanned = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->whereHas('barcodeAuxs', fn ($q) => $q->where('cancel', false))
            ->count();
        $auxComplete = ($auxInitialScanned + $auxToppingScanned) >= (1 + $auxToppingRequired);

        return $kainIncomplete || !$laComplete || !$auxComplete;
    }

    /**
     * Ambil progress barcode LA/AUX untuk proses (untuk response full).
     *
     * @return array [laRequired, laScanned, laComplete, auxRequired, auxScanned, auxComplete]
     */
    private function getProsesBarcodeProgress(Proses $proses): array
    {
        $laInitialScanned = BarcodeLa::whereHas('detailProses', fn ($q) => $q->where('proses_id', $proses->id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->exists() ? 1 : 0;
        $laToppingRequired = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->count();
        $laToppingScanned = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->whereHas('barcodeLas', fn ($q) => $q->where('cancel', false))
            ->count();
        $laRequired = 1 + $laToppingRequired;
        $laScanned = $laInitialScanned + $laToppingScanned;
        $laComplete = $laScanned >= $laRequired;

        $auxInitialScanned = BarcodeAux::whereHas('detailProses', fn ($q) => $q->where('proses_id', $proses->id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->exists() ? 1 : 0;
        $auxToppingRequired = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->count();
        $auxToppingScanned = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->whereHas('barcodeAuxs', fn ($q) => $q->where('cancel', false))
            ->count();
        $auxRequired = 1 + $auxToppingRequired;
        $auxScanned = $auxInitialScanned + $auxToppingScanned;
        $auxComplete = $auxScanned >= $auxRequired;

        return [$laRequired, $laScanned, $laComplete, $auxRequired, $auxScanned, $auxComplete];
    }
}
