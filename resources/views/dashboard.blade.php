@extends('layout.main')

@section('content')
    <style>
        .select2-container .select2-selection--single {
            height: 38px;
            padding: 5px 12px;
            font-size: 14px;
        }

        .card-date span,
        .card-time span {
            white-space: nowrap;
        }

        .card-date,
        .card-time {
            flex-wrap: nowrap !important;
        }

        /* Tampilan list OP untuk proses Multiple (klik/double click per OP) */
        .op-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 6px 0;
        }

        .op-row {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            padding: 6px 8px;
            cursor: pointer;
            user-select: none;
        }

        .op-row:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        .op-row-gda {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .op-row-noop {
            font-weight: 800;
            color: #111;
            font-size: 18px;
            letter-spacing: 1.5px;
            text-shadow: 0 1px 4px #fff8;
            text-align: center;
            margin-bottom: 2px;
        }

        .op-row-info {
            font-size: 10px;
            color: #fff;
            text-shadow: 0 1px 2px #0008;
            text-align: center;
            line-height: 1.25;
        }

        /* Fix untuk Select2 di Modal */
        #modalProses {
            overflow: visible !important;
        }

        #modalProses .modal-dialog {
            overflow: visible !important;
            position: relative;
        }

        #modalProses .modal-content {
            overflow: visible !important;
            position: relative;
        }

        #modalProses .modal-body {
            overflow-y: auto;
            max-height: calc(100vh - 200px);
            overflow-x: hidden;
            position: relative;
        }

        /* Pastikan Select2 dropdown berada di atas modal */
        .select2-container--open {
            z-index: 9999 !important;
        }

        .select2-dropdown {
            z-index: 9999 !important;
            position: absolute !important;
        }

        /* Pastikan Select2 dropdown mengikuti scroll modal */
        #modalProses .select2-container {
            z-index: 9999;
        }

        /* Fix untuk Select2 di dalam card detail */
        #modalProses .card-body {
            position: relative;
        }

        /* Pastikan Select2 dropdown modal memiliki z-index tinggi */
        .select2-dropdown-modal {
            z-index: 9999 !important;
        }

        /* Fix untuk Select2 results agar bisa di-scroll */
        .select2-results__options {
            max-height: 200px;
            overflow-y: auto;
        }

        /* Pastikan modal backdrop tidak menutupi Select2 */
        .modal-backdrop {
            z-index: 1040;
        }

        #modalProses {
            z-index: 1050;
        }

        /* Perbaiki lebar card proses di tablet */
        @media (max-width: 1200px) {
            .status-card {
                min-width: 260px;
                font-size: 13px;
            }
        }

        @media (max-width: 900px) {
            .status-card {
                min-width: 220px;
                font-size: 12px;
            }
        }

        #machines-container {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 8px;
            padding-bottom: 8px;
        }

        .machine-column {
            min-width: 320px;
            max-width: 340px;
            flex: 0 0 auto;
        }

        @media (max-width: 900px) {
            .machine-column {
                min-width: 260px;
                max-width: 280px;
            }
        }

        /* Hide scrollbar for cleaner look (optional) */
        #machines-container::-webkit-scrollbar {
            height: 8px;
            background: #eee;
        }

        #machines-container::-webkit-scrollbar-thumb {
            background: #bbb;
            border-radius: 4px;
        }

        /* Styling untuk tombol yang disabled karena pending approval */
        .btn-edit-proses.disabled,
        .btn-move-proses.disabled,
        .btn-delete-proses.disabled {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Styling untuk card-dropzone dengan vertical scroll untuk semua konten (history + proses aktif) */
        .card-dropzone {
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* Container untuk proses aktif - tidak perlu scroll sendiri */
        .proses-aktif-container {
            flex-shrink: 0;
        }

        /* Styling untuk history wrapper */
        .proses-history-wrapper {
            margin-bottom: 8px;
            flex-shrink: 0;
        }

        /* Container untuk history - tidak perlu scroll sendiri, scroll bersama parent */
        .proses-history-container {
            background: #f9f9f9;
            border-radius: 4px;
            padding: 5px;
            margin-top: 5px;
        }

        /* Styling untuk history card */
        .history-card {
            opacity: 0.85;
            transition: opacity 0.2s;
        }

        .history-card:hover {
            opacity: 1;
        }

        /* Scrollbar styling untuk card-dropzone (vertical) */
        .card-dropzone::-webkit-scrollbar {
            width: 8px;
        }

        .card-dropzone::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .card-dropzone::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .card-dropzone::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Efek kelap-kelip halus untuk indikator hijau (proses berjalan) */
        @keyframes pulse-soft-green {
            0% {
                opacity: 1;
                box-shadow: 0 0 8px rgba(0, 255, 26, 0.6);
            }

            50% {
                opacity: 0.45;
                box-shadow: 0 0 2px rgba(0, 255, 26, 0.25);
            }

            100% {
                opacity: 1;
                box-shadow: 0 0 8px rgba(0, 255, 26, 0.6);
            }
        }

        .status-light.running-light {
            animation: pulse-soft-green 1.4s ease-in-out infinite;
        }

        /* Tombol toggle history */
        .btn-toggle-history {
            width: 100%;
            margin-top: 5px;
            font-size: 12px;
            padding: 4px 8px;
        }

        /* Pastikan machine-column memiliki height yang sesuai */
        .machine-column>div>div {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
    </style>
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-4">
                        <h1 class="m-0">Dashboard</h1>
                    </div>
                    <div class="col-sm-4 d-flex justify-content-center">
                        <form id="filter-mesin-form" method="get" action="{{ url('dashboard') }}"
                            style="width:100%; display: flex; align-items: center; gap: 6px;">
                            <select name="mesin[]" id="filter-mesin" class="form-control select2" multiple
                                style="width:100%;" data-placeholder="Semua Mesin">
                                @foreach ($mesins as $mesin)
                                    <option value="{{ $mesin->id }}"
                                        {{ in_array($mesin->id, $selectedMesinArr ?? []) ? 'selected' : '' }}>
                                        {{ $mesin->jenis_mesin }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" id="clear-mesin-btn" class="btn btn-outline-secondary"
                                title="Reset Mesin"
                                style="margin-left:4px; font-weight:bold; padding: 0 10px; height:38px; line-height:1;">
                                &times;
                            </button>
                        </form>
                    </div>
                    <div class="col-sm-4 d-flex justify-content-end">
                        <div id="dashboard-controls" style="display: flex; justify-content: flex-end; gap: 10px;">
                            @if ($canAddProses ?? true)
                                <button id="add-card-btn" class="btn btn-success" style="font-weight:bold;"
                                    data-toggle="modal" data-target="#modalProses">
                                    + Tambah Proses
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row" id="machines-container">

                    @php
                        use Illuminate\Support\Str;
                        use App\Models\Approval;

                        // Ambil semua proses ID yang terlibat dalam swap position approval
                        // Termasuk swapped_proses_id dan affected_proses_ids (semua proses yang terpengaruh)
                        // Sama seperti di DashboardController untuk konsistensi
                        $affectedProsesIds = [];
                        try {
                            $swapApprovals = Approval::where('status', 'pending')
                                ->where('type', 'FM')
                                ->where('action', 'swap_position')
                                ->get();

                            foreach ($swapApprovals as $appr) {
                                $historyData = $appr->history_data;
                                if (is_string($historyData)) {
                                    $historyData = json_decode($historyData, true);
                                }
                                if (is_array($historyData)) {
                                    // Tambahkan swapped_proses_id (untuk backward compatibility)
                                    if (isset($historyData['swapped_proses_id'])) {
                                        $affectedProsesIds[] = (int) $historyData['swapped_proses_id'];
                                    }
                                    // Tambahkan semua affected_proses_ids (proses yang akan bergeser)
                                    if (
                                        isset($historyData['affected_proses_ids']) &&
                                        is_array($historyData['affected_proses_ids'])
                                    ) {
                                        foreach ($historyData['affected_proses_ids'] as $id) {
                                            $affectedProsesIds[] = (int) $id;
                                        }
                                    }
                                }
                            }
                            $affectedProsesIds = array_unique($affectedProsesIds);
                        } catch (\Exception $e) {
                            $affectedProsesIds = [];
                        }

                        // --- Fungsi helper untuk menentukan gradient dan warna teks otomatis ---
                        function getGradient($bg)
                        {
                            $bg = trim($bg);
                            $map = [
                                // Abu (belum mulai)
                                '#757575' => 'linear-gradient(180deg, #bdbdbd 0%, #757575 100%)',
                                // Kuning (menunggu approval perubahan)
                                '#ffeb3b' =>
                                    'linear-gradient(180deg, #fff9c4 0%,rgb(183, 168, 33) 60%,rgb(202, 161, 57) 100%)',
                                // Biru (berjalan)
                                '#002b80' => 'linear-gradient(180deg, #6dd5ed 0%, #2193b0 60%, #002b80 100%)',
                                // Hijau (selesai normal)
                                '#00c853' => 'linear-gradient(180deg, #b2f7c1 0%, #56ab2f 60%, #378a1b 100%)',
                                // Merah (selesai overtime atau barcode belum lengkap)
                                '#e53935' => 'linear-gradient(180deg, #ffb3b3 0%, #e53935 60%, #b71c1c 100%)',
                            ];
                            return $map[$bg] ?? $map['#757575'];
                        }
                        // Fungsi untuk menentukan warna teks otomatis berdasarkan background utama card
                        function getTextColor($bg)
                        {
                            // Jika background terang, pakai teks gelap, jika gelap pakai putih
                            $dark = ['#002b80', '#263238', '#e53935', '#00c853'];
                            if (in_array($bg, $dark)) {
                                return '#fff';
                            }
                            return '#222';
                        }

                        // --- Fungsi helper detik ke H:i:s ---
                        function detikKeWaktu($detik)
                        {
                            $jam = floor($detik / 3600);
                            $menit = floor(($detik % 3600) / 60);
                            $detik = $detik % 60;
                            return sprintf('%02d:%02d:%02d', $jam, $menit, $detik);
                        }
                    @endphp

                    {{-- Loop mesin --}}
                    @php
                        // Ambil array mesin terpilih dari controller
                        $selectedMesinArr = $selectedMesinArr ?? [];
                        // Jika ada mesin terpilih, hanya tampilkan mesin tersebut, dan reset key agar foreach tidak skip
                        $mesinList =
                            count($selectedMesinArr) > 0
                                ? $mesins
                                    ->filter(function ($m) use ($selectedMesinArr) {
                                        return in_array($m->id, $selectedMesinArr);
                                    })
                                    ->values()
                                : $mesins;
                    @endphp
                    <div class="row" id="machines-container" style="margin-left:0;margin-right:0;">
                        @foreach ($mesinList as $mesin)
                            <div class="machine-column">
                                <div class="col-lg col-md-2 col-sm-4 col-6" style="padding: 2px;">
                                    <div class="machine-column" style="border-radius: 0; box-shadow: none;">
                                        <div class="machine-header"
                                            style="background: #002bff; color: white; font-weight: bold; text-align: center; padding: 2px 0; font-size: 1.3rem; letter-spacing: 1px; user-select: none;">
                                            {{ $mesin->jenis_mesin }}
                                        </div>
                                        <div class="card-dropzone" data-machine="{{ $mesin->jenis_mesin }}"
                                            data-mesin-id="{{ $mesin->id }}"
                                            style="background: #fff; padding: 0; min-height: 0;">
                                            <div style="height: 2px; background: #fff;"></div>
                                            @php
                                                $prosesMesin = $prosesList->where('mesin_id', $mesin->id);

                                                // PISAHKAN: Proses Aktif vs History
                                                $prosesAktif = $prosesMesin
                                                    ->filter(function ($p) {
                                                        // Aktif = belum selesai (selesai === null)
                                                        return $p->selesai === null;
                                                    })
                                                    ->sort(function ($a, $b) {
                                                        // Urutkan berdasarkan order untuk proses pending (belum mulai)
                                                        if (!$a->mulai && !$b->mulai) {
                                                            $orderA = (int) ($a->order ?? 0);
                                                            $orderB = (int) ($b->order ?? 0);
                                                            if ($orderA !== $orderB) {
                                                                return $orderA <=> $orderB;
                                                            }
                                                        }
                                                        // Fallback ke created_at dan id
                                                        if ($a->created_at != $b->created_at) {
                                                            return $a->created_at <=> $b->created_at;
                                                        }
                                                        return $a->id <=> $b->id;
                                                    });

                                                $prosesHistory = $prosesMesin
                                                    ->filter(function ($p) {
                                                        // History = sudah selesai
                                                        return $p->selesai !== null;
                                                    })
                                                    ->sortByDesc('selesai'); // Urutkan terbaru di atas
                                            @endphp

                                            {{-- PROSES HISTORY (Button di atas, Hidden by default, scroll bersama parent) --}}
                                            @if ($prosesHistory->count() > 0)
                                                <div class="proses-history-wrapper" style="margin-bottom: 8px;">
                                                    <button class="btn-toggle-history btn btn-sm btn-secondary"
                                                        data-mesin-id="{{ $mesin->id }}" type="button">
                                                        <i class="fas fa-history"></i> Tampilkan History
                                                        ({{ $prosesHistory->count() }})
                                                    </button>
                                                    <div class="proses-history-container" id="history-{{ $mesin->id }}"
                                                        style="display: none;">
                                                        @foreach ($prosesHistory as $proses)
                                                            @php
                                                                // Reuse logic yang sama untuk history card
                                                                $type =
                                                                    $proses->jenis === 'Produksi'
                                                                        ? 'P'
                                                                        : ($proses->jenis === 'Reproses'
                                                                            ? 'R'
                                                                            : 'M');
                                                                if ($proses->jenis === 'Maintenance') {
                                                                    $blockColors = ['gray', 'gray', 'gray'];
                                                                } else {
                                                                    // Barcode sekarang terhubung via DetailProses (detail_proses_id)
                                                                    $hasBarcodeKain = false;
                                                                    $hasBarcodeLa = false;
                                                                    $hasBarcodeAux = false;
                                                                    if (isset($proses->details) && is_iterable($proses->details)) {
                                                                        foreach ($proses->details as $d) {
                                                                            if (isset($d->barcodeKains)) {
                                                                                $hasBarcodeKain =
                                                                                    $hasBarcodeKain ||
                                                                                    $d->barcodeKains->where('cancel', false)->count() > 0;
                                                                            }
                                                                            if (isset($d->barcodeLas)) {
                                                                                $hasBarcodeLa =
                                                                                    $hasBarcodeLa ||
                                                                                    $d->barcodeLas->where('cancel', false)->count() > 0;
                                                                            }
                                                                            if (isset($d->barcodeAuxs)) {
                                                                                $hasBarcodeAux =
                                                                                    $hasBarcodeAux ||
                                                                                    $d->barcodeAuxs->where('cancel', false)->count() > 0;
                                                                            }
                                                                        }
                                                                    }
                                                                    // Fallback backward compatibility jika masih ada kolom lama
                                                                    if (!$hasBarcodeKain && isset($proses->barcode_kain)) {
                                                                        $hasBarcodeKain = (bool) $proses->barcode_kain;
                                                                    }
                                                                    if (!$hasBarcodeLa && isset($proses->barcode_la)) {
                                                                        $hasBarcodeLa = (bool) $proses->barcode_la;
                                                                    }
                                                                    if (!$hasBarcodeAux && isset($proses->barcode_aux)) {
                                                                        $hasBarcodeAux = (bool) $proses->barcode_aux;
                                                                    }
                                                                    $blockColors = [
                                                                        $hasBarcodeKain ? 'green' : 'red',
                                                                        $hasBarcodeLa ? 'green' : 'red',
                                                                        $hasBarcodeAux ? 'green' : 'red',
                                                                    ];
                                                                }
                                                                $blocks = ['G', 'D', 'A'];
                                                                if ($proses->mulai && !$proses->selesai) {
                                                                    $light = 'green';
                                                                } else {
                                                                    $light = 'red';
                                                                }
                                                                $bg = '#757575';
                                                                $hasPendingChange = false;
                                                                $hasPendingReprocessApproval = false;
                                                                if (
                                                                    isset($proses->approvals) &&
                                                                    is_iterable($proses->approvals)
                                                                ) {
                                                                    $hasPendingChange = collect(
                                                                        $proses->approvals,
                                                                    )->contains(function ($appr) {
                                                                        return $appr->status === 'pending' &&
                                                                            $appr->type === 'FM' &&
                                                                            in_array($appr->action, [
                                                                                'edit_cycle_time',
                                                                                'delete_proses',
                                                                                'move_machine',
                                                                                'swap_position',
                                                                            ]);
                                                                    });
                                                                    if ($proses->jenis === 'Reproses') {
                                                                        $hasPendingReprocessApproval = collect(
                                                                            $proses->approvals,
                                                                        )->contains(function ($appr) {
                                                                            return $appr->status === 'pending' &&
                                                                                $appr->action === 'create_reprocess' &&
                                                                                ($appr->type === 'FM' ||
                                                                                    $appr->type === 'VP');
                                                                        });
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
                                                                    $bg = '#ffeb3b';
                                                                } elseif ($proses->jenis === 'Maintenance') {
                                                                    $bg = '#757575';
                                                                } elseif (!$proses->mulai) {
                                                                    $bg = '#757575';
                                                                } elseif ($proses->selesai) {
                                                                    $mulai = \Carbon\Carbon::parse($proses->mulai);
                                                                    $selesai = \Carbon\Carbon::parse($proses->selesai);
                                                                    $cycle_time_actual = max(
                                                                        0,
                                                                        $mulai->diffInSeconds($selesai, false),
                                                                    );
                                                                    $cycle_time = $proses->cycle_time
                                                                        ? (int) $proses->cycle_time
                                                                        : 0;
                                                                    $cycle_time_actual = $proses->cycle_time_actual
                                                                        ? (int) $proses->cycle_time_actual
                                                                        : 0;
                                                                    if ($cycle_time_actual > $cycle_time + 3600) {
                                                                        $bg = '#e53935';
                                                                    } else {
                                                                        $bg = '#00c853';
                                                                    }
                                                                } else {
                                                                    // Proses sedang berjalan (mulai ada, selesai belum)
                                                                    // Cek barcode menggunakan relasi yang sama seperti DashboardController
                                                                    $hasBarcodeKain = false;
                                                                    $hasBarcodeLa = false;
                                                                    $hasBarcodeAux = false;
                                                                    if (isset($proses->details) && is_iterable($proses->details)) {
                                                                        foreach ($proses->details as $d) {
                                                                            if (isset($d->barcodeKains)) {
                                                                                $hasBarcodeKain =
                                                                                    $hasBarcodeKain ||
                                                                                    $d->barcodeKains->where('cancel', false)->count() > 0;
                                                                            }
                                                                            if (isset($d->barcodeLas)) {
                                                                                $hasBarcodeLa =
                                                                                    $hasBarcodeLa ||
                                                                                    $d->barcodeLas->where('cancel', false)->count() > 0;
                                                                            }
                                                                            if (isset($d->barcodeAuxs)) {
                                                                                $hasBarcodeAux =
                                                                                    $hasBarcodeAux ||
                                                                                    $d->barcodeAuxs->where('cancel', false)->count() > 0;
                                                                            }
                                                                        }
                                                                    }
                                                                    if (
                                                                        $proses->jenis !== 'Maintenance' &&
                                                                        (!$hasBarcodeKain ||
                                                                            !$hasBarcodeLa ||
                                                                            !$hasBarcodeAux)
                                                                    ) {
                                                                        $bg = '#e53935'; // merah (barcode belum lengkap)
                                                                    } else {
                                                                        $bg = '#002b80'; // biru (berjalan dengan barcode lengkap)
                                                                    }
                                                                }
                                                                $gradient = getGradient($bg);
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
                                                                    $cycle_time_actual_str = detikKeWaktu(
                                                                        $cycle_time_actual,
                                                                    );
                                                                }
                                                            @endphp
                                                            <div class="status-card history-card draggable"
                                                                draggable="false"
                                                                style="background: {{ $gradient }}; background-repeat: no-repeat; background-size: cover; border-radius: 0; color: #fff; margin: 5px 0 0 0; padding: 2px 2px; cursor: default; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"
                                                                data-proses='@json($proses)'
                                                                data-proses-id="{{ $proses->id }}" data-can-move="0"
                                                                data-has-pending-reprocess="{{ $hasPendingReprocessApproval ? '1' : '0' }}"
                                                                data-bg-color="{{ $bg }}">
                                                                {{-- Header --}}
                                                                <div class="card-header"
                                                                    style="display: flex; flex-direction: row; align-items: center; padding: 0 10px 2px 10px; gap: 0; border-bottom: none;">
                                                                    <div style="flex: 1; text-align: left;">
                                                                        <span class="status-type"
                                                                            style="font-weight: bold; font-size: 32px; color: #111; text-shadow: 0 1px 4px #fff8;">
                                                                            {{ $type }}
                                                                        </span>
                                                                    </div>
                                                                    <div
                                                                        style="flex: 2; text-align: center; display: flex; justify-content: center; gap: 6px;">
                                                                        @foreach ($blocks as $i => $b)
                                                                            @php
                                                                                $color = $blockColors[$i];
                                                                                if ($proses->jenis === 'Maintenance') {
                                                                                    $blockBg = '#e0e0e0';
                                                                                    $blockBorder = '#757575';
                                                                                } else {
                                                                                    $blockBg =
                                                                                        $color === 'green'
                                                                                            ? '#d4f8e8'
                                                                                            : '#ffb3b3';
                                                                                    $blockBorder =
                                                                                        $color === 'green'
                                                                                            ? '#43a047'
                                                                                            : '#c62828';
                                                                                }
                                                                            @endphp
                                                                            <span class="gda-block"
                                                                                data-block-type="{{ $b }}"
                                                                                style="display: inline-block; background: {{ $blockBg }}; color: #111; font-weight: bold; font-size: 22px; padding: 2px 10px; border-radius: 6px; border: 2.5px solid {{ $blockBorder }}; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px; text-shadow: 0 1px 2px #fff8;">
                                                                                {{ $b }}
                                                                            </span>
                                                                        @endforeach
                                                                    </div>
                                                                    <div style="flex: 1; text-align: right;">
                                                                        <div class="status-light {{ $light == 'green' ? 'running-light' : '' }}"
                                                                            style="width: 24px; height: 24px; border-radius: 50%; background: {{ $light == 'green' ? '#00ff1a' : '#ff2a2a' }}; display: inline-block; border: 3px solid #fff; box-shadow: 0 0 0 0 transparent; transition: background 0.2s;">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                {{-- Body --}}
                                                                <div class="card-body"
                                                                    style="text-align: center; font-size: 12px; padding: 2px 10px; color: #fff;">
                                                                    @php
                                                                        $detailList = $proses->jenis === 'Maintenance'
                                                                            ? collect()
                                                                            : ($proses->details ?? collect());
                                                                        $isMultipleOp = $detailList->count() > 1;
                                                                    @endphp
                                                                    <div class="op-list">
                                                                        @if ($proses->jenis === 'Maintenance' || $detailList->isEmpty())
                                                                            {{-- Maintenance atau tidak ada detail --}}
                                                                            <div class="op-row" data-detail-id="">
                                                                                <div class="op-row-noop"
                                                                                    style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8;">
                                                                                    MAINTENANCE
                                                                                </div>
                                                                            </div>
                                                                        @elseif ($isMultipleOp)
                                                                            {{-- Multiple OP: OP pertama dengan header lengkap, OP kedua+ dengan garis pemisah --}}
                                                                            @php
                                                                                $firstDetail = $detailList->first();
                                                                            @endphp
                                                                            {{-- OP Pertama: Detail lengkap dengan No OP dan Info --}}
                                                                            <div class="op-row" data-detail-id="{{ $firstDetail->id }}">
                                                                                <div class="op-row-noop"
                                                                                    style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin-bottom: 4px;">
                                                                                    {{ $firstDetail->no_op ?? '-' }}
                                                                                </div>
                                                                                <div class="op-row-info"
                                                                                    style="font-size: 9px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
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
                                                                                    $subHasKain = isset($d->barcodeKains)
                                                                                        ? $d->barcodeKains->where('cancel', false)->count() > 0
                                                                                        : false;
                                                                                    $subHasLa = isset($d->barcodeLas)
                                                                                        ? $d->barcodeLas->where('cancel', false)->count() > 0
                                                                                        : false;
                                                                                    $subHasAux = isset($d->barcodeAuxs)
                                                                                        ? $d->barcodeAuxs->where('cancel', false)->count() > 0
                                                                                        : false;
                                                                                    $subMap = [
                                                                                        'G' => $subHasKain ? 'green' : 'red',
                                                                                        'D' => $subHasLa ? 'green' : 'red',
                                                                                        'A' => $subHasAux ? 'green' : 'red',
                                                                                    ];
                                                                                @endphp
                                                                                {{-- Garis pemisah --}}
                                                                                <div style="border-top: 1px solid rgba(255,255,255,0.3); margin: 8px 0; padding-top: 8px;"></div>
                                                                                {{-- GDA per OP (di luar detail OP, ukuran sama dengan header) --}}
                                                                                <div style="display: flex; justify-content: center; gap: 6px; margin-bottom: 6px;">
                                                                                    @foreach ($blocks as $b)
                                                                                        @php
                                                                                            $color = $subMap[$b] ?? 'red';
                                                                                            $blockBg = $color === 'green' ? '#d4f8e8' : '#ffb3b3';
                                                                                            $blockBorder = $color === 'green' ? '#43a047' : '#c62828';
                                                                                        @endphp
                                                                                        <span class="gda-block"
                                                                                            data-block-type="{{ $b }}"
                                                                                            style="display: inline-block; background: {{ $blockBg }}; color: #111; font-weight: bold; font-size: 22px; padding: 2px 10px; border-radius: 6px; border: 2.5px solid {{ $blockBorder }}; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px; text-shadow: 0 1px 2px #fff8;">
                                                                                            {{ $b }}
                                                                                        </span>
                                                                                    @endforeach
                                                                                </div>
                                                                                {{-- Detail OP (No OP + Info) --}}
                                                                                <div class="op-row" data-detail-id="{{ $d->id }}">
                                                                                    {{-- No OP --}}
                                                                                    <div class="op-row-noop"
                                                                                        style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin-bottom: 4px;">
                                                                                        {{ $d->no_op ?? '-' }}
                                                                                    </div>
                                                                                    {{-- Info warna/kategori/konstruksi --}}
                                                                                    <div class="op-row-info"
                                                                                        style="font-size: 9px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
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
                                                                                    style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8;">
                                                                                    {{ $singleDetail->no_op ?? '-' }}
                                                                                </div>
                                                                                <div class="op-row-info"
                                                                                    style="font-size: 9px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
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
                                                                    <div class="card-time"
                                                                        style="display: flex; justify-content: space-between; font-size: 12px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
                                                                        <span>
                                                                            @php
                                                                                $showTime = '00:00:00';
                                                                                if ($proses->cycle_time_actual) {
                                                                                    $showTime = detikKeWaktu(
                                                                                        $proses->cycle_time_actual,
                                                                                    );
                                                                                } elseif (
                                                                                    $proses->mulai &&
                                                                                    $proses->selesai
                                                                                ) {
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
                                                                                } elseif (
                                                                                    $proses->mulai &&
                                                                                    !$proses->selesai
                                                                                ) {
                                                                                    $now = \Carbon\Carbon::now();
                                                                                    $mulai = \Carbon\Carbon::parse(
                                                                                        $proses->mulai,
                                                                                    );
                                                                                    $showTime = detikKeWaktu(
                                                                                        max(
                                                                                            0,
                                                                                            $mulai->diffInSeconds($now),
                                                                                        ),
                                                                                    );
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
                                                                                Est:
                                                                                {{ $estimasi_selesai->format('d-m-Y H:i:s') }}
                                                                            @else
                                                                                DD-MM-YYYY HH:MM:SS
                                                                            @endif
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- PROSES AKTIF (Default Visible, scroll bersama parent) --}}
                                            <div class="proses-aktif-container">
                                                @foreach ($prosesAktif as $proses)
                                                    @php
                                                        // Jenis proses: P/R/M
                                                        $type =
                                                            $proses->jenis === 'Produksi'
                                                                ? 'P'
                                                                : ($proses->jenis === 'Reproses'
                                                                    ? 'R'
                                                                    : 'M');
                                                        // Status blok G, D, A (hijau jika barcode ada, merah jika tidak)
                                                        if ($proses->jenis === 'Maintenance') {
                                                            $blockColors = ['gray', 'gray', 'gray'];
                                                        } else {
                                                            // G: hijau jika ada minimal 1 barcode kain (cancel=false)
                                                            $hasBarcodeKain = false;
                                                            $hasBarcodeLa = false;
                                                            $hasBarcodeAux = false;
                                                            if (isset($proses->details) && is_iterable($proses->details)) {
                                                                foreach ($proses->details as $d) {
                                                                    if (isset($d->barcodeKains)) {
                                                                        $hasBarcodeKain =
                                                                            $hasBarcodeKain ||
                                                                            $d->barcodeKains->where('cancel', false)->count() > 0;
                                                                    }
                                                                    if (isset($d->barcodeLas)) {
                                                                        $hasBarcodeLa =
                                                                            $hasBarcodeLa ||
                                                                            $d->barcodeLas->where('cancel', false)->count() > 0;
                                                                    }
                                                                    if (isset($d->barcodeAuxs)) {
                                                                        $hasBarcodeAux =
                                                                            $hasBarcodeAux ||
                                                                            $d->barcodeAuxs->where('cancel', false)->count() > 0;
                                                                    }
                                                                }
                                                            }
                                                            if (!$hasBarcodeKain && isset($proses->barcode_kain)) {
                                                                $hasBarcodeKain = (bool) $proses->barcode_kain;
                                                            }
                                                            if (!$hasBarcodeLa && isset($proses->barcode_la)) {
                                                                $hasBarcodeLa = (bool) $proses->barcode_la;
                                                            }
                                                            if (!$hasBarcodeAux && isset($proses->barcode_aux)) {
                                                                $hasBarcodeAux = (bool) $proses->barcode_aux;
                                                            }
                                                            // D: hijau jika ada minimal 1 barcode LA (cancel=false)
                                                            $blockColors = [
                                                                $hasBarcodeKain ? 'green' : 'red',
                                                                $hasBarcodeLa ? 'green' : 'red',
                                                                $hasBarcodeAux ? 'green' : 'red',
                                                            ];
                                                        }
                                                        $blocks = ['G', 'D', 'A'];
                                                        // Lampu indikator: hijau jika mulai ada dan selesai null, merah jika mulai dan selesai ada, atau mulai null
                                                        if ($proses->mulai && !$proses->selesai) {
                                                            $light = 'green';
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
                                                            if ($cycle_time_actual > $cycle_time + 3600) {
                                                                $bg = '#e53935'; // merah (overtime)
                                                            } else {
                                                                $bg = '#00c853'; // hijau (selesai normal)
                                                            }
                                                        } else {
                                                            // Proses sedang berjalan (mulai ada, selesai belum)
                                                            // Cek barcode menggunakan relasi yang sama seperti DashboardController
                                                            $hasBarcodeKain = false;
                                                            $hasBarcodeLa = false;
                                                            $hasBarcodeAux = false;
                                                            if (isset($proses->details) && is_iterable($proses->details)) {
                                                                foreach ($proses->details as $d) {
                                                                    if (isset($d->barcodeKains)) {
                                                                        $hasBarcodeKain =
                                                                            $hasBarcodeKain ||
                                                                            $d->barcodeKains->where('cancel', false)->count() > 0;
                                                                    }
                                                                    if (isset($d->barcodeLas)) {
                                                                        $hasBarcodeLa =
                                                                            $hasBarcodeLa ||
                                                                            $d->barcodeLas->where('cancel', false)->count() > 0;
                                                                    }
                                                                    if (isset($d->barcodeAuxs)) {
                                                                        $hasBarcodeAux =
                                                                            $hasBarcodeAux ||
                                                                            $d->barcodeAuxs->where('cancel', false)->count() > 0;
                                                                    }
                                                                }
                                                            }
                                                            if (
                                                                $proses->jenis !== 'Maintenance' &&
                                                                (!$hasBarcodeKain || !$hasBarcodeLa || !$hasBarcodeAux)
                                                            ) {
                                                                $bg = '#e53935'; // merah (barcode belum lengkap)
                                                            } else {
                                                                $bg = '#002b80'; // biru (berjalan dengan barcode lengkap)
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
                                                        }
                                                    @endphp
                                                    @php
                                                        $canDragDrop =
                                                            $bg === '#757575' &&
                                                            !$proses->mulai &&
                                                            !$hasPendingChange &&
                                                            !$hasPendingReprocessApproval &&
                                                            ($canSwapProses ?? true);
                                                    @endphp
                                                    <div class="status-card draggable"
                                                        draggable="{{ $canDragDrop ? 'true' : 'false' }}"
                                                        style="background: {{ $gradient }}; background-repeat: no-repeat; background-size: cover; border-radius: 0; color: #fff; margin: 5px 0 0 0; padding: 2px 2px; cursor: {{ $canDragDrop ? 'grab' : 'default' }}; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"
                                                        data-proses='@json($proses)'
                                                        data-proses-id="{{ $proses->id }}"
                                                        data-can-move="{{ $canDragDrop ? '1' : '0' }}"
                                                        data-has-pending-reprocess="{{ $hasPendingReprocessApproval ? '1' : '0' }}"
                                                        data-bg-color="{{ $bg }}">
                                                        {{-- Header --}}
                                                        <div class="card-header"
                                                            style="display: flex; flex-direction: row; align-items: center; padding: 0 10px 2px 10px; gap: 0; border-bottom: none;">
                                                            <div style="flex: 1; text-align: left;">
                                                                <span class="status-type"
                                                                    style="font-weight: bold; font-size: 32px; color: #111; text-shadow: 0 1px 4px #fff8;">
                                                                    {{ $type }}
                                                                </span>
                                                            </div>
                                                            <div
                                                                style="flex: 2; text-align: center; display: flex; justify-content: center; gap: 6px;">
                                                                @foreach ($blocks as $i => $b)
                                                                    @php
                                                                        $color = $blockColors[$i];
                                                                        if ($proses->jenis === 'Maintenance') {
                                                                            $blockBg = '#e0e0e0'; // abu-abu terang
                                                                            $blockBorder = '#757575'; // abu-abu gelap
                                                                        } else {
                                                                            $blockBg =
                                                                                $color === 'green'
                                                                                    ? '#d4f8e8'
                                                                                    : '#ffb3b3';
                                                                            $blockBorder =
                                                                                $color === 'green'
                                                                                    ? '#43a047'
                                                                                    : '#c62828';
                                                                        }
                                                                    @endphp
                                                                    <span class="gda-block"
                                                                        data-block-type="{{ $b }}"
                                                                        style="display: inline-block; background: {{ $blockBg }}; color: #111; font-weight: bold; font-size: 22px; padding: 2px 10px; border-radius: 6px; border: 2.5px solid {{ $blockBorder }}; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px; text-shadow: 0 1px 2px #fff8;">
                                                                        {{ $b }}
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                            <div style="flex: 1; text-align: right;">
                                                                <div class="status-light {{ $light == 'green' ? 'running-light' : '' }}"
                                                                    style="width: 24px; height: 24px; border-radius: 50%; background: {{ $light == 'green' ? '#00ff1a' : '#ff2a2a' }}; display: inline-block; border: 3px solid #fff; box-shadow: 0 0 0 0 transparent; transition: background 0.2s;">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        {{-- Body --}}
                                                        <div class="card-body"
                                                            style="text-align: center; font-size: 12px; padding: 2px 10px; color: #fff;">
                                                            @php
                                                                $detailList = $proses->jenis === 'Maintenance'
                                                                    ? collect()
                                                                    : ($proses->details ?? collect());
                                                                $isMultipleOp = $detailList->count() > 1;
                                                            @endphp
                                                            <div class="op-list">
                                                                @if ($proses->jenis === 'Maintenance' || $detailList->isEmpty())
                                                                    {{-- Maintenance atau tidak ada detail --}}
                                                                    <div class="op-row" data-detail-id="">
                                                                        <div class="op-row-noop"
                                                                            style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8;">
                                                                            MAINTENANCE
                                                                        </div>
                                                                    </div>
                                                                @elseif ($isMultipleOp)
                                                                    {{-- Multiple OP: OP pertama dengan header lengkap, OP kedua+ dengan garis pemisah --}}
                                                                    @php
                                                                        $firstDetail = $detailList->first();
                                                                        $firstHasKain = isset($firstDetail->barcodeKains)
                                                                            ? $firstDetail->barcodeKains->where('cancel', false)->count() > 0
                                                                            : false;
                                                                        $firstHasLa = isset($firstDetail->barcodeLas)
                                                                            ? $firstDetail->barcodeLas->where('cancel', false)->count() > 0
                                                                            : false;
                                                                        $firstHasAux = isset($firstDetail->barcodeAuxs)
                                                                            ? $firstDetail->barcodeAuxs->where('cancel', false)->count() > 0
                                                                            : false;
                                                                        $firstMap = [
                                                                            'G' => $firstHasKain ? 'green' : 'red',
                                                                            'D' => $firstHasLa ? 'green' : 'red',
                                                                            'A' => $firstHasAux ? 'green' : 'red',
                                                                        ];
                                                                    @endphp
                                                                    {{-- OP Pertama: Detail lengkap dengan No OP dan Info --}}
                                                                    <div class="op-row" data-detail-id="{{ $firstDetail->id }}">
                                                                        <div class="op-row-noop"
                                                                            style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin-bottom: 4px;">
                                                                            {{ $firstDetail->no_op ?? '-' }}
                                                                        </div>
                                                                        <div class="op-row-info"
                                                                            style="font-size: 9px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
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
                                                                            $subHasKain = isset($d->barcodeKains)
                                                                                ? $d->barcodeKains->where('cancel', false)->count() > 0
                                                                                : false;
                                                                            $subHasLa = isset($d->barcodeLas)
                                                                                ? $d->barcodeLas->where('cancel', false)->count() > 0
                                                                                : false;
                                                                            $subHasAux = isset($d->barcodeAuxs)
                                                                                ? $d->barcodeAuxs->where('cancel', false)->count() > 0
                                                                                : false;
                                                                            $subMap = [
                                                                                'G' => $subHasKain ? 'green' : 'red',
                                                                                'D' => $subHasLa ? 'green' : 'red',
                                                                                'A' => $subHasAux ? 'green' : 'red',
                                                                            ];
                                                                        @endphp
                                                                        {{-- Garis pemisah --}}
                                                                        <div style="border-top: 1px solid rgba(255,255,255,0.3); margin: 8px 0; padding-top: 8px;"></div>
                                                                        {{-- GDA per OP (di luar detail OP, ukuran sama dengan header) --}}
                                                                        <div style="display: flex; justify-content: center; gap: 6px; margin-bottom: 6px;">
                                                                            @foreach ($blocks as $b)
                                                                                @php
                                                                                    $color = $subMap[$b] ?? 'red';
                                                                                    $blockBg = $color === 'green' ? '#d4f8e8' : '#ffb3b3';
                                                                                    $blockBorder = $color === 'green' ? '#43a047' : '#c62828';
                                                                                @endphp
                                                                                <span class="gda-block"
                                                                                    data-block-type="{{ $b }}"
                                                                                    style="display: inline-block; background: {{ $blockBg }}; color: #111; font-weight: bold; font-size: 22px; padding: 2px 10px; border-radius: 6px; border: 2.5px solid {{ $blockBorder }}; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px; text-shadow: 0 1px 2px #fff8;">
                                                                                    {{ $b }}
                                                                                </span>
                                                                            @endforeach
                                                                        </div>
                                                                        {{-- Detail OP (No OP + Info) --}}
                                                                        <div class="op-row" data-detail-id="{{ $d->id }}">
                                                                            {{-- No OP --}}
                                                                            <div class="op-row-noop"
                                                                                style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin-bottom: 4px;">
                                                                                {{ $d->no_op ?? '-' }}
                                                                            </div>
                                                                            {{-- Info warna/kategori/konstruksi --}}
                                                                            <div class="op-row-info"
                                                                                style="font-size: 9px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
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
                                                                            style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8;">
                                                                            {{ $singleDetail->no_op ?? '-' }}
                                                                        </div>
                                                                        <div class="op-row-info"
                                                                            style="font-size: 9px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
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
                                                                            $now = \Carbon\Carbon::now();
                                                                            $mulai = \Carbon\Carbon::parse(
                                                                                $proses->mulai,
                                                                            );
                                                                            $showTime = detikKeWaktu(
                                                                                max(0, $mulai->diffInSeconds($now)),
                                                                            );
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
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        <!-- Modal Tambah Proses -->
        <div class="modal fade" id="modalProses" tabindex="-1" aria-labelledby="modalProsesLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 1200px;">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formProses" action="{{ route('proses.store') }}" method="POST">
                        @csrf
                        <!-- Header -->
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fw-bold" id="modalProsesLabel">Tambah Proses</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="modal-body py-3 px-4">
                            <div class="row">

                                <!-- Jenis Proses -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Jenis Proses</label>
                                        <select name="jenis" id="jenis" class="form-control" required>
                                            <option value="Produksi" selected>Produksi</option>
                                            <option value="Maintenance">Maintenance</option>
                                            <option value="Reproses">Reproses</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Jenis OP (Single/Multiple) -->
                                <div class="col-md-6 hide-if-maintenance">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Jenis OP</label>
                                        <select name="jenis_op" id="jenis_op" class="form-control" required>
                                            <option value="Single" selected>Single</option>
                                            <option value="Multiple">Multiple</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Mesin -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Mesin</label>
                                        <select name="mesin_id" id="mesin_id" class="form-control select2" style="width: 100%;" required>
                                            <option value="" disabled>-- Pilih Mesin --</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Cycle Time -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">
                                            Cycle Time (jam:menit:detik)
                                            <i class="fas fa-info-circle text-info ml-1" data-toggle="tooltip"
                                                data-placement="top"
                                                title="Isi dengan durasi proses, bukan jam selesai mesin.">
                                            </i>
                                        </label>
                                        <input type="text" name="cycle_time" class="form-control"
                                            placeholder="Jam:Menit:Detik" pattern="^[0-9]{2}:[0-9]{2}:[0-9]{2}$"
                                            title="Format durasi Jam:Menit:Detik" required>
                                    </div>
                                </div>

                                <!-- Container untuk DetailProses (Single/Multiple) -->
                                <div class="col-12 hide-if-maintenance" id="detail-proses-container">
                                    <!-- Single OP Form (Default) -->
                                    <div class="detail-proses-item" data-index="0">
                                        <div class="card border mb-3" style="background: #f8f9fa;">
                                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 fw-bold">Detail OP #1</h6>
                                                <button type="button" class="btn btn-sm btn-danger remove-detail-btn ml-auto" style="display: none;">
                                                    <i class="fas fa-times"></i> Hapus
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <!-- No OP -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">No. OP</label>
                                                            <select name="details[0][no_op]" class="form-control select2-detail no-op-select"
                                                                style="width: 100%;" required>
                                                                <option value="" disabled>-- Pilih No. OP --</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- No Partai -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">No. Partai</label>
                                                            <select name="details[0][no_partai]" class="form-control select2-detail no-partai-select"
                                                                style="width: 100%;" required>
                                                                <option value="" disabled>-- Pilih No. Partai --</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Item OP (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Item OP</label>
                                                            <input type="text" name="details[0][item_op]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Kode Material (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Kode Material</label>
                                                            <input type="text" name="details[0][kode_material]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Konstruksi (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Konstruksi</label>
                                                            <input type="text" name="details[0][konstruksi]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Gramasi (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Gramasi</label>
                                                            <input type="text" name="details[0][gramasi]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Lebar (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Lebar</label>
                                                            <input type="text" name="details[0][lebar]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Hand Feel (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Hand Feel</label>
                                                            <input type="text" name="details[0][hfeel]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Warna (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Warna</label>
                                                            <input type="text" name="details[0][warna]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Kode Warna (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Kode Warna</label>
                                                            <input type="text" name="details[0][kode_warna]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Kategori Warna (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Kategori Warna</label>
                                                            <input type="text" name="details[0][kategori_warna]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- QTY (readonly, 2 digit koma) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Quantity</label>
                                                            <input type="text" name="details[0][qty]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Roll (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Roll</label>
                                                            <input type="text" name="details[0][roll]" class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tombol Tambah OP (untuk Multiple) -->
                                    <div class="text-center mb-3" id="add-detail-btn-container" style="display: none;">
                                        <button type="button" class="btn btn-success btn-sm" id="add-detail-btn">
                                            <i class="fas fa-plus mr-1"></i> Tambah OP
                                        </button>
                                    </div>
                                </div>


                            </div>
                        </div>


                        <!-- Footer -->
                        <div class="modal-footer d-flex justify-content-between px-4">
                            <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">
                                <i class="fas fa-times mr-2"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save mr-2"></i>Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Detail Proses -->
        <div class="modal fade" id="modalDetailProses" tabindex="-1" aria-labelledby="modalDetailProsesLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold" id="modalDetailProsesLabel">Detail Proses</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body py-3 px-4">
                        <table class="table table-bordered table-sm mb-0">
                            <tbody id="detail-proses-body">
                                <!-- Diisi via JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer d-flex justify-content-between px-4">
                        <div>
                            @if ($canEditProses ?? true)
                                <button type="button" class="btn btn-primary btn-edit-proses mr-2">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                            @endif
                            @if ($canMoveProses ?? true)
                                <button type="button" class="btn btn-warning btn-move-proses mr-2">
                                    <i class="fas fa-random mr-1"></i>Pindah Mesin
                                </button>
                            @endif
                            @if ($canDeleteProses ?? true)
                                <button type="button" class="btn btn-danger btn-delete-proses">
                                    <i class="fas fa-trash mr-1"></i>Hapus
                                </button>
                            @endif
                        </div>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Detail Proses dengan Pending Approval -->
        <div class="modal fade" id="modalDetailProsesPending" tabindex="-1"
            aria-labelledby="modalDetailProsesPendingLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold" id="modalDetailProsesPendingLabel">Detail Proses - Menunggu
                            Approval</h5>
                        <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body py-3 px-4">
                        <!-- Informasi Pending Approval -->
                        <div id="pending-approval-info" class="mb-3">
                            <!-- Diisi via JS -->
                        </div>
                        <!-- Detail Proses -->
                        <table class="table table-bordered table-sm mb-0">
                            <tbody id="detail-proses-pending-body">
                                <!-- Diisi via JS -->
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer d-flex justify-content-end px-4">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Edit Proses (tampilkan semua info, hanya Cycle Time bisa diubah) -->
        <div class="modal fade" id="modalEditProses" tabindex="-1" aria-labelledby="modalEditProsesLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formEditProses" method="POST" action="">
                        @csrf
                        <input type="hidden" name="proses_id" id="editProsesId">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fw-bold" id="modalEditProsesLabel">
                                Edit Cycle Time Proses
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body py-3 px-4">
                            <div class="alert alert-info py-2 mb-3" style="font-size: 13px;">
                                Semua informasi proses ditampilkan hanya-baca. <strong>Cycle time</strong> saja yang
                                dapat diubah dan perubahan akan <strong>menunggu persetujuan FM</strong> sebelum
                                diterapkan.
                            </div>
                            <div class="row">
                                <!-- Kolom kiri -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Jenis Proses</label>
                                        <input type="text" id="editJenis" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">No. OP</label>
                                        <input type="text" id="editNoOp" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">No. Partai</label>
                                        <input type="text" id="editNoPartai" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Item OP</label>
                                        <input type="text" id="editItemOp" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Kode Material</label>
                                        <input type="text" id="editKodeMaterial" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Konstruksi</label>
                                        <input type="text" id="editKonstruksi" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Gramasi</label>
                                        <input type="text" id="editGramasi" class="form-control" readonly>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="form-label fw-semibold">Lebar</label>
                                        <input type="text" id="editLebar" class="form-control" readonly>
                                    </div>
                                </div>

                                <!-- Kolom kanan -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Hand Feel</label>
                                        <input type="text" id="editHfeel" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Warna</label>
                                        <input type="text" id="editWarna" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Kode Warna</label>
                                        <input type="text" id="editKodeWarna" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Kategori Warna</label>
                                        <input type="text" id="editKategoriWarna" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Quantity</label>
                                        <input type="text" id="editQty" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Roll</label>
                                        <input type="text" id="editRoll" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Cycle Time Baru (jam:menit:detik)</label>
                                        <input type="text" name="cycle_time" id="editCycleTime" class="form-control"
                                            placeholder="HH:MM:SS" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between px-4">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane mr-1"></i>Kirim Permintaan Edit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Hapus Proses -->
        <div class="modal fade" id="modalDeleteProses" tabindex="-1" aria-labelledby="modalDeleteProsesLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formDeleteProses" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="proses_id" id="deleteProsesId">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold" id="modalDeleteProsesLabel">
                                Konfirmasi Hapus Proses
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body py-3 px-4">
                            <div class="alert alert-danger py-2 mb-3" style="font-size: 13px;">
                                Proses <strong>tidak akan langsung dihapus</strong>. Permintaan hapus akan
                                <strong>menunggu persetujuan FM</strong>.
                            </div>
                            <p id="deleteProsesInfo" style="font-size: 14px; margin-bottom: 0;">
                                Apakah Anda yakin ingin mengajukan penghapusan proses ini?
                            </p>
                        </div>
                        <div class="modal-footer d-flex justify-content-between px-4">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-paper-plane mr-1"></i>Kirim Permintaan Hapus
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Pindah Mesin -->
        <div class="modal fade" id="modalMoveProses" tabindex="-1" aria-labelledby="modalMoveProsesLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formMoveProses" method="POST" action="">
                        @csrf
                        <input type="hidden" name="proses_id" id="moveProsesId">
                        <div class="modal-header bg-warning text-white">
                            <h5 class="modal-title fw-bold" id="modalMoveProsesLabel">
                                <i class="fas fa-random mr-2"></i>Pindah Mesin
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body py-3 px-4">
                            <div class="alert alert-info py-2 mb-3" style="font-size: 13px;">
                                Proses <strong>tidak akan langsung dipindah</strong>. Permintaan pindah mesin akan
                                <strong>menunggu persetujuan FM</strong>.
                            </div>
                            <div class="form-group">
                                <label class="form-label fw-semibold">Pilih Mesin Tujuan</label>
                                <select name="mesin_id" id="moveMesinId" class="form-control" required>
                                    <option value="" disabled selected>-- Pilih Mesin --</option>
                                </select>
                            </div>
                            <p id="moveProsesInfo" style="font-size: 14px; margin-bottom: 0; color: #666;">
                                Pilih mesin tujuan untuk memindahkan proses ini.
                            </p>
                        </div>
                        <div class="modal-footer d-flex justify-content-between px-4">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-paper-plane mr-1"></i>Kirim Permintaan Pindah
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Pindah Mesin (Drag & Drop) -->
        <div class="modal fade" id="modalConfirmMoveDragDrop" tabindex="-1"
            aria-labelledby="modalConfirmMoveDragDropLabel" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title fw-bold" id="modalConfirmMoveDragDropLabel">
                            <i class="fas fa-random mr-2"></i>Konfirmasi Pindah Mesin
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body py-3 px-4">
                        <div class="alert alert-info py-2 mb-3" style="font-size: 13px;">
                            Proses <strong>tidak akan langsung dipindah</strong>. Permintaan pindah mesin akan
                            <strong>menunggu persetujuan FM</strong>.
                        </div>
                        <p id="confirmMoveDragDropInfo" style="font-size: 14px; margin-bottom: 0; color: #666;">
                            Apakah Anda yakin ingin memindahkan proses ini ke mesin lain?
                        </p>
                    </div>
                    <div class="modal-footer d-flex justify-content-between px-4">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"
                            id="btnCancelMoveDragDrop">
                            <i class="fas fa-times mr-1"></i>Batal
                        </button>
                        <button type="button" class="btn btn-warning" id="btnConfirmMoveDragDrop">
                            <i class="fas fa-check mr-1"></i>Ya, Pindahkan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Swap Position (Drag & Drop) -->
        <div class="modal fade" id="modalConfirmSwapDragDrop" tabindex="-1"
            aria-labelledby="modalConfirmSwapDragDropLabel" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold" id="modalConfirmSwapDragDropLabel">
                            <i class="fas fa-exchange-alt mr-2"></i>Konfirmasi Tukar Posisi
                        </h5>
                        <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body py-3 px-4">
                        <div class="alert alert-warning py-2 mb-3" style="font-size: 13px;">
                            Proses <strong>tidak akan langsung ditukar posisinya</strong>. Permintaan tukar posisi akan
                            <strong>menunggu persetujuan FM</strong>.
                        </div>
                        <p id="confirmSwapDragDropInfo" style="font-size: 14px; margin-bottom: 0; color: #666;">
                            Apakah Anda yakin ingin menukar posisi proses ini?
                        </p>
                    </div>
                    <div class="modal-footer d-flex justify-content-between px-4">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"
                            id="btnCancelSwapDragDrop">
                            <i class="fas fa-times mr-1"></i>Batal
                        </button>
                        <button type="button" class="btn btn-warning" id="btnConfirmSwapDragDrop">
                            <i class="fas fa-check mr-1"></i>Ya, Tukar Posisi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    {{-- Script drag & drop dan select2 dashboard tanpa log console --}}
    <script>
        // Simpan data mesin ke variabel global untuk digunakan di modal pindah mesin
        window.mesinsData = @json(
            $mesins->map(function ($m) {
                return ['id' => $m->id, 'jenis_mesin' => $m->jenis_mesin];
            }));

        // Simpan role user dan permission flags (dari controller)
        window.userRole = @json($userRole ?? null);
        window.canCancelBarcode = @json($canCancelBarcode ?? false);
        window.canAddProses = @json($canAddProses ?? true);
        window.canEditProses = @json($canEditProses ?? true);
        window.canDeleteProses = @json($canDeleteProses ?? true);
        window.canMoveProses = @json($canMoveProses ?? true);
        window.canSwapProses = @json($canSwapProses ?? true);
        window.canScanBarcode = @json($canScanBarcode ?? true);
        document.addEventListener('DOMContentLoaded', () => {
            const draggables = document.querySelectorAll('.draggable');
            const containers = document.querySelectorAll('.card-dropzone');
            let draggedCard = null;
            let sourceMesinId = null;
            let targetMesinId = null;
            let dropTargetElement = null; // Simpan elemen target yang tepat di posisi drop

            draggables.forEach(draggable => {
                draggable.addEventListener('dragstart', (e) => {
                    // Cek permission swap dari controller
                    if (window.canSwapProses === false) {
                        e.preventDefault();
                        return false;
                    }
                    const canMove = draggable.getAttribute('data-can-move') === '1';
                    if (!canMove) {
                        e.preventDefault();
                        return false;
                    }

                    // Validasi tambahan: cek apakah proses sudah dimulai atau ada pending approval
                    const proses = $(draggable).data('proses');
                    if (proses && (proses.mulai !== null && proses.mulai !== undefined && proses
                            .mulai !== '')) {
                        e.preventDefault();
                        return false;
                    }
                    // Cek pending approval VP untuk Reproses
                    const hasPendingReprocess = draggable.getAttribute(
                        'data-has-pending-reprocess') === '1';
                    if (hasPendingReprocess) {
                        e.preventDefault();
                        return false;
                    }

                    draggable.classList.add('dragging');
                    draggedCard = draggable;
                    dropTargetElement = null; // Reset drop target element
                    if (proses && proses.mesin_id) {
                        sourceMesinId = proses.mesin_id;
                    }
                    // Store original position dan next sibling untuk fallback
                    // const parent = draggedCard.parentElement;
                    // draggedCard.setAttribute('data-original-parent', parent.getAttribute('data-mesin-id'));
                    const mesintContainer = draggedCard.closest('.card-dropzone');
                    if (mesintContainer) {
                        draggedCard.setAttribute('data-original-parent', mesintContainer
                            .getAttribute('data-mesin-id'));
                    }
                    const nextSibling = draggedCard.nextSibling;
                    if (nextSibling) {
                        draggedCard.setAttribute('data-original-next-sibling-id', nextSibling.id ||
                            '');
                    } else {
                        draggedCard.setAttribute('data-original-next-sibling-id', '');
                    }
                    // Simpan referensi ke next sibling element (bukan node)
                    draggedCard._originalNextSibling = nextSibling;
                });

                draggable.addEventListener('dragend', () => {
                    // Jangan hapus dragging class jika sedang dalam proses AJAX
                    // Class akan dihapus setelah AJAX selesai
                    if (!draggable.hasAttribute('data-ajax-pending')) {
                        draggable.classList.remove('dragging');
                        draggedCard = null;
                        sourceMesinId = null;
                        targetMesinId = null;
                        dropTargetElement = null; // Reset drop target element
                    }
                });
            });

            containers.forEach(container => {
                container.addEventListener('dragover', e => {
                    e.preventDefault();
                    // Cek permission swap dari controller
                    if (window.canSwapProses === false) {
                        return;
                    }
                    const dragging = document.querySelector('.draggable.dragging');
                    if (!dragging) return;

                    const canMove = dragging.getAttribute('data-can-move') === '1';
                    if (!canMove) return;

                    const targetMesin = container.getAttribute('data-mesin-id');
                    targetMesinId = targetMesin ? parseInt(targetMesin) : null;

                    // Validasi: proses yang belum mulai tidak boleh diletakkan di atas proses yang sudah selesai atau sedang berjalan
                    const draggedProses = $(dragging).data('proses');
                    const isDraggedNotStarted = draggedProses && (draggedProses.mulai === null ||
                        draggedProses.mulai === undefined || draggedProses.mulai === '');

                    if (isDraggedNotStarted) {
                        // Cek semua elemen sebelum afterElement untuk memastikan tidak ada proses yang sudah selesai atau sedang berjalan
                        const allElements = [...container.querySelectorAll(
                            '.draggable:not(.dragging)')];
                        const afterElement = getDragAfterElement(container, e.clientY, dragging);

                        if (afterElement) {
                            // Cek apakah afterElement adalah proses yang sudah selesai atau sedang berjalan
                            const afterProses = $(afterElement).data('proses');
                            if (afterProses) {
                                const isAfterFinished = afterProses.selesai !== null && afterProses
                                    .selesai !== undefined && afterProses.selesai !== '';
                                const isAfterRunning = afterProses.mulai !== null && afterProses
                                    .mulai !== undefined && afterProses.mulai !== '' &&
                                    (afterProses.selesai === null || afterProses.selesai ===
                                        undefined || afterProses.selesai === '');

                                if (isAfterFinished || isAfterRunning) {
                                    // Cari posisi yang valid (di atas proses yang belum mulai atau di akhir)
                                    let validPosition = null;
                                    let validTargetElement = null;
                                    for (let i = 0; i < allElements.length; i++) {
                                        if (allElements[i] === afterElement) {
                                            // Cari elemen sebelumnya yang belum mulai
                                            for (let j = i - 1; j >= 0; j--) {
                                                const prevProses = $(allElements[j]).data('proses');
                                                if (prevProses && (prevProses.mulai === null ||
                                                        prevProses.mulai === undefined || prevProses
                                                        .mulai === '')) {
                                                    validPosition = allElements[j].nextSibling;
                                                    validTargetElement = allElements[j];
                                                    break;
                                                }
                                            }
                                            if (!validPosition) {
                                                // Jika tidak ada posisi valid sebelumnya, letakkan di akhir
                                                validPosition = null;
                                                const allCards = [...container.querySelectorAll(
                                                    '.status-card:not(.dragging):not(.history-card)'
                                                )];
                                                validTargetElement = allCards.length > 0 ? allCards[
                                                    allCards.length - 1] : null;
                                            }
                                            break;
                                        }
                                    }

                                    // Simpan drop target element
                                    dropTargetElement = validTargetElement;

                                    if (validPosition === null) {
                                        container.appendChild(dragging);
                                    } else {
                                        // Pastikan validPosition masih merupakan child dari container sebelum insertBefore
                                        if (container.contains(validPosition) && validPosition
                                            .parentElement === container) {
                                            container.insertBefore(dragging, validPosition);
                                        } else {
                                            // Jika validPosition tidak valid, cari ulang posisi yang valid
                                            const allCards = [...container.querySelectorAll(
                                                '.status-card:not(.dragging):not(.history-card)'
                                            )];
                                            if (validTargetElement && container.contains(
                                                    validTargetElement)) {
                                                // Cari nextSibling dari validTargetElement yang masih di container
                                                const validNextSibling = validTargetElement
                                                    .nextSibling;
                                                if (validNextSibling && container.contains(
                                                        validNextSibling)) {
                                                    container.insertBefore(dragging,
                                                        validNextSibling);
                                                } else {
                                                    container.appendChild(dragging);
                                                }
                                            } else {
                                                container.appendChild(dragging);
                                            }
                                        }
                                    }
                                    return;
                                }
                            }
                        }
                    }

                    const afterElement = getDragAfterElement(container, e.clientY, dragging);

                    // Simpan drop target element untuk digunakan saat drop
                    // Target adalah elemen yang tepat di posisi drop (afterElement atau elemen terakhir)
                    if (afterElement == null) {
                        // Jika tidak ada afterElement, cek elemen terakhir di container
                        const allCards = [...container.querySelectorAll(
                            '.status-card:not(.dragging):not(.history-card)')];
                        dropTargetElement = allCards.length > 0 ? allCards[allCards.length - 1] :
                            null;
                        container.appendChild(dragging);
                    } else {
                        // Pastikan afterElement masih merupakan child dari container sebelum insertBefore
                        if (container.contains(afterElement) && afterElement.parentElement ===
                            container) {
                            // Target adalah elemen sebelum afterElement (karena dragging akan di-insert sebelum afterElement)
                            const allCards = [...container.querySelectorAll(
                                '.status-card:not(.dragging):not(.history-card)')];
                            const afterIndex = allCards.indexOf(afterElement);
                            dropTargetElement = afterIndex > 0 ? allCards[afterIndex - 1] :
                                afterElement;
                            container.insertBefore(dragging, afterElement);
                        } else {
                            // Jika afterElement tidak valid, cari ulang atau append di akhir
                            const allCards = [...container.querySelectorAll(
                                '.status-card:not(.dragging):not(.history-card)')];
                            dropTargetElement = allCards.length > 0 ? allCards[allCards.length -
                                1] : null;
                            container.appendChild(dragging);
                        }
                    }
                });

                container.addEventListener('drop', (e) => {
                    e.preventDefault();
                    // Cek permission swap dari controller
                    if (window.canSwapProses === false) {
                        const dragging = document.querySelector('.draggable.dragging');
                        if (dragging) {
                            restoreCardToOriginalPosition(dragging);
                            dragging.classList.remove('dragging');
                        }
                        return;
                    }
                    const dragging = document.querySelector('.draggable.dragging');
                    if (!dragging) return;

                    const canMove = dragging.getAttribute('data-can-move') === '1';
                    if (!canMove) {
                        restoreCardToOriginalPosition(dragging);
                        dragging.classList.remove('dragging');
                        return;
                    }

                    const proses = $(dragging).data('proses');
                    if (!proses) {
                        restoreCardToOriginalPosition(dragging);
                        dragging.classList.remove('dragging');
                        return;
                    }

                    // Validasi tambahan: cek apakah proses sudah dimulai
                    if (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !==
                        '') {
                        restoreCardToOriginalPosition(dragging);
                        dragging.classList.remove('dragging');

                        // Tampilkan notification error
                        const ToastError = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true,
                        });
                        ToastError.fire({
                            title: 'Tidak dapat memindahkan proses. Proses sudah dimulai.'
                        });
                        return;
                    }

                    // Validasi: cek pending approval VP untuk Reproses
                    const hasPendingReprocess = dragging.getAttribute(
                        'data-has-pending-reprocess') === '1';
                    if (hasPendingReprocess) {
                        restoreCardToOriginalPosition(dragging);
                        dragging.classList.remove('dragging');

                        // Tampilkan notification error
                        const ToastError = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true,
                        });
                        ToastError.fire({
                            title: 'Tidak dapat memindahkan proses. Proses Reproses masih menunggu persetujuan VP.'
                        });
                        return;
                    }

                    // Validasi: proses yang belum mulai tidak boleh dipindah ke atas proses yang sudah selesai atau sedang berjalan
                    const isDraggedNotStarted = proses.mulai === null || proses.mulai ===
                        undefined || proses.mulai === '';
                    if (isDraggedNotStarted) {
                        // Cek proses yang tepat setelah posisi drop (next element sibling setelah dragging)
                        const nextElement = dragging.nextElementSibling;
                        if (nextElement && nextElement.classList && nextElement.classList.contains(
                                'status-card')) {
                            const nextProses = $(nextElement).data('proses');
                            if (nextProses) {
                                const isNextFinished = nextProses.selesai !== null && nextProses
                                    .selesai !== undefined && nextProses.selesai !== '';
                                const isNextRunning = nextProses.mulai !== null && nextProses
                                    .mulai !== undefined && nextProses.mulai !== '' &&
                                    (nextProses.selesai === null || nextProses.selesai ===
                                        undefined || nextProses.selesai === '');

                                if (isNextFinished || isNextRunning) {
                                    restoreCardToOriginalPosition(dragging);
                                    dragging.classList.remove('dragging');

                                    // Tampilkan notification error
                                    const ToastError = Swal.mixin({
                                        toast: true,
                                        position: 'top-end',
                                        icon: 'error',
                                        showConfirmButton: false,
                                        timer: 4000,
                                        timerProgressBar: true,
                                    });
                                    ToastError.fire({
                                        title: 'Tidak dapat memindahkan proses. Proses yang belum mulai tidak dapat diletakkan di atas proses yang sudah selesai atau sedang berjalan.'
                                    });
                                    return;
                                }
                            }
                        }
                    }

                    const newMesinId = parseInt(container.getAttribute('data-mesin-id'));
                    const oldMesinId = proses.mesin_id;

                    // DETECT: Jika dipindah ke mesin yang sama = SWAP POSITION (reorder)
                    // Jika dipindah ke mesin berbeda = MOVE MACHINE
                    if (newMesinId === oldMesinId) {
                        // SWAP POSITION: Cari proses target (proses yang ada di posisi drop)
                        // Gunakan dropTargetElement yang sudah disimpan saat dragover untuk akurasi lebih baik
                        let targetProses = null;
                        let targetCard = null;

                        // Prioritas 1: Gunakan dropTargetElement yang sudah disimpan (paling akurat)
                        if (dropTargetElement &&
                            dropTargetElement.classList &&
                            dropTargetElement.classList.contains('status-card') &&
                            !dropTargetElement.classList.contains('history-card')) {
                            const targetProsesData = $(dropTargetElement).data('proses');
                            if (targetProsesData &&
                                targetProsesData.id !== proses.id &&
                                targetProsesData.mulai === null &&
                                targetProsesData.selesai === null &&
                                targetProsesData.mesin_id == newMesinId) {
                                targetProses = targetProsesData;
                                targetCard = dropTargetElement;
                            }
                        }

                        // Prioritas 2: Jika dropTargetElement tidak valid, cek next sibling (proses setelah posisi drop)
                        if (!targetProses) {
                            const nextSibling = dragging.nextElementSibling;
                            if (nextSibling && nextSibling.classList && nextSibling.classList
                                .contains('status-card') && !nextSibling.classList.contains(
                                    'history-card')) {
                                const nextProses = $(nextSibling).data('proses');
                                if (nextProses &&
                                    nextProses.id !== proses.id &&
                                    nextProses.mulai === null &&
                                    nextProses.selesai === null &&
                                    nextProses.mesin_id == newMesinId) {
                                    targetProses = nextProses;
                                    targetCard = nextSibling;
                                }
                            }
                        }

                        // Prioritas 3: Jika tidak ada next sibling, cek previous sibling
                        if (!targetProses) {
                            const prevSibling = dragging.previousElementSibling;
                            if (prevSibling && prevSibling.classList && prevSibling.classList
                                .contains('status-card') && !prevSibling.classList.contains(
                                    'history-card')) {
                                const prevProses = $(prevSibling).data('proses');
                                if (prevProses &&
                                    prevProses.id !== proses.id &&
                                    prevProses.mulai === null &&
                                    prevProses.selesai === null &&
                                    prevProses.mesin_id == newMesinId) {
                                    targetProses = prevProses;
                                    targetCard = prevSibling;
                                }
                            }
                        }

                        // Prioritas 4: Jika masih tidak ada target, cari dari semua card di container yang valid
                        // Cari card yang paling dekat dengan posisi drop berdasarkan index
                        if (!targetProses) {
                            const allCards = [...container.querySelectorAll(
                                '.status-card:not(.dragging):not(.history-card)')];
                            const draggingIndex = Array.from(container.children).indexOf(dragging);

                            // Cari card terdekat berdasarkan index
                            let closestCard = null;
                            let closestDistance = Infinity;

                            for (let card of allCards) {
                                const cardProses = $(card).data('proses');
                                if (cardProses &&
                                    cardProses.id !== proses.id &&
                                    cardProses.mulai === null &&
                                    cardProses.selesai === null &&
                                    cardProses.mesin_id == newMesinId) {
                                    const cardIndex = Array.from(container.children).indexOf(card);
                                    const distance = Math.abs(cardIndex - draggingIndex);
                                    if (distance < closestDistance) {
                                        closestDistance = distance;
                                        closestCard = card;
                                    }
                                }
                            }

                            if (closestCard) {
                                targetProses = $(closestCard).data('proses');
                                targetCard = closestCard;
                            }
                        }

                        if (!targetProses || !targetCard) {
                            // Tidak ada target yang valid, kembalikan ke posisi semula
                            restoreCardToOriginalPosition(dragging);
                            dragging.classList.remove('dragging');

                            const ToastError = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                icon: 'error',
                                showConfirmButton: false,
                                timer: 4000,
                                timerProgressBar: true,
                            });
                            ToastError.fire({
                                title: 'Tidak ada proses yang valid untuk ditukar posisinya.'
                            });
                            return;
                        }

                        // Validasi: cek pending approval untuk kedua proses
                        const hasPending1 = hasPendingApprovalFM(proses);
                        const hasPending2 = hasPendingApprovalFM(targetProses);

                        if (hasPending1 || hasPending2) {
                            restoreCardToOriginalPosition(dragging);
                            dragging.classList.remove('dragging');

                            const ToastError = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                icon: 'error',
                                showConfirmButton: false,
                                timer: 4000,
                                timerProgressBar: true,
                            });
                            ToastError.fire({
                                title: 'Salah satu proses masih memiliki permintaan yang menunggu persetujuan FM.'
                            });
                            return;
                        }

                        // Simpan data untuk swap position
                        window.pendingSwapData = {
                            dragging: dragging,
                            targetCard: targetCard,
                            proses1: proses,
                            proses2: targetProses,
                            mesinId: newMesinId,
                            originalParentId: dragging.getAttribute('data-original-parent'),
                            originalParent: document.querySelector(
                                `[data-mesin-id="${oldMesinId}"]`),
                            originalNextSibling: dragging._originalNextSibling
                        };

                        // Kembalikan card ke posisi asli terlebih dahulu (visual feedback)
                        restoreCardToOriginalPosition(dragging);

                        // Tampilkan modal konfirmasi swap position
                        const proses1NoOp = getNoOpFromProses(proses);
                        const proses2NoOp = getNoOpFromProses(targetProses);
                        const infoText =
                            `Apakah Anda yakin ingin menukar posisi proses <strong>${proses1NoOp}</strong> dengan proses <strong>${proses2NoOp}</strong>?<br><br><small class="text-muted">Permintaan ini akan menunggu persetujuan FM.</small>`;
                        $('#confirmSwapDragDropInfo').html(infoText);
                        $('#modalConfirmSwapDragDrop').modal('show');
                        return;
                    }

                    // Simpan data untuk modal konfirmasi
                    const originalParentId = dragging.getAttribute('data-original-parent');
                    const originalParent = document.querySelector(
                        `[data-mesin-id="${originalParentId}"]`);
                    const originalNextSibling = dragging._originalNextSibling;

                    // Cari nama mesin sumber dan tujuan
                    const sourceMesinName = originalParent ? originalParent.closest(
                            '.machine-column').querySelector('.machine-header').textContent.trim() :
                        'Mesin Sumber';
                    const targetMesinName = container.closest('.machine-column').querySelector(
                        '.machine-header').textContent.trim();

                    // Simpan data ke window untuk digunakan saat konfirmasi
                    window.pendingMoveData = {
                        dragging: dragging,
                        proses: proses,
                        newMesinId: newMesinId,
                        oldMesinId: oldMesinId,
                        originalParentId: originalParentId,
                        originalParent: originalParent,
                        originalNextSibling: originalNextSibling,
                        sourceMesinName: sourceMesinName,
                        targetMesinName: targetMesinName
                    };

                    // Kembalikan card ke posisi asli terlebih dahulu (visual feedback)
                    restoreCardToOriginalPosition(dragging);

                    // Pastikan card benar-benar sudah kembali ke mesin asal setelah restore
                    // Tunggu sebentar untuk memastikan DOM sudah update
                    setTimeout(function() {
                        const currentMesinContainer = dragging.closest('[data-mesin-id]');
                        const currentMesinId = currentMesinContainer ? currentMesinContainer
                            .getAttribute('data-mesin-id') : null;

                        if (currentMesinId !== originalParentId && originalParent) {
                            // Jika card masih tidak di mesin yang benar, paksa pindahkan
                            const prosesAktifContainer = originalParent.querySelector(
                                '.proses-aktif-container');
                            const targetContainer = prosesAktifContainer || originalParent;

                            if (originalNextSibling &&
                                originalNextSibling.parentElement === targetContainer &&
                                targetContainer.contains(originalNextSibling) &&
                                document.contains(originalNextSibling)) {
                                targetContainer.insertBefore(dragging, originalNextSibling);
                            } else {
                                targetContainer.appendChild(dragging);
                            }
                        }
                    }, 10);

                    // Tampilkan modal konfirmasi
                    const noOp = getNoOpFromProses(proses);
                    const infoText =
                        `Apakah Anda yakin ingin memindahkan proses <strong>${noOp}</strong> dari <strong>${sourceMesinName}</strong> ke <strong>${targetMesinName}</strong>?`;
                    $('#confirmMoveDragDropInfo').html(infoText);
                    $('#modalConfirmMoveDragDrop').modal('show');
                });
            });

            function getDragAfterElement(container, y, draggingCard) {
                const draggableElements = [...container.querySelectorAll('.draggable:not(.dragging)')];

                // Ambil data proses yang sedang di-drag
                const draggedProses = draggingCard ? $(draggingCard).data('proses') : null;
                const isDraggedNotStarted = draggedProses && (draggedProses.mulai === null || draggedProses
                    .mulai === undefined || draggedProses.mulai === '');

                return draggableElements.reduce((closest, child) => {
                    // Jika proses yang di-drag belum mulai, skip proses yang sudah selesai atau sedang berjalan
                    if (isDraggedNotStarted) {
                        const childProses = $(child).data('proses');
                        if (childProses) {
                            // Skip jika proses sudah selesai (selesai !== null)
                            if (childProses.selesai !== null && childProses.selesai !== undefined &&
                                childProses.selesai !== '') {
                                return closest;
                            }
                            // Skip jika proses sedang berjalan (mulai !== null && selesai === null)
                            if (childProses.mulai !== null && childProses.mulai !== undefined && childProses
                                .mulai !== '' &&
                                (childProses.selesai === null || childProses.selesai === undefined ||
                                    childProses.selesai === '')) {
                                return closest;
                            }
                        }
                    }

                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    return (offset < 0 && offset > closest.offset) ? {
                        offset,
                        element: child
                    } : closest;
                }, {
                    offset: Number.NEGATIVE_INFINITY
                }).element;
            }

            // Fungsi untuk mengembalikan card ke posisi asli
            function restoreCardToOriginalPosition(card) {
                const originalParentId = card.getAttribute('data-original-parent');

                // Safety check: Jika ID null (karena bug sebelumnya), stop.
                if (!originalParentId) {
                    console.error("Original parent ID missing");
                    return;
                }

                // Cari elemen dropzone mesin asal
                const originalDropzone = document.querySelector(
                    `.card-dropzone[data-mesin-id="${originalParentId}"]`);
                if (!originalDropzone) return;

                // Cari container spesifik untuk proses aktif di dalam dropzone
                // (Karena struktur HTML Anda memisahkan history dan proses aktif)
                const prosesAktifContainer = originalDropzone.querySelector('.proses-aktif-container') ||
                    originalDropzone;

                // Cek apakah card sudah ada di tempat yang benar
                if (card.parentElement === prosesAktifContainer) {
                    // Jika sudah benar, urutkan kembali berdasarkan sibling jika perlu
                    const originalNextSibling = card._originalNextSibling;
                    if (originalNextSibling && originalNextSibling.parentElement === prosesAktifContainer) {
                        prosesAktifContainer.insertBefore(card, originalNextSibling);
                    }
                    return;
                }

                // --- LOGIKA PENGEMBALIAN ---

                const originalNextSibling = card._originalNextSibling;

                // Cek 1: Kembalikan ke sebelah sibling aslinya (jika sibling masih ada di sana)
                if (originalNextSibling &&
                    originalNextSibling.parentElement === prosesAktifContainer &&
                    document.contains(originalNextSibling)) {
                    prosesAktifContainer.insertBefore(card, originalNextSibling);
                }
                // Cek 2: Jika sibling hilang/pindah, taruh di paling akhir container
                else {
                    prosesAktifContainer.appendChild(card);
                }
            }

            // Fungsi untuk update visual card menandakan ada pending approval
            function updateCardPendingApproval(card) {
                // Tambahkan class atau style untuk menandakan pending approval
                // Card akan berubah menjadi kuning setelah refresh halaman
                // Untuk sementara, kita bisa menambahkan indicator visual
                card.style.opacity = '0.8';
                card.style.border = '2px dashed #ffeb3b';

                // Setelah refresh, card akan otomatis berubah menjadi kuning
                // karena backend sudah mengecek pending approval
            }
        });

        // Handler konfirmasi pindah mesin (Drag & Drop)
        $(document).on('click', '#btnConfirmMoveDragDrop', function() {
            const moveData = window.pendingMoveData;
            if (!moveData) {
                $('#modalConfirmMoveDragDrop').modal('hide');
                return;
            }

            const {
                dragging,
                proses,
                newMesinId,
                originalParentId,
                originalParent,
                originalNextSibling
            } = moveData;

            // Disable tombol untuk mencegah double click
            $('#btnConfirmMoveDragDrop').prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm mr-1"></span>Memproses...');
            $('#btnCancelMoveDragDrop').prop('disabled', true);

            // Tandai bahwa sedang dalam proses AJAX
            dragging.setAttribute('data-ajax-pending', 'true');

            // Kirim request ke server untuk membuat approval
            $.ajax({
                url: `/proses/${proses.id}/move`,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                data: {
                    mesin_id: newMesinId
                },
                success: function(response) {
                    // Tutup modal
                    $('#modalConfirmMoveDragDrop').modal('hide');

                    // Tampilkan notification success dengan delay sangat singkat
                    if (response && response.status === 'success' && response.message) {
                        const ToastSuccess = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 1500,
                            timerProgressBar: true,
                        });
                        ToastSuccess.fire({
                            title: response.message
                        });
                    }

                    // Langsung refresh halaman agar card berubah warna menjadi kuning (pending approval)
                    // Backend akan mengecek status pending approval dan merender card dengan warna yang sesuai
                    if (response && response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        window.location.reload();
                    }
                },
                error: function(xhr) {
                    $('#modalConfirmMoveDragDrop').modal('hide');

                    // Hapus flag AJAX pending
                    dragging.removeAttribute('data-ajax-pending');
                    dragging.classList.remove('dragging');

                    // Pastikan card sudah kembali ke posisi asli
                    const originalParentId = dragging.getAttribute('data-original-parent');
                    const originalParent = document.querySelector(
                        `[data-mesin-id="${originalParentId}"]`);
                    if (originalParent && dragging.parentElement !== originalParent) {
                        const originalNextSibling = dragging._originalNextSibling;
                        if (originalNextSibling && originalNextSibling.parentElement ===
                            originalParent) {
                            originalParent.insertBefore(dragging, originalNextSibling);
                        } else {
                            originalParent.appendChild(dragging);
                        }
                    }

                    // Tampilkan notification error
                    let errorMsg = 'Gagal mengirim permintaan pindah mesin.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    } else if (xhr.status === 422) {
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                    }

                    const ToastError = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true,
                    });
                    ToastError.fire({
                        title: errorMsg
                    });
                },
                complete: function() {
                    // Reset tombol dan data
                    $('#btnConfirmMoveDragDrop').prop('disabled', false).html(
                        '<i class="fas fa-check mr-1"></i>Ya, Pindahkan');
                    $('#btnCancelMoveDragDrop').prop('disabled', false);
                    window.pendingMoveData = null;
                }
            });
        });

        // Handler cancel konfirmasi pindah mesin (Drag & Drop)
        // Hanya tutup modal, handler hidden.bs.modal yang akan mengembalikan card
        $(document).on('click', '#btnCancelMoveDragDrop', function() {
            const moveData = window.pendingMoveData;
            if (moveData && moveData.dragging) {
                moveData.dragging.classList.remove('dragging');
            }
            // Tidak perlu hapus pendingMoveData di sini, biarkan handler hidden.bs.modal yang menangani
            $('#modalConfirmMoveDragDrop').modal('hide');
        });

        // Handler konfirmasi swap position (Drag & Drop)
        $(document).on('click', '#btnConfirmSwapDragDrop', function() {
            const swapData = window.pendingSwapData;
            if (!swapData) {
                $('#modalConfirmSwapDragDrop').modal('hide');
                return;
            }

            const {
                dragging,
                targetCard,
                proses1,
                proses2
            } = swapData;

            // Disable tombol untuk mencegah double click
            $('#btnConfirmSwapDragDrop').prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm mr-1"></span>Memproses...');
            $('#btnCancelSwapDragDrop').prop('disabled', true);

            // Tandai bahwa sedang dalam proses AJAX
            dragging.setAttribute('data-ajax-pending', 'true');
            if (targetCard) {
                targetCard.setAttribute('data-ajax-pending', 'true');
            }

            // Kirim request ke server untuk membuat approval swap position
            $.ajax({
                url: `/proses/${proses1.id}/swap`,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                data: {
                    swapped_proses_id: proses2.id
                },
                success: function(response) {
                    // Tutup modal
                    $('#modalConfirmSwapDragDrop').modal('hide');

                    // Tampilkan notification success
                    if (response && response.status === 'success' && response.message) {
                        const ToastSuccess = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 1500,
                            timerProgressBar: true,
                        });
                        ToastSuccess.fire({
                            title: response.message
                        });
                    }

                    // Update visual: tandai semua proses yang terpengaruh dengan pending approval (kuning)
                    // Fungsi untuk update card menjadi kuning (pending approval)
                    function markCardAsPending(card) {
                        if (!card) return;
                        const gradient =
                            'linear-gradient(180deg, #fff9c4 0%,rgb(183, 168, 33) 60%,rgb(202, 161, 57) 100%)';
                        card.style.background = gradient;
                        card.setAttribute('data-bg-color', '#ffeb3b');
                        // Update data proses untuk menambahkan pending approval
                        const proses = $(card).data('proses');
                        if (proses) {
                            if (!proses.approvals) {
                                proses.approvals = [];
                            }
                            // Tambahkan pending approval untuk swap_position jika belum ada
                            const hasSwapPending = proses.approvals.some(function(approval) {
                                return approval.status === 'pending' &&
                                    approval.type === 'FM' &&
                                    approval.action === 'swap_position';
                            });
                            if (!hasSwapPending) {
                                proses.approvals.push({
                                    status: 'pending',
                                    type: 'FM',
                                    action: 'swap_position'
                                });
                            }
                            $(card).data('proses', proses);
                        }
                    }

                    // Tandai kedua card sebagai pending (proses yang langsung terlibat dalam swap)
                    markCardAsPending(dragging);
                    markCardAsPending(targetCard);

                    // Tandai semua proses pending di mesin yang sama yang belum mulai
                    // Karena reorder bisa mempengaruhi semua proses di antara posisi lama dan baru
                    const mesinId = swapData.mesinId;
                    const container = document.querySelector(`[data-mesin-id="${mesinId}"]`);
                    if (container) {
                        const allCards = container.querySelectorAll('.status-card:not(.history-card)');
                        allCards.forEach(function(card) {
                            const cardProses = $(card).data('proses');
                            if (cardProses &&
                                cardProses.mulai === null &&
                                cardProses.selesai === null &&
                                cardProses.mesin_id == mesinId) {
                                // Tandai semua proses pending yang belum mulai di mesin yang sama
                                // Backend akan menentukan proses mana yang benar-benar terpengaruh saat approval
                                markCardAsPending(card);
                            }
                        });

                        // Reorder card berdasarkan order yang baru (jika ada di response)
                        if (response && response.affected_orders) {
                            // Update order di data-proses untuk setiap card
                            allCards.forEach(function(card) {
                                const cardProses = $(card).data('proses');
                                if (cardProses && response.affected_orders[cardProses.id] !==
                                    undefined) {
                                    cardProses.order = response.affected_orders[cardProses.id];
                                    $(card).data('proses', cardProses);
                                }
                            });

                            // Reorder card berdasarkan order baru
                            reorderCardsByOrder(container);
                        }
                    }

                    // Langsung refresh halaman agar card berubah warna menjadi kuning (pending approval)
                    // Backend akan mengecek status pending approval dan merender card dengan warna yang sesuai
                    // Tapi tunggu sebentar untuk menampilkan visual reorder terlebih dahulu
                    setTimeout(function() {
                        if (response && response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 500);
                },
                error: function(xhr) {
                    $('#modalConfirmSwapDragDrop').modal('hide');

                    // Hapus flag AJAX pending
                    dragging.removeAttribute('data-ajax-pending');
                    dragging.classList.remove('dragging');
                    if (targetCard) {
                        targetCard.removeAttribute('data-ajax-pending');
                    }

                    // Pastikan card sudah kembali ke posisi asli
                    const originalParentId = dragging.getAttribute('data-original-parent');
                    const originalParent = document.querySelector(
                        `[data-mesin-id="${originalParentId}"]`);
                    if (originalParent && dragging.parentElement !== originalParent) {
                        const originalNextSibling = dragging._originalNextSibling;
                        if (originalNextSibling && originalNextSibling.parentElement ===
                            originalParent) {
                            originalParent.insertBefore(dragging, originalNextSibling);
                        } else {
                            originalParent.appendChild(dragging);
                        }
                    }

                    // Tampilkan notification error
                    let errorMsg = 'Gagal mengirim permintaan tukar posisi.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    } else if (xhr.status === 422) {
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                    }

                    const ToastError = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true,
                    });
                    ToastError.fire({
                        title: errorMsg
                    });
                },
                complete: function() {
                    // Reset tombol dan data
                    $('#btnConfirmSwapDragDrop').prop('disabled', false).html(
                        '<i class="fas fa-check mr-1"></i>Ya, Tukar Posisi');
                    $('#btnCancelSwapDragDrop').prop('disabled', false);
                    window.pendingSwapData = null;
                }
            });
        });

        // Handler cancel konfirmasi swap position (Drag & Drop)
        $(document).on('click', '#btnCancelSwapDragDrop', function() {
            const swapData = window.pendingSwapData;
            if (swapData && swapData.dragging) {
                swapData.dragging.classList.remove('dragging');
                const originalParentId = swapData.dragging.getAttribute('data-original-parent');
                const originalParent = document.querySelector(`[data-mesin-id="${originalParentId}"]`);
                if (originalParent && swapData.dragging.parentElement !== originalParent) {
                    const originalNextSibling = swapData.dragging._originalNextSibling;
                    if (originalNextSibling && originalNextSibling.parentElement === originalParent) {
                        originalParent.insertBefore(swapData.dragging, originalNextSibling);
                    } else {
                        originalParent.appendChild(swapData.dragging);
                    }
                }
            }
            window.pendingSwapData = null;
            $('#modalConfirmSwapDragDrop').modal('hide');
        });

        // Handler saat modal swap ditutup (untuk memastikan card dikembalikan jika modal ditutup dengan cara lain)
        $('#modalConfirmSwapDragDrop').on('hidden.bs.modal', function() {
            const swapData = window.pendingSwapData;
            if (swapData && swapData.dragging) {
                // Pastikan dragging class dihapus
                swapData.dragging.classList.remove('dragging');

                // Kembalikan card ke posisi asli jika belum dikembalikan
                const originalParentId = swapData.dragging.getAttribute('data-original-parent');
                const originalParent = document.querySelector(`[data-mesin-id="${originalParentId}"]`);
                if (originalParent && swapData.dragging.parentElement !== originalParent) {
                    const originalNextSibling = swapData.dragging._originalNextSibling;
                    if (originalNextSibling && originalNextSibling.parentElement === originalParent) {
                        originalParent.insertBefore(swapData.dragging, originalNextSibling);
                    } else {
                        originalParent.appendChild(swapData.dragging);
                    }
                }

                // Reset tombol
                $('#btnConfirmSwapDragDrop').prop('disabled', false).html(
                    '<i class="fas fa-check mr-1"></i>Ya, Tukar Posisi');
                $('#btnCancelSwapDragDrop').prop('disabled', false);
            }
            window.pendingSwapData = null;
        });

        // Handler saat modal ditutup (untuk memastikan card dikembalikan jika modal ditutup dengan cara apapun)
        // Event ini akan selalu dipanggil saat modal ditutup (cancel, X, ESC, klik luar, dll)
        $('#modalConfirmMoveDragDrop').on('hidden.bs.modal', function() {
            const moveData = window.pendingMoveData;
            if (moveData && moveData.dragging) {
                // Pastikan dragging class dihapus
                moveData.dragging.classList.remove('dragging');

                // Cari container mesin asal berdasarkan originalParentId
                const originalParentId = moveData.originalParentId;
                let originalParent = moveData.originalParent;

                // Pastikan originalParent masih valid, jika tidak cari ulang
                if (!originalParent || !document.contains(originalParent)) {
                    originalParent = document.querySelector(`[data-mesin-id="${originalParentId}"]`);
                }

                if (originalParent) {
                    // SELALU cek dan kembalikan card ke mesin asal, tidak peduli kondisinya saat ini
                    const currentMesinContainer = moveData.dragging.closest('[data-mesin-id]');
                    const currentMesinId = currentMesinContainer ? currentMesinContainer.getAttribute(
                        'data-mesin-id') : null;

                    // Jika card tidak di mesin yang benar, SELALU pindahkan ke mesin asal
                    if (currentMesinId !== originalParentId) {
                        // Cari proses-aktif-container di mesin asal
                        const prosesAktifContainer = originalParent.querySelector('.proses-aktif-container');
                        const targetContainer = prosesAktifContainer || originalParent;

                        // Gunakan originalNextSibling jika masih valid
                        const originalNextSibling = moveData.originalNextSibling;
                        if (originalNextSibling &&
                            originalNextSibling.parentElement === targetContainer &&
                            targetContainer.contains(originalNextSibling) &&
                            document.contains(originalNextSibling)) {
                            targetContainer.insertBefore(moveData.dragging, originalNextSibling);
                        } else {
                            // Jika next sibling tidak valid, append di akhir proses-aktif-container
                            targetContainer.appendChild(moveData.dragging);
                        }
                    } else {
                        // Card sudah di mesin yang benar, pastikan berada di proses-aktif-container
                        const prosesAktifContainer = originalParent.querySelector('.proses-aktif-container');
                        if (prosesAktifContainer && !prosesAktifContainer.contains(moveData.dragging)) {
                            prosesAktifContainer.appendChild(moveData.dragging);
                        }
                    }
                }

                // Reset tombol
                $('#btnConfirmMoveDragDrop').prop('disabled', false).html(
                    '<i class="fas fa-check mr-1"></i>Ya, Pindahkan');
                $('#btnCancelMoveDragDrop').prop('disabled', false);
            }

            // Hapus pendingMoveData setelah semua proses selesai
            window.pendingMoveData = null;
        });

        window.addEventListener('globalFullscreenToggle', function(e) {
            const isFullscreen = e.detail;
            const header = document.querySelector('.content-header');

            if (header) {
                header.style.display = isFullscreen ? 'none' : 'block';
            }
        });


        // Ambil data dropdown dari server dan inisialisasi select2 SETELAH data masuk
        $(document).ready(function() {
            // Ambil data dropdown dari server untuk Mesin
            fetch("{{ route('proses.create') }}")
                .then(res => res.json())
                .then(data => {
                    // Update global mesinsData jika ada data baru dari server
                    if (data.mesins && Array.isArray(data.mesins)) {
                        window.mesinsData = data.mesins;
                    }
                    // Urutkan mesin berdasarkan id terkecil
                    data.mesins.sort((a, b) => a.id - b.id);
                    // Isi dropdown mesin
                    const mesinSelect = document.getElementById('mesin_id');
                    mesinSelect.innerHTML = '<option value="">-- Pilih Mesin --</option>';
                    data.mesins.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.id;
                        opt.textContent = m.jenis_mesin;
                        mesinSelect.appendChild(opt);
                    });
                    
                    // Inisialisasi Select2 untuk Mesin
                    if ($.fn.select2) {
                        const $mesinSelect = $('#mesin_id');
                        if ($mesinSelect.hasClass('select2-hidden-accessible')) {
                            $mesinSelect.select2('destroy');
                        }
                        $mesinSelect.select2({
                            dropdownParent: $('#modalProses'),
                            placeholder: '-- Pilih Mesin --',
                            allowClear: false,
                            dropdownCssClass: 'select2-dropdown-modal',
                            width: '100%'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching dropdown data:', error);
                    // Fallback: populate mesin dari window.mesinsData
                    if (window.mesinsData && Array.isArray(window.mesinsData)) {
                        const mesinSelect = document.getElementById('mesin_id');
                        mesinSelect.innerHTML = '<option value="">-- Pilih Mesin --</option>';
                        window.mesinsData.forEach(m => {
                            const opt = document.createElement('option');
                            opt.value = m.id;
                            opt.textContent = m.jenis_mesin;
                            mesinSelect.appendChild(opt);
                        });
                        
                        // Inisialisasi Select2 untuk Mesin (fallback)
                        if ($.fn.select2) {
                            const $mesinSelect = $('#mesin_id');
                            if ($mesinSelect.hasClass('select2-hidden-accessible')) {
                                $mesinSelect.select2('destroy');
                            }
                            $mesinSelect.select2({
                                dropdownParent: $('#modalProses'),
                                placeholder: '-- Pilih Mesin --',
                                allowClear: false,
                                dropdownCssClass: 'select2-dropdown-modal',
                                width: '100%'
                            });
                        }
                    }
                });
        });

        // Inisialisasi tooltip Bootstrap (termasuk tooltip pada label Cycle Time)
        $(document).ready(function() {
            if (typeof $.fn.tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }
        });

        // Auto-format input Cycle Time menjadi HH:MM:SS (hanya dari sisi JavaScript)
        $(document).ready(function() {
            var $cycleInput = $('[name="cycle_time"]');

            // Saat user mengetik: hanya izinkan angka dan otomatis sisipkan ":"
            $cycleInput.on('input', function() {
                var val = this.value.replace(/\D/g, ''); // hanya digit

                // Batasi maksimal 6 digit (HHMMSS)
                if (val.length > 6) {
                    val = val.slice(0, 6);
                }

                var formatted = '';
                if (val.length <= 2) {
                    // H atau HH
                    formatted = val;
                } else if (val.length <= 4) {
                    // HHMM
                    formatted = val.slice(0, 2) + ':' + val.slice(2);
                } else {
                    // HHMMSS
                    formatted = val.slice(0, 2) + ':' + val.slice(2, 4) + ':' + val.slice(4);
                }

                this.value = formatted;
            });

            // Saat blur: paksa selalu jadi 6 digit -> HH:MM:SS (padding 0 di belakang jika kurang)
            $cycleInput.on('blur', function() {
                var val = this.value.replace(/\D/g, ''); // ambil digit saja
                if (!val) return;

                // Jika kurang dari 6 digit, tambahkan 0 di belakang (contoh: 12 -> 120000 -> 12:00:00)
                while (val.length < 6) {
                    val += '0';
                }
                val = val.slice(0, 6);

                var hh = val.slice(0, 2);
                var mm = val.slice(2, 4);
                var ss = val.slice(4, 6);

                this.value = hh + ':' + mm + ':' + ss;
            });
        });
    </script>
    {{-- Script tambahan --}}
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        // Fungsi helper untuk mengecek apakah ada pending approval FM
        function hasPendingApprovalFM(proses) {
            if (!proses || !proses.approvals || !Array.isArray(proses.approvals)) {
                return false;
            }
            return proses.approvals.some(function(approval) {
                return approval.status === 'pending' &&
                    approval.type === 'FM' && ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position']
                    .includes(approval.action);
            });
        }

        // Fungsi helper untuk mengecek apakah ada pending approval FM atau VP untuk Reproses (2 tahap approval)
        function hasPendingReprocessApproval(proses) {
            if (!proses || !proses.approvals || !Array.isArray(proses.approvals)) {
                return false;
            }
            if (proses.jenis !== 'Reproses') {
                return false;
            }
            return proses.approvals.some(function(approval) {
                return approval.status === 'pending' &&
                    approval.action === 'create_reprocess' &&
                    (approval.type === 'FM' || approval.type === 'VP');
            });
        }

        // Fungsi untuk mendapatkan informasi pending approval
        function getPendingApprovalInfo(proses) {
            if (!proses || !proses.approvals || !Array.isArray(proses.approvals)) {
                return null;
            }
            const pendingApproval = proses.approvals.find(function(approval) {
                return approval.status === 'pending' &&
                    approval.type === 'FM' && ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position']
                    .includes(approval.action);
            });
            if (!pendingApproval) return null;

            const actionLabels = {
                'edit_cycle_time': 'perubahan cycle time',
                'delete_proses': 'penghapusan proses',
                'move_machine': 'pemindahan mesin',
                'swap_position': 'tukar posisi'
            };
            return {
                action: pendingApproval.action,
                label: actionLabels[pendingApproval.action] || 'perubahan'
            };
        }

        // Fungsi untuk mendapatkan semua pending approval dengan detail
        function getAllPendingApprovals(proses) {
            if (!proses || !proses.approvals || !Array.isArray(proses.approvals)) {
                return [];
            }
            const pendingApprovals = proses.approvals.filter(function(approval) {
                return approval.status === 'pending';
            });

            const actionLabels = {
                'edit_cycle_time': 'Edit Cycle Time',
                'delete_proses': 'Hapus Proses',
                'move_machine': 'Pindah Mesin',
                'swap_position': 'Tukar Posisi',
                'create_reprocess': 'Buat Reproses'
            };

            const typeLabels = {
                'FM': 'Factory Manager (FM)',
                'VP': 'Vice President (VP)'
            };

            return pendingApprovals.map(function(approval) {
                return {
                    id: approval.id,
                    type: approval.type,
                    typeLabel: typeLabels[approval.type] || approval.type,
                    action: approval.action,
                    actionLabel: actionLabels[approval.action] || approval.action,
                    requested_by: approval.requested_by,
                    created_at: approval.created_at
                };
            });
        }

        // Helper function untuk mendapatkan DetailProses pertama dari proses
        function getFirstDetailProses(proses) {
            if (!proses) return null;
            // Cek apakah proses memiliki details array
            if (proses.details && Array.isArray(proses.details) && proses.details.length > 0) {
                return proses.details[0];
            }
            // Fallback: cek apakah ada detail langsung (untuk backward compatibility)
            return null;
        }

        // Helper function untuk mendapatkan no_op dari DetailProses
        function getNoOpFromProses(proses) {
            const firstDetail = getFirstDetailProses(proses);
            if (firstDetail && firstDetail.no_op) {
                let noOp = firstDetail.no_op;
                // Jika ada multiple OP, tambahkan indikator
                if (proses.details && proses.details.length > 1) {
                    noOp += ' (+' + (proses.details.length - 1) + ')';
                }
                return noOp;
            }
            // Fallback ke proses.no_op untuk backward compatibility
            return proses.no_op || 'MAINTENANCE';
        }

        function getDetailProsesById(proses, detailId) {
            if (!proses || !detailId) return null;
            if (proses.details && Array.isArray(proses.details)) {
                return proses.details.find(d => String(d.id) === String(detailId)) || null;
            }
            return null;
        }

        // Double click card proses untuk detail
        // Jika double click pada salah satu baris OP (multiple), modal akan menampilkan detail OP tersebut.
        $(document).on('dblclick', '.status-card', function(e) {
            const proses = $(this).data('proses');
            if (!proses) return;
            
            const clickedDetailId = $(e.target).closest('.op-row').data('detail-id') || null;
            const selectedDetail = getDetailProsesById(proses, clickedDetailId) || getFirstDetailProses(proses);
            const selectedDetailId = selectedDetail && selectedDetail.id ? selectedDetail.id : null;

            // Cek apakah card berwarna kuning (menunggu approval)
            const bgColor = $(this).data('bg-color');
            const isYellowCard = bgColor === '#ffeb3b' || bgColor === 'rgb(255, 235, 59)' || $(this).css(
                'background-color') === 'rgb(255, 235, 59)';

            // Cek apakah ada pending approval (FM untuk edit/delete/move atau FM/VP untuk reproses)
            const hasPending = hasPendingApprovalFM(proses);
            const hasPendingReprocess = hasPendingReprocessApproval(proses);
            const hasAnyPending = hasPending || hasPendingReprocess || isYellowCard;

            // Jika ada pending approval atau card berwarna kuning, tampilkan modal khusus pending approval
            if (hasAnyPending) {
                let pendingApprovals = getAllPendingApprovals(proses);

                // Jika card kuning tapi tidak ada approval langsung, kemungkinan terkena swap_position dari proses lain
                // Tampilkan pesan generic untuk kasus ini
                if (isYellowCard && pendingApprovals.length === 0) {
                    pendingApprovals = [{
                        id: null,
                        type: 'FM',
                        typeLabel: 'Factory Manager (FM)',
                        action: 'swap_position',
                        actionLabel: 'Tukar Posisi',
                        requested_by: null,
                        created_at: null
                    }];
                }

                // Format detail proses tanpa barcode
                const hiddenFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'mesin_id'];
                const maintenanceFields = [
                    'no_op', 'item_op', 'kode_material', 'konstruksi', 'no_partai',
                    'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll'
                ];

                function formatDetikToHMS(val) {
                    if (val === null || val === undefined || isNaN(val)) return '-';
                    val = parseInt(val);
                    const jam = Math.floor(val / 3600);
                    const menit = Math.floor((val % 3600) / 60);
                    const detik = val % 60;
                    return `${jam.toString().padStart(2, '0')}:${menit.toString().padStart(2, '0')}:${detik.toString().padStart(2, '0')}`;
                }

                let jenisMesin = '-';
                try {
                    const mesinSelect = document.getElementById('mesin_id');
                    if (mesinSelect && proses.mesin_id) {
                        const opt = mesinSelect.querySelector(`option[value="${proses.mesin_id}"]`);
                        if (opt) jenisMesin = opt.textContent;
                    }
                } catch {}

                // Ambil DetailProses yang di-double click (fallback ke pertama)
                const firstDetail = selectedDetail;
                
                // Gabungkan data dari Proses dan DetailProses
                const prosesData = {...proses};
                if (firstDetail) {
                    // Override field yang ada di DetailProses dengan data dari DetailProses
                    Object.keys(firstDetail).forEach(key => {
                        if (maintenanceFields.includes(key) || ['no_op', 'no_partai', 'item_op', 'kode_material', 'konstruksi', 'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll'].includes(key)) {
                            prosesData[key] = firstDetail[key];
                        }
                    });
                }
                
                const entries = Object.entries(prosesData)
                    .filter(([key]) => !hiddenFields.includes(key) && key !== 'barcode_kains' && key !==
                        'barcode_las' && key !== 'barcode_auxs' && key !== 'mesin' && key !== 'approvals' && key !== 'details')
                    .filter(([key]) => !(proses.jenis === 'Maintenance' && maintenanceFields.includes(key)))
                    .map(([key, val]) => {
                        if (key === 'hfeel') return ['HAND FEEL', val];
                        if (key === 'matdok') return ['MATERIAL DOKUMEN', val];
                        if (key === 'cycle_time' || key === 'cycle_time_actual') return [key.replace(/_/g, ' ')
                            .toUpperCase(), formatDetikToHMS(val)
                        ];
                        return [key.replace(/_/g, ' ').toUpperCase(), val];
                    });
                entries.unshift(['JENIS MESIN', jenisMesin]);

                let detailHtml = '';
                for (let i = 0; i < entries.length; i += 2) {
                    detailHtml += '<tr>';
                    detailHtml += `<th style="width:180px;">${entries[i][0]}</th><td>${entries[i][1] ?? '-'}</td>`;
                    if (entries[i + 1]) {
                        detailHtml +=
                            `<th style="width:180px;">${entries[i+1][0]}</th><td>${entries[i+1][1] ?? '-'}</td>`;
                    } else {
                        detailHtml += '<th></th><td></td>';
                    }
                    detailHtml += '</tr>';
                }
                $('#detail-proses-pending-body').html(detailHtml);

                // Format informasi pending approval
                let approvalHtml = '<div class="alert alert-warning mb-3">';
                approvalHtml +=
                    '<h6 class="font-weight-bold mb-2"><i class="fas fa-clock mr-2"></i>Status Approval Pending</h6>';
                approvalHtml += '<p class="mb-2">Proses ini sedang menunggu persetujuan dari:</p>';
                approvalHtml += '<ul class="mb-0 pl-3">';
                pendingApprovals.forEach(function(approval) {
                    approvalHtml +=
                        `<li><strong>${approval.typeLabel}</strong> - ${approval.actionLabel}</li>`;
                });
                approvalHtml += '</ul>';
                approvalHtml += '</div>';
                $('#pending-approval-info').html(approvalHtml);

                // Simpan detail_proses_id terpilih untuk scan/refresh barcode
                $('#modalDetailProsesPending').data('detailProsesId', selectedDetailId);
                $('#modalDetailProsesPending').modal('show');
                return;
            }

            // Jika tidak ada pending approval, tampilkan modal normal
            // Simpan proses aktif ke modal detail untuk kebutuhan edit/delete
            $('#modalDetailProses').data('proses', proses);
            $('#modalDetailProses').data('detailProsesId', selectedDetailId);

            const pendingInfo = getPendingApprovalInfo(proses);

            // Cek apakah proses sudah dimulai (mulai tidak null)
            const isStarted = proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '';

            // Disable/enable tombol action
            const $btnEdit = $('.btn-edit-proses');
            const $btnMove = $('.btn-move-proses');
            const $btnDelete = $('.btn-delete-proses');

            // Disable jika role restricted (FM/VP) ATAU ada pending approval ATAU proses sudah dimulai
            const isRoleRestricted = window.canEditProses === false || window.canDeleteProses === false || window
                .canMoveProses === false;
            if (isRoleRestricted || hasPending || hasPendingReprocess || isStarted) {
                // Disable tombol
                $btnEdit.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
                $btnMove.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
                $btnDelete.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');

                // Tentukan pesan tooltip berdasarkan kondisi
                let tooltipText = '';
                if (isRoleRestricted) {
                    tooltipText = 'Anda tidak memiliki izin untuk melakukan aksi ini. (Role: ' + (window.userRole ||
                        'Unknown') + ')';
                } else if (isStarted) {
                    tooltipText = 'Tidak dapat melakukan aksi. Proses sudah dimulai.';
                } else if (hasPendingReprocess) {
                    tooltipText = 'Tidak dapat melakukan aksi. Proses Reproses masih menunggu persetujuan VP.';
                } else if (hasPending) {
                    tooltipText = pendingInfo ?
                        `Tidak dapat melakukan aksi. Masih ada permintaan ${pendingInfo.label} yang menunggu persetujuan FM.` :
                        'Tidak dapat melakukan aksi. Masih ada permintaan yang menunggu persetujuan FM.';
                }

                $btnEdit.attr('title', tooltipText).attr('data-toggle', 'tooltip');
                $btnMove.attr('title', tooltipText).attr('data-toggle', 'tooltip');
                $btnDelete.attr('title', tooltipText).attr('data-toggle', 'tooltip');

                // Re-initialize tooltip jika sudah ada
                if (typeof $.fn.tooltip === 'function') {
                    $btnEdit.tooltip('dispose').tooltip();
                    $btnMove.tooltip('dispose').tooltip();
                    $btnDelete.tooltip('dispose').tooltip();
                }
            } else {
                // Enable tombol
                $btnEdit.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                $btnMove.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                $btnDelete.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');

                // Hapus tooltip
                $btnEdit.removeAttr('title').removeAttr('data-toggle');
                $btnMove.removeAttr('title').removeAttr('data-toggle');
                $btnDelete.removeAttr('title').removeAttr('data-toggle');

                // Dispose tooltip
                if (typeof $.fn.tooltip === 'function') {
                    $btnEdit.tooltip('dispose');
                    $btnMove.tooltip('dispose');
                    $btnDelete.tooltip('dispose');
                }
            }
            const hiddenFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'mesin_id'];
            // Daftar field yang harus disembunyikan jika Maintenance
            const maintenanceFields = [
                'no_op', 'item_op', 'kode_material', 'konstruksi', 'no_partai',
                'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll'
            ];

            function formatDetikToHMS(val) {
                if (val === null || val === undefined || isNaN(val)) return '-';
                val = parseInt(val);
                const jam = Math.floor(val / 3600);
                const menit = Math.floor((val % 3600) / 60);
                const detik = val % 60;
                return `${jam.toString().padStart(2, '0')}:${menit.toString().padStart(2, '0')}:${detik.toString().padStart(2, '0')}`;
            }
            let jenisMesin = '-';
            try {
                const mesinSelect = document.getElementById('mesin_id');
                if (mesinSelect && proses.mesin_id) {
                    const opt = mesinSelect.querySelector(`option[value="${proses.mesin_id}"]`);
                    if (opt) jenisMesin = opt.textContent;
                }
            } catch {}
            // Ambil DetailProses yang dipilih (fallback ke pertama)
            const firstDetail = selectedDetail;
            
            // Gabungkan data dari Proses dan DetailProses
            const prosesData = {...proses};
            if (firstDetail) {
                // Override field yang ada di DetailProses dengan data dari DetailProses
                Object.keys(firstDetail).forEach(key => {
                    if (maintenanceFields.includes(key) || ['no_op', 'no_partai', 'item_op', 'kode_material', 'konstruksi', 'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll'].includes(key)) {
                        prosesData[key] = firstDetail[key];
                    }
                });
            }
            
            // Hapus BARCODE KAINS, APPROVALS, DETAILS dari detail proses
            const entries = Object.entries(prosesData)
                .filter(([key]) => !hiddenFields.includes(key) && key !== 'barcode_kains' && key !==
                    'barcode_las' && key !== 'barcode_auxs' && key !== 'mesin' && key !== 'approvals' && key !== 'details')
                .filter(([key]) => !(proses.jenis === 'Maintenance' && maintenanceFields.includes(key)))
                .map(([key, val]) => {
                    if (key === 'hfeel') return ['HAND FEEL', val];
                    if (key === 'matdok') return ['MATERIAL DOKUMEN', val];
                    if (key === 'cycle_time' || key === 'cycle_time_actual') return [key.replace(/_/g, ' ')
                        .toUpperCase(), formatDetikToHMS(val)
                    ];
                    return [key.replace(/_/g, ' ').toUpperCase(), val];
                });
            entries.unshift(['JENIS MESIN', jenisMesin]);
            let html = '';
            for (let i = 0; i < entries.length; i += 2) {
                html += '<tr>';
                html += `<th style="width:180px;">${entries[i][0]}</th><td>${entries[i][1] ?? '-'}</td>`;
                if (entries[i + 1]) {
                    html += `<th style="width:180px;">${entries[i+1][0]}</th><td>${entries[i+1][1] ?? '-'}</td>`;
                } else {
                    html += '<th></th><td></td>';
                }
                html += '</tr>';
            }
            // Tampilkan barcode hanya jika proses.jenis !== 'Maintenance'
            if (proses.jenis !== 'Maintenance') {
                const showScanBtn = window.canScanBarcode !== false;
                html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode Kain';
                if (showScanBtn) {
                    html +=
                        ' <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_kain" data-id="' +
                        proses.id + '" data-detail-id="' + (selectedDetailId || '') + '" style="float:right;"><i class="fas fa-barcode"></i> Scan</button>';
                }
                html += '</th></tr>';
                html += '<tr><td colspan="4" id="barcode-kain-list">Loading...</td></tr>';
                html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode LA';
                if (showScanBtn) {
                    html +=
                        ' <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_la" data-id="' +
                        proses.id + '" data-detail-id="' + (selectedDetailId || '') + '" style="float:right;"><i class="fas fa-barcode"></i> Scan</button>';
                }
                html += '</th></tr>';
                html += '<tr><td colspan="4" id="barcode-la-list">Loading...</td></tr>';
                html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode AUX';
                if (showScanBtn) {
                    html +=
                        ' <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_aux" data-id="' +
                        proses.id + '" data-detail-id="' + (selectedDetailId || '') + '" style="float:right;"><i class="fas fa-barcode"></i> Scan</button>';
                }
                html += '</th></tr>';
                html += '<tr><td colspan="4" id="barcode-aux-list">Loading...</td></tr>';
            }
            $('#detail-proses-body').html(html);
            // Ambil barcode dari relasi dan render di modal detail proses hanya jika bukan Maintenance
            if (proses.jenis !== 'Maintenance') {
                const barcodesUrl = '/proses/' + proses.id + '/barcodes' + (selectedDetailId ? ('?detail_proses_id=' + encodeURIComponent(selectedDetailId)) : '');
                $.ajax({
                    url: barcodesUrl,
                    method: 'GET',
                    success: function(data) {
                        // Helper untuk update warna blok G, D, A di card utama setelah perubahan barcode
                        function updateGDAIndicators(prosesId, detailId, hasKain, hasLa, hasAux) {
                            let $targets = $(`.status-card[data-proses-id="${prosesId}"] .op-row[data-detail-id="${detailId}"]`);
                            if (!$targets.length) {
                                $targets = $(`.status-card[data-proses-id="${prosesId}"]`);
                            }
                            const $cards = $targets;
                            if (!$cards.length) return;

                            $cards.each(function() {
                                const $card = $(this);
                                const prosesData = $card.data('proses');
                                if (!prosesData || prosesData.jenis === 'Maintenance') {
                                    return; // Tidak ada G/D/A untuk Maintenance
                                }

                                // Update warna blok berdasarkan status barcode
                                function setBlockColor(blockType, ok) {
                                    const $block = $card.find(
                                        `.gda-block[data-block-type="${blockType}"]`);
                                    if (!$block.length) return;

                                    const blockBg = ok ? '#d4f8e8' : '#ffb3b3';
                                    const blockBorder = ok ? '#43a047' : '#c62828';
                                    $block.css({
                                        background: blockBg,
                                        borderColor: blockBorder
                                    });
                                }

                                setBlockColor('G', !!hasKain);
                                setBlockColor('D', !!hasLa);
                                setBlockColor('A', !!hasAux);
                            });
                        }

                        function renderBarcodeGrid(barcodes, barcodeType, prosesId) {
                            // Filter barcode yang belum cancel
                            const activeBarcodes = (barcodes || []).filter(bk => !bk.cancel);
                            if (!activeBarcodes.length) {
                                return '<span style="color:#888;">Belum ada barcode.</span>';
                            }
                            // Cek apakah user bisa cancel barcode
                            const canCancel = window.canCancelBarcode !== false;
                            let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                            activeBarcodes.forEach(function(bk, idx) {
                                // Hanya tampilkan button cancel jika user memiliki akses
                                const cancelButton = canCancel ?
                                    `<span style='position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;' class='cancel-barcode-btn' data-type='${barcodeType}' data-proses='${prosesId}' data-id='${bk.id}' data-matdok='${bk.matdok}' title='Cancel barcode'>&times;</span>` :
                                    '';
                                html += `<div style="position:relative;flex:1 0 30%;max-width:32%;background:#f3f3f3;border-radius:6px;padding:6px 4px;margin-bottom:6px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">
                                    ${cancelButton}
                                    ${bk.barcode} ${(bk.matdok ? '<br><span style=\'font-size:11px;color:#888;\'>' + bk.matdok + '</span>' : '')}
                                </div>`;
                            });
                            html += '</div>';
                            return html;
                        }
                        // Render ulang list barcode di modal
                        $('#barcode-kain-list').html(renderBarcodeGrid(data.barcode_kain, 'kain', proses
                            .id));
                        $('#barcode-la-list').html(renderBarcodeGrid(data.barcode_la, 'la', proses.id));
                        $('#barcode-aux-list').html(renderBarcodeGrid(data.barcode_aux, 'aux', proses
                            .id));

                        // Hitung status barcode aktif per jenis untuk update G/D/A di card utama
                        const hasKainActive = (data.barcode_kain || []).some(bk => !bk.cancel);
                        const hasLaActive = (data.barcode_la || []).some(bk => !bk.cancel);
                        const hasAuxActive = (data.barcode_aux || []).some(bk => !bk.cancel);
                        updateGDAIndicators(proses.id, selectedDetailId, hasKainActive, hasLaActive, hasAuxActive);
                    },
                    error: function() {
                        $('#barcode-kain-list').html(
                            '<span style="color:#888;">Belum ada barcode kain.</span>');
                        $('#barcode-la-list').html(
                            '<span style="color:#888;">Belum ada barcode LA.</span>');
                        $('#barcode-aux-list').html(
                            '<span style="color:#888;">Belum ada barcode AUX.</span>');
                    }
                });
            }

            // Initialize tooltip setelah modal ditampilkan
            $('#modalDetailProses').on('shown.bs.modal', function() {
                if (typeof $.fn.tooltip === 'function') {
                    $('.btn-edit-proses, .btn-move-proses, .btn-delete-proses').tooltip();
                }
            });

            $('#modalDetailProses').modal('show');
        });

        // Reset tombol saat modal ditutup
        $('#modalDetailProses').on('hidden.bs.modal', function() {
            const $btnEdit = $('.btn-edit-proses');
            const $btnMove = $('.btn-move-proses');
            const $btnDelete = $('.btn-delete-proses');

            // Reset semua tombol ke state normal
            $btnEdit.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
            $btnMove.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
            $btnDelete.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');

            // Hapus tooltip
            $btnEdit.removeAttr('title').removeAttr('data-toggle');
            $btnMove.removeAttr('title').removeAttr('data-toggle');
            $btnDelete.removeAttr('title').removeAttr('data-toggle');

            // Dispose tooltip
            if (typeof $.fn.tooltip === 'function') {
                $btnEdit.tooltip('dispose');
                $btnMove.tooltip('dispose');
                $btnDelete.tooltip('dispose');
            }
        });

        // Handler tombol Edit Proses (hanya ubah cycle time -> kirim ke approval FM)
        $(document).on('click', '.btn-edit-proses', function(e) {
            e.preventDefault();
            // Cek apakah tombol disabled
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            // Validasi: cek apakah proses sudah dimulai
            if (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '') {
                const ToastError = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
                ToastError.fire({
                    title: 'Tidak dapat mengubah cycle time. Proses sudah dimulai.'
                });
                return false;
            }

            // Validasi: cek pending approval VP untuk Reproses
            const hasPendingReprocess = hasPendingReprocessApproval(proses);
            if (hasPendingReprocess) {
                const ToastError = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
                ToastError.fire({
                    title: 'Tidak dapat mengubah cycle time. Proses Reproses masih menunggu persetujuan VP.'
                });
                return false;
            }

            const id = proses.id;
            const updateUrl = "{{ url('proses') }}/" + id + "/update";

            // Format cycle_time detik ke HH:MM:SS
            let val = proses.cycle_time || 0;
            val = parseInt(val || 0, 10);
            const jam = Math.floor(val / 3600).toString().padStart(2, '0');
            const menit = Math.floor((val % 3600) / 60).toString().padStart(2, '0');
            const detik = (val % 60).toString().padStart(2, '0');
            const hms = `${jam}:${menit}:${detik}`;

            // Ambil DetailProses pertama untuk field yang ada di DetailProses
            const firstDetail = getFirstDetailProses(proses);
            
            // Isi data ke Form Edit sebelum modal dibuka
            $('#formEditProses').attr('action', updateUrl);
            $('#editProsesId').val(id);
            $('#editCycleTime').val(hms);
            $('#editJenis').val(proses.jenis || '');
            $('#editNoOp').val(firstDetail ? (firstDetail.no_op || '') : (proses.no_op || ''));
            $('#editNoPartai').val(firstDetail ? (firstDetail.no_partai || '') : (proses.no_partai || ''));
            $('#editItemOp').val(firstDetail ? (firstDetail.item_op || '') : (proses.item_op || ''));
            $('#editKodeMaterial').val(firstDetail ? (firstDetail.kode_material || '') : (proses.kode_material || ''));
            $('#editKonstruksi').val(firstDetail ? (firstDetail.konstruksi || '') : (proses.konstruksi || ''));
            $('#editGramasi').val(firstDetail ? (firstDetail.gramasi || '') : (proses.gramasi || ''));
            $('#editLebar').val(firstDetail ? (firstDetail.lebar || '') : (proses.lebar || ''));
            $('#editHfeel').val(firstDetail ? (firstDetail.hfeel || '') : (proses.hfeel || ''));
            $('#editWarna').val(firstDetail ? (firstDetail.warna || '') : (proses.warna || ''));
            $('#editKodeWarna').val(firstDetail ? (firstDetail.kode_warna || '') : (proses.kode_warna || ''));
            $('#editKategoriWarna').val(firstDetail ? (firstDetail.kategori_warna || '') : (proses.kategori_warna || ''));
            $('#editQty').val(firstDetail ? (firstDetail.qty || '') : (proses.qty || ''));
            $('#editRoll').val(firstDetail ? (firstDetail.roll || '') : (proses.roll || ''));

            // Tutup modal detail, lalu buka modal edit SETELAH detail tertutup sempurna
            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function() {
                $('#modalEditProses').modal('show');
                // Tambahkan class modal-open secara manual jika scroll masih hilang
                $('body').addClass('modal-open');
            });
        });

        // Handler tombol Pindah Mesin (buka modal pindah mesin)
        $(document).on('click', '.btn-move-proses', function(e) {
            e.preventDefault();
            // Cek apakah tombol disabled
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            // Validasi: cek apakah proses sudah dimulai
            if (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '') {
                const ToastError = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
                ToastError.fire({
                    title: 'Tidak dapat memindahkan proses. Proses sudah dimulai.'
                });
                return false;
            }

            // Validasi: cek pending approval VP untuk Reproses
            const hasPendingReprocess = hasPendingReprocessApproval(proses);
            if (hasPendingReprocess) {
                const ToastError = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
                ToastError.fire({
                    title: 'Tidak dapat memindahkan proses. Proses Reproses masih menunggu persetujuan VP.'
                });
                return false;
            }

            const id = proses.id;
            const moveUrl = "{{ url('proses') }}/" + id + "/move";

            // Isi data ke Form Move sebelum modal dibuka
            $('#formMoveProses').attr('action', moveUrl);
            $('#moveProsesId').val(id);

            // Ambil data mesin dari dropdown mesin yang sudah ada (di form tambah proses atau dari server)
            const currentMesinId = proses.mesin_id ? parseInt(proses.mesin_id) : null;

            // Populate dropdown mesin dengan mesin yang berbeda dari mesin saat ini
            $('#moveMesinId').empty().append('<option value="" disabled selected>-- Pilih Mesin --</option>');

            // Gunakan data mesin dari variabel global (atau dari server jika belum ada)
            if (window.mesinsData && Array.isArray(window.mesinsData) && window.mesinsData.length > 0) {
                window.mesinsData.forEach(function(mesin) {
                    const mesinId = parseInt(mesin.id || mesin.mesin_id);
                    // Skip mesin saat ini
                    if (mesinId !== currentMesinId) {
                        const mesinNama = mesin.jenis_mesin || mesin.nama || mesin.text || 'Mesin ' +
                            mesinId;
                        $('#moveMesinId').append(`<option value="${mesinId}">${mesinNama}</option>`);
                    }
                });
            } else {
                // Fallback: ambil dari server via AJAX jika data belum ada
                fetch("{{ route('proses.create') }}")
                    .then(res => res.json())
                    .then(data => {
                        if (data.mesins && Array.isArray(data.mesins)) {
                            window.mesinsData = data.mesins;
                            data.mesins.forEach(function(mesin) {
                                const mesinId = parseInt(mesin.id);
                                if (mesinId !== currentMesinId) {
                                    $('#moveMesinId').append(
                                        `<option value="${mesinId}">${mesin.jenis_mesin}</option>`);
                                }
                            });
                        }
                    })
                    .catch(function(error) {
                        console.error('Error loading mesins:', error);
                    });
            }

            $('#moveMesinId').val('').trigger('change');

            // Ambil DetailProses pertama untuk mendapatkan no_op
            const firstDetail = getFirstDetailProses(proses);
            const noOp = firstDetail ? (firstDetail.no_op || '') : (proses.no_op || '');
            
            let infoText = 'Pilih mesin tujuan untuk memindahkan proses ini.';
            if (noOp) {
                infoText += `<br><br><strong>No OP:</strong> ${noOp}`;
            }
            $('#moveProsesInfo').html(infoText);

            // Tutup modal detail, lalu buka modal move SETELAH detail tertutup sempurna
            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function() {
                $('#modalMoveProses').modal('show');
                // Tambahkan class modal-open secara manual jika scroll masih hilang
                $('body').addClass('modal-open');
            });
        });

        // Handler submit form pindah mesin
        $('#formMoveProses').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const url = form.attr('action');
            const mesinId = $('#moveMesinId').val();
            const prosesId = $('#moveProsesId').val();

            if (!mesinId) {
                // Gunakan SweetAlert untuk pesan error
                Swal.fire({
                    icon: 'error',
                    title: 'Mesin belum dipilih',
                    text: 'Silakan pilih mesin tujuan terlebih dahulu.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                data: {
                    mesin_id: mesinId
                },
                success: function(response) {
                    $('#modalMoveProses').modal('hide');

                    // Tampilkan notification success jika ada
                    if (response && response.status === 'success' && response.message) {
                        const ToastSuccess = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                        });
                        ToastSuccess.fire({
                            title: response.message
                        });
                    }

                    // Redirect untuk refresh halaman setelah delay kecil untuk menampilkan notification
                    setTimeout(function() {
                        if (response && response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 500);
                },
                error: function(xhr) {
                    let errorMsg = 'Gagal mengirim permintaan pindah mesin.';

                    // Cek apakah ada pesan error dari response JSON
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    } else if (xhr.status === 422) {
                        // Validation error
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                    }

                    // Tampilkan notification error menggunakan SweetAlert
                    const ToastError = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true,
                    });
                    ToastError.fire({
                        title: errorMsg
                    });
                }
            });
        });

        // Handler tombol Hapus Proses (kirim permintaan delete ke approval FM)
        $(document).on('click', '.btn-delete-proses', function(e) {
            e.preventDefault();
            // Cek apakah tombol disabled
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            // Validasi: cek apakah proses sudah dimulai
            if (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '') {
                const ToastError = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
                ToastError.fire({
                    title: 'Tidak dapat menghapus proses. Proses sudah dimulai.'
                });
                return false;
            }

            // Validasi: cek pending approval VP untuk Reproses
            const hasPendingReprocess = hasPendingReprocessApproval(proses);
            if (hasPendingReprocess) {
                const ToastError = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
                ToastError.fire({
                    title: 'Tidak dapat menghapus proses. Proses Reproses masih menunggu persetujuan VP.'
                });
                return false;
            }

            const id = proses.id;
            const deleteUrl = "{{ url('proses') }}/" + id + "/delete";

            // Isi data ke Form Delete sebelum modal dibuka
            $('#formDeleteProses').attr('action', deleteUrl);
            $('#deleteProsesId').val(id);

            // Ambil DetailProses pertama untuk mendapatkan no_op dan no_partai
            const firstDetail = getFirstDetailProses(proses);
            const noOp = firstDetail ? (firstDetail.no_op || '') : (proses.no_op || '');
            const noPartai = firstDetail ? (firstDetail.no_partai || '') : (proses.no_partai || '');
            
            let infoText = 'Apakah Anda yakin ingin mengajukan penghapusan proses ini?';
            if (noOp || noPartai) {
                infoText += `<br><br><strong>No OP:</strong> ${noOp || '-'}<br>` +
                    `<strong>No Partai:</strong> ${noPartai || '-'}`;
            }
            $('#deleteProsesInfo').html(infoText);

            // Tutup modal detail, lalu buka modal delete SETELAH detail tertutup sempurna
            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function() {
                $('#modalDeleteProses').modal('show');
                // Tambahkan class modal-open secara manual jika scroll masih hilang
                $('body').addClass('modal-open');
            });
        });

        // Modal scan barcode (HTML, append ke body jika belum ada)
        if (!document.getElementById('modalScanBarcode')) {
            $(document.body).append(`
            <div class="modal fade" id="modalScanBarcode" tabindex="-1" aria-labelledby="modalScanBarcodeLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
                    <div class="modal-content shadow-lg border-0 rounded-3">
                        <form id="formScanBarcode" method="POST" action="">
                            @csrf
                            <input type="hidden" name="barcode" id="inputBarcodeValue">
                            <input type="hidden" name="detail_proses_id" id="inputDetailProsesId">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold" id="modalScanBarcodeLabel">Scan Barcode</h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body py-3 px-4 text-center">
                                <div id="barcode-scanner-container" style="width:100%;min-height:320px;display:flex;align-items:center;justify-content:center;"></div>
                            </div>
                            <div class="modal-footer d-flex justify-content-end px-4">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            `);
        }

        // Handler klik tombol scan barcode (hanya set data, scanner diinisialisasi saat modal tampil)
        $(document).on('click', '.scan-barcode-btn', function() {
            const barcodeType = $(this).data('barcode');
            const prosesId = $(this).data('id');
            const detailId = $(this).data('detail-id') || $('#modalDetailProses').data('detailProsesId') || '';
            let actionUrl = '';
            if (barcodeType === 'barcode_kain') {
                actionUrl = `/proses/${prosesId}/barcode/kain`;
            } else if (barcodeType === 'barcode_la') {
                actionUrl = `/proses/${prosesId}/barcode/la`;
            } else if (barcodeType === 'barcode_aux') {
                actionUrl = `/proses/${prosesId}/barcode/aux`;
            }
            $('#formScanBarcode').attr('action', actionUrl);
            $('#inputBarcodeValue').val('');
            $('#inputDetailProsesId').val(detailId);
            $('#modalScanBarcode').modal('show');
        });

        // Inisialisasi scanner hanya saat modal benar-benar tampil
        $('#modalScanBarcode').on('shown.bs.modal', function() {
            setTimeout(function() {
                // Destroy instance lama jika ada
                if (window.html5QrcodeScanner) {
                    try {
                        window.html5QrcodeScanner.stop().then(() => {
                            window.html5QrcodeScanner.clear();
                            window.html5QrcodeScanner = null;
                            $('#barcode-scanner-container').html('');
                            startScanner();
                        }).catch(() => {
                            window.html5QrcodeScanner = null;
                            $('#barcode-scanner-container').html('');
                            startScanner();
                        });
                        return;
                    } catch (e) {
                        window.html5QrcodeScanner = null;
                        $('#barcode-scanner-container').html('');
                    }
                }
                startScanner();

                function startScanner() {
                    $('#barcode-scanner-container').html(
                        '<div id="reader" style="width:100%;max-width:400px;margin:auto;"></div>');
                    window.html5QrcodeScanner = new Html5Qrcode("reader");

                    // Buat Audio object untuk beep sound
                    const beepSound = new Audio("{{ asset('sound/beep.mp3') }}");

                    window.html5QrcodeScanner.start({
                            facingMode: "environment"
                        }, {
                            fps: 10,
                            qrbox: 250,
                            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE,
                                Html5QrcodeSupportedFormats.CODE_128
                            ]
                        },
                        (decodedText, decodedResult) => {
                            // Mainkan suara beep ketika barcode berhasil di-scan
                            beepSound.play().catch(e => {
                                console.log("Tidak dapat memainkan suara beep:", e);
                            });

                            // Setelah scan, isi input hidden dan submit form
                            $('#inputBarcodeValue').val(decodedText);
                            // Submit form POST ke endpoint barcode terkait
                            $('#formScanBarcode').submit();
                            window.html5QrcodeScanner.stop().catch(() => {});
                        },
                        (errorMessage) => {}
                    ).catch(err => {
                        $('#barcode-scanner-container').html(
                            '<span style="color:#fff;">Tidak dapat mengakses kamera: ' + err +
                            '</span>');
                    });
                }
            }, 200);
        });
        // Stop scanner saat modal ditutup
        $('#modalScanBarcode').on('hidden.bs.modal', function() {
            if (window.html5QrcodeScanner) {
                try {
                    window.html5QrcodeScanner.stop().then(() => {
                        window.html5QrcodeScanner.clear();
                        window.html5QrcodeScanner = null;
                        $('#barcode-scanner-container').html('');
                    }).catch(() => {
                        window.html5QrcodeScanner = null;
                        $('#barcode-scanner-container').html('');
                    });
                } catch (e) {
                    window.html5QrcodeScanner = null;
                    $('#barcode-scanner-container').html('');
                }
            } else {
                $('#barcode-scanner-container').html('');
            }
        });

        // Inject data proses ke card
        $(document).ready(function() {
            $('.status-card').each(function() {
                const proses = $(this).data('proses');
                if (!proses) {
                    // Ambil data dari atribut blade
                    const prosesData = $(this).attr('data-proses-json');
                    if (prosesData) {
                        try {
                            $(this).data('proses', JSON.parse(prosesData));
                        } catch {}
                    }
                }
            });
        });

        $(document).ready(function() {
            function updateRunningTimes() {
                $('.status-card').each(function() {
                    const proses = $(this).data('proses');
                    if (!proses) return;
                    // Hanya update jika proses sedang berjalan (mulai ada, selesai null)
                    if (proses.mulai && !proses.selesai) {
                        const mulai = new Date(proses.mulai.replace(/-/g, '/'));
                        const now = new Date();
                        let diff = Math.floor((now - mulai) / 1000);
                        if (diff < 0) diff = 0;
                        // Format ke HH:MM:SS
                        const jam = Math.floor(diff / 3600).toString().padStart(2, '0');
                        const menit = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
                        const detik = (diff % 60).toString().padStart(2, '0');
                        const waktuStr = `${jam}:${menit}:${detik}`;
                        // Update elemen waktu berjalan di card-time (span pertama)
                        $(this).find('.card-time span').eq(0).text(waktuStr);
                    }
                });
            }
            setInterval(updateRunningTimes, 1000);
            updateRunningTimes(); // jalankan sekali di awal
        });

        // Real-time update warna card berdasarkan status mulai/selesai
        $(document).ready(function() {
            // Fungsi untuk mendapatkan gradient berdasarkan warna background
            // Konsisten dengan function getGradient di PHP (blade template)
            // Mapping warna sama persis dengan DashboardController::prosesStatuses()
            function getGradient(bg) {
                const gradientMap = {
                    // Abu (belum mulai atau Maintenance)
                    '#757575': 'linear-gradient(180deg, #bdbdbd 0%, #757575 100%)',
                    // Kuning (menunggu approval perubahan: edit_cycle_time, delete_proses, move_machine, swap_position)
                    '#ffeb3b': 'linear-gradient(180deg, #fff9c4 0%,rgb(183, 168, 33) 60%,rgb(202, 161, 57) 100%)',
                    // Biru (berjalan dengan barcode lengkap)
                    '#002b80': 'linear-gradient(180deg, #6dd5ed 0%, #2193b0 60%, #002b80 100%)',
                    // Hijau (selesai normal)
                    '#00c853': 'linear-gradient(180deg, #b2f7c1 0%, #56ab2f 60%, #378a1b 100%)',
                    // Merah (selesai overtime atau barcode belum lengkap)
                    '#e53935': 'linear-gradient(180deg, #ffb3b3 0%, #e53935 60%, #b71c1c 100%)'
                };
                return gradientMap[bg] || gradientMap['#757575'];
            }

            // Fungsi untuk memindahkan card dari aktif ke history saat proses selesai
            function moveToHistory($card, statusData) {
                const mesinId = $card.closest('.card-dropzone').data('mesin-id');
                const $dropzone = $card.closest('.card-dropzone');
                let historyContainer = $(`#history-${mesinId}`);

                // Jika history container belum ada, buat di atas (sebelum proses aktif container)
                if (historyContainer.length === 0) {
                    const $prosesAktifContainer = $dropzone.find('.proses-aktif-container').first();
                    const newHistoryWrapper = $(`
                    <div class="proses-history-wrapper" data-mesin-id="${mesinId}" style="margin-bottom: 8px;">
                        <button class="btn-toggle-history btn btn-sm btn-secondary" 
                                data-mesin-id="${mesinId}" type="button">
                            <i class="fas fa-history"></i> Tampilkan History
                        </button>
                        <div class="proses-history-container" id="history-${mesinId}" style="display: none;">
                        </div>
                    </div>
                `);
                    // Insert sebelum proses aktif container
                    if ($prosesAktifContainer.length > 0) {
                        $prosesAktifContainer.before(newHistoryWrapper);
                    } else {
                        // Fallback: append jika proses aktif container tidak ditemukan
                        $dropzone.prepend(newHistoryWrapper);
                    }
                    historyContainer = $(`#history-${mesinId}`);
                }

                // Pindahkan card ke history (prepend agar terbaru di atas)
                $card.addClass('history-card');
                $card.attr('draggable', 'false');
                $card.attr('data-can-move', '0');
                $card.css('cursor', 'default');
                historyContainer.prepend($card);

                // Update count di button
                const count = historyContainer.find('.status-card').length;
                $(`.btn-toggle-history[data-mesin-id="${mesinId}"]`)
                    .html(`<i class="fas fa-history"></i> Tampilkan History (${count})`);
            }

            // Fungsi untuk reorder card berdasarkan order
            function reorderCardsByOrder(container) {
                if (!container) return;

                const $container = $(container);
                const mesinId = $container.data('mesin-id');
                if (!mesinId) return;

                // Ambil semua card proses aktif (bukan history) di mesin ini
                const $cards = $container.find('.status-card:not(.history-card)');
                if ($cards.length === 0) return;

                // Sort card berdasarkan order dari data-proses
                const sortedCards = $cards.toArray().sort(function(a, b) {
                    const prosesA = $(a).data('proses');
                    const prosesB = $(b).data('proses');

                    if (!prosesA || !prosesB) return 0;

                    // Hanya reorder jika kedua proses belum mulai (pending)
                    if (!prosesA.mulai && !prosesB.mulai && !prosesA.selesai && !prosesB.selesai) {
                        const orderA = parseInt(prosesA.order || 0);
                        const orderB = parseInt(prosesB.order || 0);
                        if (orderA !== orderB) {
                            return orderA - orderB;
                        }
                    }

                    // Fallback: tetap urutkan berdasarkan posisi DOM saat ini
                    return 0;
                });

                // Cek apakah urutan sudah benar
                let needsReorder = false;
                for (let i = 0; i < sortedCards.length; i++) {
                    if (sortedCards[i] !== $cards[i]) {
                        needsReorder = true;
                        break;
                    }
                }

                // Reorder jika diperlukan
                if (needsReorder) {
                    const $prosesAktifContainer = $container.find('.proses-aktif-container').first();
                    if ($prosesAktifContainer.length > 0) {
                        // Detach semua card terlebih dahulu
                        const cardsToReorder = sortedCards.map(card => $(card).detach());
                        // Append kembali sesuai urutan baru
                        cardsToReorder.forEach(function($card) {
                            $prosesAktifContainer.append($card);
                        });
                    }
                }
            }

            // Fungsi global untuk update warna GDA per detail proses
            // Bisa digunakan untuk update real-time dari API atau setelah scan barcode
            function updateGDAIndicatorsGlobal(prosesId, detailId, hasKain, hasLa, hasAux) {
                const $card = $(`.status-card[data-proses-id="${prosesId}"]`);
                if (!$card.length) return;

                const prosesData = $card.data('proses');
                if (!prosesData || prosesData.jenis === 'Maintenance') {
                    return; // Tidak ada G/D/A untuk Maintenance
                }

                // Fungsi helper untuk set warna blok GDA
                function setBlockColor($container, blockType, ok) {
                    const $blocks = $container.find(`.gda-block[data-block-type="${blockType}"]`);
                    if (!$blocks.length) return;

                    const blockBg = ok ? '#d4f8e8' : '#ffb3b3';
                    const blockBorder = ok ? '#43a047' : '#c62828';
                    $blocks.css({
                        background: blockBg,
                        borderColor: blockBorder
                    });
                }

                // Jika detailId tidak ada atau kosong, update GDA di header card (untuk single OP atau OP pertama)
                if (!detailId || detailId === '') {
                    // Update GDA di header card
                    setBlockColor($card, 'G', !!hasKain);
                    setBlockColor($card, 'D', !!hasLa);
                    setBlockColor($card, 'A', !!hasAux);
                    return;
                }

                // Cari OP row yang sesuai dengan detailId
                const $opRow = $card.find(`.op-row[data-detail-id="${detailId}"]`);
                
                // Untuk multiple OP:
                // - OP pertama: GDA ada di header card
                // - OP kedua+: GDA ada di luar .op-row (sebelum .op-row, biasanya di div dengan class khusus)
                
                // Cek apakah ini OP pertama
                const $firstOpRow = $card.find('.op-row').first();
                const isFirstOp = $firstOpRow.length && $firstOpRow.attr('data-detail-id') === String(detailId);

                if (isFirstOp) {
                    // OP pertama: update GDA di header card
                    setBlockColor($card, 'G', !!hasKain);
                    setBlockColor($card, 'D', !!hasLa);
                    setBlockColor($card, 'A', !!hasAux);
                } else if ($opRow.length) {
                    // OP kedua+: cari GDA yang berada sebelum .op-row ini
                    // Struktur HTML: <div>GDA blocks</div> <div class="op-row">...</div>
                    let $gdaContainer = null;
                    
                    // Cek sibling sebelumnya yang memiliki GDA blocks
                    const $prevSibling = $opRow.prev();
                    if ($prevSibling.length && $prevSibling.find('.gda-block').length > 0) {
                        $gdaContainer = $prevSibling;
                    } else {
                        // Cek parent dari op-row untuk mencari GDA di level yang sama
                        const $opList = $opRow.closest('.op-list');
                        if ($opList.length) {
                            // Cari semua elemen sebelum op-row ini dalam op-list
                            const $allBefore = $opList.children().slice(0, $opList.children().index($opRow));
                            for (let i = $allBefore.length - 1; i >= 0; i--) {
                                const $elem = $($allBefore[i]);
                                if ($elem.find('.gda-block').length > 0) {
                                    $gdaContainer = $elem;
                                    break;
                                }
                            }
                        }
                    }
                    
                    if ($gdaContainer && $gdaContainer.length) {
                        // Update GDA di container yang ditemukan
                        setBlockColor($gdaContainer, 'G', !!hasKain);
                        setBlockColor($gdaContainer, 'D', !!hasLa);
                        setBlockColor($gdaContainer, 'A', !!hasAux);
                    } else {
                        // Fallback: update di header card jika GDA khusus tidak ditemukan
                        setBlockColor($card, 'G', !!hasKain);
                        setBlockColor($card, 'D', !!hasLa);
                        setBlockColor($card, 'A', !!hasAux);
                    }
                } else {
                    // Jika OP row tidak ditemukan, update di header card sebagai fallback
                    setBlockColor($card, 'G', !!hasKain);
                    setBlockColor($card, 'D', !!hasLa);
                    setBlockColor($card, 'A', !!hasAux);
                }
            }

            // Fungsi untuk update warna card berdasarkan status dari API
            function updateProsesStatuses() {
                // Ambil parameter mesin dari URL jika ada (untuk filter)
                const urlParams = new URLSearchParams(window.location.search);
                const mesinParams = urlParams.get('mesin');
                let apiUrl = '/dashboard/proses-statuses';
                if (mesinParams) {
                    apiUrl += '?mesin=' + encodeURIComponent(mesinParams);
                }

                fetch(apiUrl)
                    .then(response => response.json())
                    .then(data => {
                        // Group card by mesin untuk reorder
                        const cardsByMesin = {};

                        $('.status-card').each(function() {
                            const $card = $(this);
                            const prosesId = $card.attr('data-proses-id');
                            if (!prosesId || !data[prosesId]) return;

                            const statusData = data[prosesId];
                            const currentBgColor = $card.attr('data-bg-color');
                            const proses = $card.data('proses');

                            if (!proses) return;

                            // CEK: Jika proses baru selesai, pindahkan ke history
                            const wasNotFinished = !proses.selesai || proses.selesai === null;
                            const isNowFinished = statusData.selesai && statusData.selesai !== null;

                            if (wasNotFinished && isNowFinished && !$card.hasClass('history-card')) {
                                // Proses baru selesai, pindahkan ke history container
                                moveToHistory($card, statusData);
                                return; // Skip update warna karena sudah dipindahkan
                            }

                            // Update data proses untuk mulai, selesai, dan order
                            const oldOrder = parseInt(proses.order || 0);
                            proses.mulai = statusData.mulai;
                            proses.selesai = statusData.selesai;
                            proses.order = statusData.order || 0;
                            $card.data('proses', proses);

                            // Track card untuk reorder jika order berubah
                            if (!proses.selesai && !proses.mulai && statusData.order !== undefined) {
                                const newOrder = parseInt(statusData.order || 0);
                                if (oldOrder !== newOrder) {
                                    const mesinId = proses.mesin_id;
                                    if (!cardsByMesin[mesinId]) {
                                        cardsByMesin[mesinId] = [];
                                    }
                                    cardsByMesin[mesinId].push($card);
                                }
                            }

                            // Update warna jika berbeda
                            if (currentBgColor !== statusData.bg_color) {
                                const gradient = getGradient(statusData.bg_color);
                                $card.css('background', gradient);
                                $card.attr('data-bg-color', statusData.bg_color);
                            }

                            // Update warna GDA per detail proses jika ada data gda_details
                            if (statusData.gda_details && Array.isArray(statusData.gda_details)) {
                                statusData.gda_details.forEach(function(gdaDetail) {
                                    const detailId = gdaDetail.detail_id;
                                    const hasKain = gdaDetail.has_kain || false;
                                    const hasLa = gdaDetail.has_la || false;
                                    const hasAux = gdaDetail.has_aux || false;

                                    // Update GDA untuk detail ini menggunakan fungsi global
                                    updateGDAIndicatorsGlobal(prosesId, detailId, hasKain, hasLa, hasAux);
                                });
                            }
                        });

                        // Reorder card di setiap mesin yang terpengaruh
                        Object.keys(cardsByMesin).forEach(function(mesinId) {
                            const container = document.querySelector(`[data-mesin-id="${mesinId}"]`);
                            if (container) {
                                reorderCardsByOrder(container);
                            }
                        });

                        // Reorder semua mesin untuk memastikan urutan sesuai dengan order terbaru
                        // (untuk menangani kasus order berubah dari API)
                        $('.card-dropzone').each(function() {
                            reorderCardsByOrder(this);
                        });
                    })
                    .catch(error => {
                        // Silent fail, jangan tampilkan error di console
                    });
            }

            // Polling setiap 1 detik untuk update status
            setInterval(updateProsesStatuses, 1000);
            updateProsesStatuses(); // jalankan sekali di awal
        });

        // Counter untuk detail item index
        let detailItemIndex = 0;
        let globalOpDataMap = {}; // Map untuk menyimpan data OP per detail item

        // Fungsi untuk inisialisasi select2 untuk detail item
        function initDetailSelect2($container, index) {
            const $noOpSelect = $container.find('.no-op-select');
            const $noPartaiSelect = $container.find('.no-partai-select');

            // Destroy select2 jika sudah ada
            if ($noOpSelect.hasClass('select2-hidden-accessible')) {
                try {
                    $noOpSelect.select2('destroy');
                } catch(e) {
                    // Jika destroy gagal, ignore (mungkin sudah di-destroy sebelumnya)
                }
            }
            if ($noPartaiSelect.hasClass('select2-hidden-accessible')) {
                try {
                    $noPartaiSelect.select2('destroy');
                } catch(e) {
                    // Jika destroy gagal, ignore (mungkin sudah di-destroy sebelumnya)
                }
            }

            // Init select2 untuk No OP
            $noOpSelect.select2({
                dropdownParent: $('#modalProses'),
                placeholder: '-- Pilih No. OP --',
                minimumInputLength: 3,
                dropdownCssClass: 'select2-dropdown-modal',
                width: '100%',
                ajax: {
                    url: '/api/proxy-op',
                    type: 'POST',
                    dataType: 'json',
                    delay: 500,
                    data: function(params) {
                        return {
                            no_op: params.term
                        };
                    },
                    processResults: function(data) {
                        if (Array.isArray(data.results)) {
                            return {
                                results: data.results
                            };
                        } else {
                            return {
                                results: []
                            };
                        }
                    },
                    error: function(xhr, status, error) {
                        return {
                            results: []
                        };
                    }
                }
            });

            // Init select2 untuk No Partai sejak awal (sebelum No OP dipilih)
            $noPartaiSelect.select2({
                dropdownParent: $('#modalProses'),
                placeholder: '-- Pilih No. Partai --',
                allowClear: true,
                dropdownCssClass: 'select2-dropdown-modal',
                width: '100%'
            });

            // Remove event handler sebelumnya jika ada, lalu tambahkan yang baru
            $noOpSelect.off('select2:select');
            // Event handler untuk No OP select
            $noOpSelect.on('select2:select', function(e) {
                const selectedOp = e.params.data.id;
                const $item = $(this).closest('.detail-proses-item');
                const itemIndex = $item.data('index');
                
                // Clear auto fields di item ini
                $item.find('.auto-field-detail').val('');
                
                // Disable No Partai select
                $noPartaiSelect.prop('disabled', true);
                $noPartaiSelect.empty().append('<option value="">-- Pilih No. Partai --</option>');
                
                if (!selectedOp) return;
                
                $.ajax({
                    url: '/api/proxy-op',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    data: {
                        no_op: selectedOp
                    },
                    success: function(res) {
                        globalOpDataMap[itemIndex] = res.raw || [];
                        const filtered = globalOpDataMap[itemIndex].filter(x => 
                            (x.no_op || x.noOp || x.NO_OP) == selectedOp
                        );
                        const uniquePartai = [...new Set(filtered.map(x => 
                            x.no_partai || x.noPartai || x.NO_PARTAI
                        ))].filter(Boolean);
                        
                        if (uniquePartai.length === 0) {
                            $noPartaiSelect.append(
                                '<option value="">(Tidak ada partai ditemukan)</option>');
                        } else {
                            uniquePartai.forEach(partai => {
                                $noPartaiSelect.append(
                                    `<option value="${partai}">${partai}</option>`);
                            });
                        }
                        
                        // Destroy dan re-init select2 untuk update options
                        if ($noPartaiSelect.hasClass('select2-hidden-accessible')) {
                            $noPartaiSelect.select2('destroy');
                        }
                        $noPartaiSelect.select2({
                            dropdownParent: $('#modalProses'),
                            placeholder: '-- Pilih No. Partai --',
                            allowClear: false,
                            dropdownCssClass: 'select2-dropdown-modal',
                            width: '100%'
                        });
                        $noPartaiSelect.val('').trigger('change');
                        $noPartaiSelect.prop('disabled', false);
                    },
                    error: function(xhr) {
                        $noPartaiSelect.append(
                            '<option value="">(Gagal mengambil data partai)</option>');
                        // Destroy dan re-init select2 untuk update options
                        if ($noPartaiSelect.hasClass('select2-hidden-accessible')) {
                            $noPartaiSelect.select2('destroy');
                        }
                        $noPartaiSelect.select2({
                            dropdownParent: $('#modalProses'),
                            placeholder: '-- Pilih No. Partai --',
                            allowClear: false,
                            dropdownCssClass: 'select2-dropdown-modal',
                            width: '100%'
                        });
                        $noPartaiSelect.val('').trigger('change');
                        $noPartaiSelect.prop('disabled', false);
                    }
                });
            });

            // Remove event handler sebelumnya jika ada, lalu tambahkan yang baru
            $noPartaiSelect.off('change');
            // Event handler untuk No Partai select
            $noPartaiSelect.on('change', function() {
                const partai = $(this).val();
                const $item = $(this).closest('.detail-proses-item');
                const itemIndex = $item.data('index');
                
                if (!partai) {
                    $item.find('.auto-field-detail').val('');
                    return;
                }
                
                const op = $noOpSelect.val();
                const opData = globalOpDataMap[itemIndex] || [];
                const row = opData.find(x => 
                    (x.no_op || x.noOp || x.NO_OP) === op && 
                    (x.no_partai || x.noPartai || x.NO_PARTAI) === partai
                );
                
                if (row) {
                    $item.find('[name*="[item_op]"]').val(row.item_op || '');
                    $item.find('[name*="[kode_material]"]').val(row.kode_material || '');
                    $item.find('[name*="[konstruksi]"]').val(row.konstruksi || '');
                    $item.find('[name*="[gramasi]"]').val(row.gramasi || '');
                    $item.find('[name*="[lebar]"]').val(row.lebar || '');
                    $item.find('[name*="[hfeel]"]').val(row.hfeel || '');
                    $item.find('[name*="[warna]"]').val(row.warna || '');
                    $item.find('[name*="[kode_warna]"]').val(row.kode_warna || '');
                    $item.find('[name*="[kategori_warna]"]').val(row.kat_warna || '');
                    
                    // Format qty 2 digit koma
                    var qtyVal = row.qty || '';
                    if (qtyVal !== '' && !isNaN(qtyVal)) {
                        qtyVal = parseFloat(qtyVal).toFixed(2);
                    }
                    $item.find('[name*="[qty]"]').val(qtyVal);
                    
                    // Roll dari API
                    var rollVal = row.roll || '';
                    $item.find('[name*="[roll]"]').val(rollVal);
                } else {
                    $item.find('.auto-field-detail').val('');
                }
            });
        }

        // Fungsi untuk menambah detail item baru
        function addDetailItem() {
            const $container = $('#detail-proses-container');
            const $firstItem = $container.find('.detail-proses-item').first();
            
            // Gunakan jumlah item yang ada sebagai index baru (mengisi kekosongan)
            const currentItemCount = $container.find('.detail-proses-item').length;
            const newIndex = currentItemCount;
            
            // Update detailItemIndex global
            detailItemIndex = newIndex;
            
            // Clone element tanpa event handler (karena akan di-init Select2 baru dengan event handler baru)
            const $newItem = $firstItem.clone(false);
            
            // Update index dan name attributes
            $newItem.attr('data-index', newIndex);
            $newItem.find('.card-header h6').text('Detail OP #' + (newIndex + 1));
            $newItem.find('.remove-detail-btn').show();
            
            // Update semua name attributes
            $newItem.find('[name]').each(function() {
                const $this = $(this);
                const name = $this.attr('name');
                if (name && name.includes('[0]')) {
                    $this.attr('name', name.replace('[0]', '[' + newIndex + ']'));
                }
            });
            
            // Clear values
            $newItem.find('input, select').val('');
            $newItem.find('.auto-field-detail').val('');
            
            // Clean up Select2 HTML dari clone (karena clone membawa HTML Select2 tapi tidak state-nya)
            $newItem.find('.no-op-select, .no-partai-select').each(function() {
                const $select = $(this);
                // Remove Select2 wrapper classes dan elements
                $select.removeClass('select2-hidden-accessible');
                $select.removeAttr('data-select2-id');
                $select.removeAttr('tabindex');
                $select.removeAttr('aria-hidden');
                // Remove Select2 container yang mungkin ter-clone
                $select.siblings('.select2-container').remove();
            });
            
            // Insert sebelum tombol add
            $newItem.insertBefore($('#add-detail-btn-container'));
            
            // Init select2 untuk item baru
            initDetailSelect2($newItem, newIndex);
        }

        // Fungsi untuk menghapus detail item
        function removeDetailItem($item) {
            const itemIndex = $item.data('index');
            
            // Destroy select2 dengan check terlebih dahulu
            $item.find('.no-op-select, .no-partai-select').each(function() {
                const $select = $(this);
                if ($select.hasClass('select2-hidden-accessible')) {
                    try {
                        $select.select2('destroy');
                    } catch(e) {
                        // Jika destroy gagal, ignore (mungkin sudah di-destroy sebelumnya)
                    }
                }
            });
            
            // Hapus dari map
            delete globalOpDataMap[itemIndex];
            
            // Hapus item
            $item.remove();
            
            // Update nomor urut
            updateDetailItemNumbers();
        }

        // Fungsi untuk update nomor urut detail items
        function updateDetailItemNumbers() {
            const $container = $('#detail-proses-container');
            const $items = $container.find('.detail-proses-item');
            
            $items.each(function(index) {
                const $item = $(this);
                const newIndex = index;
                
                // Update data-index
                $item.attr('data-index', newIndex);
                
                // Update teks nomor
                $item.find('.card-header h6').text('Detail OP #' + (index + 1));
                
                // Update semua name attributes
                $item.find('[name]').each(function() {
                    const $this = $(this);
                    const name = $this.attr('name');
                    if (name && name.match(/\[(\d+)\]/)) {
                        // Ganti index di name attribute dengan index baru
                        $this.attr('name', name.replace(/\[\d+\]/, '[' + newIndex + ']'));
                    }
                });
            });
            
            // Update detailItemIndex global: set ke index terakhir setelah renumbering
            // Setelah renumbering, index terakhir = jumlah item - 1
            detailItemIndex = $items.length > 0 ? $items.length - 1 : 0;
        }

        $(document).ready(function() {
            // Inisialisasi select2 untuk detail item pertama
            initDetailSelect2($('#detail-proses-container .detail-proses-item').first(), 0);

            // Handler untuk Jenis OP (Single/Multiple)
            $('#jenis_op').on('change', function() {
                const jenisOp = $(this).val();
                const $container = $('#detail-proses-container');
                const $items = $container.find('.detail-proses-item');
                const $addBtn = $('#add-detail-btn-container');
                const $removeBtns = $container.find('.remove-detail-btn');
                
                if (jenisOp === 'Multiple') {
                    // Tampilkan tombol tambah dan hapus
                    $addBtn.show();
                    $removeBtns.show();
                } else {
                    // Sembunyikan tombol tambah dan hapus
                    $addBtn.hide();
                    $removeBtns.hide();
                    
                    // Hapus semua item kecuali yang pertama
                    $items.slice(1).each(function() {
                        removeDetailItem($(this));
                    });
                    
                    // Reset index
                    detailItemIndex = 0;
                    $items.first().attr('data-index', 0);
                    updateDetailItemNumbers();
                }
            });

            // Handler untuk Jenis Proses
            $('[name="jenis"]').on('change', function() {
                var isMaintenance = $(this).val() === 'Maintenance';

                // Sembunyikan field tertentu jika Maintenance
                if (isMaintenance) {
                    $('.hide-if-maintenance').hide();
                } else {
                    $('.hide-if-maintenance').show();
                }
            });

            // Handler untuk tombol tambah detail
            $(document).on('click', '#add-detail-btn', function() {
                addDetailItem();
            });

            // Handler untuk tombol hapus detail
            $(document).on('click', '.remove-detail-btn', function() {
                const $item = $(this).closest('.detail-proses-item');
                const itemCount = $('#detail-proses-container .detail-proses-item').length;
                
                if (itemCount > 1) {
                    removeDetailItem($item);
                } else {
                    // Gunakan SweetAlert untuk validasi minimal 1 detail OP
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tidak bisa dihapus',
                        text: 'Minimal harus ada 1 Detail OP.',
                        confirmButtonText: 'OK'
                    });
                }
            });

            // Inisialisasi Select2 saat modal dibuka
            $('#modalProses').on('shown.bs.modal', function() {
                // Pastikan Select2 untuk Mesin sudah terinisialisasi
                const $mesinSelect = $('#mesin_id');
                if (!$mesinSelect.hasClass('select2-hidden-accessible') && $.fn.select2) {
                    $mesinSelect.select2({
                        dropdownParent: $('#modalProses'),
                        placeholder: '-- Pilih Mesin --',
                        allowClear: false,
                        dropdownCssClass: 'select2-dropdown-modal',
                        width: '100%'
                    });
                }
            });

            // Reset form saat modal ditutup
            $('#modalProses').on('hidden.bs.modal', function() {
                // Reset form
                $('#formProses')[0].reset();
                
                // Hapus semua detail item kecuali yang pertama
                const $items = $('#detail-proses-container .detail-proses-item');
                $items.slice(1).each(function() {
                    const $item = $(this);
                    const itemIndex = $item.data('index');
                    delete globalOpDataMap[itemIndex];
                    $item.find('.select2-detail').select2('destroy');
                    $item.remove();
                });
                
                // Reset index dan update nomor
                detailItemIndex = 0;
                $items.first().attr('data-index', 0);
                updateDetailItemNumbers();
                
                // Clear global data
                globalOpDataMap = {};
                
                // Destroy semua select2
                $('#detail-proses-container .select2-detail').select2('destroy');
                
                // Destroy Select2 untuk Mesin
                const $mesinSelect = $('#mesin_id');
                if ($mesinSelect.hasClass('select2-hidden-accessible')) {
                    $mesinSelect.select2('destroy');
                }
                
                // Re-init select2 untuk item pertama
                initDetailSelect2($('#detail-proses-container .detail-proses-item').first(), 0);
                
                // Reset jenis_op ke Single
                $('#jenis_op').val('Single').trigger('change');
            });

            // Trigger di awal
            $('[name="jenis"]').trigger('change');
            $('#jenis_op').trigger('change');

            // Validasi tambahan saat submit form:
            // Jika jenis_op = Multiple maka jumlah Detail OP harus >= 2
            $('#formProses').on('submit', function(e) {
                const jenisOp = $('#jenis_op').val();
                if (jenisOp === 'Multiple') {
                    const detailCount = $('#detail-proses-container .detail-proses-item').length;
                    if (detailCount < 2) {
                        e.preventDefault();
                        // Gunakan SweetAlert untuk validasi Multiple OP
                        Swal.fire({
                            icon: 'warning',
                            title: 'Detail OP kurang',
                            text: 'Jenis OP dengan jenis Multiple, minimal harus ada 2 Detail OP.',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                }
            });
        });

        // Helper function untuk notifikasi Swal Toast
        function showToastNotification(type, message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                icon: type,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            Toast.fire({
                title: message
            });
        }

        $(document).on('click', '.cancel-barcode-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const barcodeType = $(this).data('type');
            const prosesId = $(this).data('proses');
            const barcodeId = $(this).data('id');
            const matdok = $(this).data('matdok');
            const barcode = $(this).data('barcode') || '';

            if (!matdok) {
                showToastNotification('error', 'Material document tidak tersedia!');
                return;
            }

            // Simpan data untuk konfirmasi
            cancelBarcodeData = {
                barcodeType,
                prosesId,
                barcodeId,
                matdok,
                barcode
            };

            // Update informasi di modal
            const typeLabel = barcodeType === 'kain' ? 'Barcode Kain' :
                barcodeType === 'la' ? 'Barcode LA' : 'Barcode AUX';
            const barcodeText = barcode ? `<strong>${barcode}</strong>` : 'barcode ini';
            const matdokText = matdok ? `<br><small class="text-muted">Material Document: ${matdok}</small>` : '';

            $('#confirmCancelBarcodeText').html(
                `Apakah Anda yakin ingin membatalkan ${typeLabel}?<br><br>` +
                `${barcodeText}${matdokText}<br><br>` +
                `<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Tindakan ini tidak dapat dibatalkan!</span>`
            );

            // Reset loading state
            $('#btnConfirmCancelBarcode').prop('disabled', false).html(
                '<i class="fas fa-times mr-1"></i>Ya, Cancel');

            // Tampilkan modal
            $('#modalConfirmCancelBarcode').modal('show');
        });

        // Modal konfirmasi cancel barcode
        if (!document.getElementById('modalConfirmCancelBarcode')) {
            $(document.body).append(`
            <div class="modal fade" id="modalConfirmCancelBarcode" tabindex="-1" aria-labelledby="modalConfirmCancelBarcodeLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
                    <div class="modal-content shadow-lg border-0 rounded-3">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title fw-bold" id="modalConfirmCancelBarcodeLabel">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Konfirmasi Cancel Barcode
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body py-4 px-4 text-center">
                            <div id="confirmCancelBarcodeText" style="font-size:15px;line-height:1.6;">
                                Apakah Anda yakin ingin mengcancel barcode ini?
                            </div>
                            <div id="cancelBarcodeLoading" style="display:none;margin-top:15px;">
                                <div class="spinner-border text-danger" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Memproses cancel barcode...</p>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-end px-4">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal" id="btnCancelCancelBarcode">
                                <i class="fas fa-times mr-1"></i>Batal
                            </button>
                            <button type="button" class="btn btn-danger" id="btnConfirmCancelBarcode">
                                <i class="fas fa-check mr-1"></i>Ya, Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            `);
        }

        let cancelBarcodeData = null;
        let currentProsesForRefresh = null;
        let currentDetailForRefresh = null;

        $(document).on('click', '#btnConfirmCancelBarcode', function() {
            if (!cancelBarcodeData) return;

            const {
                barcodeType,
                prosesId,
                barcodeId,
                matdok,
                barcode
            } = cancelBarcodeData;

            // Simpan proses ID untuk refresh
            currentProsesForRefresh = prosesId;
            // Simpan detail_proses_id aktif (jika ada)
            currentDetailForRefresh = $('#modalDetailProses').data('detailProsesId') || null;

            // Tampilkan loading dan disable button
            $('#btnConfirmCancelBarcode').prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm mr-1"></span>Memproses...');
            $('#btnCancelCancelBarcode').prop('disabled', true);
            $('#cancelBarcodeLoading').show();

            // Request ke backend Laravel
            $.ajax({
                url: `/proses/${prosesId}/barcode/${barcodeType}/${barcodeId}/cancel`,
                method: 'POST',
                data: {
                    matdok: matdok
                },
                success: function(r) {
                    $('#modalConfirmCancelBarcode').modal('hide');

                    if (r && r.status === 'success') {
                        // Show success message
                        showToastNotification('success', 'Barcode berhasil di-cancel!');

                        // Refresh barcode list di modal detail proses jika masih terbuka
                        if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(
                                ':visible')) {
                            // Simulasi double click pada card proses yang aktif untuk refresh5
                            const activeProses = $('.status-card').filter(function() {
                                const proses = $(this).data('proses');
                                return proses && proses.id == currentProsesForRefresh;
                            }).first();

                            if (activeProses.length) {
                                // Reload barcode data via AJAX
                                $.ajax({
                                    url: '/proses/' + currentProsesForRefresh + '/barcodes' +
                                        (currentDetailForRefresh ?
                                            ('?detail_proses_id=' + encodeURIComponent(currentDetailForRefresh)) :
                                            ''),
                                    method: 'GET',
                                    success: function(data) {
                                        function renderBarcodeGrid(barcodes, barcodeType,
                                            prosesId) {
                                            // Filter barcode yang belum cancel
                                            const activeBarcodes = (barcodes || []).filter(
                                                bk => !bk.cancel);
                                            if (!activeBarcodes.length) {
                                                return '<span style="color:#888;">Belum ada barcode.</span>';
                                            }
                                            // Cek apakah user bisa cancel barcode
                                            const canCancel = window.canCancelBarcode !==
                                                false;
                                            let html =
                                                '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                                            activeBarcodes.forEach(function(bk, idx) {
                                                // Hanya tampilkan button cancel jika user memiliki akses
                                                const cancelButton = canCancel ?
                                                    `<span style='position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;' class='cancel-barcode-btn' data-type='${barcodeType}' data-proses='${prosesId}' data-id='${bk.id}' data-matdok='${bk.matdok}' title='Cancel barcode'>&times;</span>` :
                                                    '';
                                                html += `<div style="position:relative;flex:1 0 30%;max-width:32%;background:#f3f3f3;border-radius:6px;padding:6px 4px;margin-bottom:6px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">
                                                    ${cancelButton}
                                                    ${bk.barcode} ${(bk.matdok ? '<br><span style=\'font-size:11px;color:#888;\'>' + bk.matdok + '</span>' : '')}
                                                </div>`;
                                            });
                                            html += '</div>';
                                            return html;
                                        }
                                        // Helper untuk update warna blok G, D, A di card utama setelah perubahan barcode
                                        function updateGDAIndicators(prosesId, detailId, hasKain, hasLa, hasAux) {
                                            let $targets = $(`.status-card[data-proses-id="${prosesId}"] .op-row[data-detail-id="${detailId}"]`);
                                            if (!$targets.length) {
                                                $targets = $(`.status-card[data-proses-id="${prosesId}"]`);
                                            }
                                            if (!$targets.length) return;

                                            $targets.each(function() {
                                                const $card = $(this);

                                                function setBlockColor(blockType, ok) {
                                                    const $block = $card.find(
                                                        `.gda-block[data-block-type="${blockType}"]`
                                                    );
                                                    if (!$block.length) return;

                                                    const blockBg = ok ? '#d4f8e8' : '#ffb3b3';
                                                    const blockBorder = ok ? '#43a047' : '#c62828';
                                                    $block.css({
                                                        background: blockBg,
                                                        borderColor: blockBorder
                                                    });
                                                }

                                                setBlockColor('G', !!hasKain);
                                                setBlockColor('D', !!hasLa);
                                                setBlockColor('A', !!hasAux);
                                            });
                                        }

                                        // Render ulang list barcode di modal
                                        $('#barcode-kain-list').html(renderBarcodeGrid(data
                                            .barcode_kain, 'kain',
                                            currentProsesForRefresh));
                                        $('#barcode-la-list').html(renderBarcodeGrid(data
                                            .barcode_la, 'la',
                                            currentProsesForRefresh));
                                        $('#barcode-aux-list').html(renderBarcodeGrid(data
                                            .barcode_aux, 'aux',
                                            currentProsesForRefresh));

                                        // Hitung status barcode aktif per jenis untuk update G/D/A di card utama
                                        const hasKainActive = (data.barcode_kain || [])
                                            .some(bk => !bk.cancel);
                                        const hasLaActive = (data.barcode_la || []).some(
                                            bk => !bk.cancel);
                                        const hasAuxActive = (data.barcode_aux || []).some(
                                            bk => !bk.cancel);
                                        updateGDAIndicators(
                                            currentProsesForRefresh,
                                            currentDetailForRefresh,
                                            hasKainActive,
                                            hasLaActive,
                                            hasAuxActive
                                        );
                                    },
                                    error: function() {
                                        showToastNotification('error',
                                            'Gagal me-refresh data barcode. Silakan tutup dan buka kembali modal detail.'
                                        );
                                    }
                                });
                            }
                        }
                    } else {
                        const errorMsg = 'Cancel barcode gagal: ' + (r && r.message ? r.message :
                            'Unknown error');
                        showToastNotification('error', errorMsg);
                    }
                },
                error: function(xhr) {
                    $('#modalConfirmCancelBarcode').modal('hide');
                    let errorMsg = 'Gagal request ke server.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.status === 0) {
                        errorMsg = 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Terjadi kesalahan pada server. Silakan coba lagi.';
                    } else if (xhr.status === 404) {
                        errorMsg = 'Endpoint tidak ditemukan.';
                    }
                    showToastNotification('error', errorMsg);
                },
                complete: function() {
                    // Reset loading state
                    $('#btnConfirmCancelBarcode').prop('disabled', false).html(
                        '<i class="fas fa-check mr-1"></i>Ya, Cancel');
                    $('#btnCancelCancelBarcode').prop('disabled', false);
                    $('#cancelBarcodeLoading').hide();
                    cancelBarcodeData = null;
                }
            });
        });

        // Reset data saat modal ditutup
        $('#modalConfirmCancelBarcode').on('hidden.bs.modal', function() {
            cancelBarcodeData = null;
            $('#btnConfirmCancelBarcode').prop('disabled', false).html(
                '<i class="fas fa-check mr-1"></i>Ya, Cancel');
            $('#btnCancelCancelBarcode').prop('disabled', false);
            $('#cancelBarcodeLoading').hide();
        });

        $(document).ready(function() {
            // Submit form filter mesin otomatis saat select berubah
            $('#filter-mesin').on('change', function() {
                $('#filter-mesin-form').submit();
            });
            // Tombol clear mesin
            $('#clear-mesin-btn').on('click', function() {
                $('#filter-mesin').val(null).trigger('change');
                setTimeout(function() {
                    $('#filter-mesin-form').submit();
                }, 100);
            });
        });

        // Toggle history per mesin
        $(document).on('click', '.btn-toggle-history', function() {
            const mesinId = $(this).data('mesin-id');
            const historyContainer = $(`#history-${mesinId}`);
            const btn = $(this);

            if (historyContainer.is(':visible')) {
                // Sembunyikan
                historyContainer.slideUp(300);
                const count = historyContainer.find('.status-card').length;
                btn.html(`<i class="fas fa-history"></i> Tampilkan History (${count})`);
            } else {
                // Tampilkan
                historyContainer.slideDown(300);
                btn.html(`<i class="fas fa-chevron-up"></i> Sembunyikan History`);
            }
        });
    </script>
@endsection