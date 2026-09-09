@php
    if (!function_exists('getGradient')) {
        function getGradient($bg)
        {
            $bg = trim($bg);
            $map = [
                '#757575' => 'linear-gradient(180deg, #bdbdbd 0%, #757575 100%)',
                '#ffeb3b' => 'linear-gradient(180deg, #fff9c4 0%,rgb(183, 168, 33) 60%,rgb(202, 161, 57) 100%)',
                '#002b80' => 'linear-gradient(180deg, #6dd5ed 0%, #2193b0 60%, #002b80 100%)',
                '#00c853' => 'linear-gradient(180deg, #b2f7c1 0%, #56ab2f 60%, #378a1b 100%)',
                '#e53935' => '#e53935',
                '#ef9a9a' => '#ef9a9a',
            ];
            return $map[$bg] ?? $map['#757575'];
        }
    }
    if (!function_exists('getTextColor')) {
        function getTextColor($bg)
        {
            $dark = ['#002b80', '#263238', '#e53935', '#00c853'];
            if (in_array($bg, $dark)) {
                return '#fff';
            }
            return '#222';
        }
    }
    if (!function_exists('detikKeWaktu')) {
        function detikKeWaktu($detik)
        {
            $jam = floor($detik / 3600);
            $menit = floor(($detik % 3600) / 60);
            $detik = $detik % 60;
            return sprintf('%02d:%02d:%02d', $jam, $menit, $detik);
        }
    }

    // Jenis proses: P/R/M
    $type =
        $proses->jenis === 'Produksi'
        ? 'P'
        : ($proses->jenis === 'Reproses'
            ? 'R'
            : 'M');
    // Status blok G, D, A (G: hijau jika barcode kain >= roll, D/A: hijau jika ada barcode)
    if ($proses->jenis === 'Maintenance') {
        $blockColors = ['gray', 'gray', 'gray'];
    } else {
        // G: hijau hanya jika SEMUA detail OP sudah memenuhi barcode kain >= roll, kuning jika ada pending approval over limit
        $allKainComplete = true;
        $hasPendingKainOverGi = false;
        if (isset($proses->details) && is_iterable($proses->details)) {
            foreach ($proses->details as $d) {
                if (isset($d->barcodeKains)) {
                    if ($d->barcodeKains->where('cancel', false)->where('approval_status', 'pending')->isNotEmpty()) {
                        $hasPendingKainOverGi = true;
                    }
                }
                // Cek apakah detail ini sudah memenuhi barcode kain >= roll
                $detailRoll = $d->roll ?? 0;
                $detailKainCount = isset($d->barcodeKains)
                    ? $d->barcodeKains->where('cancel', false)->where('approval_status', 'approved')->count()
                    : 0;
                if ($detailRoll > 0 && $detailKainCount < $detailRoll) {
                    $allKainComplete = false;
                }
            }
        } else {
            $allKainComplete = false;
        }

        $hasBarcodeLa = isset($proses->details) && $proses->details->isNotEmpty()
            ? $proses->details->every(fn($d) => $d->barcodeLas && $d->barcodeLas->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_dye_stuff ?? 0))
            : false;
        $hasBarcodeAux = isset($proses->details) && $proses->details->isNotEmpty()
            ? $proses->details->every(fn($d) => $d->barcodeAuxs && $d->barcodeAuxs->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_aux ?? 0))
            : false;

        // Fallback untuk LA dan AUX
        if (!$hasBarcodeLa && isset($proses->barcode_la)) {
            $hasBarcodeLa = (bool) $proses->barcode_la;
        }
        if (!$hasBarcodeAux && isset($proses->barcode_aux)) {
            $hasBarcodeAux = (bool) $proses->barcode_aux;
        }
        // G: kuning jika ada pending approval over limit, hijau jika semua detail OP sudah memenuhi barcode kain >= roll
        // D: hijau jika ada minimal 1 barcode LA (cancel=false)
        // A: hijau jika ada minimal 1 barcode AUX (cancel=false)
        $kainColor = $hasPendingKainOverGi ? 'yellow' : ($allKainComplete ? 'green' : 'red');
        $blockColors = [
            $kainColor,
            $hasBarcodeLa ? 'green' : 'red',
            $hasBarcodeAux ? 'green' : 'red',
        ];
    }
    $barcodeKainOptional = $proses->barcode_kain_optional ?? false;
    if ($barcodeKainOptional) {
        $blocks = ['D', 'A'];
        $blockColors = [
            $hasBarcodeLa ? 'green' : 'red',
            $hasBarcodeAux ? 'green' : 'red',
        ];
    } else {
        $blocks = (($proses->mode ?? 'greige') === 'finish') ? ['F', 'D', 'A'] : ['G', 'D', 'A'];
    }
    // Lampu indikator: merah jika is_paused, hijau jika mulai ada dan selesai null, merah jika mulai dan selesai ada, atau mulai null
    $alarmOnState = \Illuminate\Support\Facades\Cache::get("iot:mesin:{$proses->mesin_id}:alarm_on_state", null);
    if ($proses->mulai && !$proses->selesai) {
        if ($proses->is_paused) {
            $light = 'red';
        } else {
            $light = $alarmOnState ? 'yellow' : 'green';
        }
    } else {
        $light = 'red';
    }
    // Background card sesuai status proses
    $bg = '#757575';
    // Cek apakah ada approval pending untuk edit/delete/move -> kuning
    $hasPendingChange = false;
    $hasPendingReprocessApproval = false;
    if (
        isset($proses->approvals) &&
        is_iterable($proses->approvals)
    ) {
        // Cek pending approval FM untuk edit/delete/move/swap
        $hasPendingChange = collect($proses->approvals)->contains(
            function ($appr) {
                return $appr->status === 'pending' &&
                    $appr->type === 'FM' &&
                    in_array($appr->action, [
                        'edit_cycle_time',
                        'delete_proses',
                        'move_machine',
                        'swap_position',
                        'pause_proses',
                    ]);
            },
        );
        // Cek pending approval FM atau VP untuk Reproses (2 tahap approval: FM dulu, baru VP)
        if ($proses->jenis === 'Reproses') {
            $hasPendingReprocessApproval = collect(
                $proses->approvals,
            )->contains(function ($appr) {
                return $appr->status === 'pending' &&
                    $appr->action === 'create_reprocess' &&
                    ($appr->type === 'FM' || $appr->type === 'VP');
            });
        }
        $pendingToppingLa = collect($proses->approvals ?? [])->contains(fn($a) => ($a->action ?? '') === 'topping_la' && ($a->status ?? '') === 'pending');
        $pendingToppingAux = collect($proses->approvals ?? [])->contains(fn($a) => ($a->action ?? '') === 'topping_aux' && ($a->status ?? '') === 'pending');
        $hasToppingLa = collect($proses->approvals ?? [])->contains(fn($a) => ($a->action ?? '') === 'topping_la' && in_array(($a->status ?? ''), ['pending', 'approved']));
        $hasToppingAux = collect($proses->approvals ?? [])->contains(fn($a) => ($a->action ?? '') === 'topping_aux' && in_array(($a->status ?? ''), ['pending', 'approved']));
        $approvedToppingLaNotScanned = collect($proses->approvals ?? [])->contains(function ($a) {
            if (($a->action ?? '') !== 'topping_la' || ($a->status ?? '') !== 'approved')
                return false;
            return !($a->barcodeLas && $a->barcodeLas->where('cancel', false)->count() > 0);
        });
        $approvedToppingAuxNotScanned = collect($proses->approvals ?? [])->contains(function ($a) {
            if (($a->action ?? '') !== 'topping_aux' || ($a->status ?? '') !== 'approved')
                return false;
            return !($a->barcodeAuxs && $a->barcodeAuxs->where('cancel', false)->count() > 0);
        });
        $tdColor = $hasToppingLa ? ($pendingToppingLa ? 'yellow' : ($approvedToppingLaNotScanned ? 'red' : 'green')) : null;
        $taColor = $hasToppingAux ? ($pendingToppingAux ? 'yellow' : ($approvedToppingAuxNotScanned ? 'red' : 'green')) : null;
        [$tdColor, $taColor] = \App\Services\ProsesStatusService::exclusiveToppingIndicatorColors($tdColor, $taColor);
        $laToppingRequired = collect($proses->approvals ?? [])->where('action', 'topping_la')->where('status', 'approved')->count();
        $auxToppingRequired = collect($proses->approvals ?? [])->where('action', 'topping_aux')->where('status', 'approved')->count();
        $laComplete = isset($proses->details) && $proses->details->isNotEmpty()
            ? $proses->details->every(fn($d) => ($d->barcodeLas ? $d->barcodeLas->where('cancel', false)->count() : 0) >= (($proses->qty_dye_stuff ?? 0) + $laToppingRequired))
            : false;
        $auxComplete = isset($proses->details) && $proses->details->isNotEmpty()
            ? $proses->details->every(fn($d) => ($d->barcodeAuxs ? $d->barcodeAuxs->where('cancel', false)->count() : 0) >= (($proses->qty_aux ?? 0) + $auxToppingRequired))
            : false;
        $laInitialComplete = isset($proses->details) && $proses->details->isNotEmpty()
            ? $proses->details->every(fn($d) => $d->barcodeLas && $d->barcodeLas->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_dye_stuff ?? 0))
            : false;
        $auxInitialComplete = isset($proses->details) && $proses->details->isNotEmpty()
            ? $proses->details->every(fn($d) => $d->barcodeAuxs && $d->barcodeAuxs->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_aux ?? 0))
            : false;
        if ($barcodeKainOptional) {
            $blockColors = [$laInitialComplete ? 'green' : 'red', $auxInitialComplete ? 'green' : 'red'];
        } else {
            $blockColors[1] = $laInitialComplete ? 'green' : 'red';
            $blockColors[2] = $auxInitialComplete ? 'green' : 'red';
        }
    } else {
        $pendingToppingLa = $hasToppingLa = $hasToppingAux = false;
        $tdColor = $taColor = null;
        $laComplete = $hasBarcodeLa;
        $auxComplete = $hasBarcodeAux;
        $laInitialComplete = $hasBarcodeLa;
        $auxInitialComplete = $hasBarcodeAux;
        if ($barcodeKainOptional) {
            $blockColors = [$laInitialComplete ? 'green' : 'red', $auxInitialComplete ? 'green' : 'red'];
        } else {
            $blockColors[1] = $laInitialComplete ? 'green' : 'red';
            $blockColors[2] = $auxInitialComplete ? 'green' : 'red';
        }
    }
    // Cek apakah proses ini terlibat dalam swap position approval dari proses lain
    // (sebagai swapped_proses_id atau affected_proses_ids di history_data approval swap_position)
    if (
        !$hasPendingChange &&
        in_array($proses->id, $affectedProsesIds)
    ) {
        $hasPendingChange = true;
    }
    if ($hasPendingChange || $hasPendingReprocessApproval) {
        $bg = '#ffeb3b'; // kuning untuk menandai ada perubahan yang menunggu approval
    } elseif ($proses->jenis === 'Maintenance') {
        $bg = '#757575'; // selalu abu-abu untuk Maintenance
    } elseif (!$proses->mulai) {
        $bg = '#757575'; // abu2
    } elseif ($proses->selesai) {
        // Hitung cycle_time_actual jika belum ada
        $cycle_time_actual = $proses->cycle_time_actual;
        if (
            !$cycle_time_actual &&
            $proses->mulai &&
            $proses->selesai
        ) {
            $mulai = \Carbon\Carbon::parse($proses->mulai);
            $selesai = \Carbon\Carbon::parse($proses->selesai);
            $cycle_time_actual = max(
                0,
                $mulai->diffInSeconds($selesai, false),
            );
        }
        $cycle_time = $proses->cycle_time
            ? (int) $proses->cycle_time
            : 0;
        $cycle_time_actual = $cycle_time_actual
            ? (int) $cycle_time_actual
            : 0;
        // Merah: durasi sangat singkat (< 1 jam). Hijau: sudah lebih dari 1 jam berjalan dan berhenti.
        if ($cycle_time_actual < 3600) {
            $bg = '#e53935'; // merah (durasi terlalu singkat)
        } elseif ($cycle_time_actual > $cycle_time + 3600) {
            $bg = '#e53935'; // merah (overtime)
        } else {
            $bg = '#00c853'; // hijau (>= 1 jam)
        }
    } else {
        // Proses sedang berjalan (mulai ada, selesai belum)
        if ($proses->is_paused || (bool) ($proses->is_break ?? false)) {
            $bg = '#757575'; // abu-abu
        } else {
            $barcodeKainOpt = $barcodeKainOptional ?? false;
            if ($proses->jenis !== 'Maintenance') {
                $kainComplete = $barcodeKainOpt || $allKainComplete;
                if (!$kainComplete) {
                    $bg = '#ef9a9a';
                } else {
                    $isLate = \App\Http\Controllers\ApiCheckStatusBarcodeController::isProsesScheduleLate($proses);
                    $bg = $isLate ? '#ef9a9a' : '#002b80';
                }
            } else {
                $bg = '#002b80';
            }
        }
    }
    $gradient = getGradient($bg);
    // Tambahkan inisialisasi variabel agar tidak undefined
    $estimasi_selesai = null;
    if (
        $proses->mulai &&
        !$proses->selesai &&
        $proses->cycle_time
    ) {
        $estimasi_selesai = \Carbon\Carbon::parse(
            $proses->mulai,
        )->addSeconds((int) $proses->cycle_time);
    }
    $cycle_time_actual_str = '00:00:00';
    if ($proses->mulai && $proses->selesai) {
        $mulai = \Carbon\Carbon::parse($proses->mulai);
        $selesai = \Carbon\Carbon::parse($proses->selesai);
        $cycle_time_actual = max(
            0,
            $mulai->diffInSeconds($selesai, false),
        );
        $cycle_time_actual_str = detikKeWaktu($cycle_time_actual);
    } elseif ($proses->mulai && (bool) ($proses->is_break ?? false) && $proses->break_at) {
        $mulai = \Carbon\Carbon::parse($proses->mulai);
        $breakAt = \Carbon\Carbon::parse($proses->break_at);
        $cycle_time_actual = max(
            0,
            $mulai->diffInSeconds($breakAt, false),
        );
        $cycle_time_actual_str = detikKeWaktu($cycle_time_actual);
    }

    $canDragDrop =
        $bg === '#757575' &&
        !$proses->mulai &&
        !$hasPendingChange &&
        !$hasPendingReprocessApproval &&
        ($canSwapProses ?? true);

    $mesin = $proses->mesin ?? ($proses->mesin_id ? \App\Models\Mesin::find($proses->mesin_id) : null);
    $nowTs = now()->getTimestamp();
    $lastSeenTs = null;
    if ($mesin && !empty($mesin->last_seen_at)) {
        try {
            $lastSeenTs = $mesin->last_seen_at instanceof \DateTimeInterface
                ? $mesin->last_seen_at->getTimestamp()
                : \Carbon\Carbon::parse($mesin->last_seen_at)->getTimestamp();
        } catch (\Throwable $e) {
            $lastSeenTs = null;
        }
    }
    $isIotDisconnected = ($lastSeenTs === null) || (($nowTs - $lastSeenTs) > 90);
    $isRunning = $proses->mulai !== null && ($proses->selesai === null);
    $showIotDisconnected = $isRunning && $isIotDisconnected;
