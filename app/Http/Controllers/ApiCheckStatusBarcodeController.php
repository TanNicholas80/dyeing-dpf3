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
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
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

        // 1. Log detail ke file (storage/logs/iot.log) untuk monitoring tiap 4 detik
        \Illuminate\Support\Facades\Log::channel('iot')->info("Heartbeat Mesin ID: {$mesin->id} ({$mesin->jenis_mesin})", [
            'payload' => $validated,
            'ip' => $request->ip()
        ]);

        // 2. Deteksi jika sinyal baru masuk setelah sempat terputus (> 5 detik)
        // Note: Tidak mencatat ke activity log sesuai permintaan (hanya ke file log saja)

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
        // Jika sengaja OFF, selesaikan proses aktif terlebih dahulu sebelum merubah status mesin
        if (!$isOn) {
            $prosesAktif = $mesin->proses()
                ->whereNotNull('mulai')
                ->whereNull('selesai')
                ->orderBy('order', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($prosesAktif) {
                // KEMBALIKAN KE ASAL: Jika OFF disengaja dari IoT, berarti SELESAI (Masuk History)
                Cache::forget("iot:proses:{$prosesAktif->id}:kain_latched");
                $now = now();
                $prosesAktif->selesai = $now;
                $prosesAktif->is_paused = false;
                if ($prosesAktif->mulai) {
                    $mulai = \Carbon\Carbon::parse($prosesAktif->mulai);
                    $prosesAktif->cycle_time_actual = max(0, (int) round(abs($now->diffInSeconds($mulai))));
                }
                if ($prosesAktif->is_pinjam_mesin) {
                    \App\Models\PinjamMesinHistory::where('proses_id', $prosesAktif->id)
                        ->whereNull('selesai_at')
                        ->latest('pinjam_at')
                        ->update([
                            'selesai_at' => $now,
                            'selesai_by' => null,
                        ]);
                    $prosesAktif->is_pinjam_mesin = false;
                }
                $prosesAktif->save();

                // Broadcast ProsesStatusUpdated agar UI langsung hilang / update ke history
                $prosesAktif->refresh();
                $prosesAktif->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
                $statusService = new \App\Services\ProsesStatusService();
                $affectedProsesIds = $statusService->getAffectedProsesIds();
                $statusData = $statusService->generateProsesStatus($prosesAktif, $affectedProsesIds);

                // KEMBALIKAN KE ASAL: Karena ini selesai/masuk history, gunakan event standar
                event(new \App\Events\ProsesStatusUpdated($prosesAktif->id, $statusData));
            }
        } elseif ($isOn && !$mesin->status) {
            // Mesin baru saja menyala (0 -> 1)
            $prosesAktif = $mesin->proses()
                ->whereNotNull('mulai')
                ->whereNull('selesai')
                ->orderBy('order', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($prosesAktif->isNotEmpty()) {
                foreach ($prosesAktif as $p) {
                    // Cek apakah ada pengajuan pause yang masih PENDING
                    $hasPendingPause = $p->approvals()
                        ->where('action', 'pause_proses')
                        ->where('status', 'pending')
                        ->where('created_at', '>=', $mesin->last_off_at ?? '1970-01-01 00:00:00')
                        ->exists();

                    if (!$hasPendingPause) {
                        // Proses otomatis lanjut ketika mesin hidup kembali, KECUALI jika masih ada pengajuan pause yang menggantung (pending)
                        
                        // Geser 'mulai' maju sebesar durasi pause agar perhitungan elapsed time akurat
                        if ($p->is_paused && $p->mulai) {
                            // Waktu mulai pause tepat saat proses diset is_paused = true (yaitu pada $p->updated_at)
                            $updatedAt = $p->updated_at ? \Carbon\Carbon::parse($p->updated_at) : now();
                            $pauseDuration = abs(now()->diffInSeconds($updatedAt));
                            if ($pauseDuration > 0) {
                                $p->mulai = \Carbon\Carbon::parse($p->mulai)->addSeconds($pauseDuration);
                            }
                        }

                        $p->is_paused = false;
                        $p->save();

                        // Refresh dan load relasi untuk broadcast
                        $p->refresh();
                        $p->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
                        $statusService = new \App\Services\ProsesStatusService();
                        $affectedProsesIds = $statusService->getAffectedProsesIds();
                        $statusData = $statusService->generateProsesStatus($p, $affectedProsesIds);
                        event(new \App\Events\ProsesStatusUpdated($p->id, $statusData));
                    }
                }
            } else {
                // TAPI JIKA TIDAK ADA PROSES AKTIF (Mesin idle dan menyala kembali)
                // Maka jalankan proses selanjutnya otomatis
                $prosesSelanjutnya = $mesin->proses()
                    ->whereNull('mulai')
                    ->whereNull('selesai')
                    ->where('order', '>', 0)
                    ->whereDoesntHave('approvals', function ($query) {
                        $query->where('status', 'pending')
                            ->where('action', 'create_reprocess')
                            ->whereIn('type', ['FM', 'VP']);
                    })
                    ->orderBy('order', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                if ($prosesSelanjutnya) {
                    // Auto reject pending approvals lama (kompatibel untuk MySQL & PostgreSQL)
                    Approval::where('proses_id', $prosesSelanjutnya->id)
                        ->where('status', 'pending')
                        ->where('type', 'FM')
                        ->whereIn('action', ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position'])
                        ->get()
                        ->each(function ($approval) {
                            $prefix = $approval->note ? $approval->note . ' | ' : '';
                            $approval->update([
                                'status' => 'rejected',
                                'note' => $prefix . 'Auto rejected by system: proses otomatis berjalan saat mesin ON.',
                                'approved_by' => null,
                            ]);
                        });

                    // Jalankan proses
                    $prosesSelanjutnya->mulai = now();
                    $prosesSelanjutnya->order = 0;
                    $prosesSelanjutnya->save();

                    // Renumber sisa antrean
                    $sisaPending = $mesin->proses()
                        ->whereNull('mulai')
                        ->whereNull('selesai')
                        ->where('id', '!=', $prosesSelanjutnya->id)
                        ->orderBy('order', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();

                    $order = 1;
                    foreach ($sisaPending as $pending) {
                        $pending->order = $order++;
                        $pending->save();
                    }

                    // Broadcast untuk proses yang baru mulai
                    $prosesSelanjutnya->refresh();
                    $prosesSelanjutnya->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
                    $statusService = new \App\Services\ProsesStatusService();
                    $affectedProsesIds = $statusService->getAffectedProsesIds();
                    $statusData = $statusService->generateProsesStatus($prosesSelanjutnya, $affectedProsesIds);
                    event(new \App\Events\ProsesStatusUpdated($prosesSelanjutnya->id, $statusData));
                }
            }
        }

        $oldStatus = $mesin->status;
        $mesin->status = $isOn;
        $mesin->last_seen_at = now();
        $mesin->save();

        // OPTIMASI CPU: Hanya tembak Event WebSocket JIKA statusnya benar-benar berubah!
        // Jangan spam Queue/Reverb setiap 4 detik.
        if ($oldStatus != $isOn) {
            event(new MesinUpdated($mesin));
        }

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

        // 1. Cek Barcode Kain untuk Proses Aktif & Antrean
        $kainIncompleteAktif = $this->checkBarcodeKainIncomplete($prosesAktif);
        $kainIncompleteRunnable = $this->checkBarcodeKainIncomplete($prosesAntriRunnable);

        // Jika proses sedang berjalan (prosesAktif) dan barcode kain tidak lengkap,
        // maka latch alarm kain untuk proses ini sampai proses selesai.
        $prosesKainLatchedKey = $prosesAktif ? "iot:proses:{$prosesAktif->id}:kain_latched" : null;
        if ($prosesAktif && $kainIncompleteAktif) {
            Cache::put($prosesKainLatchedKey, true, now()->addDays(7));
        }
        $isKainLatched = $prosesKainLatchedKey ? (bool) Cache::get($prosesKainLatchedKey, false) : false;

        // 2. Cek Jadwal Breakdown Dye Stuff & AUX untuk proses yang sedang aktif/berjalan
        $scheduleStatus = $this->checkProsesScheduleAlarm($proses ?: $prosesAktif);
        $isScheduleLate = $scheduleStatus['is_schedule_late'] ?? false;

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

        // 3. Logic Alarm:
        // - Mesin OFF: Alarm ON jika barcode kain belum lengkap pada proses aktif/antrean.
        // - Mesin ON:
        //   * Jika Barcode Kain tidak lengkap saat berjalan -> Alarm ON (latched) sampai proses selesai/history.
        //   * Jika Barcode Kain lengkap -> Alarm mengikuti breakdown Dye Stuff & AUX (ON jika terlambat, OTOMATIS PADAM saat barcode di-scan).
        $alarmOn = false;
        $reason = '';

        if ($forceAlarmOff) {
            $alarmOn = false;
            $reason = 'Alarm dipaksa OFF oleh Super Admin';
        } elseif (!$isOn) {
            // Mesin OFF:
            if ($isKainLatched || $kainIncompleteAktif || $kainIncompleteRunnable) {
                $alarmOn = true;
                $reason = 'Mesin OFF, Barcode Kain belum lengkap';
            } else {
                $alarmOn = false;
                $reason = 'Mesin OFF';
            }
        } else {
            // Mesin ON:
            $operationalAlarm = $noPlanOrProses || $pendingReprocessWithoutPlanning;
            if ($operationalAlarm) {
                $alarmOn = true;
                $reason = $noPlanOrProses
                    ? 'Mesin ON tetapi tidak ada plan/proses'
                    : 'Mesin ON dan Reproses masih menunggu approval FM/VP';
            } elseif ($isKainLatched) {
                // Barcode kain tidak lengkap saat proses berjalan -> ALARM NYALA TERUS SAMPAI PROSES SELESAI
                $alarmOn = true;
                $reason = 'Proses berjalan dengan Barcode Kain tidak lengkap (Alarm aktif hingga proses selesai/history)';
            } elseif ($isScheduleLate) {
                // Barcode kain lengkap, tetapi Dye Stuff atau AUX terlambat di-input sesuai breakdown cycle time atau topping belum dilengkapi (> 45 menit)
                $alarmOn = true;
                $reasons = [];
                if (!empty($scheduleStatus['la_initial_late'])) {
                    $reasons[] = "Dye Stuff terlambat (Dibutuhkan: {$scheduleStatus['la_due_count']}, Ter-scan: {$scheduleStatus['la_scanned']})";
                }
                if (!empty($scheduleStatus['la_topping_overdue'])) {
                    $reasons[] = "Topping Dye Stuff belum dilengkapi (> 45 menit setelah approval Kashift)";
                }
                if (!empty($scheduleStatus['aux_initial_late'])) {
                    $reasons[] = "AUX terlambat (Dibutuhkan: {$scheduleStatus['aux_due_count']}, Ter-scan: {$scheduleStatus['aux_scanned']})";
                }
                if (!empty($scheduleStatus['aux_topping_overdue'])) {
                    $reasons[] = "Topping AUX belum dilengkapi (> 45 menit setelah approval Kashift)";
                }
                $reason = 'Jadwal input / Topping terlambat: ' . implode(' & ', $reasons);
            } else {
                $alarmOn = false;
                $reason = (!$proses)
                    ? 'Tidak ada proses aktif'
                    : (($proses->jenis ?? null) === 'Maintenance'
                        ? 'Maintenance'
                        : 'Barcode Kain lengkap & Dye Stuff/AUX sesuai jadwal breakdown');
            }
        }

        // Sinyal Modbus Address 103 & 105:
        $signal103 = $this->checkSignal103($mesin, $prosesAktif, $prosesSelesai);
        $signal105 = $this->checkSignal105($mesin, $prosesAktif, $prosesAntriRunnable);

        $minimal = [
            'mesin_id' => $mesin->id,
            'alarm_on' => (bool) $alarmOn,
            'address_100' => $alarmOn ? 1 : 0,
            'address_103' => $signal103 ? 1 : 0,
            'address_105' => $signal105 ? 1 : 0,
            'signals' => [
                '100' => $alarmOn ? 1 : 0,
                '103' => $signal103 ? 1 : 0,
                '105' => $signal105 ? 1 : 0,
            ],
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

        $fullPayload = array_merge($minimal, [
            'status' => 'success',
            'mesin' => $mesin->jenis_mesin,
            'is_on' => $isOn,
            'reason' => $reason,
            'state' => $state,
            'force_alarm_off' => $forceAlarmOff,
            'proses_id' => $proses?->id,
            'proses_selesai_id' => $prosesSelesai?->id,
            'no_plan_or_proses' => $noPlanOrProses,
            'has_pending_reprocess_approval' => $hasPendingReprocessApproval,
            'has_next_planning' => $hasNextPlanning,
            'pending_reprocess_without_planning' => $pendingReprocessWithoutPlanning,
            'is_kain_latched' => $isKainLatched,
            'schedule_status' => $scheduleStatus,
            'signal_103_detail' => [
                'value' => $signal103 ? 1 : 0,
                'is_active' => $signal103,
                'description' => '1 jika maintenance selesai manual atau semua barcode (kain, LA, AUX, topping) lengkap',
            ],
            'signal_105_detail' => [
                'value' => $signal105 ? 1 : 0,
                'is_active' => $signal105,
                'description' => '1 jika proses memiliki status / flag pinjam mesin',
            ],
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
     * Helper konversi string jam (JJ:MM:DD / JJ:MM / detik) ke Total Detik.
     */
    private function parseScheduleTimeToSeconds($val): int
    {
        if (empty($val)) {
            return 0;
        }
        if (is_array($val) && isset($val['time'])) {
            $val = $val['time'];
        } elseif (is_object($val) && isset($val->time)) {
            $val = $val->time;
        }
        if (is_numeric($val)) {
            return (int) $val;
        }
        $parts = explode(':', trim((string) $val));
        if (count($parts) === 3) {
            return ((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60 + ((int) $parts[2]);
        }
        if (count($parts) === 2) {
            return ((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60;
        }
        return (int) $val;
    }

    /**
     * Cek apakah Barcode Kain (G/F) belum lengkap pada proses tertentu.
     */
    private function checkBarcodeKainIncomplete(?Proses $proses): bool
    {
        if (!$proses || ($proses->jenis ?? null) === 'Maintenance') {
            return false;
        }

        $details = DetailProses::where('proses_id', $proses->id)->get(['id', 'no_op', 'no_partai', 'roll']);
        $mode = $proses->mode ?? 'greige';
        $jenis = $proses->jenis ?? null;

        // Apakah barcode kain (G/F) wajib?
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

        if (!$requireKain) {
            return false;
        }

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
                return true; // Kain belum lengkap
            }
        }

        return false;
    }

    /**
     * Cek status untuk sinyal Modbus Address 103:
     * 1. Maintenance dihentikan/diselesaikan manual oleh Kashift/Super Admin.
     * 2. Atau Proses (Single OP/Multiple OP) seluruh barcode-nya sudah LENGKAP di-scan (Kain + Dye Stuff + AUX + Topping).
     */
    private function checkSignal103(Mesin $mesin, ?Proses $prosesAktif, ?Proses $prosesSelesai): bool
    {
        // 1. Cek Maintenance
        if ($prosesAktif && ($prosesAktif->jenis ?? null) === 'Maintenance') {
            // Jika proses aktif adalah Maintenance dan sudah diselesaikan:
            if ($prosesAktif->selesai !== null) {
                return true;
            }
        }

        // Cek jika proses selesai terakhir adalah Maintenance yang diselesaikan secara manual
        if (!$prosesAktif && $prosesSelesai && ($prosesSelesai->jenis ?? null) === 'Maintenance') {
            if ($prosesSelesai->selesai && \Carbon\Carbon::parse($prosesSelesai->selesai)->isToday()) {
                return true;
            }
        }

        // 2. Cek Proses Produksi / Reproses (Single OP & Multiple OP)
        if ($prosesAktif && ($prosesAktif->jenis ?? null) !== 'Maintenance') {
            // A. Cek Barcode Kain
            $kainIncomplete = $this->checkBarcodeKainIncomplete($prosesAktif);
            if ($kainIncomplete) {
                return false;
            }

            // B. Cek Barcode Dye Stuff (Initial + Topping)
            $qtyDyeStuff = (int) ($prosesAktif->qty_dye_stuff ?? 0);
            $laInitialScanned = BarcodeLa::whereHas('detailProses', fn($q) => $q->where('proses_id', $prosesAktif->id))
                ->whereNull('approval_id')
                ->where('cancel', false)
                ->distinct()
                ->count('barcode');
            $laInitialComplete = ($qtyDyeStuff > 0) ? ($laInitialScanned >= $qtyDyeStuff) : true;
            if (!$laInitialComplete) {
                return false;
            }

            $approvedToppingLa = Approval::where('proses_id', $prosesAktif->id)
                ->where('type', 'KEPALA_SHIFT')
                ->where('action', 'topping_la')
                ->where('status', 'approved')
                ->get();
            $laToppingAllScanned = $approvedToppingLa->every(function ($appr) {
                return BarcodeLa::where('approval_id', $appr->id)->where('cancel', false)->exists();
            });
            if (!$laToppingAllScanned) {
                return false;
            }

            // C. Cek Barcode AUX (Initial + Topping)
            $qtyAux = (int) ($prosesAktif->qty_aux ?? 0);
            $auxInitialScanned = BarcodeAux::whereHas('detailProses', fn($q) => $q->where('proses_id', $prosesAktif->id))
                ->whereNull('approval_id')
                ->where('cancel', false)
                ->distinct()
                ->count('barcode');
            $auxInitialComplete = ($qtyAux > 0) ? ($auxInitialScanned >= $qtyAux) : true;
            if (!$auxInitialComplete) {
                return false;
            }

            $approvedToppingAux = Approval::where('proses_id', $prosesAktif->id)
                ->where('type', 'KEPALA_SHIFT')
                ->where('action', 'topping_aux')
                ->where('status', 'approved')
                ->get();
            $auxToppingAllScanned = $approvedToppingAux->every(function ($appr) {
                return BarcodeAux::where('approval_id', $appr->id)->where('cancel', false)->exists();
            });
            if (!$auxToppingAllScanned) {
                return false;
            }

            // Semua barcode (Kain, Dye Stuff, AUX, Topping) sudah LENGKAP
            return true;
        }

        return false;
    }

    /**
     * Cek status untuk sinyal Modbus Address 105:
     * Bernilai true (1) ketika proses memiliki flag toggle Pinjam Mesin aktif (is_pinjam_mesin = true).
     * Bernilai false (0) ketika pinjam mesin dimatikan / tidak aktif.
     */
    private function checkSignal105(Mesin $mesin, ?Proses $prosesAktif, ?Proses $prosesAntriRunnable): bool
    {
        // 1. Cek langsung pada proses aktif
        if ($prosesAktif) {
            if ($prosesAktif->is_pinjam_mesin) {
                return true;
            }
            // Jika proses aktif secara eksplisit is_pinjam_mesin == false, return false
            if ($prosesAktif->is_pinjam_mesin === false) {
                // Jangan override jika dimatikan
                return false;
            }
        }

        // 2. Cek pada proses antrean berikutnya yang runnable jika belum ada proses aktif
        if (!$prosesAktif && $prosesAntriRunnable) {
            if ($prosesAntriRunnable->is_pinjam_mesin) {
                return true;
            }
        }

        // Fallback backward-compatibility untuk record approval lama jika kolom is_pinjam_mesin belum diset
        if ($prosesAktif) {
            $hasPinjamAktif = Approval::where('proses_id', $prosesAktif->id)
                ->where('action', 'pinjam_mesin')
                ->where('status', 'approved')
                ->exists();
            if ($hasPinjamAktif && $prosesAktif->is_pinjam_mesin !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek status keterlambatan jadwal input Dye Stuff & AUX berdasarkan Breakdown Cycle Time.
     * Alarm otomatis ON jika waktu breakdown sudah terlewat dan barcode belum di-scan,
     * dan otomatis PADAM (OFF) saat barcode sudah di-input.
     */
    private function checkProsesScheduleAlarm(?Proses $proses): array
    {
        $default = [
            'elapsed_seconds' => 0,
            'la_due_count' => 0,
            'la_required_now' => 0,
            'la_scanned' => 0,
            'la_late' => false,
            'aux_due_count' => 0,
            'aux_required_now' => 0,
            'aux_scanned' => 0,
            'aux_late' => false,
            'is_schedule_late' => false,
        ];

        if (!$proses || ($proses->jenis ?? null) === 'Maintenance') {
            return $default;
        }

        // Hitung durasi proses yang sudah berjalan (elapsed seconds)
        $elapsedSeconds = 0;
        if ($proses->mulai) {
            $mulaiCarbon = \Carbon\Carbon::parse($proses->mulai);
            if ($proses->is_paused) {
                $pausedAt = $proses->updated_at ? \Carbon\Carbon::parse($proses->updated_at) : now();
                $elapsedSeconds = max(0, abs($pausedAt->diffInSeconds($mulaiCarbon)));
            } else {
                $elapsedSeconds = max(0, abs(now()->diffInSeconds($mulaiCarbon)));
            }
        }

        // 1. Evaluasi Jadwal Breakdown Dye Stuff (LA)
        $qtyDyeStuff = (int) ($proses->qty_dye_stuff ?? 0);
        $schedulesDs = $proses->dye_stuff_schedules;
        if (is_string($schedulesDs)) {
            $schedulesDs = json_decode($schedulesDs, true);
        }

        $laDueCount = 0;
        if ($qtyDyeStuff > 0) {
            if (is_array($schedulesDs) && count($schedulesDs) > 0) {
                foreach ($schedulesDs as $sch) {
                    $schSec = $this->parseScheduleTimeToSeconds($sch);
                    if ($elapsedSeconds >= $schSec) {
                        $laDueCount++;
                    }
                }
                $laDueCount = min($laDueCount, $qtyDyeStuff);
            } else {
                // Jika tidak ada jadwal breakdown, jatuh tempo sejak proses mulai berjalan
                $laDueCount = $proses->mulai ? $qtyDyeStuff : 0;
            }
        }

        // Cek initial barcode LA (tanpa approval_id)
        $laInitialScanned = BarcodeLa::whereHas('detailProses', fn($q) => $q->where('proses_id', $proses->id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->distinct()
            ->count('barcode');
        $laInitialLate = $laInitialScanned < $laDueCount;

        // Cek topping LA: Berikan spare waktu 45 menit (2700 detik) sejak approval Kashift
        $approvedToppingLa = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->get();

        $laToppingOverdue = false;
        $laToppingOverdueCount = 0;
        $laToppingScannedCount = 0;

        foreach ($approvedToppingLa as $appr) {
            $isScanned = BarcodeLa::where('approval_id', $appr->id)
                ->where('cancel', false)
                ->exists();

            if ($isScanned) {
                $laToppingScannedCount++;
            } else {
                $approvedAt = $appr->updated_at ?: $appr->created_at;
                $secondsSinceApproved = $approvedAt ? max(0, abs(now()->diffInSeconds(\Carbon\Carbon::parse($approvedAt)))) : 0;
                // Jika sudah melewati spare waktu 45 menit (2700 detik) dan belum di-scan
                if ($secondsSinceApproved >= 2700) {
                    $laToppingOverdue = true;
                    $laToppingOverdueCount++;
                }
            }
        }

        $laLate = $laInitialLate || $laToppingOverdue;
        $laRequiredNow = $laDueCount + $approvedToppingLa->count();
        $laScannedTotal = $laInitialScanned + $laToppingScannedCount;

        // 2. Evaluasi Jadwal Breakdown AUX
        $qtyAux = (int) ($proses->qty_aux ?? 0);
        $schedulesAux = $proses->aux_schedules;
        if (is_string($schedulesAux)) {
            $schedulesAux = json_decode($schedulesAux, true);
        }

        $auxDueCount = 0;
        if ($qtyAux > 0) {
            if (is_array($schedulesAux) && count($schedulesAux) > 0) {
                foreach ($schedulesAux as $sch) {
                    $schSec = $this->parseScheduleTimeToSeconds($sch);
                    if ($elapsedSeconds >= $schSec) {
                        $auxDueCount++;
                    }
                }
                $auxDueCount = min($auxDueCount, $qtyAux);
            } else {
                // Jika tidak ada jadwal breakdown, jatuh tempo sejak proses mulai berjalan
                $auxDueCount = $proses->mulai ? $qtyAux : 0;
            }
        }

        // Cek initial barcode AUX (tanpa approval_id)
        $auxInitialScanned = BarcodeAux::whereHas('detailProses', fn($q) => $q->where('proses_id', $proses->id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->distinct()
            ->count('barcode');
        $auxInitialLate = $auxInitialScanned < $auxDueCount;

        // Cek topping AUX: Berikan spare waktu 45 menit (2700 detik) sejak approval Kashift
        $approvedToppingAux = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->get();

        $auxToppingOverdue = false;
        $auxToppingOverdueCount = 0;
        $auxToppingScannedCount = 0;

        foreach ($approvedToppingAux as $appr) {
            $isScanned = BarcodeAux::where('approval_id', $appr->id)
                ->where('cancel', false)
                ->exists();

            if ($isScanned) {
                $auxToppingScannedCount++;
            } else {
                $approvedAt = $appr->updated_at ?: $appr->created_at;
                $secondsSinceApproved = $approvedAt ? max(0, abs(now()->diffInSeconds(\Carbon\Carbon::parse($approvedAt)))) : 0;
                // Jika sudah melewati spare waktu 45 menit (2700 detik) dan belum di-scan
                if ($secondsSinceApproved >= 2700) {
                    $auxToppingOverdue = true;
                    $auxToppingOverdueCount++;
                }
            }
        }

        $auxLate = $auxInitialLate || $auxToppingOverdue;
        $auxRequiredNow = $auxDueCount + $approvedToppingAux->count();
        $auxScannedTotal = $auxInitialScanned + $auxToppingScannedCount;

        return [
            'elapsed_seconds' => $elapsedSeconds,
            'la_due_count' => $laDueCount,
            'la_required_now' => $laRequiredNow,
            'la_scanned' => $laScannedTotal,
            'la_late' => $laLate,
            'la_initial_late' => $laInitialLate,
            'la_topping_overdue' => $laToppingOverdue,
            'aux_due_count' => $auxDueCount,
            'aux_required_now' => $auxRequiredNow,
            'aux_scanned' => $auxScannedTotal,
            'aux_late' => $auxLate,
            'aux_initial_late' => $auxInitialLate,
            'aux_topping_overdue' => $auxToppingOverdue,
            'is_schedule_late' => ($laLate || $auxLate),
        ];
    }

    /**
     * Cek apakah barcode proses belum lengkap (Kain incomplete atau Dye Stuff/AUX terlambat).
     * Kecuali Maintenance: selalu false (tidak perlu barcode).
     *
     * @param Proses|null $proses
     * @return bool true jika barcode incomplete atau late
     */
    private function checkProsesBarcodeIncomplete(?Proses $proses): bool
    {
        if (!$proses || ($proses->jenis ?? null) === 'Maintenance') {
            return false;
        }

        $kainIncomplete = $this->checkBarcodeKainIncomplete($proses);
        $scheduleStatus = $this->checkProsesScheduleAlarm($proses);

        return $kainIncomplete || ($scheduleStatus['is_schedule_late'] ?? false);
    }

    /**
     * Helper publik untuk mengecek apakah jadwal kimia (DS, AUX, atau Topping) untuk proses sedang terlambat.
     */
    public static function isProsesScheduleLate(?Proses $proses): bool
    {
        if (!$proses || ($proses->jenis ?? null) === 'Maintenance') {
            return false;
        }
        $inst = new self();
        $status = $inst->checkProsesScheduleAlarm($proses);
        return (bool) ($status['is_schedule_late'] ?? false);
    }

    /**
     * Ambil progress barcode LA/AUX untuk proses (untuk response full).
     *
     * @return array [laRequired, laScanned, laComplete, auxRequired, auxScanned, auxComplete]
     */
    private function getProsesBarcodeProgress(Proses $proses): array
    {
        $laInitialScanned = BarcodeLa::whereHas('detailProses', fn($q) => $q->where('proses_id', $proses->id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->distinct()
            ->count('barcode');
        $laToppingRequired = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->count();
        $laToppingScanned = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->whereHas('barcodeLas', fn($q) => $q->where('cancel', false))
            ->count();
        $laRequired = ($proses->qty_dye_stuff ?? 0) + $laToppingRequired;
        $laScanned = $laInitialScanned + $laToppingScanned;
        $laComplete = $laScanned >= $laRequired;

        $auxInitialScanned = BarcodeAux::whereHas('detailProses', fn($q) => $q->where('proses_id', $proses->id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->distinct()
            ->count('barcode');
        $auxToppingRequired = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->count();
        $auxToppingScanned = Approval::where('proses_id', $proses->id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->whereHas('barcodeAuxs', fn($q) => $q->where('cancel', false))
            ->count();
        $auxRequired = ($proses->qty_aux ?? 0) + $auxToppingRequired;
        $auxScanned = $auxInitialScanned + $auxToppingScanned;
        $auxComplete = $auxScanned >= $auxRequired;

        return [$laRequired, $laScanned, $laComplete, $auxRequired, $auxScanned, $auxComplete];
    }

    /**
     * Polling semua sinyal Modbus (Address 100, 103, 105) untuk semua mesin sekaligus.
     * GET /api/iot/signals
     */
    public function getAllSignals(Request $request)
    {
        $this->assertDeviceToken($request);
        $mesins = Mesin::orderBy('id')->get();
        $results = [];

        foreach ($mesins as $mesin) {
            $signalResponse = $this->getAlarmStatus($request, $mesin);
            $results[] = $signalResponse->getData(true);
        }

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String(),
            'total_mesin' => count($results),
            'data' => $results,
        ]);
    }

    /**
     * Fallback endpoint untuk firmware lama (Backward Compatibility)
     * POST /api/iot/checkStatus
     */
    public function checkStatusLegacy(Request $request)
    {
        $machineId = $request->input('id_mesin');
        $statusMesin = $request->input('status_mesin');

        if (!$machineId) {
            return response()->json(['status' => 'error', 'message' => 'id_mesin required'], 400);
        }

        $mesin = Mesin::where('jenis_mesin', $machineId)
            ->orWhere('id', $machineId)
            ->first();

        if (!$mesin) {
            return response()->json(['status' => 'error', 'message' => 'Mesin not found'], 404);
        }

        // Bypass device token check for legacy firmware if missing
        if (!$request->hasHeader('X-DEVICE-TOKEN') && env('IOT_DEVICE_TOKEN')) {
            $request->headers->set('X-DEVICE-TOKEN', env('IOT_DEVICE_TOKEN'));
        }

        // Merge parameter lama ke request
        $request->merge([
            'is_on' => (bool)$statusMesin,
            'source' => 'legacy',
        ]);

        return $this->updateMesinState($request, $mesin);
    }
}

// Trigger Mesin ON itu dari Pompa Air menyala, Relay akan mengirimkan Sinyal API ke ESP32 untuk mematikan Alarm

// Trigger Finish Roller kain nyala dan mengeluarkan kain 