@endphp
<div class="status-card draggable" draggable="{{ $canDragDrop ? 'true' : 'false' }}"
    style="background: {{ $gradient }}; background-repeat: no-repeat; background-size: cover; border-radius: 0; color: #fff; margin: 5px 0 0 0; padding: 2px 2px; cursor: {{ $canDragDrop ? 'grab' : 'default' }}; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"
    data-proses='@json($proses)' data-proses-id="{{ $proses->id }}" data-can-move="{{ $canDragDrop ? '1' : '0' }}"
    data-has-pending-reprocess="{{ $hasPendingReprocessApproval ? '1' : '0' }}" data-bg-color="{{ $bg }}">
    @if ($proses->stop_requested_at && !$proses->selesai)
        <div class="waiting-stop-banner" style="background: linear-gradient(90deg, #ff9800, #ffb74d); color: #111; font-weight: 800; font-size: 11px; padding: 3px 6px; text-align: center; border-bottom: 1px solid rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; gap: 5px;">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Menunggu Mesin Berhenti</span>
        </div>
    @elseif ($proses->is_break && !$proses->selesai)
        <div class="break-banner" style="background: linear-gradient(90deg, #424242, #616161); color: #fff; font-weight: 800; font-size: 11px; padding: 3px 6px; text-align: center; border-bottom: 1px solid rgba(0,0,0,0.25); display: flex; align-items: center; justify-content: center; gap: 5px;">
            <i class="fas fa-pause-circle text-warning"></i>
            <span>BREAK{{ !empty($proses->break_alasan) ? ': ' . $proses->break_alasan : '' }}</span>
        </div>
    @endif
    {{-- Header --}}
    <div class="card-header"
        style="display: flex; flex-direction: row; align-items: center; padding: 0 10px 2px 10px; gap: 0; border-bottom: none;">
        <div style="{{ $proses->jenis === 'Maintenance' ? 'flex: 0 0 auto;' : 'flex: 1;' }} text-align: left;">
            <span class="status-type"
                style="font-weight: bold; font-size: 32px; color: #111; text-shadow: 0 1px 4px #fff8;">
                {{ $type }}
            </span>
        </div>
        <div class="status-header-center" style="{{ $proses->jenis === 'Maintenance' ? 'flex: 1; padding: 0 4px;' : 'flex: 2;' }} text-align: center; display: flex; justify-content: center; align-items: center;">
            {{-- Button Detail Proses --}}
            @if ($proses->jenis === 'Maintenance')
                <div class="op-row" data-detail-id=""
                    style="flex: 1; width: auto; padding: 4px 8px; margin: 0; display: flex; align-items: center; justify-content: center; gap: 6px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.22); border: 1.5px solid rgba(0,0,0,0.18);">
                    {{-- Icon Sinyal IoT Terputus di dalam kotak Maintenance --}}
                    <i class="fas fa-exclamation-triangle iot-offline-icon"
                        title="Peringatan: Sinyal IoT Mesin Terputus / Tidak Stabil (> 90 detik tidak ada sinyal)"
                        style="{{ $showIotDisconnected ? 'display: inline-block;' : 'display: none;' }}; font-size: 16px;"></i>
                    <div class="op-row-noop"
                        style="font-weight: 800; color: #111; font-size: 19px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin: 0;">
                        MAINTENANCE
                    </div>
                    {{-- Icon Catatan di dalam kotak Maintenance --}}
                    <span class="note-icon-slot">
                        @if(!empty($proses->note))
                            <i class="fas fa-sticky-note text-warning icon-has-note" title="Catatan: {{ Str::limit($proses->note, 60) }}" style="font-size: 16px; vertical-align: middle; text-shadow: 0 1px 2px #000; cursor: pointer;"></i>
                        @endif
                    </span>
                </div>
            @else
                <div style="display: flex; justify-content: center; align-items: center; gap: 6px;">
                    @foreach ($blocks as $i => $b)
                        @php
                            $color = $blockColors[$i];
                            $blockBg =
                                $color === 'green'
                                ? '#d4f8e8'
                                : ($color === 'yellow' ? '#fff9c4' : '#ffb3b3');
                            $blockBorder =
                                $color === 'green'
                                ? '#43a047'
                                : ($color === 'yellow' ? '#f9a825' : '#c62828');
                        @endphp
                        <span class="gda-block" data-block-type="{{ $b }}"
                            style="display: inline-block; background: {{ $blockBg }}; color: #111; font-weight: bold; font-size: 22px; padding: 2px 10px; border-radius: 6px; border: 2.5px solid {{ $blockBorder }}; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px; text-shadow: 0 1px 2px #fff8;">
                            {{ $b }}
                        </span>
                    @endforeach
                    @php
                        $tdStyle2 = $tdColor === 'yellow' ? 'background:#fff9c4;color:#111;border:2.5px solid #f9a825' : ($tdColor === 'red' ? 'background:#ffb3b3;color:#111;border:2.5px solid #c62828' : ($tdColor === 'green' ? 'background:#d4f8e8;color:#111;border:2.5px solid #43a047' : ($tdColor === 'inactive' ? 'background:#eceff1;color:#555;border:2.5px solid #90a4ae' : '')));
                        $taStyle2 = $taColor === 'yellow' ? 'background:#fff9c4;color:#111;border:2.5px solid #f9a825' : ($taColor === 'red' ? 'background:#ffb3b3;color:#111;border:2.5px solid #c62828' : ($taColor === 'green' ? 'background:#d4f8e8;color:#111;border:2.5px solid #43a047' : ($taColor === 'inactive' ? 'background:#eceff1;color:#555;border:2.5px solid #90a4ae' : '')));
                    @endphp
                    @if($hasToppingLa ?? false)
                        <span class="topping-indicator topping-td" data-block-type="TD"
                            title="Topping Dyes - {{ \App\Services\ProsesStatusService::toppingIndicatorTitle($tdColor, 'td') }}"
                            style="display: inline-block; {{ $tdStyle2 }}; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TD</span>
                    @endif
                    @if($hasToppingAux ?? false)
                        <span class="topping-indicator topping-ta" data-block-type="TA"
                            title="Topping Auxiliaries - {{ \App\Services\ProsesStatusService::toppingIndicatorTitle($taColor, 'ta') }}"
                            style="display: inline-block; {{ $taStyle2 }}; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TA</span>
                    @endif
                </div>
            @endif
        </div>
        <div class="status-header-right" style="{{ $proses->jenis === 'Maintenance' ? 'flex: 0 0 auto;' : 'flex: 1;' }} display: flex; flex-direction: column; align-items: flex-end; justify-content: center;">
            <div style="display: flex; align-items: center; justify-content: flex-end;">
                <div class="status-light {{ $light == 'green' ? 'running-light' : ($light == 'yellow' ? 'running-light-yellow' : '') }}"
                    style="width: 24px; height: 24px; border-radius: 50%; background: {{ $light == 'green' ? '#00ff1a' : ($light == 'yellow' ? '#ffeb3b' : '#ff2a2a') }}; display: inline-block; border: 3px solid #fff; box-shadow: 0 0 0 0 transparent; transition: background 0.2s;">
                </div>
            </div>
            @php
                $isPinjamMesin = (bool) ($proses->is_pinjam_mesin ?? false);
                $isBreak = (bool) ($proses->is_break ?? false);
            @endphp
            <div class="pinjam-mesin-indicator" style="{{ $isPinjamMesin ? 'display: flex;' : 'display: none;' }} justify-content: center; align-items: center; width: 24px; margin-top: 2px;">
                <span class="badge-pinjam-mesin" title="Pinjam Mesin Aktif{{ !empty($proses->pinjam_mesin_alasan) ? ': ' . $proses->pinjam_mesin_alasan : '' }}"
                    style="font-weight: 800; font-size: 13px; color: #111; text-shadow: 0 1px 3px rgba(255,255,255,0.9), 0 0 4px rgba(255,255,255,0.8); letter-spacing: 0.5px; line-height: 1; text-align: center;">
                    PM
                </span>
            </div>
            <div class="break-indicator" style="{{ $isBreak ? 'display: flex;' : 'display: none;' }} justify-content: center; align-items: center; width: 24px; margin-top: 2px;">
                <span class="badge-break" title="Break Aktif{{ !empty($proses->break_alasan) ? ': ' . $proses->break_alasan : '' }}"
                    style="font-weight: 800; font-size: 13px; color: #ffeb3b; text-shadow: 0 1px 3px rgba(0,0,0,0.9); letter-spacing: 0.5px; line-height: 1; text-align: center;">
                    BR
                </span>
            </div>
        </div>
    </div>
    {{-- Body --}}
    <div class="card-body" style="text-align: center; font-size: 12px; padding: 2px 10px; color: #fff;">
        @php
            $detailList = $proses->jenis === 'Maintenance'
                ? collect()
                : ($proses->details ?? collect());
            $isMultipleOp = $detailList->count() > 1;
        @endphp
        @if ($proses->jenis !== 'Maintenance')
        <div class="op-list">
            @if ($detailList->isEmpty())
                {{-- Tidak ada detail --}}
                <div class="op-row" data-detail-id="">
                    <div class="op-row-noop"
                        style="display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8;">
                        <i class="fas fa-exclamation-triangle iot-offline-icon"
                            title="Peringatan: Sinyal IoT Mesin Terputus / Tidak Stabil (> 90 detik tidak ada sinyal)"
                            style="{{ $showIotDisconnected ? 'display: inline-block;' : 'display: none;' }}; font-size: 17px;"></i>
                        <span>-</span>
                        <span class="note-icon-slot">
                            @if(!empty($proses->note))
                                <i class="fas fa-sticky-note text-warning icon-has-note" title="Catatan: {{ Str::limit($proses->note, 60) }}" style="font-size: 16px; vertical-align: middle; text-shadow: 0 1px 2px #000; cursor: pointer;"></i>
                            @endif
                        </span>
                    </div>
                </div>
            @elseif ($isMultipleOp)
                {{-- Multiple OP: OP pertama dengan header lengkap, OP kedua+ dengan
                garis pemisah --}}
                @php
                    $firstDetail = $detailList->first();
                    // Indikator G: hijau hanya jika jumlah barcode kain >= roll
                    $firstRoll = $firstDetail->roll ?? 0;
                    $firstBarcodeKainCount = isset($firstDetail->barcodeKains)
                        ? $firstDetail->barcodeKains->where('cancel', false)->count()
                        : 0;
                    $firstHasKain = ($firstBarcodeKainCount >= $firstRoll && $firstRoll > 0);
                    $firstHasLa = ($firstDetail->barcodeLas ? $firstDetail->barcodeLas->where('cancel', false)->where('approval_id', null)->count() : 0) >= ($proses->qty_dye_stuff ?? 0);
                    $firstHasAux = ($firstDetail->barcodeAuxs ? $firstDetail->barcodeAuxs->where('cancel', false)->where('approval_id', null)->count() : 0) >= ($proses->qty_aux ?? 0);
                    $firstMap = $barcodeKainOptional
                        ? [$blocks[0] => $firstHasLa ? 'green' : 'red', $blocks[1] => $firstHasAux ? 'green' : 'red']
                        : [$blocks[0] => $firstHasKain ? 'green' : 'red', $blocks[1] => $firstHasLa ? 'green' : 'red', $blocks[2] => $firstHasAux ? 'green' : 'red'];
                @endphp
                {{-- OP Pertama: Detail lengkap dengan No OP dan Info --}}
                <div class="op-row" data-detail-id="{{ $firstDetail->id }}">
                    <div class="op-row-noop"
                        style="display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin-bottom: 4px;">
                        <i class="fas fa-exclamation-triangle iot-offline-icon"
                            title="Peringatan: Sinyal IoT Mesin Terputus / Tidak Stabil (> 90 detik tidak ada sinyal)"
                            style="{{ $showIotDisconnected ? 'display: inline-block;' : 'display: none;' }}; font-size: 17px;"></i>
                        <span>{{ $firstDetail->no_op ?? '-' }}</span>
                        <span class="note-icon-slot">
                            @if(!empty($proses->note))
                                <i class="fas fa-sticky-note text-warning icon-has-note" title="Catatan: {{ Str::limit($proses->note, 60) }}" style="font-size: 16px; vertical-align: middle; text-shadow: 0 1px 2px #000; cursor: pointer;"></i>
                            @endif
                        </span>
                    </div>
                    @if($firstDetail->customer)
                        <div
                            style="font-size: 16px; margin: 2px 0; color: #111; font-weight: 500; text-shadow: 0 1px 2px #fff8;">
                            {{ $firstDetail->customer }}
                        </div>
                    @endif
                    <div class="op-row-info" style="margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
                        <div>
                            {{ $firstDetail->warna ?? 'Warna' }} -
                            {{ $firstDetail->kategori_warna ?? 'Kategori' }} -
                            {{ $firstDetail->kode_warna ?? 'Kode' }}
                        </div>
                        <div>{{ $firstDetail->konstruksi ?? 'Konstruksi' }}</div>
                    </div>
                </div>

                {{-- Loop OP kedua dan seterusnya dengan garis pemisah --}}
                @foreach ($detailList->skip(1) as $d)
                    @php
                        // Indikator G: kuning jika pending approval, hijau jika approved >= roll
                        $subRoll = $d->roll ?? 0;
                        $subHasPendingKain = isset($d->barcodeKains) && $d->barcodeKains->where('cancel', false)->where('approval_status', 'pending')->isNotEmpty();
                        $subApprovedKainCount = isset($d->barcodeKains)
                            ? $d->barcodeKains->where('cancel', false)->where('approval_status', 'approved')->count()
                            : 0;
                        $subHasKain = ($subApprovedKainCount >= $subRoll && $subRoll > 0);
                        $subKainColor = $subHasPendingKain ? 'yellow' : ($subHasKain ? 'green' : 'red');
                        $subHasLa = ($d->barcodeLas ? $d->barcodeLas->where('cancel', false)->where('approval_id', null)->count() : 0) >= ($proses->qty_dye_stuff ?? 0);
                        $subHasAux = ($d->barcodeAuxs ? $d->barcodeAuxs->where('cancel', false)->where('approval_id', null)->count() : 0) >= ($proses->qty_aux ?? 0);
                        $subMap = $barcodeKainOptional
                            ? [$blocks[0] => $subHasLa ? 'green' : 'red', $blocks[1] => $subHasAux ? 'green' : 'red']
                            : [$blocks[0] => $subKainColor, $blocks[1] => $subHasLa ? 'green' : 'red', $blocks[2] => $subHasAux ? 'green' : 'red'];
                    @endphp
                    {{-- Garis pemisah --}}
                    <div style="border-top: 1px solid rgba(255,255,255,0.3); margin: 8px 0; padding-top: 8px;">
                    </div>
                    {{-- GDA/FDA + TD/TA per OP (di luar detail OP, ukuran sama dengan
                    header) --}}
                    <div style="display: flex; justify-content: center; gap: 6px; margin-bottom: 6px;">
                        @foreach ($blocks as $b)
                            @php
                                $color = $subMap[$b] ?? 'red';
                                $blockBg = $color === 'green' ? '#d4f8e8' : ($color === 'yellow' ? '#fff9c4' : '#ffb3b3');
                                $blockBorder = $color === 'green' ? '#43a047' : ($color === 'yellow' ? '#f9a825' : '#c62828');
                            @endphp
                            <span class="gda-block" data-block-type="{{ $b }}"
                                style="display: inline-block; background: {{ $blockBg }}; color: #111; font-weight: bold; font-size: 22px; padding: 2px 10px; border-radius: 6px; border: 2.5px solid {{ $blockBorder }}; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px; text-shadow: 0 1px 2px #fff8;">
                                {{ $b }}
                            </span>
                        @endforeach
                        @if($proses->jenis !== 'Maintenance' && ($hasToppingLa ?? false))
                            <span class="topping-indicator topping-td" data-block-type="TD"
                                title="Topping Dyes - {{ \App\Services\ProsesStatusService::toppingIndicatorTitle($tdColor, 'td') }}"
                                style="display: inline-block; {{ $tdStyle2 }}; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TD</span>
                        @endif
                        @if($proses->jenis !== 'Maintenance' && ($hasToppingAux ?? false))
                            <span class="topping-indicator topping-ta" data-block-type="TA"
                                title="Topping Auxiliaries - {{ \App\Services\ProsesStatusService::toppingIndicatorTitle($taColor, 'ta') }}"
                                style="display: inline-block; {{ $taStyle2 }}; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TA</span>
                        @endif
                    </div>
                    {{-- Detail OP (No OP + Info) --}}
                    <div class="op-row" data-detail-id="{{ $d->id }}">
                        {{-- No OP --}}
                        <div class="op-row-noop"
                            style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin-bottom: 4px;">
                            {{ $d->no_op ?? '-' }}
                        </div>
                        @if($d->customer)
                            <div
                                style="font-size: 16px; margin: 2px 0; color: #111; font-weight: bold; text-shadow: 0 1px 2px #fff8;">
                                {{ $d->customer }}
                            </div>
                        @endif
                        {{-- Info warna/kategori/konstruksi --}}
                        <div class="op-row-info" style="margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
                            <div>
                                {{ $d->warna ?? 'Warna' }} -
                                {{ $d->kategori_warna ?? 'Kategori' }} -
                                {{ $d->kode_warna ?? 'Kode' }}
                            </div>
                            <div>{{ $d->konstruksi ?? 'Konstruksi' }}</div>
                        </div>
                    </div>
                @endforeach
            @else
                {{-- Single OP: Tampilan normal dengan semua komponen --}}
                @php
                    $singleDetail = $detailList->first();
                @endphp
                <div class="op-row" data-detail-id="{{ $singleDetail->id }}">
                    <div class="op-row-noop"
                        style="display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8;">
                        <i class="fas fa-exclamation-triangle iot-offline-icon"
                            title="Peringatan: Sinyal IoT Mesin Terputus / Tidak Stabil (> 90 detik tidak ada sinyal)"
                            style="{{ $showIotDisconnected ? 'display: inline-block;' : 'display: none;' }}; font-size: 17px;"></i>
                        <span>{{ $singleDetail->no_op ?? '-' }}</span>
                        <span class="note-icon-slot">
                            @if(!empty($proses->note))
                                <i class="fas fa-sticky-note text-warning icon-has-note" title="Catatan: {{ Str::limit($proses->note, 60) }}" style="font-size: 16px; vertical-align: middle; text-shadow: 0 1px 2px #000; cursor: pointer;"></i>
                            @endif
                        </span>
                    </div>
                    @if($singleDetail->customer)
                        <div
                            style="font-size: 16px; margin: 2px 0; color: #111; font-weight: bold; text-shadow: 0 1px 2px #fff8;">
                            {{ $singleDetail->customer }}
                        </div>
                    @endif
                    <div class="op-row-info" style="margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
                        <div>
                            {{ $singleDetail->warna ?? 'Warna' }} -
                            {{ $singleDetail->kategori_warna ?? 'Kategori' }} -
                            {{ $singleDetail->kode_warna ?? 'Kode' }}
                        </div>
                        <div>{{ $singleDetail->konstruksi ?? 'Konstruksi' }}</div>
                    </div>
                </div>
            @endif
        </div>
        @endif
        <div class="card-time"
            style="display: flex; justify-content: space-between; font-size: 12px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
            <span>
                @php
                    // Logic: jika sudah ada cycle_time_actual, tampilkan itu
                    $showTime = '00:00:00';
                    if ($proses->cycle_time_actual) {
                        $showTime = detikKeWaktu(
                            $proses->cycle_time_actual,
                        );
                    } elseif ($proses->mulai && $proses->selesai) {
                        $mulai = \Carbon\Carbon::parse(
                            $proses->mulai,
                        );
                        $selesai = \Carbon\Carbon::parse(
                            $proses->selesai,
                        );
                        $showTime = detikKeWaktu(
                            max(
                                0,
                                $mulai->diffInSeconds(
                                    $selesai,
                                    false,
                                ),
                            ),
                        );
                    } elseif ($proses->mulai && !$proses->selesai) {
                        if ($proses->is_paused) {
                            $pausedAt = \Carbon\Carbon::parse($proses->updated_at);
                            $mulai = \Carbon\Carbon::parse($proses->mulai);
                            $showTime = detikKeWaktu(
                                max(0, $mulai->diffInSeconds($pausedAt))
                            );
                        } else {
                            $now = \Carbon\Carbon::now();
                            $mulai = \Carbon\Carbon::parse(
                                $proses->mulai,
                            );
                            $showTime = detikKeWaktu(
                                max(0, $mulai->diffInSeconds($now)),
                            );
                        }
                    }
                @endphp
                {{ $showTime }}
            </span>
            <span>/</span>
            <span>
                @if ($proses->cycle_time)
                    {{ detikKeWaktu($proses->cycle_time) }}
                @else
                    -
                @endif
            </span>
        </div>
        <div class="card-date"
            style="display: flex; justify-content: space-between; font-size: 10px; color: #fff; text-shadow: 0 1px 2px #0008;">
            <span>
                @if ($proses->mulai)
                    {{ \Carbon\Carbon::parse($proses->mulai)->format('d-m-Y H:i:s') }}
                @else
                    DD-MM-YYYY HH:MM:SS
                @endif
            </span>
            <span>|</span>
            <span>
                @if ($proses->selesai)
                    {{ \Carbon\Carbon::parse($proses->selesai)->format('d-m-Y H:i:s') }}
                @elseif ($estimasi_selesai)
                    Est: {{ $estimasi_selesai->format('d-m-Y H:i:s') }}
                @else
                    DD-MM-YYYY HH:MM:SS
                @endif
            </span>
        </div>
    </div>
</div>