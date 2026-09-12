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
            transition: background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .op-row:hover {
            background: rgba(255, 255, 255, 0.55);
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.6);
            border-color: rgba(255, 255, 255, 0.8);
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
            font-size: 12px;
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
            overflow-x: hidden;
            /* BLOCK physical sliding */
            gap: 8px;
            padding-bottom: 8px;
            /* Hint to browser for smoother scrolling */
            will-change: scroll-position;
            transform: translateZ(0);
            /* Transition for fade effect */
            transition: opacity 0.5s ease-in-out;
            opacity: 1;

            /* Hide scrollbar for Firefox and IE/Edge */
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        #machines-container::-webkit-scrollbar {
            display: none;
        }

        #machines-container.fade-out {
            opacity: 0;
        }

        .machine-column {
            /* Now driven by JS variable --cols-per-page for a truly fluid layout */
            flex: 0 0 calc((100% - (var(--cols-per-page, 5) - 1) * 8px) / var(--cols-per-page, 5)) !important;
            min-width: 0;
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

        /* Efek kelap-kelip halus untuk indikator kuning (alarm ON) */
        @keyframes pulse-soft-yellow {
            0% {
                opacity: 1;
                box-shadow: 0 0 8px rgba(255, 235, 59, 0.6);
            }

            50% {
                opacity: 0.45;
                box-shadow: 0 0 2px rgba(255, 235, 59, 0.25);
            }

            100% {
                opacity: 1;
                box-shadow: 0 0 8px rgba(255, 235, 59, 0.6);
            }
        }

        .status-light.running-light-yellow {
            animation: pulse-soft-yellow 1.4s ease-in-out infinite;
        }

        /* Efek peringatan kelap-kelip untuk icon sinyal IoT terputus / tidak stabil */
        @keyframes pulse-iot-warning {
            0% {
                transform: scale(1);
                opacity: 1;
                filter: drop-shadow(0 0 2px rgba(255, 193, 7, 0.8));
            }

            50% {
                transform: scale(1.18);
                opacity: 0.6;
                filter: drop-shadow(0 0 6px rgba(255, 61, 0, 0.9));
            }

            100% {
                transform: scale(1);
                opacity: 1;
                filter: drop-shadow(0 0 2px rgba(255, 193, 7, 0.8));
            }
        }

        .iot-offline-icon {
            animation: pulse-iot-warning 1.5s ease-in-out infinite;
            font-size: 18px;
            vertical-align: middle;
            color: #ffc107;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.9);
            cursor: help;
        }

        /* Tombol toggle history */
        .btn-toggle-history {
            width: 100%;
            margin-top: 5px;
            font-size: 12px;
            padding: 4px 8px;
        }

        /* Toggle mode Produksi / History */
        #dashboard-mode-toggle .btn.active {
            font-weight: bold;
        }

        /* Pastikan machine-column memiliki height yang sesuai */
        .machine-column>div>div {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        /* Fullscreen height fix */
        body.fullscreen-active {
            overflow: hidden !important;
        }

        body.fullscreen-active .content-header {
            display: none !important;
        }

        body.fullscreen-active .content {
            padding: 0 !important;
        }

        body.fullscreen-active #machines-container {
            height: 100vh;
            margin: 0 !important;
            padding: 0 !important;
        }

        body.fullscreen-active .machine-column {
            height: 100vh;
        }

        body.fullscreen-active .machine-column>div,
        body.fullscreen-active .machine-column-wrapper {
            height: 100%;
            display: flex !important;
            flex-direction: column !important;
        }

        body.fullscreen-active .card-dropzone {
            flex: 1 !important;
            max-height: none !important;
            overflow-y: auto !important;
        }

        /* Floating Navigation Buttons */
        .slideshow-nav-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 1060;
            /* Above modals backdrop if needed, but usually 1050 is modal */
        }

        .nav-btn {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            font-size: 20px;
            outline: none !important;
        }

        .nav-btn:hover {
            background: rgba(0, 0, 0, 0.6);
            transform: translateY(-4px) scale(1.1);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 255, 255, 0.5);
            color: #fff;
        }

        .nav-btn:active {
            transform: translateY(0) scale(0.95);
        }

        /* Hide buttons when modal is open to keep UI clean */
        body.modal-open .slideshow-nav-container {
            display: none;
        }
    </style>
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header pb-1">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6 d-flex align-items-center mb-2 mb-sm-0">
                        <h1 class="m-0 font-weight-bold" style="font-size: 1.6rem; letter-spacing: 0.5px;">Dashboard</h1>
                        <button type="button"
                            class="btn btn-sm btn-outline-primary ml-3 d-flex align-items-center shadow-sm"
                            data-toggle="modal" data-target="#modalFilterDashboard"
                            style="font-weight: 600; border-radius: 20px; padding: 4px 14px;">
                            <i class="fas fa-filter mr-1"></i> Filter
                            @if(($activeFilterCount ?? 0) > 0)
                                <span class="badge badge-danger ml-2 px-2 py-1"
                                    style="border-radius: 10px; font-size: 0.75rem;">{{ $activeFilterCount }}</span>
                            @endif
                        </button>
                        @if(($activeFilterCount ?? 0) > 0)
                            <a href="{{ url('dashboard') }}" class="btn btn-sm btn-link text-danger ml-2 p-0"
                                title="Reset Semua Filter" style="font-size: 0.85rem; text-decoration: underline;">
                                <i class="fas fa-times-circle"></i> Reset Semua Filter
                            </a>
                        @endif
                    </div>
                    <div class="col-sm-6 d-flex justify-content-end align-items-center">
                        <div class="btn-group btn-group-sm mr-2 shadow-sm" role="group" id="dashboard-mode-toggle"
                            title="Mode tampilan">
                            <button type="button" class="btn btn-outline-primary" data-mode="produksi"
                                id="mode-produksi-btn">
                                <i class="fas fa-industry"></i> Production
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-mode="history"
                                id="mode-history-btn">
                                <i class="fas fa-history"></i> History
                            </button>
                        </div>
                        @if(in_array(Auth::user()->role ?? '', ['super_admin', 'kepala_shift', 'ppic'], true))
                            <button type="button" class="btn btn-sm btn-outline-info shadow-sm mr-2" id="btn-resync-iot"
                                title="Sinkronkan ulang seluruh register sinyal IoT (100, 103, 105) ke semua PLC"
                                style="font-weight: 600;">
                                <i class="fas fa-sync-alt" id="icon-resync-iot"></i> Resync Sinyal
                            </button>
                        @endif
                        <div id="dashboard-controls" style="display: flex; justify-content: flex-end; gap: 10px;">
                            @if ($canAddProses ?? true)
                                <button type="button" id="add-card-btn" class="btn btn-success shadow-sm"
                                    style="font-weight:bold;" data-toggle="modal" data-target="#modalProses">
                                    + Tambah Proses
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Active Filter Pills / Chips --}}
                @if(($activeFilterCount ?? 0) > 0)
                    <div class="row mt-1 mb-2">
                        <div class="col-12 d-flex align-items-center flex-wrap" style="gap: 6px; font-size: 0.85rem;">
                            <span class="text-muted mr-1 font-weight-bold"><i class="fas fa-tags mr-1"></i> Filter Aktif:</span>
                            @if(!empty($selectedMesinArr))
                                @php
                                    $mesinNames = $mesins->whereIn('id', $selectedMesinArr)->pluck('jenis_mesin')->implode(', ');
                                @endphp
                                <span class="badge badge-light border border-primary text-primary px-2 py-1"
                                    style="font-size: 0.82rem;">
                                    <strong>Mesin:</strong> {{ Str::limit($mesinNames, 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedCustomerArr))
                                <span class="badge badge-light border border-info text-info px-2 py-1" style="font-size: 0.82rem;">
                                    <strong>Customer:</strong> {{ Str::limit(implode(', ', $selectedCustomerArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedMarketingArr))
                                <span class="badge badge-light border border-info text-info px-2 py-1" style="font-size: 0.82rem;">
                                    <strong>Marketing:</strong> {{ Str::limit(implode(', ', $selectedMarketingArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedWarnaArr))
                                <span class="badge badge-light border border-success text-success px-2 py-1"
                                    style="font-size: 0.82rem;">
                                    <strong>Warna:</strong> {{ Str::limit(implode(', ', $selectedWarnaArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedKategoriWarnaArr))
                                <span class="badge badge-light border border-success text-success px-2 py-1"
                                    style="font-size: 0.82rem;">
                                    <strong>Kategori Warna:</strong> {{ Str::limit(implode(', ', $selectedKategoriWarnaArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedKodeWarnaArr))
                                <span class="badge badge-light border border-success text-success px-2 py-1"
                                    style="font-size: 0.82rem;">
                                    <strong>Kode Warna:</strong> {{ Str::limit(implode(', ', $selectedKodeWarnaArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedHfeelArr))
                                <span class="badge badge-light border border-secondary text-dark px-2 py-1"
                                    style="font-size: 0.82rem;">
                                    <strong>Handfeel:</strong> {{ Str::limit(implode(', ', $selectedHfeelArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedGramasiArr))
                                <span class="badge badge-light border border-secondary text-dark px-2 py-1"
                                    style="font-size: 0.82rem;">
                                    <strong>Gramasi:</strong> {{ Str::limit(implode(', ', $selectedGramasiArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedNoOpArr))
                                <span class="badge badge-light border border-warning text-dark px-2 py-1"
                                    style="font-size: 0.82rem;">
                                    <strong>No OP:</strong> {{ Str::limit(implode(', ', $selectedNoOpArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedNoPartaiArr))
                                <span class="badge badge-light border border-warning text-dark px-2 py-1"
                                    style="font-size: 0.82rem;">
                                    <strong>No Partai:</strong> {{ Str::limit(implode(', ', $selectedNoPartaiArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedKonstruksiArr))
                                <span class="badge badge-light border border-dark text-dark px-2 py-1" style="font-size: 0.82rem;">
                                    <strong>Konstruksi:</strong> {{ Str::limit(implode(', ', $selectedKonstruksiArr), 30) }}
                                </span>
                            @endif
                            @if(!empty($selectedKodeMaterialArr))
                                <span class="badge badge-light border border-dark text-dark px-2 py-1" style="font-size: 0.82rem;">
                                    <strong>Kode Material:</strong> {{ Str::limit(implode(', ', $selectedKodeMaterialArr), 30) }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">

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
                                // Merah tua (proses selesai dengan masalah: overtime/durasi singkat) - solid
                                '#e53935' => '#e53935',
                                // Merah muda (proses berjalan dengan barcode belum lengkap) - solid
                                '#ef9a9a' => '#ef9a9a',
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
                                <div style="padding: 2px; width: 100%;">
                                    <div class="machine-column-wrapper"
                                        style="border-radius: 0; box-shadow: none; display: flex; flex-direction: column; height: 100%;">
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
                                                        // 1. Proses yang sedang berjalan (mulai terisi dan belum selesai) HARUS selalu di paling atas
                                                        $isRunningA = $a->mulai && !$a->selesai;
                                                        $isRunningB = $b->mulai && !$b->selesai;
                                                        if ($isRunningA && !$isRunningB) {
                                                            return -1;
                                                        }
                                                        if (!$isRunningA && $isRunningB) {
                                                            return 1;
                                                        }

                                                        // 2. Jika keduanya pending (belum mulai), urutkan berdasarkan order (1, 2, 3...)
                                                        if (!$a->mulai && !$b->mulai) {
                                                            $orderA = (int) ($a->order ?? 0);
                                                            $orderB = (int) ($b->order ?? 0);
                                                            if ($orderA !== $orderB) {
                                                                return $orderA <=> $orderB;
                                                            }
                                                        }
                                                        // 3. Fallback ke created_at dan id
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
                                                <div class="proses-history-wrapper" data-section="history"
                                                    style="margin-bottom: 8px;">
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
                                                                        : ($proses->jenis === 'Proses Makloon'
                                                                            ? 'PM'
                                                                            : ($proses->jenis === 'Reproses Makloon'
                                                                                ? 'RM'
                                                                                : 'M')));
                                                                // Inisialisasi default agar aman dan tidak undefined untuk semua jenis proses (termasuk Maintenance)
                                                                $isPinjamMesinHist = (bool) ($proses->is_pinjam_mesin ?? false);
                                                                $isBreakHist = (bool) ($proses->is_break ?? false);
                                                                $firstDetail = (isset($proses->details) && is_iterable($proses->details)) ? collect($proses->details)->first() : null;
                                                                $firstRoll = $firstDetail->roll ?? 0;
                                                                $firstPendingKain = false;
                                                                $firstApprovedKainCount = 0;
                                                                $firstHasKain = false;
                                                                $firstKainColor = 'red';
                                                                $firstHasLa = false;
                                                                $firstHasAux = false;
                                                                $hasBarcodeLa = false;
                                                                $hasBarcodeAux = false;
                                                                $allKainComplete = false;

                                                                if ($proses->jenis === 'Maintenance') {
                                                                    $blockColors = ['gray', 'gray', 'gray'];
                                                                } else {
                                                                    // G: hijau hanya jika SEMUA detail OP sudah memenuhi barcode kain >= roll
                                                                    $allKainComplete = true;
                                                                    $hasBarcodeLa = isset($proses->details) && $proses->details->isNotEmpty()
                                                                        ? $proses->details->every(fn($d) => $d->barcodeLas && $d->barcodeLas->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_dye_stuff ?? 0))
                                                                        : false;
                                                                    $hasBarcodeAux = isset($proses->details) && $proses->details->isNotEmpty()
                                                                        ? $proses->details->every(fn($d) => $d->barcodeAuxs && $d->barcodeAuxs->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_aux ?? 0))
                                                                        : false;

                                                                    if (isset($proses->details) && is_iterable($proses->details)) {
                                                                        foreach ($proses->details as $d) {
                                                                            // Cek apakah detail ini sudah memenuhi barcode kain >= roll
                                                                            $detailRoll = $d->roll ?? 0;
                                                                            $detailKainCount = isset($d->barcodeKains)
                                                                                ? $d->barcodeKains->where('cancel', false)->count()
                                                                                : 0;
                                                                            if ($detailRoll > 0 && $detailKainCount < $detailRoll) {
                                                                                $allKainComplete = false;
                                                                            }
                                                                        }
                                                                    } else {
                                                                        $allKainComplete = false;
                                                                    }
                                                                    // Fallback backward compatibility untuk LA dan AUX
                                                                    if (!$hasBarcodeLa && isset($proses->barcode_la)) {
                                                                        $hasBarcodeLa = (bool) $proses->barcode_la;
                                                                    }
                                                                    if (!$hasBarcodeAux && isset($proses->barcode_aux)) {
                                                                        $hasBarcodeAux = (bool) $proses->barcode_aux;
                                                                    }
                                                                    // Status GDA khusus untuk OP pertama (header card)
                                                                    $firstDetail = (isset($proses->details) && is_iterable($proses->details)) ? collect($proses->details)->first() : null;
                                                                    $firstRoll = $firstDetail->roll ?? 0;
                                                                    $firstPendingKain = isset($firstDetail->barcodeKains) && $firstDetail->barcodeKains->where('cancel', false)->where('approval_status', 'pending')->isNotEmpty();
                                                                    $firstApprovedKainCount = isset($firstDetail->barcodeKains)
                                                                        ? $firstDetail->barcodeKains->where('cancel', false)->where('approval_status', 'approved')->count()
                                                                        : 0;
                                                                    $firstHasKain = ($firstApprovedKainCount >= $firstRoll && $firstRoll > 0);
                                                                    $firstKainColor = $firstPendingKain ? 'yellow' : ($firstHasKain ? 'green' : 'red');

                                                                    $firstHasLa = isset($firstDetail->barcodeLas)
                                                                        ? $firstDetail->barcodeLas->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_dye_stuff ?? 0)
                                                                        : $hasBarcodeLa;
                                                                    $firstHasAux = isset($firstDetail->barcodeAuxs)
                                                                        ? $firstDetail->barcodeAuxs->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_aux ?? 0)
                                                                        : $hasBarcodeAux;

                                                                    $blockColors = [
                                                                        $firstKainColor,
                                                                        $firstHasLa ? 'green' : 'red',
                                                                        $firstHasAux ? 'green' : 'red',
                                                                    ];
                                                                }
                                                                $barcodeKainOptional = $proses->barcode_kain_optional ?? false;
                                                                if ($barcodeKainOptional) {
                                                                    $blocks = ['D', 'A'];
                                                                    $blockColors = [
                                                                        $firstHasLa ? 'green' : 'red',
                                                                        $firstHasAux ? 'green' : 'red',
                                                                    ];
                                                                } else {
                                                                    $blocks = (($proses->mode ?? 'greige') === 'finish') ? ['F', 'D', 'A'] : ['G', 'D', 'A'];
                                                                }
                                                                $alarmOnState = \Illuminate\Support\Facades\Cache::get("iot:mesin:{$proses->mesin_id}:alarm_on_state", null);
                                                                if ($proses->mulai && !$proses->selesai) {
                                                                    $light = $alarmOnState ? 'yellow' : 'green';
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
                                                                    if (in_array($proses->jenis, ['Reproses', 'Reproses Makloon'])) {
                                                                        $hasPendingReprocessApproval = collect(
                                                                            $proses->approvals,
                                                                        )->contains(function ($appr) {
                                                                            return $appr->status === 'pending' &&
                                                                                $appr->action === 'create_reprocess' &&
                                                                                ($appr->type === 'FM' ||
                                                                                    $appr->type === 'VP');
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
                                                                    $laToppingScanned = 0;
                                                                    $auxToppingScanned = 0;
                                                                    foreach ($proses->approvals ?? [] as $a) {
                                                                        if (($a->action ?? '') === 'topping_la' && ($a->status ?? '') === 'approved' && $a->barcodeLas && $a->barcodeLas->where('cancel', false)->count() > 0)
                                                                            $laToppingScanned++;
                                                                        if (($a->action ?? '') === 'topping_aux' && ($a->status ?? '') === 'approved' && $a->barcodeAuxs && $a->barcodeAuxs->where('cancel', false)->count() > 0)
                                                                            $auxToppingScanned++;
                                                                    }
                                                                    $laInitialScanned = isset($proses->details) && $proses->details->isNotEmpty()
                                                                        ? $proses->details->flatMap(fn($d) => $d->barcodeLas ?? [])->where('cancel', false)->where('approval_id', null)->pluck('barcode')->unique()->count()
                                                                        : 0;
                                                                    $auxInitialScanned = isset($proses->details) && $proses->details->isNotEmpty()
                                                                        ? $proses->details->flatMap(fn($d) => $d->barcodeAuxs ?? [])->where('cancel', false)->where('approval_id', null)->pluck('barcode')->unique()->count()
                                                                        : 0;
                                                                    $laComplete = ($laInitialScanned + $laToppingScanned) >= (($proses->qty_dye_stuff ?? 0) + $laToppingRequired);
                                                                    $auxComplete = ($auxInitialScanned + $auxToppingScanned) >= (($proses->qty_aux ?? 0) + $auxToppingRequired);
                                                                    $laInitialComplete = $laInitialScanned >= ($proses->qty_dye_stuff ?? 0);
                                                                    $auxInitialComplete = $auxInitialScanned >= ($proses->qty_aux ?? 0);
                                                                    if ($proses->jenis !== 'Maintenance') {
                                                                        if ($barcodeKainOptional) {
                                                                            $blockColors = [$firstHasLa ? 'green' : 'red', $firstHasAux ? 'green' : 'red'];
                                                                        } else {
                                                                            $blockColors[0] = $firstKainColor;
                                                                            $blockColors[1] = $firstHasLa ? 'green' : 'red';
                                                                            $blockColors[2] = $firstHasAux ? 'green' : 'red';
                                                                        }
                                                                    }
                                                                } else {
                                                                    $pendingToppingLa = $hasToppingLa = $hasToppingAux = false;
                                                                    $tdColor = $taColor = null;
                                                                    $laComplete = $hasBarcodeLa;
                                                                    $auxComplete = $hasBarcodeAux;
                                                                    $laInitialComplete = $hasBarcodeLa;
                                                                    $auxInitialComplete = $hasBarcodeAux;
                                                                    if ($proses->jenis !== 'Maintenance') {
                                                                        if ($barcodeKainOptional) {
                                                                            $blockColors = [$firstHasLa ? 'green' : 'red', $firstHasAux ? 'green' : 'red'];
                                                                        } else {
                                                                            $blockColors[0] = $firstKainColor;
                                                                            $blockColors[1] = $firstHasLa ? 'green' : 'red';
                                                                            $blockColors[2] = $firstHasAux ? 'green' : 'red';
                                                                        }
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
                                                                    $barcodeKainOptionalLocal = $barcodeKainOptional ?? false;
                                                                    if ((bool) ($proses->is_break ?? false)) {
                                                                        $bg = '#757575';
                                                                    } elseif ($proses->jenis !== 'Maintenance') {
                                                                        $kainComplete = $barcodeKainOptionalLocal || $hasBarcodeKain;
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
                                                                } elseif ($proses->mulai && (bool) ($proses->is_break ?? false)) {
                                                                    $mulai = \Carbon\Carbon::parse($proses->mulai);
                                                                    $breakAt = $proses->break_at ? \Carbon\Carbon::parse($proses->break_at) : \Carbon\Carbon::now();
                                                                    $cycle_time_actual = max(0, $mulai->diffInSeconds($breakAt, false));
                                                                    $cycle_time_actual_str = detikKeWaktu($cycle_time_actual);
                                                                }
                                                            @endphp
                                                            <div class="status-card history-card draggable" draggable="false"
                                                                style="background: {{ $gradient }}; background-repeat: no-repeat; background-size: cover; border-radius: 0; color: #fff; margin: 5px 0 0 0; padding: 2px 2px; cursor: default; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"
                                                                data-proses='@json($proses)' data-proses-id="{{ $proses->id }}"
                                                                data-can-move="0"
                                                                data-has-pending-reprocess="{{ $hasPendingReprocessApproval ? '1' : '0' }}"
                                                                data-bg-color="{{ $bg }}">
                                                                {{-- Header --}}
                                                                <div class="card-header"
                                                                    style="display: flex; flex-direction: row; align-items: center; padding: 0 10px 2px 10px; gap: 0; border-bottom: none;">
                                                                    <div
                                                                        style="{{ $proses->jenis === 'Maintenance' ? 'flex: 0 0 auto;' : 'flex: 1;' }} text-align: left;">
                                                                        <span class="status-type"
                                                                            style="font-weight: bold; font-size: 32px; color: #111; text-shadow: 0 1px 4px #fff8;">
                                                                            {{ $type }}
                                                                        </span>
                                                                    </div>
                                                                    <div class="status-header-center"
                                                                        style="{{ $proses->jenis === 'Maintenance' ? 'flex: 1; padding: 0 4px;' : 'flex: 2;' }} text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                                                        @if ($proses->jenis === 'Maintenance')
                                                                            <div class="op-row" data-detail-id=""
                                                                                style="flex: 1; width: auto; padding: 4px 8px; margin: 0; display: flex; align-items: center; justify-content: center; gap: 6px; border-radius: 8px; cursor: pointer; background: rgba(255,255,255,0.22); border: 1.5px solid rgba(0,0,0,0.18);">
                                                                                <div class="op-row-noop"
                                                                                    style="font-weight: 800; color: #111; font-size: 19px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin: 0;">
                                                                                    MAINTENANCE
                                                                                </div>
                                                                            </div>
                                                                        @else
                                                                            <div class="op-gda-container" data-detail-id="{{ $firstDetail->id ?? '' }}"
                                                                                style="display: flex; justify-content: center; align-items: center; gap: 6px;">
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
                                                                                    $tdStyle = $tdColor === 'yellow' ? 'background:#fff9c4;color:#111;border:2.5px solid #f9a825' : ($tdColor === 'red' ? 'background:#ffb3b3;color:#111;border:2.5px solid #c62828' : ($tdColor === 'green' ? 'background:#d4f8e8;color:#111;border:2.5px solid #43a047' : ($tdColor === 'inactive' ? 'background:#eceff1;color:#555;border:2.5px solid #90a4ae' : '')));
                                                                                    $taStyle = $taColor === 'yellow' ? 'background:#fff9c4;color:#111;border:2.5px solid #f9a825' : ($taColor === 'red' ? 'background:#ffb3b3;color:#111;border:2.5px solid #c62828' : ($taColor === 'green' ? 'background:#d4f8e8;color:#111;border:2.5px solid #43a047' : ($taColor === 'inactive' ? 'background:#eceff1;color:#555;border:2.5px solid #90a4ae' : '')));
                                                                                @endphp
                                                                                @if($hasToppingLa ?? false)
                                                                                    <span class="topping-indicator topping-td"
                                                                                        data-block-type="TD"
                                                                                        title="Topping Dyes - {{ \App\Services\ProsesStatusService::toppingIndicatorTitle($tdColor, 'td') }}"
                                                                                        style="display: inline-block; {{ $tdStyle }}; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TD</span>
                                                                                @endif
                                                                                @if($hasToppingAux ?? false)
                                                                                    <span class="topping-indicator topping-ta"
                                                                                        data-block-type="TA"
                                                                                        title="Topping Auxiliaries - {{ \App\Services\ProsesStatusService::toppingIndicatorTitle($taColor, 'ta') }}"
                                                                                        style="display: inline-block; {{ $taStyle }}; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TA</span>
                                                                                @endif
                                                                            </div>
                                                                            {{-- Indikator di bawah GDA pertama (Sinyal IoT, Pinjam Mesin, Catatan) --}}
                                                                            <div class="card-indicators-bar proses-indicators-bar" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 3px;">
                                                                                <div class="pinjam-mesin-indicator" style="{{ ($isPinjamMesinHist ?? false) ? 'display: flex;' : 'display: none;' }} justify-content: center; align-items: center;">
                                                                                    <span class="badge-pinjam-mesin"
                                                                                        title="Pinjam Mesin Aktif{{ !empty($proses->pinjam_mesin_alasan) ? ': ' . $proses->pinjam_mesin_alasan : '' }}"
                                                                                        style="font-weight: 800; font-size: 12px; color: #111; background: #ffeb3b; border-radius: 4px; padding: 1px 5px; border: 1px solid rgba(0,0,0,0.25); box-shadow: 0 1px 2px rgba(0,0,0,0.25); letter-spacing: 0.5px; line-height: 1.2; text-align: center;">
                                                                                        PM
                                                                                    </span>
                                                                                </div>
                                                                                <span class="note-icon-slot">
                                                                                    @if(!empty($proses->note))
                                                                                        <i class="fas fa-sticky-note text-warning icon-has-note"
                                                                                            title="Catatan: {{ Str::limit($proses->note, 60) }}"
                                                                                            style="font-size: 15px; vertical-align: middle; text-shadow: 0 1px 2px #000; cursor: pointer;"></i>
                                                                                    @endif
                                                                                </span>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                    <div class="status-header-right"
                                                                        style="{{ $proses->jenis === 'Maintenance' ? 'flex: 0 0 auto;' : 'flex: 1;' }} display: flex; flex-direction: column; align-items: flex-end; justify-content: center;">
                                                                        <div
                                                                            style="display: flex; align-items: center; justify-content: flex-end;">
                                                                            <div class="status-light {{ $light == 'green' ? 'running-light' : ($light == 'yellow' ? 'running-light-yellow' : '') }}"
                                                                                style="width: 24px; height: 24px; border-radius: 50%; background: {{ $light == 'green' ? '#00ff1a' : ($light == 'yellow' ? '#ffeb3b' : '#ff2a2a') }}; display: inline-block; border: 3px solid #fff; box-shadow: 0 0 0 0 transparent; transition: background 0.2s;">
                                                                            </div>
                                                                        </div>
                                                                        @php
                                                                            $isBreakHist = (bool) ($proses->is_break ?? false);
                                                                        @endphp
                                                                        <div class="break-proses-indicator"
                                                                            style="{{ ($isBreakHist ?? false) ? 'display: flex;' : 'display: none;' }} justify-content: center; align-items: center; width: 24px; margin-top: 2px;">
                                                                            <span class="badge-break-proses"
                                                                                title="Break Aktif{{ !empty($proses->break_alasan) ? ': ' . $proses->break_alasan : '' }}"
                                                                                style="font-weight: 800; font-size: 13px; color: #fff; background: #424242; border-radius: 4px; padding: 1px 3px; letter-spacing: 0.5px; line-height: 1; text-align: center;">
                                                                                BR
                                                                            </span>
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
                                                                    @if ($proses->jenis === 'Maintenance')
                                                                        {{-- Indikator Maintenance: Di bawah kotak Maintenance, di atas cycle time --}}
                                                                        <div class="card-indicators-bar maintenance-indicators-bar" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin: 4px 0 6px 0;">
                                                                            <div class="pinjam-mesin-indicator" style="{{ ($isPinjamMesinHist ?? false) ? 'display: flex;' : 'display: none;' }} justify-content: center; align-items: center;">
                                                                                <span class="badge-pinjam-mesin"
                                                                                    title="Pinjam Mesin Aktif{{ !empty($proses->pinjam_mesin_alasan) ? ': ' . $proses->pinjam_mesin_alasan : '' }}"
                                                                                    style="font-weight: 800; font-size: 12px; color: #111; background: #ffeb3b; border-radius: 4px; padding: 1px 5px; border: 1px solid rgba(0,0,0,0.25); box-shadow: 0 1px 2px rgba(0,0,0,0.25); letter-spacing: 0.5px; line-height: 1.2; text-align: center;">
                                                                                    PM
                                                                                </span>
                                                                            </div>
                                                                            <span class="note-icon-slot">
                                                                                @if(!empty($proses->note))
                                                                                    <i class="fas fa-sticky-note text-warning icon-has-note"
                                                                                        title="Catatan: {{ Str::limit($proses->note, 60) }}"
                                                                                        style="font-size: 15px; vertical-align: middle; text-shadow: 0 1px 2px #000; cursor: pointer;"></i>
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                    @else
                                                                        <div class="op-list">
                                                                            @if ($detailList->isEmpty())
                                                                                {{-- Tidak ada detail --}}
                                                                                <div class="op-row" data-detail-id="">
                                                                                    <div class="op-row-noop"
                                                                                        style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8;">
                                                                                        -
                                                                                    </div>
                                                                                </div>
                                                                            @elseif ($isMultipleOp)
                                                                                {{-- Multiple OP: OP pertama dengan header lengkap, OP kedua+
                                                                                dengan garis pemisah --}}
                                                                                @php
                                                                                    $firstDetail = $detailList->first();
                                                                                @endphp
                                                                                {{-- OP Pertama: Detail lengkap dengan No OP dan Info --}}
                                                                                <div class="op-row" data-detail-id="{{ $firstDetail->id }}">
                                                                                    <div class="op-row-noop"
                                                                                        style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin-bottom: 4px;">
                                                                                        {{ $firstDetail->no_op ?? '-' }}
                                                                                    </div>
                                                                                    @if($firstDetail->customer)
                                                                                        <div
                                                                                            style="font-size: 16px; margin: 2px 0; color: #111; font-weight: bold; text-shadow: 0 1px 2px #fff8;">
                                                                                            {{ $firstDetail->customer }}
                                                                                        </div>
                                                                                    @endif
                                                                                    <div class="op-row-info"
                                                                                        style="margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
                                                                                        <div>
                                                                                            {{ $firstDetail->warna ?? 'Warna' }} -
                                                                                            {{ $firstDetail->kategori_warna ?? 'Kategori' }} -
                                                                                            {{ $firstDetail->kode_warna ?? 'Kode' }}
                                                                                        </div>
                                                                                        <div>{{ $firstDetail->konstruksi ?? 'Konstruksi' }}
                                                                                        </div>
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
                                                                                        $subHasLa = isset($d->barcodeLas)
                                                                                            ? $d->barcodeLas->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_dye_stuff ?? 0)
                                                                                            : false;
                                                                                        $subHasAux = isset($d->barcodeAuxs)
                                                                                            ? $d->barcodeAuxs->where('cancel', false)->where('approval_id', null)->count() >= ($proses->qty_aux ?? 0)
                                                                                            : false;
                                                                                        $subMap = $barcodeKainOptional
                                                                                            ? [$blocks[0] => $subHasLa ? 'green' : 'red', $blocks[1] => $subHasAux ? 'green' : 'red']
                                                                                            : [$blocks[0] => $subKainColor, $blocks[1] => $subHasLa ? 'green' : 'red', $blocks[2] => $subHasAux ? 'green' : 'red'];
                                                                                    @endphp
                                                                                    {{-- Garis pemisah --}}
                                                                                    <div
                                                                                        style="border-top: 1px solid rgba(255,255,255,0.3); margin: 8px 0; padding-top: 8px;">
                                                                                    </div>
                                                                                    {{-- GDA/FDA + TD/TA per OP (di luar detail OP, ukuran sama
                                                                                    dengan header) --}}
                                                                                    <div class="op-gda-container" data-detail-id="{{ $d->id }}"
                                                                                        style="display: flex; justify-content: center; gap: 6px; margin-bottom: 6px;">
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
                                                                                            <span class="topping-indicator topping-td"
                                                                                                data-block-type="TD"
                                                                                                title="Topping Dyes - {{ \App\Services\ProsesStatusService::toppingIndicatorTitle($tdColor, 'td') }}"
                                                                                                style="display: inline-block; {{ $tdStyle }}; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TD</span>
                                                                                        @endif
                                                                                        @if($proses->jenis !== 'Maintenance' && ($hasToppingAux ?? false))
                                                                                            <span class="topping-indicator topping-ta"
                                                                                                data-block-type="TA"
                                                                                                title="Topping Auxiliaries - {{ \App\Services\ProsesStatusService::toppingIndicatorTitle($taColor, 'ta') }}"
                                                                                                style="display: inline-block; {{ $taStyle }}; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TA</span>
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
                                                                                        <div class="op-row-info"
                                                                                            style="margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
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
                                                                                        style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8; margin-bottom: 4px;">
                                                                                        {{ $singleDetail->no_op ?? '-' }}
                                                                                    </div>
                                                                                    @if($singleDetail->customer)
                                                                                        <div
                                                                                            style="font-size: 16px; margin: 2px 0; color: #111; font-weight: bold; text-shadow: 0 1px 2px #fff8;">
                                                                                            {{ $singleDetail->customer }}
                                                                                        </div>
                                                                                    @endif
                                                                                    <div class="op-row-info"
                                                                                        style="margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
                                                                                        <div>
                                                                                            {{ $singleDetail->warna ?? 'Warna' }} -
                                                                                            {{ $singleDetail->kategori_warna ?? 'Kategori' }} -
                                                                                            {{ $singleDetail->kode_warna ?? 'Kode' }}
                                                                                        </div>
                                                                                        <div>{{ $singleDetail->konstruksi ?? 'Konstruksi' }}
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @endif
                                                                    @if ((bool) ($proses->is_break ?? false))
                                                                        <div class="alert alert-dark py-1 px-2 mb-1 text-center font-weight-bold"
                                                                            style="font-size: 11px; background: rgba(0,0,0,0.4); border: 1px dashed #fff; color: #fff;">
                                                                            <i class="fas fa-pause mr-1"></i>BREAK
                                                                            @if(!empty($proses->break_alasan))
                                                                                <div class="small font-italic text-truncate mt-1"
                                                                                    style="max-width: 100%;">
                                                                                    "{{ $proses->break_alasan }}"
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @endif
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
                                                                                    $now = ((bool) ($proses->is_break ?? false) && $proses->break_at)
                                                                                        ? \Carbon\Carbon::parse($proses->break_at)
                                                                                        : \Carbon\Carbon::now();
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
                                            <div class="proses-aktif-container" data-section="produksi">
                                                @foreach ($prosesAktif as $proses)
                                                    @include('partials.dashboard.status_card', ['proses' => $proses])
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        {{-- Placeholder columns for "Airport Board" feel (clean groups across all sizes) --}}
                        @php
                            $totalMesin = count($mesinList);
                            $multiple = 120; // Highly divisible multiple for flexible layouts
                            $modulo = $totalMesin % $multiple;
                            $placeholders = $modulo > 0 ? ($multiple - $modulo) : 0;
                        @endphp
                        @if ($placeholders > 0)
                            @for ($i = 0; $i < $placeholders; $i++)
                                <div class="machine-column placeholder-column">
                                    <div style="padding: 2px; width: 100%;">
                                        <div style="height: 100%; border-radius: 0; background: transparent;">
                                            {{-- Empty white space as requested --}}
                                        </div>
                                    </div>
                                </div>
                            @endfor
                        @endif
                    </div>
                </div>

                <!-- Floating Navigation Buttons -->
                <div class="slideshow-nav-container">
                    <button type="button" id="btn-slide-prev" class="nav-btn" title="Halaman Sebelumnya">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" id="btn-slide-next" class="nav-btn" title="Halaman Berikutnya">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </section>
        <!-- Modal Filter Dashboard (12 Kriteria) -->
        <div class="modal fade" id="modalFilterDashboard" tabindex="-1" aria-labelledby="modalFilterDashboardLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 1250px;">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formFilterDashboard" action="{{ url('dashboard') }}" method="GET">
                        <!-- Header -->
                        <div class="modal-header bg-gradient-primary text-white py-3 px-4"
                            style="background: linear-gradient(135deg, #0052cc 0%, #002b80 100%);">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-sliders-h mr-2" style="font-size: 1.4rem;"></i>
                                <div>
                                    <h5 class="modal-title fw-bold mb-0" id="modalFilterDashboardLabel"
                                        style="font-weight: 700;">Filter Pencarian Dashboard</h5>
                                    <small class="text-white-50">Filter otomatis menyesuaikan dengan data proses pada tab
                                        yang sedang aktif</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span
                                    class="badge badge-light px-3 py-2 text-primary font-weight-bold shadow-sm rounded-pill mr-3"
                                    id="filter-modal-active-badge" style="font-size: 0.85rem;">
                                    <i class="fas fa-industry mr-1"></i> Mode Production
                                </span>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"
                                    style="opacity: 0.85;">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="modal-body py-4 px-4" style="background: #f8fafc; max-height: 75vh; overflow-y: auto;">
                            <!-- Action Quick Bar -->
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <div class="text-muted" style="font-size: 0.9rem;">
                                    <i class="fas fa-info-circle text-primary mr-1"></i> Menampilkan opsi filter untuk:
                                    <strong id="filter-modal-current-mode-label" class="text-primary">Mode Production
                                        (Proses Berjalan / Belum Selesai)</strong>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-xs btn-outline-secondary"
                                        id="btn-clear-all-filter-inputs">
                                        <i class="fas fa-eraser mr-1"></i> Kosongkan Semua Pilihan
                                    </button>
                                </div>
                            </div>

                            <div class="row">
                                <!-- 1. Mesin -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-cogs text-primary mr-1"></i> Mesin
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_mesin"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_mesin"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="mesin[]" id="filter_modal_mesin"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Mesin">
                                                @foreach ($mesins as $m)
                                                    <option value="{{ $m->id }}" {{ in_array($m->id, $selectedMesinArr ?? []) ? 'selected' : '' }}>
                                                        {{ $m->jenis_mesin }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. Customer -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-building text-info mr-1"></i> Customer
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_customer"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_customer"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="customer[]" id="filter_modal_customer"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Customer">
                                                @foreach ($filterOptions['customers'] ?? [] as $cust)
                                                    <option value="{{ $cust }}" {{ in_array($cust, $selectedCustomerArr ?? []) ? 'selected' : '' }}>
                                                        {{ $cust }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Marketing -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-user-tie text-info mr-1"></i> Marketing
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_marketing"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_marketing"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="marketing[]" id="filter_modal_marketing"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Marketing">
                                                @foreach ($filterOptions['marketings'] ?? [] as $mkt)
                                                    <option value="{{ $mkt }}" {{ in_array($mkt, $selectedMarketingArr ?? []) ? 'selected' : '' }}>
                                                        {{ $mkt }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. Warna -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-palette text-success mr-1"></i> Warna
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_warna"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_warna"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="warna[]" id="filter_modal_warna"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Warna">
                                                @foreach ($filterOptions['warnas'] ?? [] as $wrn)
                                                    <option value="{{ $wrn }}" {{ in_array($wrn, $selectedWarnaArr ?? []) ? 'selected' : '' }}>
                                                        {{ $wrn }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. Kategori Warna -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-swatchbook text-success mr-1"></i> Kategori Warna
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_kategori_warna"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_kategori_warna"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="kategori_warna[]" id="filter_modal_kategori_warna"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Kategori Warna">
                                                @foreach ($filterOptions['kategori_warnas'] ?? [] as $kat)
                                                    <option value="{{ $kat }}" {{ in_array($kat, $selectedKategoriWarnaArr ?? []) ? 'selected' : '' }}>
                                                        {{ $kat }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 6. Kode Warna -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-barcode text-success mr-1"></i> Kode Warna
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_kode_warna"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_kode_warna"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="kode_warna[]" id="filter_modal_kode_warna"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Kode Warna">
                                                @foreach ($filterOptions['kode_warnas'] ?? [] as $kdw)
                                                    <option value="{{ $kdw }}" {{ in_array($kdw, $selectedKodeWarnaArr ?? []) ? 'selected' : '' }}>
                                                        {{ $kdw }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 7. Handfeel -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-hand-paper text-secondary mr-1"></i> Handfeel
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_hfeel"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_hfeel"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="hfeel[]" id="filter_modal_hfeel"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Handfeel">
                                                @foreach ($filterOptions['hfeels'] ?? [] as $hf)
                                                    <option value="{{ $hf }}" {{ in_array($hf, $selectedHfeelArr ?? []) ? 'selected' : '' }}>
                                                        {{ $hf }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 8. Gramasi -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-weight-hanging text-secondary mr-1"></i> Gramasi
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_gramasi"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_gramasi"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="gramasi[]" id="filter_modal_gramasi"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Gramasi">
                                                @foreach ($filterOptions['gramasis'] ?? [] as $grm)
                                                    <option value="{{ $grm }}" {{ in_array($grm, $selectedGramasiArr ?? []) ? 'selected' : '' }}>
                                                        {{ $grm }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 9. No OP -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-file-invoice text-warning mr-1"></i> No OP
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_no_op"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_no_op"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="no_op[]" id="filter_modal_no_op"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua No OP">
                                                @foreach ($filterOptions['no_ops'] ?? [] as $op)
                                                    <option value="{{ $op }}" {{ in_array($op, $selectedNoOpArr ?? []) ? 'selected' : '' }}>
                                                        {{ $op }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 10. No Partai -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-layer-group text-warning mr-1"></i> No Partai
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_no_partai"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_no_partai"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="no_partai[]" id="filter_modal_no_partai"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua No Partai">
                                                @foreach ($filterOptions['no_partais'] ?? [] as $npr)
                                                    <option value="{{ $npr }}" {{ in_array($npr, $selectedNoPartaiArr ?? []) ? 'selected' : '' }}>
                                                        {{ $npr }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 11. Konstruksi -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-th text-dark mr-1"></i> Konstruksi
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_konstruksi"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_konstruksi"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="konstruksi[]" id="filter_modal_konstruksi"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Konstruksi">
                                                @foreach ($filterOptions['konstruksis'] ?? [] as $kst)
                                                    <option value="{{ $kst }}" {{ in_array($kst, $selectedKonstruksiArr ?? []) ? 'selected' : '' }}>
                                                        {{ $kst }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- 12. Kode Material -->
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 8px;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label class="font-weight-bold text-dark mb-0" style="font-size: 0.88rem;">
                                                    <i class="fas fa-cubes text-dark mr-1"></i> Kode Material
                                                </label>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn-filter-select-all text-primary"
                                                        data-target="#filter_modal_kode_material"
                                                        style="font-size: 0.75rem;">Semua</a>
                                                    <span class="text-muted" style="font-size: 0.75rem;">|</span>
                                                    <a href="javascript:void(0)" class="btn-filter-clear text-danger"
                                                        data-target="#filter_modal_kode_material"
                                                        style="font-size: 0.75rem;">Clear</a>
                                                </div>
                                            </div>
                                            <select name="kode_material[]" id="filter_modal_kode_material"
                                                class="form-control select2-dashboard-filter" multiple style="width:100%;"
                                                data-placeholder="Semua Kode Material">
                                                @foreach ($filterOptions['kode_materials'] ?? [] as $kdm)
                                                    <option value="{{ $kdm }}" {{ in_array($kdm, $selectedKodeMaterialArr ?? []) ? 'selected' : '' }}>
                                                        {{ $kdm }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="modal-footer d-flex justify-content-between bg-light py-3 px-4">
                            <div>
                                <a href="{{ url('dashboard') }}" class="btn btn-outline-danger" style="font-weight: 600;">
                                    <i class="fas fa-trash-alt mr-1"></i> Reset Semua Filter
                                </a>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal"
                                    style="font-weight: 600;">
                                    Batal
                                </button>
                                <button type="submit" class="btn btn-primary px-4 shadow-sm" style="font-weight: 700;">
                                    <i class="fas fa-check-circle mr-1"></i> Terapkan Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Proses -->
        <div class="modal fade" id="modalProses" tabindex="-1" aria-labelledby="modalProsesLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 1200px;">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formProses" action="{{ route('proses.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="mode" id="proses_mode" value="greige">
                        <!-- Header -->
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fw-bold" id="modalProsesLabel">Tambah Proses</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="modal-body py-3 px-4">
                            @if (isset($errors) && $errors->any())
                                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                    <strong><i class="fas fa-exclamation-circle"></i> Error validasi</strong>
                                    <ul class="mb-0 mt-2 pl-3">
                                        @foreach ($errors->all() as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            @endif
                            <!-- Pilihan Mode (di dalam modal) -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Mode</label>
                                    <div class="d-flex gap-4 flex-wrap">
                                        <label class="d-flex align-items-center mr-3" style="cursor:pointer;">
                                            <input type="radio" name="proses_mode_radio" id="proses_mode_greige"
                                                value="greige" class="mr-2" checked>
                                            <span>Greige</span>
                                            <small class="text-muted ml-1">(Block GDA)</small>
                                        </label>
                                        <label class="d-flex align-items-center" style="cursor:pointer;">
                                            <input type="radio" name="proses_mode_radio" id="proses_mode_finish"
                                                value="finish" class="mr-2">
                                            <span>Finish</span>
                                            <small class="text-muted ml-1">(Block FDA)</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-3">
                            <div class="row">

                                <!-- Jenis Proses (Greige: Produksi/Maintenance/Reproses, Finish: hanya Reproses) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Jenis Proses</label>
                                        <select name="jenis" id="jenis" class="form-control" required>
                                            <option value="Produksi" selected id="jenis-option-produksi">Produksi</option>
                                            <option value="Maintenance" id="jenis-option-maintenance">Maintenance</option>
                                            <option value="Reproses" id="jenis-option-reproses">Reproses</option>
                                            {{-- Jenis Proses Makloon dinonaktifkan sementara --}}
                                            {{-- <option value="Proses Makloon" id="jenis-option-proses-makloon">Proses Makloon</option> --}}
                                            {{-- <option value="Reproses Makloon" id="jenis-option-reproses-makloon">Reproses Makloon</option> --}}
                                        </select>
                                        <small id="reprocess-hint-greige" class="form-text text-info mt-1"
                                            style="display:none;">
                                            <i class="fas fa-info-circle"></i> Reproses hanya untuk No OP &amp; No Partai
                                            yang pernah dipakai pada jenis proses Produksi.
                                        </small>
                                        {{-- Hint Makloon dinonaktifkan sementara --}}
                                        {{-- <small id="makloon-hint-op" class="form-text text-warning mt-1"
                                            style="display:none;">
                                            <i class="fas fa-info-circle"></i> Format No OP untuk Makloon wajib berawalan <strong>007</strong> (contoh: 007000000001).
                                        </small> --}}
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
                                        <select name="mesin_id" id="mesin_id" class="form-control select2"
                                            style="width: 100%;" required>
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

                                <!-- Dye Stuff -->
                                <div class="col-md-6 hide-if-maintenance">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Dye Stuff</label>
                                        <select name="qty_dye_stuff" id="qty_dye_stuff" class="form-control">
                                            <option value="0" selected>0 (Tidak Ada)</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                        </select>
                                    </div>
                                    <!-- Container Breakdown Jam Dye Stuff -->
                                    <div id="dye_stuff_schedule_container" class="mt-2 mb-3 p-3 bg-light rounded border"
                                        style="display: none;">
                                        <label class="form-label fw-semibold text-primary mb-2" style="font-size: 13px;">
                                            <i class="fas fa-clock mr-1"></i> Breakdown Jam Input Dye Stuff
                                        </label>
                                        <div id="dye_stuff_schedule_inputs"></div>
                                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                                            * Format: <code>JJ:MM:DD</code> (Contoh: <code>03:00:00</code>), tidak boleh
                                            melebihi Cycle Time.
                                        </small>
                                    </div>
                                </div>

                                <!-- AUX -->
                                <div class="col-md-6 hide-if-maintenance">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">AUX</label>
                                        <select name="qty_aux" id="qty_aux" class="form-control">
                                            <option value="0" selected>0 (Tidak Ada)</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                        </select>
                                    </div>
                                    <!-- Container Breakdown Jam AUX -->
                                    <div id="aux_schedule_container" class="mt-2 mb-3 p-3 bg-light rounded border"
                                        style="display: none;">
                                        <label class="form-label fw-semibold text-success mb-2" style="font-size: 13px;">
                                            <i class="fas fa-clock mr-1"></i> Breakdown Jam Input AUX
                                        </label>
                                        <div id="aux_schedule_inputs"></div>
                                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                                            * Format: <code>JJ:MM:DD</code> (Contoh: <code>01:30:00</code>), tidak boleh
                                            melebihi Cycle Time.
                                        </small>
                                    </div>
                                </div>

                                <!-- Container untuk DetailProses (Single/Multiple) -->
                                <div class="col-12 hide-if-maintenance" id="detail-proses-container">
                                    <!-- Single OP Form (Default) -->
                                    <div class="detail-proses-item" data-index="0">
                                        <div class="card border mb-3" style="background: #f8f9fa;">
                                            <div
                                                class="card-header bg-light d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 fw-bold">Detail OP #1</h6>
                                                <button type="button"
                                                    class="btn btn-sm btn-danger remove-detail-btn ml-auto"
                                                    style="display: none;">
                                                    <i class="fas fa-times"></i> Hapus
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <!-- No OP -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">No. OP</label>
                                                            <select name="details[0][no_op]"
                                                                class="form-control select2-detail no-op-select"
                                                                style="width: 100%;" required>
                                                                <option value="" disabled>-- Pilih No. OP --</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- No Partai -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">No. Partai</label>
                                                            <select name="details[0][no_partai]"
                                                                class="form-control select2-detail no-partai-select"
                                                                style="width: 100%;" required>
                                                                <option value="" disabled>-- Pilih No. Partai --</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Customer -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Customer</label>
                                                            <input type="text" name="details[0][customer]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Marketing -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Marketing</label>
                                                            <input type="text" name="details[0][marketing]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Item OP (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Item OP</label>
                                                            <input type="text" name="details[0][item_op]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Kode Material (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Kode Material</label>
                                                            <input type="text" name="details[0][kode_material]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Konstruksi (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Konstruksi</label>
                                                            <input type="text" name="details[0][konstruksi]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Gramasi (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Gramasi</label>
                                                            <input type="text" name="details[0][gramasi]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Lebar (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Lebar</label>
                                                            <input type="text" name="details[0][lebar]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Hand Feel (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Hand Feel</label>
                                                            <input type="text" name="details[0][hfeel]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Warna (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Warna</label>
                                                            <input type="text" name="details[0][warna]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Kode Warna (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Kode Warna</label>
                                                            <input type="text" name="details[0][kode_warna]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Kategori Warna (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Kategori Warna</label>
                                                            <input type="text" name="details[0][kategori_warna]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- QTY (readonly, 2 digit koma) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Quantity</label>
                                                            <input type="text" name="details[0][qty]"
                                                                class="form-control auto-field-detail" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Roll (readonly) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="form-label fw-semibold">Roll</label>
                                                            <input type="text" name="details[0][roll]"
                                                                class="form-control auto-field-detail" readonly>
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
                        <!-- Banner Peringatan Menunggu Mesin Berhenti -->
                        <div id="alert-waiting-stop" class="alert alert-warning py-2 px-3 mb-3 d-none shadow-sm"
                            style="border-left: 5px solid #f57c00; background-color: #fff3e0;">
                            <div class="d-flex align-items-center">
                                <div class="mr-3 text-warning">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold text-dark" id="alert-waiting-stop-title"
                                        style="font-size: 14px;">
                                        Sedang Menunggu Mesin Berhenti
                                    </div>
                                    <div class="small text-muted" id="alert-waiting-stop-desc">
                                        Instruksi selesai telah dikirim. Menunggu verifikasi unload atau operator di
                                        lapangan mematikan mesin sebelum proses selesai ke history.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <table class="table table-bordered table-sm mb-0">
                            <tbody id="detail-proses-body">
                                <!-- Diisi via JS -->
                            </tbody>
                        </table>

                        <!-- Kotak Catatan Proses (Optional) -->
                        <div class="mt-3 p-3 bg-light rounded border shadow-sm" id="container-proses-note">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="proses-note-text" class="form-label font-weight-bold mb-0 text-dark">
                                    <i class="fas fa-sticky-note text-warning mr-1"></i> Catatan Proses (Optional)
                                </label>
                                <span class="badge badge-secondary" id="badge-proses-status-note"></span>
                            </div>
                            <textarea class="form-control bg-white" id="proses-note-text" rows="3"
                                placeholder="Tambahkan catatan khusus untuk proses ini (opsional)..."
                                maxlength="2000"></textarea>
                            <div class="mt-2 text-right" id="proses-note-actions">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-save-proses-note">
                                    <i class="fas fa-save mr-1"></i>Simpan Catatan
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between px-4">
                        <div>
                            @if ($canEditProses ?? true)
                                <button type="button" class="btn btn-primary btn-edit-proses mr-2">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                            @endif
                            @if ($canFinishMaintenance ?? in_array($userRole ?? '', ['super_admin', 'kepala_shift', 'kepala_ruangan']))
                                <button type="button" class="btn btn-success btn-finish-maintenance d-none mr-2">
                                    <i class="fas fa-check mr-1"></i>Proses Selesai
                                </button>
                            @endif
                            @if (in_array($userRole ?? '', ['super_admin', 'kepala_shift']) || (($userRole ?? '') === 'kepala_ruangan' && \App\Services\AbsenService::isKashiftOff()))
                                <button type="button" class="btn btn-danger btn-finish-force d-none mr-2"
                                    title="Selesaikan proses saat ini secara paksa dan lanjutkan ke antrian berikutnya">
                                    <i class="fas fa-check-double mr-1"></i>Proses Selesai
                                </button>
                            @endif
                            @if (in_array($userRole ?? '', ['super_admin', 'kepala_shift', 'kepala_ruangan']))
                                <button type="button" class="btn btn-outline-warning btn-cancel-stop-request d-none mr-2"
                                    title="Batalkan permohonan selesai dan lanjutkan proses kembali">
                                    <i class="fas fa-undo mr-1"></i>Batal Selesai
                                </button>
                            @endif
                            @if ($canPinjamMesin ?? in_array($userRole ?? '', ['super_admin', 'kepala_ruangan', 'kepala_shift', 'operator']))
                                <button type="button" class="btn btn-info btn-pinjam-mesin d-none mr-2 text-white">
                                    <i class="fas fa-exchange-alt mr-1"></i>Pinjam Mesin
                                </button>
                            @endif
                            @if (in_array($userRole ?? '', ['super_admin', 'kepala_ruangan', 'kepala_shift', 'operator', 'ppic']))
                                <button type="button" class="btn btn-outline-info btn-preview-pinjam-mesin d-none mr-2"
                                    title="Lihat riwayat peminjaman mesin pada proses ini">
                                    <i class="fas fa-history mr-1"></i>Riwayat Pinjam Mesin
                                </button>
                            @endif
                            @if ($canBreakProses ?? in_array($userRole ?? '', ['super_admin', 'kepala_ruangan', 'kepala_shift', 'ppic']))
                                <button type="button" class="btn btn-secondary btn-break-proses d-none mr-2 text-white">
                                    <i class="fas fa-pause mr-1"></i>Break
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-preview-break-proses d-none mr-2"
                                    title="Lihat riwayat break pada proses ini">
                                    <i class="fas fa-history mr-1"></i>Riwayat Break
                                </button>
                            @endif
                            @if (in_array($userRole ?? '', ['super_admin', 'ppic']))
                                <button type="button" class="btn btn-warning btn-recovery-proses d-none mr-2 font-weight-bold"
                                    title="Kembalikan proses dari history ke antrian produksi">
                                    <i class="fas fa-undo mr-1"></i>Recovery Proses
                                </button>
                            @endif
                            @if ($canMoveProses ?? true)
                                <button type="button" class="btn btn-warning btn-move-proses mr-2">
                                    <i class="fas fa-random mr-1"></i>Pindah Mesin
                                </button>
                            @endif
                            @if ($canDeleteProses ?? true)
                                <button type="button" class="btn btn-warning btn-pause-proses d-none text-dark mr-2">
                                    <i class="fas fa-pause mr-1"></i>Pause
                                </button>
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

        <!-- Modal Riwayat Pinjam Mesin -->
        <div class="modal fade" id="modalPinjamMesinHistory" tabindex="-1" aria-labelledby="modalPinjamMesinHistoryLabel"
            aria-hidden="true" style="z-index: 1065;">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title fw-bold" id="modalPinjamMesinHistoryLabel">
                            <i class="fas fa-history mr-2"></i>Riwayat Peminjaman Mesin
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-3">
                        <div
                            class="alert alert-info py-2 px-3 mb-3 small d-flex justify-content-between align-items-center">
                            <span id="pinjam-history-proses-info"><i class="fas fa-info-circle mr-1"></i> Memuat info
                                proses...</span>
                            <span class="badge badge-light" id="pinjam-history-count">0 Sesi</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">No</th>
                                        <th>Mulai Pinjam</th>
                                        <th>Selesai Pinjam</th>
                                        <th>Durasi</th>
                                        <th>Alasan Pinjam</th>
                                        <th>Dipinjam Oleh</th>
                                        <th>Dimatikan Oleh</th>
                                    </tr>
                                </thead>
                                <tbody id="pinjam-history-tbody">
                                    <tr>
                                        <td colspan="7" class="text-center py-3 text-muted">Memuat riwayat peminjaman
                                            mesin...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Riwayat Break -->
        <div class="modal fade" id="modalBreakProsesHistory" tabindex="-1" aria-labelledby="modalBreakProsesHistoryLabel"
            aria-hidden="true" style="z-index: 1065;">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title fw-bold" id="modalBreakProsesHistoryLabel">
                            <i class="fas fa-history mr-2"></i>Riwayat Break Proses
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-3">
                        <div
                            class="alert alert-secondary py-2 px-3 mb-3 small d-flex justify-content-between align-items-center">
                            <span id="break-history-proses-info"><i class="fas fa-info-circle mr-1"></i> Memuat info
                                proses...</span>
                            <span class="badge badge-light" id="break-history-count">0 Sesi</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">No</th>
                                        <th>Mulai Break</th>
                                        <th>Selesai Break</th>
                                        <th>Durasi</th>
                                        <th>Alasan Break</th>
                                        <th>Dibreak Oleh</th>
                                        <th>Dilanjutkan Oleh</th>
                                    </tr>
                                </thead>
                                <tbody id="break-history-tbody">
                                    <tr>
                                        <td colspan="7" class="text-center py-3 text-muted">Memuat riwayat break proses...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Recovery Proses -->
        <div class="modal fade" id="modalRecoveryProses" tabindex="-1" aria-labelledby="modalRecoveryProsesLabel"
            aria-hidden="true" style="z-index: 1065;">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formRecoveryProses" method="POST" action="">
                        @csrf
                        <input type="hidden" name="proses_id" id="recoveryProsesId">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fw-bold" id="modalRecoveryProsesLabel">
                                <i class="fas fa-undo mr-2"></i>Recovery Proses ke Produksi
                            </h5>
                            <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body py-3 px-4">
                            <div class="alert alert-warning py-2 mb-3"
                                style="font-size: 13px; color: #856404; background-color: #fff3cd; border-color: #ffeeba;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>Perhatian:</strong> Proses yang di-recovery akan dikembalikan ke <strong>antrian
                                    produksi</strong>.
                                Waktu mulai, waktu selesai, dan cycle time actual akan di-<strong>reset</strong>, namun
                                semua data barcode dan informasi OP tetap tersimpan.
                            </div>

                            <!-- Ringkasan Info Proses -->
                            <div class="card mb-3 bg-light border-0">
                                <div class="card-body p-2" style="font-size: 13px;">
                                    <div class="row no-gutters mb-1">
                                        <div class="col-4 text-muted">No. OP / Partai:</div>
                                        <div class="col-8 font-weight-bold" id="recoveryOpPartai">-</div>
                                    </div>
                                    <div class="row no-gutters mb-1">
                                        <div class="col-4 text-muted">Customer:</div>
                                        <div class="col-8" id="recoveryCustomer">-</div>
                                    </div>
                                    <div class="row no-gutters mb-1">
                                        <div class="col-4 text-muted">Mesin Asal:</div>
                                        <div class="col-8" id="recoveryMesinAsal">-</div>
                                    </div>
                                    <div class="row no-gutters mb-1">
                                        <div class="col-4 text-muted">Durasi Sebelumnya:</div>
                                        <div class="col-8 text-danger font-weight-bold" id="recoveryDurasiLama">-</div>
                                    </div>
                                    <div class="row no-gutters">
                                        <div class="col-4 text-muted">Cycle Time:</div>
                                        <div class="col-8" id="recoveryCycleTime">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-2">
                                <label class="form-label fw-semibold mb-1" for="recoveryMesinId">
                                    <i class="fas fa-cogs mr-1"></i>Pilih Mesin Tujuan Produksi
                                </label>
                                <select name="target_mesin_id" id="recoveryMesinId" class="form-control" required>
                                    <option value="" disabled>-- Pilih Mesin --</option>
                                </select>
                                <small class="form-text text-muted">
                                    Pilih tetap di mesin yang sama atau alihkan ke mesin lain yang tersedia.
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between px-4">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-warning font-weight-bold" id="btnSubmitRecovery">
                                <i class="fas fa-check mr-1"></i>Konfirmasi Recovery
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Detail Proses dengan Pending Approval -->
        <div class="modal fade" id="modalDetailProsesPending" tabindex="-1" aria-labelledby="modalDetailProsesPendingLabel"
            aria-hidden="true">
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

        <!-- Modal Konfirmasi Pause Proses -->
        <div class="modal fade" id="modalPauseProses" tabindex="-1" aria-labelledby="modalPauseProsesLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formPauseProses" method="POST" action="">
                        @csrf
                        <input type="hidden" name="proses_id" id="pauseProsesId">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title fw-bold" id="modalPauseProsesLabel">
                                Konfirmasi Pause Proses
                            </h5>
                            <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body py-3 px-4">
                            <div class="alert alert-warning py-2 mb-3 text-dark" style="font-size: 13px;">
                                Proses akan <strong>di-pause</strong> karena mesin tidak mengirimkan sinyal > 90 detik.
                                Permintaan pause ini akan
                                <strong>menunggu persetujuan FM</strong>.
                            </div>
                            <p id="pauseProsesInfo" style="font-size: 14px; margin-bottom: 0;">
                                Apakah Anda yakin ingin mengajukan pause proses ini?
                            </p>
                        </div>
                        <div class="modal-footer d-flex justify-content-between px-4">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-warning text-dark">
                                <i class="fas fa-pause mr-1"></i>Kirim Permintaan Pause
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

        <!-- Modal Pinjam Mesin -->
        <div class="modal fade" id="modalPinjamMesin" tabindex="-1" aria-labelledby="modalPinjamMesinLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formPinjamMesin" method="POST" action="">
                        @csrf
                        <input type="hidden" name="proses_id" id="pinjamProsesId">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title fw-bold" id="modalPinjamMesinLabel">
                                <i class="fas fa-exchange-alt mr-2"></i>Pinjam Mesin
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body py-3 px-4">
                            <div class="alert alert-info py-2 mb-3" style="font-size: 13px;">
                                <i class="fas fa-info-circle mr-1"></i>Peminjaman mesin akan <strong>langsung aktif</strong>
                                secara otomatis tanpa memerlukan persetujuan.
                            </div>
                            <div class="form-group mb-2">
                                <label class="form-label fw-semibold mb-1" style="font-size: 13px;">Informasi Proses</label>
                                <div id="pinjamProsesInfo" class="p-2 bg-light rounded border text-muted"
                                    style="font-size: 13px;">
                                    -
                                </div>
                            </div>
                            <div class="form-group mb-2">
                                <label class="form-label fw-semibold mb-1" style="font-size: 13px;">Mesin</label>
                                <input type="text" id="pinjamMesinAsal"
                                    class="form-control form-control-sm bg-light font-weight-bold" readonly>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label fw-semibold mb-1" style="font-size: 13px;">Alasan Pinjam Mesin
                                    <span class="text-danger">*</span></label>
                                <textarea name="alasan" id="pinjamAlasan" class="form-control" rows="3"
                                    placeholder="Masukkan alasan peminjaman mesin (wajib diisi)..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between px-4">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-info text-white">
                                <i class="fas fa-check-circle mr-1"></i>Aktifkan Pinjam Mesin
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Break Proses -->
        <div class="modal fade" id="modalBreakProses" tabindex="-1" aria-labelledby="modalBreakProsesLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content shadow-lg border-0 rounded-3">
                    <form id="formBreakProses" method="POST" action="">
                        @csrf
                        <input type="hidden" name="proses_id" id="breakProsesId">
                        <div class="modal-header bg-secondary text-white">
                            <h5 class="modal-title fw-bold" id="modalBreakProsesLabel">
                                <i class="fas fa-pause mr-2"></i>Aktifkan Break Proses
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body py-3 px-4">
                            <div class="alert alert-warning py-2 mb-3" style="font-size: 13px;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Saat status Break diaktifkan:
                                <ul class="mb-0 pl-3 mt-1">
                                    <li>Mesin akan <strong>dihentikan sementara</strong>.</li>
                                    <li>Cycle time actual akan <strong>dijeda (freeze)</strong>.</li>
                                    <li>Proses akan tetap berada di mesin dengan status Break (warna abu-abu).</li>
                                </ul>
                            </div>
                            <div class="form-group mb-2">
                                <label class="form-label fw-semibold mb-1" style="font-size: 13px;">Informasi Proses</label>
                                <div id="breakProsesInfo" class="p-2 bg-light rounded border text-muted"
                                    style="font-size: 13px;">
                                    -
                                </div>
                            </div>
                            <div class="form-group mb-2">
                                <label class="form-label fw-semibold mb-1" style="font-size: 13px;">Mesin</label>
                                <input type="text" id="breakMesinAsal"
                                    class="form-control form-control-sm bg-light font-weight-bold" readonly>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label fw-semibold mb-1" style="font-size: 13px;">Alasan Break
                                    <span class="text-danger">*</span></label>
                                <textarea name="alasan" id="breakAlasan" class="form-control" rows="3"
                                    placeholder="Masukkan alasan break proses (wajib diisi)..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between px-4">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fas fa-times mr-1"></i>Batal
                            </button>
                            <button type="submit" class="btn btn-secondary text-white font-weight-bold">
                                <i class="fas fa-pause mr-1"></i>Aktifkan Break
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Pindah Mesin (Drag & Drop) -->
        <div class="modal fade" id="modalConfirmMoveDragDrop" tabindex="-1" aria-labelledby="modalConfirmMoveDragDropLabel"
            aria-hidden="true">
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
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" id="btnCancelMoveDragDrop">
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
        <div class="modal fade" id="modalConfirmSwapDragDrop" tabindex="-1" aria-labelledby="modalConfirmSwapDragDropLabel"
            aria-hidden="true">
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
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" id="btnCancelSwapDragDrop">
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
    @if (isset($errors) && $errors->any())
        <script>
            $(function () {
                $('#modalProses').modal('show');
            });
        </script>
    @endif
    {{-- Script drag & drop dan select2 dashboard tanpa log console --}}
    <script>
        // Simpan data mesin ke variabel global untuk digunakan di modal pindah mesin
        window.mesinsData = @json(
            $mesins->map(function ($m) {
                return ['id' => $m->id, 'jenis_mesin' => $m->jenis_mesin];
            })
        );

        // Simpan role user dan permission flags (dari controller)
        window.userRole = @json($userRole ?? null);
        window.canCancelBarcode = @json($canCancelBarcode ?? false);
        window.canAddProses = @json($canAddProses ?? true);
        window.canEditProses = @json($canEditProses ?? true);
        window.canDeleteProses = @json($canDeleteProses ?? true);
        window.canMoveProses = @json($canMoveProses ?? true);
        window.canSwapProses = @json($canSwapProses ?? true);
        window.canScanBarcode = @json($canScanBarcode ?? true);
        window.canFinishMaintenance = {{ ($canFinishMaintenance ?? in_array($userRole ?? '', ['super_admin', 'kepala_shift', 'kepala_ruangan'])) ? 'true' : 'false' }};
        window.canPinjamMesin = {{ ($canPinjamMesin ?? in_array($userRole ?? '', ['super_admin', 'kepala_ruangan', 'kepala_shift', 'operator'])) ? 'true' : 'false' }};
        window.canBreakProses = {{ ($canBreakProses ?? in_array($userRole ?? '', ['super_admin', 'kepala_ruangan', 'kepala_shift', 'ppic'])) ? 'true' : 'false' }};
        window.isKashiftOff = @json(\App\Services\AbsenService::isKashiftOff());
        window.isKaruOff = @json(\App\Services\AbsenService::isKaruOff());

        // Toast mixin global: Error = close button (tanpa timer), Success = timer 8 detik (tanpa close button)
        window.ToastError = Swal.mixin({
            toast: true,
            position: 'top-end',
            icon: 'error',
            showConfirmButton: true,
            confirmButtonText: 'Tutup',
            showCloseButton: true,
            timer: undefined,
            timerProgressBar: false
        });
        window.ToastSuccess = Swal.mixin({
            toast: true,
            position: 'top-end',
            icon: 'success',
            showConfirmButton: false,
            timer: 8000,
            timerProgressBar: true
        });
        const ToastError = window.ToastError;
        const ToastSuccess = window.ToastSuccess;

        // Helper global format detik ke HH:MM:SS
        function formatDetikToHMS(detik) {
            if (detik === null || detik === undefined || isNaN(detik)) return '-';
            const val = parseInt(detik, 10);
            const jam = Math.floor(val / 3600).toString().padStart(2, '0');
            const menit = Math.floor((val % 3600) / 60).toString().padStart(2, '0');
            const d = (val % 60).toString().padStart(2, '0');
            return jam + ':' + menit + ':' + d;
        }
        window.formatDetikToHMS = formatDetikToHMS;

        // Helper global format tanggal & waktu
        function formatDateTimeDisplay(val) {
            if (val === null || val === undefined || val === '') return '-';
            try {
                let str = typeof val === 'string' ? val.trim() : val;
                if (typeof str === 'string' && /^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?$/.test(str)) {
                    str = str.replace(' ', 'T');
                }
                const d = new Date(str);
                if (isNaN(d.getTime())) return typeof val === 'string' ? val : '-';
                const pad = (n) => String(n).padStart(2, '0');
                const tgl = pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
                const jam = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
                return tgl + ' ' + jam;
            } catch (e) {
                return typeof val === 'string' ? val : '-';
            }
        }
        window.formatDateTimeDisplay = formatDateTimeDisplay;

        document.addEventListener('DOMContentLoaded', () => {
            const draggables = document.querySelectorAll('.draggable');
            const containers = document.querySelectorAll('.card-dropzone');
            let draggedCard = null;
            let sourceMesinId = null;
            let targetMesinId = null;
            let dropTargetElement = null; // Simpan elemen target yang tepat di posisi drop

            window.initDraggable = function (draggable) {
                if (draggable.dataset.dragInitialized) return;
                draggable.dataset.dragInitialized = '1';
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
            };
            draggables.forEach(window.initDraggable);

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
                        ToastError.fire({
                            title: 'Tidak dapat memindahkan proses. Proses Reproses masih menunggu persetujuan FM/VP dan akan di-skip dari antrian start otomatis.'
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

                    // Validasi: Cek izin memindahkan proses ke mesin lain (window.canMoveProses)
                    if (window.canMoveProses === false) {
                        restoreCardToOriginalPosition(dragging);
                        dragging.classList.remove('dragging');
                        ToastError.fire({
                            title: 'Anda tidak memiliki izin untuk memindahkan proses ke mesin lain.'
                        });
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
                    setTimeout(function () {
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
        $(document).on('click', '#btnConfirmMoveDragDrop', function () {
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
                success: function (response) {
                    // Tutup modal
                    $('#modalConfirmMoveDragDrop').modal('hide');

                    // Tampilkan notification success
                    if (response && response.status === 'success' && response.message) {
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
                error: function (xhr) {
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

                    ToastError.fire({
                        title: errorMsg
                    });
                },
                complete: function () {
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
        $(document).on('click', '#btnCancelMoveDragDrop', function () {
            const moveData = window.pendingMoveData;
            if (moveData && moveData.dragging) {
                moveData.dragging.classList.remove('dragging');
            }
            // Tidak perlu hapus pendingMoveData di sini, biarkan handler hidden.bs.modal yang menangani
            $('#modalConfirmMoveDragDrop').modal('hide');
        });

        // Handler konfirmasi swap position (Drag & Drop)
        $(document).on('click', '#btnConfirmSwapDragDrop', function () {
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
                success: function (response) {
                    // Tutup modal
                    $('#modalConfirmSwapDragDrop').modal('hide');

                    // Tampilkan notification success
                    if (response && response.status === 'success' && response.message) {
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
                            const hasSwapPending = proses.approvals.some(function (approval) {
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
                        allCards.forEach(function (card) {
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
                            allCards.forEach(function (card) {
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
                    setTimeout(function () {
                        if (response && response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 500);
                },
                error: function (xhr) {
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

                    ToastError.fire({
                        title: errorMsg
                    });
                },
                complete: function () {
                    // Reset tombol dan data
                    $('#btnConfirmSwapDragDrop').prop('disabled', false).html(
                        '<i class="fas fa-check mr-1"></i>Ya, Tukar Posisi');
                    $('#btnCancelSwapDragDrop').prop('disabled', false);
                    window.pendingSwapData = null;
                }
            });
        });

        // Handler cancel konfirmasi swap position (Drag & Drop)
        $(document).on('click', '#btnCancelSwapDragDrop', function () {
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
        $('#modalConfirmSwapDragDrop').on('hidden.bs.modal', function () {
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
        $('#modalConfirmMoveDragDrop').on('hidden.bs.modal', function () {
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

        window.addEventListener('globalFullscreenToggle', function (e) {
            const isFullscreen = e.detail;
            const header = document.querySelector('.content-header');

            if (header) {
                header.style.display = isFullscreen ? 'none' : 'block';
            }
        });


        // Ambil data dropdown dari server dan inisialisasi select2 SETELAH data masuk
        $(document).ready(function () {
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
        $(document).ready(function () {
            if (typeof $.fn.tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }
        });

        // Auto-format input Cycle Time menjadi HH:MM:SS (hanya dari sisi JavaScript)
        $(document).ready(function () {
            var $cycleInput = $('[name="cycle_time"]');

            // Saat user mengetik: hanya izinkan angka dan otomatis sisipkan ":"
            $cycleInput.on('input', function () {
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
            $cycleInput.on('blur', function () {
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
        // PRIORITAS: pending_approvals dari event ProsesStatusUpdated (real-time lintas browser)
        function hasPendingApprovalFM(proses) {
            if (!proses) return false;
            const fmActions = ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position', 'pause_proses'];
            if (proses.pending_approvals && Array.isArray(proses.pending_approvals)) {
                return proses.pending_approvals.some(function (a) {
                    const typeVal = (a && a.type != null) ? String(a.type) : '';
                    const actionVal = (a && a.action != null) ? String(a.action) : '';
                    return typeVal === 'FM' && fmActions.indexOf(actionVal) !== -1;
                });
            }
            if (!proses.approvals || !Array.isArray(proses.approvals)) return false;
            return proses.approvals.some(function (approval) {
                return approval.status === 'pending' &&
                    approval.type === 'FM' && fmActions.includes(approval.action);
            });
        }

        // Fungsi helper untuk mengecek apakah ada pending approval FM atau VP untuk Reproses (2 tahap approval)
        // PRIORITAS: pending_approvals dari event ProsesStatusUpdated (real-time lintas browser)
        function hasPendingReprocessApproval(proses) {
            if (!proses) return false;
            if (proses.jenis !== 'Reproses') return false;
            if (proses.pending_approvals && Array.isArray(proses.pending_approvals)) {
                return proses.pending_approvals.some(function (a) {
                    const typeVal = (a && a.type != null) ? String(a.type) : '';
                    const actionVal = (a && a.action != null) ? String(a.action) : '';
                    return actionVal === 'create_reprocess' && (typeVal === 'FM' || typeVal === 'VP');
                });
            }
            if (!proses.approvals || !Array.isArray(proses.approvals)) return false;
            return proses.approvals.some(function (approval) {
                return approval.status === 'pending' &&
                    approval.action === 'create_reprocess' &&
                    (approval.type === 'FM' || approval.type === 'VP');
            });
        }

        // Fungsi untuk mendapatkan informasi pending approval
        // PRIORITAS: pending_approvals dari event ProsesStatusUpdated (real-time lintas browser)
        function getPendingApprovalInfo(proses) {
            if (!proses) return null;
            const fmActions = ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position', 'pause_proses'];
            const actionLabels = {
                'edit_cycle_time': 'perubahan cycle time',
                'delete_proses': 'penghapusan proses',
                'move_machine': 'pemindahan mesin',
                'swap_position': 'tukar posisi',
                'pause_proses': 'pause proses'
            };
            if (proses.pending_approvals && Array.isArray(proses.pending_approvals)) {
                const fmPending = proses.pending_approvals.find(function (a) {
                    const typeVal = (a && a.type != null) ? String(a.type) : '';
                    const actionVal = (a && a.action != null) ? String(a.action) : '';
                    return typeVal === 'FM' && fmActions.indexOf(actionVal) !== -1;
                });
                if (fmPending) {
                    const actionVal = (fmPending.action != null) ? String(fmPending.action) : '';
                    return { action: actionVal, label: actionLabels[actionVal] || 'perubahan' };
                }
                return null;
            }
            if (!proses.approvals || !Array.isArray(proses.approvals)) return null;
            const pendingApproval = proses.approvals.find(function (approval) {
                return approval.status === 'pending' &&
                    approval.type === 'FM' && fmActions.includes(approval.action);
            });
            if (!pendingApproval) return null;
            return {
                action: pendingApproval.action,
                label: actionLabels[pendingApproval.action] || 'perubahan'
            };
        }

        // Fungsi untuk mendapatkan semua pending approval dengan detail
        function getAllPendingApprovals(proses) {
            if (!proses) return [];

            const actionLabels = {
                'edit_cycle_time': 'Edit Cycle Time',
                'delete_proses': 'Hapus Proses',
                'move_machine': 'Pindah Mesin',
                'swap_position': 'Tukar Posisi',
                'create_reprocess': 'Buat Reproses',
                'pause_proses': 'Pause Proses',
                'pinjam_mesin': 'Pinjam Mesin'
            };

            const typeLabels = {
                'FM': 'Factory Manager (FM)',
                'VP': 'Vice President (VP)',
                'KEPALA_SHIFT': 'Kepala Shift'
            };

            // PRIORITAS: Gunakan pending_approvals dari update real-time jika tersedia
            if (proses.pending_approvals && Array.isArray(proses.pending_approvals)) {
                return proses.pending_approvals.map(function (approval) {
                    var typeVal = (approval && typeof approval.type === 'string') ? approval.type : (approval && approval.type != null ? String(approval.type) : null);
                    var actionVal = (approval && typeof approval.action === 'string') ? approval.action : (approval && approval.action != null ? String(approval.action) : null);
                    return {
                        id: approval.id || null,
                        type: typeVal,
                        typeLabel: (typeVal && typeLabels[typeVal]) ? typeLabels[typeVal] : (typeVal || '-'),
                        action: actionVal,
                        actionLabel: (actionVal && actionLabels[actionVal]) ? actionLabels[actionVal] : (actionVal || '-'),
                        requested_by: approval.requested_by,
                        created_at: approval.created_at
                    };
                });
            }

            // FALLBACK: Gunakan data approvals awal (stale) jika belum ada update real-time
            if (!proses.approvals || !Array.isArray(proses.approvals)) {
                return [];
            }
            const pendingApprovals = proses.approvals.filter(function (approval) {
                return approval.status === 'pending';
            });

            return pendingApprovals.map(function (approval) {
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

        // Map pending_approvals dari API/WebSocket (array of {type, action}) ke format tampilan
        function mapPendingApprovalsFromStatus(pendingApprovals) {
            if (!Array.isArray(pendingApprovals) || pendingApprovals.length === 0) return [];
            const actionLabels = { 'edit_cycle_time': 'Edit Cycle Time', 'delete_proses': 'Hapus Proses', 'move_machine': 'Pindah Mesin', 'swap_position': 'Tukar Posisi', 'create_reprocess': 'Buat Reproses', 'pause_proses': 'Pause Proses', 'pinjam_mesin': 'Pinjam Mesin' };
            const typeLabels = { 'FM': 'Factory Manager (FM)', 'VP': 'Vice President (VP)', 'KEPALA_SHIFT': 'Kepala Shift' };
            function toStr(v) { return (v != null && typeof v === 'string') ? v : ''; }
            return pendingApprovals.map(function (a) {
                if (!a || (a.type == null && a.action == null)) return null;
                var typeStr = toStr(a.type);
                var actionStr = toStr(a.action);
                return { typeLabel: typeLabels[typeStr] || typeStr || '-', actionLabel: actionLabels[actionStr] || actionStr || '-' };
            }).filter(Boolean);
        }

        // Bangun HTML kotak "Status Approval Pending" (FM/VP) untuk modal pending
        function buildPendingApprovalInfoHtml(approvalsList) {
            if (!Array.isArray(approvalsList) || approvalsList.length === 0) return '';
            function toLabel(v) {
                if (v == null) return '-';
                if (typeof v === 'string') return v;
                if (typeof v === 'object') return '-';
                return String(v);
            }
            let html = '<div class="alert alert-warning mb-3">';
            html += '<h6 class="font-weight-bold mb-2"><i class="fas fa-clock mr-2"></i>Status Approval Pending</h6>';
            html += '<p class="mb-2">Proses ini sedang menunggu persetujuan dari:</p><ul class="mb-0 pl-3">';
            approvalsList.forEach(function (a) {
                var typeText = toLabel(a.typeLabel || a.type);
                var actionText = toLabel(a.actionLabel || a.action);
                html += '<li><strong>' + typeText + '</strong> - ' + actionText + '</li>';
            });
            html += '</ul></div>';
            return html;
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

        // Fungsi untuk render barcode di detail proses
        function renderBarcodeColumn($container, barcodeArr, type) {
            if (!Array.isArray(barcodeArr) || barcodeArr.length === 0) {
                $container.text('Tidak ada data barcode');
                return;
            }
            // Render barcode sesuai tipe
            let html = '';
            barcodeArr.forEach(function (barcode) {
                html += `<span class="badge badge-info mr-1">${barcode.kode_barcode || barcode.barcode || barcode}</span>`;
            });
            $container.html(html);
        }

        // Contoh pemakaian setelah AJAX selesai:
        // renderBarcodeColumn($('#barcodeKainCol'), detail.barcode_kains, 'kain');
        // renderBarcodeColumn($('#barcodeLaCol'), detail.barcode_las, 'la');
        // renderBarcodeColumn($('#barcodeAuxCol'), detail.barcode_auxs, 'aux');

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
        // Hanya trigger jika klik berada di dalam .op-row (Box detail OP) saja
        $(document).on('dblclick', '.status-card', function (e) {
            const proses = $(this).data('proses');
            if (!proses) return;

            // Cek apakah klik berada di dalam .op-row (Box detail OP)
            const $clickedElement = $(e.target);
            const $opRow = $clickedElement.closest('.op-row');

            // Hanya buka modal jika klik berada di dalam .op-row
            if (!$opRow.length) {
                return; // Klik di luar .op-row, jangan buka modal
            }

            const clickedDetailId = $opRow.data('detail-id') || null;
            const selectedDetail = getDetailProsesById(proses, clickedDetailId) || getFirstDetailProses(proses);
            const selectedDetailId = selectedDetail && selectedDetail.id ? selectedDetail.id : null;

            // Cek apakah card berwarna kuning (menunggu approval) â€” di-update oleh ProsesStatusUpdated
            const bgColor = $(this).data('bg-color');
            const isYellowCard = bgColor === '#ffeb3b' || bgColor === 'rgb(255, 235, 59)' || $(this).css(
                'background-color') === 'rgb(255, 235, 59)';

            // Keputusan modal mengikuti status real-time (ProsesStatusUpdated): pending_approvals + bg_color
            // hasPending* memprioritaskan proses.pending_approvals agar lintas browser konsisten
            const hasPending = hasPendingApprovalFM(proses);
            const hasPendingReprocess = hasPendingReprocessApproval(proses);
            const hasAnyPending = hasPending || hasPendingReprocess || isYellowCard;

            // Jika masih pending â†’ modal pending approval; jika sudah approved/rejected â†’ modal normal
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
                const hiddenFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'mesin_id', 'barcode_kain_optional'];
                const maintenanceFields = [
                    'no_op', 'item_op', 'customer', 'marketing', 'kode_material', 'konstruksi', 'no_partai',
                    'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll',
                    'mode', 'jenis_op', 'qty_dye_stuff', 'qty_aux', 'dye_stuff_schedules', 'aux_schedules', 'barcode_kain_optional'
                ];

                function formatDetikToHMS(val) {
                    if (val === null || val === undefined || isNaN(val)) return '-';
                    val = parseInt(val);
                    const jam = Math.floor(val / 3600);
                    const menit = Math.floor((val % 3600) / 60);
                    const detik = val % 60;
                    return `${jam.toString().padStart(2, '0')}:${menit.toString().padStart(2, '0')}:${detik.toString().padStart(2, '0')}`;
                }

                function formatDateTimeDisplay(val) {
                    if (val === null || val === undefined || val === '') return '-';
                    try {
                        let str = typeof val === 'string' ? val.trim() : val;
                        if (typeof str === 'string' && /^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?$/.test(str)) {
                            str = str.replace(' ', 'T');
                        }
                        const d = new Date(str);
                        if (isNaN(d.getTime())) return val;
                        const pad = n => String(n).padStart(2, '0');
                        return `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
                    } catch (e) {
                        return val;
                    }
                }

                let jenisMesin = '-';
                try {
                    const mesinSelect = document.getElementById('mesin_id');
                    if (mesinSelect && proses.mesin_id) {
                        const opt = mesinSelect.querySelector(`option[value="${proses.mesin_id}"]`);
                        if (opt) jenisMesin = opt.textContent;
                    }
                } catch { }

                // Ambil DetailProses yang di-double click (fallback ke pertama)
                const firstDetail = selectedDetail;

                // Gabungkan data dari Proses dan DetailProses
                const prosesData = { ...proses };
                if (firstDetail) {
                    // Override field yang ada di DetailProses dengan data dari DetailProses
                    Object.keys(firstDetail).forEach(key => {
                        if (maintenanceFields.includes(key) || ['no_op', 'no_partai', 'item_op', 'customer', 'marketing', 'kode_material', 'konstruksi', 'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll'].includes(key)) {
                            prosesData[key] = firstDetail[key];
                        }
                    });
                }

                const entries = Object.entries(prosesData)
                    .filter(([key]) => !hiddenFields.includes(key) && key !== 'barcode_kains' && key !==
                        'barcode_las' && key !== 'barcode_auxs' && key !== 'mesin' && key !== 'approvals' && key !== 'details' && key !== 'pending_approvals')
                    .filter(([key]) => !(proses.jenis === 'Maintenance' && maintenanceFields.includes(key)))
                    .map(([key, val]) => {
                        if (key === 'hfeel') return ['HAND FEEL', val];
                        if (key === 'matdok') return ['MATERIAL DOKUMEN', val];
                        if (key === 'cycle_time' || key === 'cycle_time_actual') return [key.replace(/_/g, ' ')
                            .toUpperCase(), formatDetikToHMS(val)
                        ];
                        if (key === 'mulai' || key === 'selesai') return [key.toUpperCase(), formatDateTimeDisplay(val)];
                        if (key === 'dye_stuff_schedules') {
                            let dsFormatted = '-';
                            if (Array.isArray(val) && val.length > 0) {
                                dsFormatted = val.map((t, idx) => `DS ${idx + 1}: ${t}`).join(', ');
                            } else if (typeof val === 'string' && val) {
                                try {
                                    const parsed = JSON.parse(val);
                                    if (Array.isArray(parsed) && parsed.length > 0) {
                                        dsFormatted = parsed.map((t, idx) => `DS ${idx + 1}: ${t}`).join(', ');
                                    }
                                } catch (e) { dsFormatted = val; }
                            }
                            return ['JADWAL DYE STUFF', dsFormatted];
                        }
                        if (key === 'aux_schedules') {
                            let auxFormatted = '-';
                            if (Array.isArray(val) && val.length > 0) {
                                auxFormatted = val.map((t, idx) => `AUX ${idx + 1}: ${t}`).join(', ');
                            } else if (typeof val === 'string' && val) {
                                try {
                                    const parsed = JSON.parse(val);
                                    if (Array.isArray(parsed) && parsed.length > 0) {
                                        auxFormatted = parsed.map((t, idx) => `AUX ${idx + 1}: ${t}`).join(', ');
                                    }
                                } catch (e) { auxFormatted = val; }
                            }
                            return ['JADWAL AUX', auxFormatted];
                        }
                        return [key.replace(/_/g, ' ').toUpperCase(), val];
                    });
                entries.unshift(['JENIS MESIN', jenisMesin]);

                let detailHtml = '';
                function formatCellValue(val) {
                    if (val === null || val === undefined) return '-';
                    if (typeof val === 'object') return '-';
                    return val;
                }
                for (let i = 0; i < entries.length; i += 2) {
                    detailHtml += '<tr>';
                    detailHtml += `<th style="width:180px;">${entries[i][0]}</th><td>${formatCellValue(entries[i][1])}</td>`;
                    if (entries[i + 1]) {
                        detailHtml +=
                            `<th style="width:180px;">${entries[i + 1][0]}</th><td>${formatCellValue(entries[i + 1][1])}</td>`;
                    } else {
                        detailHtml += '<th></th><td></td>';
                    }
                    detailHtml += '</tr>';
                }
                $('#detail-proses-pending-body').html(detailHtml);

                // Format informasi pending approval (FM/VP) â€” dipakai juga saat refresh lintas browser
                $('#pending-approval-info').html(buildPendingApprovalInfoHtml(pendingApprovals));

                // Simpan prosesId dan proses untuk refresh saat approval disetujui di browser lain
                $('#modalDetailProsesPending').data('prosesId', proses.id);
                $('#modalDetailProsesPending').data('proses', proses);
                $('#modalDetailProsesPending').data('detailProsesId', selectedDetailId);
                $('#modalDetailProsesPending').modal('show');
                return;
            }

            // Jika tidak ada pending approval, tampilkan modal normal
            // Simpan proses aktif ke modal detail untuk kebutuhan edit/delete dan refresh saat approval disetujui
            $('#modalDetailProses').data('proses', proses);
            $('#modalDetailProses').data('prosesId', proses.id);
            $('#modalDetailProses').data('detailProsesId', selectedDetailId);

            const pendingInfo = getPendingApprovalInfo(proses);

            // Cek apakah proses sudah dimulai (mulai tidak null)
            const isStarted = proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '';

            // Disable/enable tombol action
            const $btnEdit = $('.btn-edit-proses');
            const $btnMove = $('.btn-move-proses');
            const $btnDelete = $('.btn-delete-proses');
            const $btnPause = $('.btn-pause-proses');
            const $btnFinishMaintenance = $('.btn-finish-maintenance');
            const $btnFinishForce = $('.btn-finish-force');
            const $btnCancelStopRequest = $('.btn-cancel-stop-request');
            const $alertWaitingStop = $('#alert-waiting-stop');
            const $btnPinjam = $('.btn-pinjam-mesin');
            const $btnRecoveryProses = $('.btn-recovery-proses');

            // Logic untuk tombol Pinjam Mesin (Super Admin, Kepala Ruangan, Kepala Shift, Operator, PPIC)
            // Muncul saat:
            // 1. Proses belum selesai (!proses.selesai)
            // 2. Status proses: Sedang berjalan (isStarted) ATAU antrian berikutnya (order == 1)
            const userRoleStr = (window.userRole || '').toLowerCase();
            $btnPinjam.addClass('d-none');
            if (!proses.selesai) {
                const isNextInQueue = !isStarted && (parseInt(proses.order) === 1);
                if (isStarted || isNextInQueue) {
                    const isAuthorizedPinjam = window.canPinjamMesin === true || ['super_admin', 'kepala_ruangan', 'kepala_shift', 'operator', 'ppic'].includes(userRoleStr);
                    if (isAuthorizedPinjam) {
                        $btnPinjam.removeClass('d-none');
                        const isPinjamActive = proses.is_pinjam_mesin === true || proses.is_pinjam_mesin === 1 || proses.is_pinjam_mesin === '1';
                        $btnPinjam.data('is-pinjam-active', isPinjamActive);
                        if (isPinjamActive) {
                            $btnPinjam.removeClass('btn-info').addClass('btn-danger');
                            $btnPinjam.html('<i class="fas fa-power-off mr-1"></i>Matikan Pinjam Mesin');
                            $btnPinjam.attr('title', 'Klik untuk mematikan status Pinjam Mesin');
                        } else {
                            $btnPinjam.removeClass('btn-danger').addClass('btn-info');
                            $btnPinjam.html('<i class="fas fa-exchange-alt mr-1"></i>Pinjam Mesin');
                            $btnPinjam.attr('title', 'Klik untuk mengaktifkan status Pinjam Mesin');
                        }

                        if (hasPending || hasPendingReprocess) {
                            $btnPinjam.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
                        } else {
                            $btnPinjam.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                        }
                    }
                }
            }

            // Logic untuk tombol Preview Riwayat Pinjam Mesin (Karu, Kashift, Operator, PPIC, Admin)
            const $btnPreviewPinjam = $('.btn-preview-pinjam-mesin');
            const canPreviewPinjam = ['super_admin', 'kepala_ruangan', 'kepala_shift', 'operator', 'ppic'].includes(userRoleStr);
            if (canPreviewPinjam) {
                $btnPreviewPinjam.removeClass('d-none');
            } else {
                $btnPreviewPinjam.addClass('d-none');
            }

            // Logic untuk tombol Break & Riwayat Break (Super Admin, Kepala Ruangan, Kepala Shift, PPIC)
            // Tombol Break muncul saat:
            // 1. Proses belum selesai (!proses.selesai)
            // 2. Status proses: Sedang berjalan (isStarted)
            const $btnBreak = $('.btn-break-proses');
            const $btnPreviewBreak = $('.btn-preview-break-proses');
            $btnBreak.addClass('d-none');
            $btnPreviewBreak.addClass('d-none');

            const isAuthorizedBreak = window.canBreakProses === true || ['super_admin', 'kepala_ruangan', 'kepala_shift', 'ppic'].includes(userRoleStr);

            if (isAuthorizedBreak) {
                $btnPreviewBreak.removeClass('d-none');

                if (!proses.selesai && isStarted) {
                    $btnBreak.removeClass('d-none');
                    const isBreakActive = proses.is_break === true || proses.is_break === 1 || proses.is_break === '1';
                    $btnBreak.data('is-break-active', isBreakActive);

                    if (isBreakActive) {
                        $btnBreak.removeClass('btn-secondary').addClass('btn-success');
                        $btnBreak.html('<i class="fas fa-play mr-1"></i>Lanjutkan Proses');
                        $btnBreak.attr('title', 'Klik untuk menonaktifkan status Break dan melanjutkan proses');
                    } else {
                        $btnBreak.removeClass('btn-success').addClass('btn-secondary');
                        $btnBreak.html('<i class="fas fa-pause mr-1"></i>Break');
                        $btnBreak.attr('title', 'Klik untuk mengaktifkan status Break (Freeze cycle time)');
                    }
                }
            }

            // Logic untuk tombol Recovery Proses (PPIC dan Super Admin)
            // Hanya muncul jika:
            // 1. Proses sudah selesai (berada di history)
            // 2. Durasi berjalan di bawah 30 menit (1800 detik)
            // 3. User role: PPIC atau Super Admin
            $btnRecoveryProses.addClass('d-none');
            if (proses.selesai) {
                let actualDurationSec = proses.cycle_time_actual !== null && proses.cycle_time_actual !== undefined ? parseInt(proses.cycle_time_actual) : null;
                if (actualDurationSec === null && proses.mulai && proses.selesai) {
                    const m = new Date(proses.mulai).getTime();
                    const s = new Date(proses.selesai).getTime();
                    actualDurationSec = Math.max(0, Math.round((s - m) / 1000));
                }
                actualDurationSec = actualDurationSec || 0;
                const isEligibleRecovery = actualDurationSec < 1800;
                const isAuthRecovery = ['super_admin', 'ppic'].includes(userRoleStr);

                if (isEligibleRecovery && isAuthRecovery) {
                    $btnRecoveryProses.removeClass('d-none');
                    $btnRecoveryProses.data('durasi-detik', actualDurationSec);
                }
            }

            // Setup Kotak Catatan Proses (Note)
            const noteText = proses.note || '';
            $('#proses-note-text').val(noteText);
            const canEditNote = ['super_admin', 'kepala_ruangan', 'kepala_shift'].includes(userRoleStr);
            if (canEditNote) {
                $('#proses-note-text').prop('readonly', false).attr('placeholder', 'Tambahkan catatan khusus untuk proses ini (opsional)...');
                $('#proses-note-actions').show();
            } else {
                $('#proses-note-text').prop('readonly', true).attr('placeholder', noteText ? '' : 'Tidak ada catatan.');
                $('#proses-note-actions').hide();
            }
            if (proses.selesai) {
                $('#badge-proses-status-note').text('History / Selesai').removeClass('badge-success badge-info').addClass('badge-secondary');
            } else if (isStarted) {
                $('#badge-proses-status-note').text('Sedang Berjalan').removeClass('badge-secondary badge-info').addClass('badge-success');
            } else {
                $('#badge-proses-status-note').text('Antrian').removeClass('badge-secondary badge-success').addClass('badge-info');
            }

            // Status Menunggu Mesin Mati / Sinyal Selesai Aktif
            const isWaitingStop = !!(proses.stop_requested_at && !proses.selesai);
            $btnCancelStopRequest.addClass('d-none');
            if (isWaitingStop) {
                $alertWaitingStop.removeClass('d-none');
                if (proses.stop_request_type === 'maintenance_finish') {
                    $('#alert-waiting-stop-title').text('Maintenance Sedang Menunggu Mesin Berhenti');
                    $('#alert-waiting-stop-desc').text('Instruksi selesai telah dikirim. Menunggu operator di lapangan mematikan mesin sebelum proses selesai.');
                } else {
                    $('#alert-waiting-stop-title').text('Sedang Menunggu Mesin Berhenti / Unload');
                    $('#alert-waiting-stop-desc').text('Instruksi selesai telah dikirim. Menunggu verifikasi unload atau operator mematikan mesin sebelum proses selesai.');
                }
                const canCancelStop = ['super_admin', 'kepala_shift', 'kepala_ruangan'].includes(userRoleStr);
                if (canCancelStop) {
                    $btnCancelStopRequest.removeClass('d-none');
                }
            } else {
                $alertWaitingStop.addClass('d-none');
            }

            // Logic untuk tombol Selesai Proses Maintenance (Super Admin, Kepala Shift, dan Kepala Ruangan / KARU)
            $btnFinishMaintenance.addClass('d-none');
            if (proses.jenis === 'Maintenance' && isStarted && !proses.selesai && !isWaitingStop) {
                const userRoleStr = (window.userRole || '').toLowerCase();
                const isAuthorized = window.canFinishMaintenance === true || userRoleStr === 'super_admin' || userRoleStr === 'kepala_shift' || userRoleStr === 'kepala_ruangan';
                if (isAuthorized) {
                    $btnFinishMaintenance.removeClass('d-none');
                    if (hasPending || hasPendingReprocess) {
                        $btnFinishMaintenance.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
                    } else {
                        $btnFinishMaintenance.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                    }
                }
            }

            // Logic untuk tombol Selesai Proses Produksi / Reproses secara Paksa (Super Admin & Kepala Shift)
            $btnFinishForce.addClass('d-none');
            if (proses.jenis !== 'Maintenance' && isStarted && !proses.selesai && !isWaitingStop) {
                const userRoleStr = (window.userRole || '').toLowerCase();
                const isAuthorizedForce = userRoleStr === 'super_admin' || userRoleStr === 'kepala_shift' || (userRoleStr === 'kepala_ruangan' && window.isKashiftOff);
                if (isAuthorizedForce) {
                    $btnFinishForce.removeClass('d-none');
                    if (hasPending || hasPendingReprocess) {
                        $btnFinishForce.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
                    } else {
                        $btnFinishForce.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                    }
                }
            }

            // Logic untuk tombol Pause Proses
            $btnPause.addClass('d-none');
            // Hanya tampilkan Pause Proses jika mesin sedang OFF (status == 0) dan belum ada pending approval
            if (window.userRole && (window.userRole.toLowerCase() === 'ppic' || window.userRole.toLowerCase() === 'super_admin') && isStarted && !proses.selesai) {
                if (proses.mesin && (proses.mesin.status == 0 || proses.mesin.status === false)) {
                    // Cek apakah belum ada pending/approved approval pause_proses untuk pause incident saat ini
                    let pauseTime = 0;
                    if (proses.mesin && proses.mesin.last_off_at) {
                        pauseTime = new Date(proses.mesin.last_off_at.replace(' ', 'T')).getTime();
                    }
                    const hasPendingPause = proses.approvals && proses.approvals.some(a => a.action === 'pause_proses' && a.status === 'pending');
                    const hasApprovedPause = proses.approvals && proses.approvals.some(a => {
                        if (a.action !== 'pause_proses' || a.status !== 'approved') return false;
                        const approvalTime = new Date(a.created_at.replace(' ', 'T')).getTime();
                        return approvalTime >= pauseTime;
                    });
                    if (!hasPendingPause && !hasApprovedPause) {
                        $btnPause.removeClass('d-none');
                    }
                }
            }

            // Disable jika role restricted (FM/VP) ATAU ada pending approval ATAU proses sudah dimulai
            const isRoleRestricted = window.canEditProses === false || window.canDeleteProses === false || window
                .canMoveProses === false;
            if (isRoleRestricted || hasPending || hasPendingReprocess || isStarted) {
                // Disable tombol (kecuali Pause yang hanya muncul saat started)
                $btnEdit.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
                $btnMove.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
                $btnDelete.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');

                if (hasPending || hasPendingReprocess) {
                    $btnPause.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed').css('opacity', '0.65');
                } else {
                    $btnPause.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer').css('opacity', '1');
                }

                // Tentukan pesan tooltip berdasarkan kondisi
                let tooltipText = '';
                if (isRoleRestricted) {
                    tooltipText = 'Anda tidak memiliki izin untuk melakukan aksi ini. (Role: ' + (window.userRole ||
                        'Unknown') + ')';
                } else if (isStarted) {
                    tooltipText = 'Tidak dapat melakukan aksi. Proses sudah dimulai.';
                } else if (hasPendingReprocess) {
                    tooltipText = 'Tidak dapat melakukan aksi. Proses Reproses masih menunggu persetujuan FM/VP dan akan di-skip dari antrian start otomatis.';
                } else if (hasPending) {
                    tooltipText = pendingInfo ?
                        `Tidak dapat melakukan aksi. Masih ada permintaan ${pendingInfo.label} yang menunggu persetujuan FM.` :
                        'Tidak dapat melakukan aksi. Masih ada permintaan yang menunggu persetujuan FM.';
                }

                $btnEdit.attr('title', tooltipText).attr('data-toggle', 'tooltip');
                $btnMove.attr('title', tooltipText).attr('data-toggle', 'tooltip');
                $btnDelete.attr('title', tooltipText).attr('data-toggle', 'tooltip');
                if (hasPending || hasPendingReprocess) {
                    $btnPause.attr('title', tooltipText).attr('data-toggle', 'tooltip');
                } else {
                    $btnPause.removeAttr('title').removeAttr('data-toggle');
                }

                // Re-initialize tooltip jika sudah ada
                if (typeof $.fn.tooltip === 'function') {
                    $btnEdit.tooltip('dispose').tooltip();
                    $btnMove.tooltip('dispose').tooltip();
                    $btnDelete.tooltip('dispose').tooltip();
                    $btnPause.tooltip('dispose').tooltip();
                }
            } else {
                // Enable tombol
                $btnEdit.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                $btnMove.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                $btnDelete.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                $btnPause.prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');

                // Hapus tooltip
                $btnEdit.removeAttr('title').removeAttr('data-toggle');
                $btnMove.removeAttr('title').removeAttr('data-toggle');
                $btnDelete.removeAttr('title').removeAttr('data-toggle');
                $btnPause.removeAttr('title').removeAttr('data-toggle');

                // Dispose tooltip
                if (typeof $.fn.tooltip === 'function') {
                    $btnEdit.tooltip('dispose');
                    $btnMove.tooltip('dispose');
                    $btnDelete.tooltip('dispose');
                }
            }
            const hiddenFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'mesin_id', 'barcode_kain_optional'];
            // Daftar field yang harus disembunyikan jika Maintenance
            const maintenanceFields = [
                'no_op', 'item_op', 'customer', 'marketing', 'kode_material', 'konstruksi', 'no_partai',
                'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll',
                'mode', 'jenis_op', 'qty_dye_stuff', 'qty_aux', 'dye_stuff_schedules', 'aux_schedules', 'barcode_kain_optional'
            ];

            function formatDetikToHMS(val) {
                if (val === null || val === undefined || isNaN(val)) return '-';
                val = parseInt(val);
                const jam = Math.floor(val / 3600);
                const menit = Math.floor((val % 3600) / 60);
                const detik = val % 60;
                return `${jam.toString().padStart(2, '0')}:${menit.toString().padStart(2, '0')}:${detik.toString().padStart(2, '0')}`;
            }

            function formatDateTimeDisplay(val) {
                if (val === null || val === undefined || val === '') return '-';
                try {
                    let str = typeof val === 'string' ? val.trim() : val;
                    if (typeof str === 'string' && /^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?$/.test(str)) {
                        str = str.replace(' ', 'T');
                    }
                    const d = new Date(str);
                    if (isNaN(d.getTime())) return val;
                    const pad = n => String(n).padStart(2, '0');
                    return `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
                } catch (e) {
                    return val;
                }
            }
            let jenisMesin = '-';
            try {
                const mesinSelect = document.getElementById('mesin_id');
                if (mesinSelect && proses.mesin_id) {
                    const opt = mesinSelect.querySelector(`option[value="${proses.mesin_id}"]`);
                    if (opt) jenisMesin = opt.textContent;
                }
            } catch { }
            // Ambil DetailProses yang dipilih (fallback ke pertama)
            const firstDetail = selectedDetail;

            // Gabungkan data dari Proses dan DetailProses
            const prosesData = { ...proses };
            if (firstDetail) {
                // Override field yang ada di DetailProses dengan data dari DetailProses
                Object.keys(firstDetail).forEach(key => {
                    if (maintenanceFields.includes(key) || ['no_op', 'no_partai', 'item_op', 'customer', 'marketing', 'kode_material', 'konstruksi', 'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll'].includes(key)) {
                        prosesData[key] = firstDetail[key];
                    }
                });
            }

            // Hapus BARCODE KAINS, APPROVALS, DETAILS dari detail proses
            const entries = Object.entries(prosesData)
                .filter(([key]) => !hiddenFields.includes(key) && key !== 'barcode_kains' && key !==
                    'barcode_las' && key !== 'barcode_auxs' && key !== 'mesin' && key !== 'approvals' && key !== 'details' && key !== 'pending_approvals')
                .filter(([key]) => !(proses.jenis === 'Maintenance' && maintenanceFields.includes(key)))
                .map(([key, val]) => {
                    if (key === 'hfeel') return ['HAND FEEL', val];
                    if (key === 'matdok') return ['MATERIAL DOKUMEN', val];
                    if (key === 'cycle_time' || key === 'cycle_time_actual') return [key.replace(/_/g, ' ')
                        .toUpperCase(), formatDetikToHMS(val)
                    ];
                    if (key === 'mulai' || key === 'selesai') return [key.toUpperCase(), formatDateTimeDisplay(val)];
                    if (key === 'dye_stuff_schedules') {
                        let dsFormatted = '-';
                        if (Array.isArray(val) && val.length > 0) {
                            dsFormatted = val.map((t, idx) => `DS ${idx + 1}: ${t}`).join(', ');
                        } else if (typeof val === 'string' && val) {
                            try {
                                const parsed = JSON.parse(val);
                                if (Array.isArray(parsed) && parsed.length > 0) {
                                    dsFormatted = parsed.map((t, idx) => `DS ${idx + 1}: ${t}`).join(', ');
                                }
                            } catch (e) { dsFormatted = val; }
                        }
                        return ['JADWAL DYE STUFF', dsFormatted];
                    }
                    if (key === 'aux_schedules') {
                        let auxFormatted = '-';
                        if (Array.isArray(val) && val.length > 0) {
                            auxFormatted = val.map((t, idx) => `AUX ${idx + 1}: ${t}`).join(', ');
                        } else if (typeof val === 'string' && val) {
                            try {
                                const parsed = JSON.parse(val);
                                if (Array.isArray(parsed) && parsed.length > 0) {
                                    auxFormatted = parsed.map((t, idx) => `AUX ${idx + 1}: ${t}`).join(', ');
                                }
                            } catch (e) { auxFormatted = val; }
                        }
                        return ['JADWAL AUX', auxFormatted];
                    }
                    return [key.replace(/_/g, ' ').toUpperCase(), val];
                });
            entries.unshift(['JENIS MESIN', jenisMesin]);
            function formatCellValueNormal(val) {
                if (val === null || val === undefined || val === '') return '-';
                if (Array.isArray(val)) {
                    return val.length ? val.join(', ') : '-';
                }
                if (typeof val === 'object') return '-';
                return val;
            }
            let html = '';
            for (let i = 0; i < entries.length; i += 2) {
                html += '<tr>';
                html += `<th style="width:180px;">${entries[i][0]}</th><td>${formatCellValueNormal(entries[i][1])}</td>`;
                if (entries[i + 1]) {
                    html += `<th style="width:180px;">${entries[i + 1][0]}</th><td>${formatCellValueNormal(entries[i + 1][1])}</td>`;
                } else {
                    html += '<th></th><td></td>';
                }
                html += '</tr>';
            }
            // Tampilkan barcode hanya jika proses.jenis !== 'Maintenance'
            if (proses.jenis !== 'Maintenance') {
                const barcodeKainOptionalModal = proses.barcode_kain_optional === true;
                const showScanBtn = window.canScanBarcode !== false;
                if (!barcodeKainOptionalModal) {
                    html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode Kain';
                    if (showScanBtn) {
                        html +=
                            ' <button type="button" id = btn-scan-kain class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_kain" data-id="' +
                            proses.id + '" data-detail-id="' + (selectedDetailId || '') + '" style="float:right;"><i class="fas fa-barcode"></i> Scan</button>';
                    }
                    html += '</th></tr>';
                    html += '<tr><td colspan="4" id="barcode-kain-list">Loading...</td></tr>';
                    html += '<tr><td colspan="4" id="barcode-kain-progress" style="padding:8px;background:#f9f9f9;font-size:12px;"></td></tr>';
                }
                html += '<tr><th colspan="4" id="barcode-la-header" style="background:#f8f8f8;">Barcode Dye Stuff <span id="barcode-la-badges"></span><span id="barcode-la-buttons" style="float:right;"></span></th></tr>';
                html += '<tr><td colspan="4" id="barcode-la-list">Loading...</td></tr>';
                html += '<tr><td colspan="4" id="barcode-la-progress" style="padding:8px;background:#f9f9f9;font-size:12px;"></td></tr>';
                html += '<tr><th colspan="4" id="barcode-aux-header" style="background:#f8f8f8;">Barcode AUX <span id="barcode-aux-badges"></span><span id="barcode-aux-buttons" style="float:right;"></span></th></tr>';
                html += '<tr><td colspan="4" id="barcode-aux-list">Loading...</td></tr>';
                html += '<tr><td colspan="4" id="barcode-aux-progress" style="padding:8px;background:#f9f9f9;font-size:12px;"></td></tr>';
            }
            $('#detail-proses-body').html(html);
            // Ambil barcode dari relasi dan render di modal detail proses hanya jika bukan Maintenance
            if (proses.jenis !== 'Maintenance') {
                const barcodesUrl = '/proses/' + proses.id + '/barcodes' + (selectedDetailId ? ('?detail_proses_id=' + encodeURIComponent(selectedDetailId)) : '');
                $.ajax({
                    url: barcodesUrl,
                    method: 'GET',
                    success: function (data) {
                        const barcodeKainOptional = data.barcode_kain_optional === true;
                        // Helper untuk update warna blok G, D, A di card utama setelah perubahan barcode

                        function renderBarcodeGrid(barcodes, barcodeType, prosesId) {
                            // Filter barcode yang belum cancel
                            const activeBarcodes = (barcodes || []).filter(bk => !bk.cancel);
                            if (!activeBarcodes.length) {
                                return '<span style="color:#888;">Belum ada barcode.</span>';
                            }
                            // Cek apakah user bisa cancel barcode
                            const canCancel = window.canCancelBarcode !== false;

                            // Ambil data proses dari card atau modal
                            const $card = $(`.status-card[data-proses-id="${prosesId}"]`);
                            const prosesData = $card.length ? $card.data('proses') : $('#modalDetailProses').data('proses');
                            const canCancelByProses = !prosesData || (!prosesData.mulai || !prosesData.selesai);

                            const allowCancel = canCancel && canCancelByProses;
                            let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                            activeBarcodes.forEach(function (bk, idx) {
                                const isPending = bk.approval_status === 'pending';
                                const isRejected = bk.approval_status === 'rejected';

                                // Tombol cancel barcode dihilangkan jika pending approval QTY GI,
                                // dan hanya muncul ketika barcode sudah approved atau rejected dari SAP (atau jenis barcode lain)
                                const canCancelThis = allowCancel && !isPending;
                                const cancelButton = canCancelThis ?
                                    `<span style='position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;' class='cancel-barcode-btn' data-type='${barcodeType}' data-proses='${prosesId}' data-id='${bk.id}' data-matdok='${bk.matdok || ""}' data-item-document='${bk.item_document || ""}' data-approval-status='${bk.approval_status || ""}' data-barcode='${bk.barcode}' title='Cancel barcode'>&times;</span>` :
                                    '';

                                let boxBg = '#f3f3f3';
                                let boxBorder = '';
                                let badgeHtml = '';

                                if (isPending) {
                                    boxBg = '#fff9c4';
                                    boxBorder = 'border:1.5px solid #fbc02d;';
                                    badgeHtml = '<br><span class="badge badge-warning" style="font-size:10px; background:#fff176; color:#856404; border:1px solid #fbc02d; margin-top:3px; padding:2px 4px;"><i class="fas fa-clock mr-1"></i>Menunggu Approval</span>';
                                } else if (isRejected) {
                                    boxBg = '#ffebee';
                                    boxBorder = 'border:1.5px solid #ef5350;';
                                    badgeHtml = '<br><span class="badge badge-danger" style="font-size:10px; background:#ef9a9a; color:#b71c1c; border:1px solid #ef5350; margin-top:3px; padding:2px 4px;"><i class="fas fa-times-circle mr-1"></i>Ditolak SAP</span>';
                                }

                                html += `<div style="position:relative;flex:1 0 30%;max-width:32%;min-width:130px;background:${boxBg};${boxBorder}border-radius:6px;padding:6px 4px;margin-bottom:6px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">
                                        ${cancelButton}
                                        ${bk.barcode} ${(bk.matdok ? '<br><span style=\'font-size:11px;color:#888;\'>' + bk.matdok + '</span>' : '')}
                                        ${badgeHtml}
                                    </div>`;
                            });
                            html += '</div>';
                            return html;
                        }
                        window.renderBarcodeGrid = renderBarcodeGrid;

                        // Render ulang list barcode di modal
                        if (!barcodeKainOptional && $('#barcode-kain-list').length) {
                            $('#barcode-kain-list').html(renderBarcodeGrid(data.barcode_kain, 'kain', proses.id));
                        }
                        $('#barcode-la-list').html(renderBarcodeGrid(data.barcode_la, 'la', proses.id));
                        $('#barcode-aux-list').html(renderBarcodeGrid(data.barcode_aux, 'aux', proses
                            .id));

                        // Topping LA/AUX: badges TD/TA, tombol Request, tombol Scan
                        const userRole = window.userRole || '';
                        const canRequestToppingRole = (userRole === 'kepala_ruangan' || userRole === 'super_admin' || (userRole === 'kepala_shift' && window.isKaruOff));
                        const canRequestLa = data.can_request_topping_la && canRequestToppingRole;
                        const canRequestAux = data.can_request_topping_aux && canRequestToppingRole;
                        const canScanLa = data.can_scan_la === true;
                        const canScanAux = data.can_scan_aux === true;
                        const approvedToppingLa = data.approved_topping_la || null;
                        const approvedToppingAux = data.approved_topping_aux || null;
                        const isMultipleOp = data.is_multiple_op === true;
                        const detailIdAttr = selectedDetailId ? (selectedDetailId + '') : '';
                        const reqToppingTitle = isMultipleOp ? 'Request Topping untuk semua OP. Kepala Shift hanya perlu approve sekali.' : '';

                        let laBadges = '';
                        if (data.pending_topping_la) {
                            laBadges += ' <span class="badge badge-warning" title="Topping Dyes - Menunggu approval">TD</span>';
                        }
                        let laBtns = '';
                        if (canRequestLa) {
                            laBtns += ' <button type="button" class="btn btn-sm btn-info request-topping-btn mr-1" data-type="la" data-id="' + proses.id + '" title="' + reqToppingTitle + '"><i class="fas fa-plus"></i> Request Topping' + (isMultipleOp ? ' (semua OP)' : '') + '</button>';
                        }
                        if (canScanLa) {
                            const approvalAttr = approvedToppingLa ? ' data-approval-id="' + approvedToppingLa.id + '"' : '';
                            laBtns += ' <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_la" data-id="' + proses.id + '" data-detail-id="' + detailIdAttr + '"' + approvalAttr + '><i class="fas fa-barcode"></i> ' + (approvedToppingLa ? 'Scan (Topping)' : 'Scan') + '</button>';
                        }
                        $('#barcode-la-badges').html(laBadges);
                        $('#barcode-la-buttons').html(laBtns);

                        // Progress LA: keterangan kebutuhan awal + topping
                        const laProgress = data.la_progress || {};
                        const laReq = laProgress.required ?? 0;
                        const laScn = laProgress.scanned ?? 0;
                        const laComplete = laProgress.is_complete === true;
                        const laToppingReq = laProgress.topping_required ?? 0;
                        const laInitialReq = laProgress.initial_required ?? 0;
                        let laProgressHtml = '';
                        if (laInitialReq === 0 && laToppingReq === 0) {
                            laProgressHtml = `Kebutuhan: 0 awal (Tidak Memerlukan Scanning) | <span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>`;
                        } else if (laToppingReq > 0) {
                            laProgressHtml = `Kebutuhan: ${laInitialReq} awal + ${laToppingReq} topping (TD) = ${laReq} total | Sudah: ${laScn} | ${laComplete ? '<span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' : '<span style="color:#c62828;">Kurang: ' + (laReq - laScn) + '</span>'}`;
                        } else {
                            laProgressHtml = `Kebutuhan: ${laInitialReq} awal | Sudah: ${laScn} | ${laComplete ? '<span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' : '<span style="color:#c62828;">Kurang: ' + (laInitialReq - laScn) + '</span>'}`;
                        }
                        const laProgressBg = laComplete ? '#e8f5e9' : '#fff3e0';
                        $('#barcode-la-progress').html('<div style="padding:4px 8px;background:' + laProgressBg + ';border-radius:4px;">' + laProgressHtml + '</div>').show();

                        let auxBadges = '';
                        if (data.pending_topping_aux) {
                            auxBadges += ' <span class="badge badge-warning" title="Topping Auxiliaries - Menunggu approval">TA</span>';
                        }
                        let auxBtns = '';
                        if (canRequestAux) {
                            auxBtns += ' <button type="button" class="btn btn-sm btn-info request-topping-btn mr-1" data-type="aux" data-id="' + proses.id + '" title="' + reqToppingTitle + '"><i class="fas fa-plus"></i> Request Topping' + (isMultipleOp ? ' (semua OP)' : '') + '</button>';
                        }
                        if (canScanAux) {
                            const approvalAttrAux = approvedToppingAux ? ' data-approval-id="' + approvedToppingAux.id + '"' : '';
                            auxBtns += ' <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_aux" data-id="' + proses.id + '" data-detail-id="' + detailIdAttr + '"' + approvalAttrAux + '><i class="fas fa-barcode"></i> ' + (approvedToppingAux ? 'Scan (Topping)' : 'Scan') + '</button>';
                        }
                        $('#barcode-aux-badges').html(auxBadges);
                        $('#barcode-aux-buttons').html(auxBtns);

                        // Progress AUX: keterangan kebutuhan awal + topping
                        const auxProgress = data.aux_progress || {};
                        const auxReq = auxProgress.required ?? 0;
                        const auxScn = auxProgress.scanned ?? 0;
                        const auxComplete = auxProgress.is_complete === true;
                        const auxToppingReq = auxProgress.topping_required ?? 0;
                        const auxInitialReq = auxProgress.initial_required ?? 0;
                        let auxProgressHtml = '';
                        if (auxInitialReq === 0 && auxToppingReq === 0) {
                            auxProgressHtml = `Kebutuhan: 0 awal (Tidak Memerlukan Scanning) | <span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>`;
                        } else if (auxToppingReq > 0) {
                            auxProgressHtml = `Kebutuhan: ${auxInitialReq} awal + ${auxToppingReq} topping (TA) = ${auxReq} total | Sudah: ${auxScn} | ${auxComplete ? '<span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' : '<span style="color:#c62828;">Kurang: ' + (auxReq - auxScn) + '</span>'}`;
                        } else {
                            auxProgressHtml = `Kebutuhan: ${auxInitialReq} awal | Sudah: ${auxScn} | ${auxComplete ? '<span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' : '<span style="color:#c62828;">Kurang: ' + (auxInitialReq - auxScn) + '</span>'}`;
                        }
                        const auxProgressBg = auxComplete ? '#e8f5e9' : '#fff3e0';
                        $('#barcode-aux-progress').html('<div style="padding:4px 8px;background:' + auxProgressBg + ';border-radius:4px;">' + auxProgressHtml + '</div>').show();

                        // Tampilkan progress barcode kain
                        // Gunakan barcode_kain_progress untuk detail yang dipilih (untuk display)
                        // dan all_barcode_kain_progress untuk validasi can_scan_la_aux (semua detail)
                        const selectedProgress = data.barcode_kain_progress || [];
                        const allProgress = data.all_barcode_kain_progress || [];
                        const incompleteDetails = data.incomplete_details || [];
                        let progressHtml = '';

                        // Tampilkan progress untuk detail yang dipilih
                        if (selectedProgress.length > 0) {
                            progressHtml += '<div style="padding:4px 0;"><strong>Progress Barcode Kain (Detail yang Dipilih):</strong><br>';
                            selectedProgress.forEach(function (p) {
                                let statusIcon = '';
                                let statusText = '';
                                let bgColor = '';
                                let borderStyle = '';

                                if (p.is_complete) {
                                    statusIcon = '<span style="color:#43a047;"><i class="fas fa-check"></i></span>';
                                    statusText = '<span style="color:#43a047;font-weight:600;">Lengkap</span>';
                                    bgColor = '#e8f5e9';
                                } else if (p.has_pending) {
                                    statusIcon = '<span style="color:#f57f17;"><i class="fas fa-clock"></i></span>';
                                    const remaining = p.roll - p.scanned;
                                    if (remaining > 0) {
                                        statusText = '<span style="color:#b78103;font-weight:600;">Menunggu Approval SAP (Kurang ' + remaining + ' roll)</span>';
                                    } else {
                                        statusText = '<span style="color:#b78103;font-weight:600;">Menunggu Approval SAP</span>';
                                    }
                                    bgColor = '#fffde7';
                                    borderStyle = 'border:1px solid #ffe082;';
                                } else {
                                    statusIcon = '<span style="color:#c62828;"><i class="fas fa-times"></i></span>';
                                    statusText = '<span style="color:#c62828;">Kurang ' + (p.roll - p.scanned) + ' roll</span>';
                                    bgColor = '#ffebee';
                                }

                                progressHtml += `<div style="background:${bgColor};${borderStyle}padding:4px 8px;margin:2px 0;border-radius:4px;">`;
                                progressHtml += `${statusIcon} <strong>OP ${p.no_op || 'N/A'}:</strong> ${p.scanned}/${p.roll} roll - ${statusText}`;
                                progressHtml += '</div>';
                            });
                            progressHtml += '</div>';
                        }

                        // Tampilkan status keseluruhan (untuk validasi scan LA/AUX)
                        if (barcodeKainOptional) {
                            const hintHtml = '<div style="padding:6px 8px;background:#e3f2fd;border-radius:4px;margin-bottom:8px;font-size:12px;color:#1565c0;"><i class="fas fa-info-circle"></i> <strong>Proses ini hanya wajib Barcode Dye Stuff &amp; AUX (D &amp; A).</strong> Barcode Kain (G/F) tidak wajib.</div>';
                            progressHtml = hintHtml + progressHtml;
                        } else if (allProgress.length > 0) {
                            const totalDetails = allProgress.length;
                            const completeCount = allProgress.filter(p => p.is_complete).length;
                            const allComplete = completeCount === totalDetails;
                            const hasAnyPending = allProgress.some(p => p.has_pending);

                            if (allComplete) {
                                progressHtml = '<div style="padding:4px 0;background:#e8f5e9;border-radius:4px;margin-bottom:8px;">' + progressHtml;
                                progressHtml += '<div style="padding:4px 0;background:#e8f5e9;border-radius:4px;margin-top:8px;">';
                                progressHtml += '<strong style="color:#2e7d32;"><i class="fas fa-check-circle"></i> Semua Detail OP Sudah Lengkap!</strong>';
                                progressHtml += '<br><span style="color:#43a047;font-size:12px;">Scan Barcode Dye Stuff & AUX sudah diizinkan.</span>';
                                progressHtml += '</div></div>';
                            } else if (hasAnyPending) {
                                progressHtml = '<div style="padding:4px 0;background:#fffde7;border:1px solid #ffe082;border-radius:4px;margin-bottom:8px;">' + progressHtml;
                                progressHtml += '<div style="padding:6px 8px;background:#fff9c4;border-radius:4px;margin-top:8px;">';
                                progressHtml += '<strong style="color:#b78103;"><i class="fas fa-clock mr-1"></i> Barcode Kain Sedang Menunggu Approval SAP</strong>';
                                progressHtml += '<br><span style="color:#856404;font-size:12px;">Terdapat pengajuan QTY GI Over Limit yang sedang menunggu persetujuan dari SAP. Scan Barcode Dye Stuff & AUX dapat dilakukan setelah disetujui.</span>';
                                progressHtml += '</div></div>';
                            } else {
                                progressHtml = '<div style="padding:4px 0;background:#ffebee;border-radius:4px;margin-bottom:8px;">' + progressHtml;
                                progressHtml += '<div style="padding:4px 0;background:#ffebee;border-radius:4px;margin-top:8px;">';
                                progressHtml += `<strong style="color:#c62828;"><i class="fas fa-exclamation-triangle"></i> ${completeCount} dari ${totalDetails} Detail OP Lengkap</strong>`;
                                progressHtml += '<br><span style="color:#c62828;font-size:12px;">Semua Detail OP harus lengkap sebelum scan Barcode Dye Stuff & AUX.</span>';
                                progressHtml += '</div></div>';
                            }
                        }
                        if ($('#barcode-kain-progress').length) {
                            $('#barcode-kain-progress').html(progressHtml);
                        }

                        // Barcode dapat ditambahkan kapanpun (belum mulai, sedang berjalan, atau sudah selesai)
                        // Rules: LA & AUX memerlukan barcode kain lengkap (kecuali barcodeKainOptional)
                        const canScanLaAux = data.can_scan_la_aux !== false;
                        const $btnScanKain = $('#btn-scan-kain');
                        const $btnScanLa = $('#barcode-la-buttons .scan-barcode-btn');
                        const $btnScanAux = $('#barcode-aux-buttons .scan-barcode-btn');

                        // Scan Kain (G/F): disable jika semua roll sudah terpenuhi atau sedang menunggu approval
                        const allProgressKain = data.all_barcode_kain_progress || [];
                        const allRollComplete = allProgressKain.length > 0 && allProgressKain.every(p => p.is_complete);
                        const anyPendingKain = allProgressKain.some(p => p.has_pending);
                        const allScannedOrPending = allProgressKain.length > 0 && allProgressKain.every(p => p.scanned >= p.roll);

                        if (allRollComplete) {
                            $btnScanKain.prop('disabled', true)
                                .removeClass('btn-success')
                                .addClass('btn-secondary')
                                .css('cursor', 'not-allowed')
                                .attr('title', 'Barcode kain sudah lengkap sesuai roll');
                        } else if (allScannedOrPending && anyPendingKain) {
                            $btnScanKain.prop('disabled', true)
                                .removeClass('btn-success')
                                .addClass('btn-secondary')
                                .css('cursor', 'not-allowed')
                                .attr('title', 'Menunggu approval SAP untuk barcode kain');
                        } else {
                            $btnScanKain.prop('disabled', false)
                                .removeClass('btn-secondary')
                                .addClass('btn-success')
                                .css('cursor', 'pointer')
                                .removeAttr('title');
                        }

                        let tooltipLa = '';
                        let tooltipAux = '';
                        if (!canScanLaAux) {
                            tooltipLa = tooltipAux = 'Tidak dapat scan LA/AUX. ';
                            if (incompleteDetails.length > 0) {
                                const detailMsgs = incompleteDetails.map(d => `OP ${d.no_op} (kurang ${d.remaining} roll)`);
                                tooltipLa = tooltipAux += 'Detail OP belum lengkap: ' + detailMsgs.join(', ');
                            } else {
                                tooltipLa = tooltipAux += 'Pastikan semua barcode kain sudah memenuhi jumlah roll terlebih dahulu.';
                            }
                        } else if (laComplete) {
                            tooltipLa = 'Barcode Dye Stuff sudah lengkap sesuai kebutuhan (awal + topping).';
                        } else if (auxComplete) {
                            tooltipAux = 'Barcode AUX sudah lengkap sesuai kebutuhan (awal + topping).';
                        }
                        if (!canScanLaAux || laComplete) {
                            $btnScanLa.prop('disabled', true)
                                .removeClass('btn-success')
                                .addClass('btn-secondary')
                                .css('cursor', 'not-allowed')
                                .attr('title', tooltipLa || 'Barcode Dye Stuff sudah lengkap');
                        } else {
                            $btnScanLa.prop('disabled', false)
                                .removeClass('btn-secondary')
                                .addClass('btn-success')
                                .css('cursor', 'pointer')
                                .removeAttr('title');
                        }
                        if (!canScanLaAux || auxComplete) {
                            $btnScanAux.prop('disabled', true)
                                .removeClass('btn-success')
                                .addClass('btn-secondary')
                                .css('cursor', 'not-allowed')
                                .attr('title', tooltipAux || 'Barcode AUX sudah lengkap');
                        } else {
                            $btnScanAux.prop('disabled', false)
                                .removeClass('btn-secondary')
                                .addClass('btn-success')
                                .css('cursor', 'pointer')
                                .removeAttr('title');
                        }

                        // Update G/D/A indicators per OP
                        const laProgGlobal = data.la_progress || {};
                        const auxProgGlobal = data.aux_progress || {};
                        const hasLaActiveGlobal = laProgGlobal.initial_is_complete !== undefined ? laProgGlobal.initial_is_complete : (laProgGlobal.initial_scanned >= (laProgGlobal.initial_required !== undefined ? laProgGlobal.initial_required : 0));
                        const hasAuxActiveGlobal = auxProgGlobal.initial_is_complete !== undefined ? auxProgGlobal.initial_is_complete : (auxProgGlobal.initial_scanned >= (auxProgGlobal.initial_required !== undefined ? auxProgGlobal.initial_required : 0));

                        if (data.all_barcode_kain_progress && Array.isArray(data.all_barcode_kain_progress) && data.all_barcode_kain_progress.length > 0) {
                            data.all_barcode_kain_progress.forEach(function (p) {
                                const detailId = p.detail_proses_id;
                                const kainColor = p.has_pending ? 'yellow' : (p.is_complete ? 'green' : 'red');
                                window.updateGDAIndicators(proses.id, detailId, kainColor, hasLaActiveGlobal, hasAuxActiveGlobal);
                            });
                        } else if (data.barcode_kain_progress && Array.isArray(data.barcode_kain_progress) && data.barcode_kain_progress.length > 0) {
                            data.barcode_kain_progress.forEach(function (p) {
                                const detailId = p.detail_proses_id || selectedDetailId;
                                const kainColor = p.has_pending ? 'yellow' : (p.is_complete ? 'green' : 'red');
                                window.updateGDAIndicators(proses.id, detailId, kainColor, hasLaActiveGlobal, hasAuxActiveGlobal);
                            });
                        } else {
                            window.updateGDAIndicators(proses.id, selectedDetailId, 'red', hasLaActiveGlobal, hasAuxActiveGlobal);
                        }
                    },
                    error: function () {
                        $('#barcode-kain-list').html(
                            '<span style="color:#888;">Belum ada barcode kain.</span>');
                        $('#barcode-kain-progress').html('');
                        $('#barcode-la-list').html(
                            '<span style="color:#888;">Belum ada barcode Dye Stuff.</span>');
                        $('#barcode-la-progress').html('');
                        $('#barcode-aux-list').html(
                            '<span style="color:#888;">Belum ada barcode AUX.</span>');
                        $('#barcode-aux-progress').html('');

                        // Disable tombol scan LA dan AUX jika error
                        const $btnScanLa = $('#barcode-la-buttons .scan-barcode-btn');
                        const $btnScanAux = $('#barcode-aux-buttons .scan-barcode-btn');
                        $btnScanLa.prop('disabled', true)
                            .removeClass('btn-success')
                            .addClass('btn-secondary')
                            .css('cursor', 'not-allowed');
                        $btnScanAux.prop('disabled', true)
                            .removeClass('btn-success')
                            .addClass('btn-secondary')
                            .css('cursor', 'not-allowed');
                    }
                });
            }

            // Initialize tooltip setelah modal ditampilkan
            $('#modalDetailProses').on('shown.bs.modal', function () {
                if (typeof $.fn.tooltip === 'function') {
                    $('.btn-edit-proses, .btn-move-proses, .btn-delete-proses').tooltip();
                }
            });

            $('#modalDetailProses').modal('show');
        });

        // Reset tombol saat modal ditutup
        $('#modalDetailProses').on('hidden.bs.modal', function () {
            const $btnEdit = $('.btn-edit-proses');
            const $btnMove = $('.btn-move-proses');
            const $btnDelete = $('.btn-delete-proses');
            const $btnRecovery = $('.btn-recovery-proses');
            $btnRecovery.addClass('d-none');

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
        $(document).on('click', '.btn-edit-proses', function (e) {
            e.preventDefault();
            // Cek apakah tombol disabled atau user tidak punya hak akses
            if ($(this).prop('disabled') || $(this).hasClass('disabled') || window.canEditProses === false) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            // Validasi: cek apakah proses sudah dimulai
            if (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '') {
                ToastError.fire({
                    title: 'Tidak dapat mengubah cycle time. Proses sudah dimulai.'
                });
                return false;
            }

            // Validasi: cek pending approval VP untuk Reproses
            const hasPendingReprocess = hasPendingReprocessApproval(proses);
            if (hasPendingReprocess) {
                ToastError.fire({
                    title: 'Tidak dapat mengubah cycle time. Proses Reproses masih menunggu persetujuan FM/VP dan akan di-skip dari antrian start otomatis.'
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
            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function () {
                $('#modalEditProses').modal('show');
                // Tambahkan class modal-open secara manual jika scroll masih hilang
                $('body').addClass('modal-open');
            });
        });

        // Handler tombol Pindah Mesin (buka modal pindah mesin)
        $(document).on('click', '.btn-move-proses', function (e) {
            e.preventDefault();
            // Cek apakah tombol disabled atau user tidak punya hak akses
            if ($(this).prop('disabled') || $(this).hasClass('disabled') || window.canMoveProses === false) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            // Validasi: cek apakah proses sudah dimulai
            if (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '') {
                ToastError.fire({
                    title: 'Tidak dapat memindahkan proses. Proses sudah dimulai.'
                });
                return false;
            }

            // Validasi: cek pending approval VP untuk Reproses
            const hasPendingReprocess = hasPendingReprocessApproval(proses);
            if (hasPendingReprocess) {
                ToastError.fire({
                    title: 'Tidak dapat memindahkan proses. Proses Reproses masih menunggu persetujuan FM/VP dan akan di-skip dari antrian start otomatis.'
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
                window.mesinsData.forEach(function (mesin) {
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
                            data.mesins.forEach(function (mesin) {
                                const mesinId = parseInt(mesin.id);
                                if (mesinId !== currentMesinId) {
                                    $('#moveMesinId').append(
                                        `<option value="${mesinId}">${mesin.jenis_mesin}</option>`);
                                }
                            });
                        }
                    })
                    .catch(function (error) {
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
            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function () {
                $('#modalMoveProses').modal('show');
                // Tambahkan class modal-open secara manual jika scroll masih hilang
                $('body').addClass('modal-open');
            });
        });

        // Handler submit form pindah mesin
        $('#formMoveProses').on('submit', function (e) {
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
                success: function (response) {
                    $('#modalMoveProses').modal('hide');

                    // Tampilkan notification success jika ada
                    if (response && response.status === 'success' && response.message) {
                        ToastSuccess.fire({
                            title: response.message
                        });
                    }

                    // Redirect untuk refresh halaman setelah delay kecil untuk menampilkan notification
                    setTimeout(function () {
                        if (response && response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 500);
                },
                error: function (xhr) {
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
                    ToastError.fire({
                        title: errorMsg
                    });
                }
            });
        });

        // Handler tombol Recovery Proses (buka modal recovery proses)
        $(document).on('click', '.btn-recovery-proses', function (e) {
            e.preventDefault();
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            // Validasi: hanya proses history yang bisa direcovery
            if (!proses.selesai) {
                ToastError.fire({ title: 'Hanya proses yang sudah selesai / history yang dapat di-recovery.' });
                return false;
            }

            // Hitung durasi berjalan
            let actualSec = proses.cycle_time_actual !== null && proses.cycle_time_actual !== undefined ? parseInt(proses.cycle_time_actual) : null;
            if (actualSec === null && proses.mulai && proses.selesai) {
                const m = new Date(proses.mulai).getTime();
                const s = new Date(proses.selesai).getTime();
                actualSec = Math.max(0, Math.round((s - m) / 1000));
            }
            actualSec = actualSec || 0;

            if (actualSec >= 1800) {
                const menit = Math.floor(actualSec / 60);
                const detik = actualSec % 60;
                ToastError.fire({ title: `Proses telah berjalan ${menit} menit ${detik} detik (>= 30 menit). Tidak dapat di-recovery.` });
                return false;
            }

            const id = proses.id;
            const recoveryUrl = "{{ url('proses') }}/" + id + "/recovery";

            $('#formRecoveryProses').attr('action', recoveryUrl);
            $('#recoveryProsesId').val(id);

            // Tampilkan info proses
            let noOp = '-';
            let noPartai = '-';
            let customer = '-';
            if (proses.details && Array.isArray(proses.details) && proses.details.length > 0) {
                noOp = proses.details.map(d => d.no_op).filter(Boolean).join(', ') || '-';
                noPartai = proses.details.map(d => d.no_partai).filter(Boolean).join(', ') || '-';
                customer = proses.details[0].customer || '-';
            } else {
                noOp = proses.no_op || '-';
                noPartai = proses.no_partai || '-';
                customer = proses.customer || '-';
            }

            let jenisMesin = '-';
            const currentMesinId = proses.mesin_id ? parseInt(proses.mesin_id) : null;
            try {
                const mesinSelect = document.getElementById('mesin_id');
                if (mesinSelect && currentMesinId) {
                    const opt = mesinSelect.querySelector(`option[value="${currentMesinId}"]`);
                    if (opt) jenisMesin = opt.textContent;
                }
            } catch (e) { }

            const mnt = Math.floor(actualSec / 60);
            const dtk = actualSec % 60;
            const durasiStr = `${mnt} mnt ${dtk} dtk (${formatDetikToHMS(actualSec)})`;

            $('#recoveryOpPartai').text(`${noOp} / ${noPartai}`);
            $('#recoveryCustomer').text(customer);
            $('#recoveryMesinAsal').text(jenisMesin);
            $('#recoveryDurasiLama').text(durasiStr);
            $('#recoveryCycleTime').text(formatDetikToHMS(proses.cycle_time || 0));

            // Populate select mesin tujuan
            $('#recoveryMesinId').empty();
            if (window.mesinsData && Array.isArray(window.mesinsData) && window.mesinsData.length > 0) {
                window.mesinsData.forEach(function (mesin) {
                    const mId = parseInt(mesin.id || mesin.mesin_id);
                    const isCurrent = mId === currentMesinId;
                    const mNama = (mesin.jenis_mesin || mesin.nama || mesin.text || ('Mesin ' + mId)) + (isCurrent ? ' (Mesin Saat Ini)' : '');
                    $('#recoveryMesinId').append(`<option value="${mId}" ${isCurrent ? 'selected' : ''}>${mNama}</option>`);
                });
            } else {
                $('#mesin_id option').each(function () {
                    const val = $(this).val();
                    if (val) {
                        const mId = parseInt(val);
                        const isCurrent = mId === currentMesinId;
                        const mNama = $(this).text() + (isCurrent ? ' (Mesin Saat Ini)' : '');
                        $('#recoveryMesinId').append(`<option value="${mId}" ${isCurrent ? 'selected' : ''}>${mNama}</option>`);
                    }
                });
            }

            // Tutup modal detail, lalu buka modal recovery
            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function () {
                $('#modalRecoveryProses').modal('show');
                $('body').addClass('modal-open');
            });
        });

        // Handler submit form recovery proses
        $('#formRecoveryProses').on('submit', function (e) {
            e.preventDefault();
            const form = $(this);
            const url = form.attr('action');
            const targetMesinId = $('#recoveryMesinId').val();
            const $btnSubmit = $('#btnSubmitRecovery');

            if (!targetMesinId) {
                ToastError.fire({ title: 'Silakan pilih mesin tujuan terlebih dahulu.' });
                return;
            }

            $btnSubmit.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Memproses Recovery...');

            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    target_mesin_id: targetMesinId
                },
                success: function (response) {
                    $('#modalRecoveryProses').modal('hide');
                    if (response && response.status === 'success') {
                        ToastSuccess.fire({
                            title: response.message || 'Proses berhasil di-recovery ke antrian produksi!'
                        });
                    }
                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                },
                error: function (xhr) {
                    let errorMsg = 'Gagal merecovery proses.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    ToastError.fire({ title: errorMsg });
                },
                complete: function () {
                    $btnSubmit.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Konfirmasi Recovery');
                }
            });
        });

        // Handler tombol Pinjam Mesin (Toggle ON / OFF)
        $(document).on('click', '.btn-pinjam-mesin', function (e) {
            e.preventDefault();
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            const id = proses.id;
            const pinjamUrl = "{{ url('proses') }}/" + id + "/pinjam-mesin";
            const isPinjamActive = $(this).data('is-pinjam-active') === true;

            if (isPinjamActive) {
                // Konfirmasi untuk mematikan Pinjam Mesin
                Swal.fire({
                    title: 'Matikan Pinjam Mesin?',
                    text: 'Status pinjam mesin akan dinonaktifkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Matikan',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Memproses...',
                            text: 'Menonaktifkan status pinjam mesin',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $.ajax({
                            url: pinjamUrl,
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                action: 'deactivate'
                            },
                            success: function (response) {
                                $('#modalDetailProses').modal('hide');
                                ToastSuccess.fire({
                                    title: response.message || 'Status pinjam mesin berhasil dimatikan.'
                                });
                                // Update DOM card langsung tanpa reload
                                const $card = $(`.status-card[data-proses-id="${id}"]`);
                                if ($card.length) {
                                    const cardProses = $card.data('proses') || {};
                                    cardProses.is_pinjam_mesin = false;
                                    cardProses.pinjam_mesin_alasan = null;
                                    $card.data('proses', cardProses);
                                    $card.find('.pinjam-mesin-indicator').css('display', 'none');
                                }
                            },
                            error: function (xhr) {
                                let errorMsg = 'Gagal mematikan status pinjam mesin.';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                ToastError.fire({
                                    title: errorMsg
                                });
                            }
                        });
                    }
                });
                return false;
            }

            if (hasPendingApprovalFM(proses) || hasPendingReprocessApproval(proses)) {
                ToastError.fire({
                    title: 'Tidak dapat mengaktifkan pinjam mesin. Masih ada persetujuan lain yang menunggu.'
                });
                return false;
            }

            $('#formPinjamMesin').attr('action', pinjamUrl);
            $('#pinjamProsesId').val(id);
            $('#pinjamAlasan').val('');

            // Set info proses
            const firstDetail = getFirstDetailProses(proses);
            const noOpStr = firstDetail ? (firstDetail.no_op || '-') : '-';
            const noPartaiStr = firstDetail ? (firstDetail.no_partai || '-') : '-';
            const jenisStr = proses.jenis || '-';
            if (jenisStr === 'Maintenance') {
                $('#pinjamProsesInfo').html(`<strong>Jenis:</strong> Maintenance | <strong>Mesin:</strong> ${currentMesinNama}`);
            } else {
                $('#pinjamProsesInfo').html(`<strong>Jenis:</strong> ${jenisStr} | <strong>No OP:</strong> ${noOpStr} | <strong>No Partai:</strong> ${noPartaiStr}`);
            }

            const currentMesinId = proses.mesin_id ? parseInt(proses.mesin_id) : null;
            let currentMesinNama = 'Mesin ' + currentMesinId;
            if (proses.mesin && (proses.mesin.nama || proses.mesin.jenis_mesin)) {
                currentMesinNama = proses.mesin.nama || proses.mesin.jenis_mesin;
            } else if (window.mesinsData && Array.isArray(window.mesinsData)) {
                const found = window.mesinsData.find(m => parseInt(m.id || m.mesin_id) === currentMesinId);
                if (found) currentMesinNama = found.nama || found.jenis_mesin || ('Mesin ' + currentMesinId);
            }
            $('#pinjamMesinAsal').val(currentMesinNama);

            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function () {
                $('#modalPinjamMesin').modal('show');
                $('body').addClass('modal-open');
            });
        });

        // Handler submit form pinjam mesin
        $('#formPinjamMesin').on('submit', function (e) {
            e.preventDefault();
            const form = $(this);
            const url = form.attr('action');
            const alasan = ($('#pinjamAlasan').val() || '').trim();

            if (!alasan) {
                ToastError.fire({
                    title: 'Alasan pinjam mesin wajib diisi.'
                });
                $('#pinjamAlasan').focus();
                return false;
            }

            const $submitBtn = form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Mengaktifkan...');

            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    alasan: alasan
                },
                success: function (response) {
                    $('#modalPinjamMesin').modal('hide');
                    if (response && response.status === 'success' && response.message) {
                        ToastSuccess.fire({
                            title: response.message
                        });
                    }
                    // Update DOM card langsung tanpa reload
                    const pinjamPid = $('#pinjamProsesId').val();
                    const $card = $(`.status-card[data-proses-id="${pinjamPid}"]`);
                    if ($card.length) {
                        const cardProses = $card.data('proses') || {};
                        cardProses.is_pinjam_mesin = true;
                        cardProses.pinjam_mesin_alasan = alasan;
                        $card.data('proses', cardProses);
                        const $pinjamIndicator = $card.find('.pinjam-mesin-indicator');
                        $pinjamIndicator.css('display', 'flex');
                        $pinjamIndicator.find('.badge-pinjam-mesin').attr('title', 'Pinjam Mesin Aktif: ' + alasan);
                    }
                },
                error: function (xhr) {
                    let errorMsg = 'Gagal mengaktifkan pinjam mesin.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    }
                    ToastError.fire({
                        title: errorMsg
                    });
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i>Aktifkan Pinjam Mesin');
                }
            });
        });

        // Handler Simpan Catatan Proses (Note)
        $(document).on('click', '#btn-save-proses-note', function (e) {
            e.preventDefault();
            const proses = $('#modalDetailProses').data('proses') || {};
            const prosesId = proses.id || $('#modalDetailProses').data('prosesId');
            if (!prosesId) return;

            const noteContent = ($('#proses-note-text').val() || '').trim();
            const $btn = $(this);

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...');

            $.ajax({
                url: `/proses/${prosesId}/note`,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    note: noteContent
                },
                success: function (response) {
                    ToastSuccess.fire({
                        title: response.message || 'Catatan proses berhasil disimpan.'
                    });

                    // Update data cache di modal & card
                    proses.note = noteContent;
                    $('#modalDetailProses').data('proses', proses);

                    const $card = $(`.status-card[data-proses-id="${prosesId}"]`);
                    if ($card.length) {
                        const cardProses = $card.data('proses') || {};
                        cardProses.note = noteContent;
                        $card.data('proses', cardProses);

                        // Update indikator icon note di card (di samping kanan button detail proses)
                        const $noteSlot = $card.find('.note-icon-slot');
                        if (noteContent) {
                            if ($noteSlot.length) {
                                $noteSlot.html('<i class="fas fa-sticky-note text-warning icon-has-note" title="Catatan proses tersimpan" style="font-size: 16px; vertical-align: middle; text-shadow: 0 1px 2px #000; cursor: pointer;"></i>');
                            }
                        } else {
                            if ($noteSlot.length) {
                                $noteSlot.empty();
                            } else {
                                $card.find('.icon-has-note').remove();
                            }
                        }
                    }
                },
                error: function (xhr) {
                    ToastError.fire({
                        title: xhr.responseJSON?.message || 'Gagal menyimpan catatan proses.'
                    });
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan Catatan');
                }
            });
        });

        // Handler Preview Riwayat Peminjaman Mesin
        $(document).on('click', '.btn-preview-pinjam-mesin', function (e) {
            e.preventDefault();
            const proses = $('#modalDetailProses').data('proses') || {};
            const prosesId = proses.id || $('#modalDetailProses').data('prosesId');
            if (!prosesId) return;

            const jenisText = proses.jenis || 'Produksi';
            const noOp = proses.details && proses.details.length ? proses.details.map(d => d.no_op).filter(Boolean).join(', ') : (proses.no_op || '-');
            $('#pinjam-history-proses-info').html(`<i class="fas fa-info-circle mr-1"></i> <strong>Proses #${prosesId}</strong> (${jenisText}) | OP: ${noOp}`);
            $('#pinjam-history-count').text('Memuat...');
            $('#pinjam-history-tbody').html('<tr><td colspan="7" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Memuat riwayat peminjaman mesin...</td></tr>');

            $('#modalPinjamMesinHistory').modal('show');

            $.ajax({
                url: `/proses/${prosesId}/pinjam-mesin-history`,
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function (response) {
                    const data = response.data || [];
                    $('#pinjam-history-count').text(`${data.length} Sesi`);

                    if (!data.length) {
                        $('#pinjam-history-tbody').html('<tr><td colspan="7" class="text-center py-3 text-muted">Belum ada riwayat peminjaman mesin untuk proses ini.</td></tr>');
                        return;
                    }

                    let html = '';
                    data.forEach((item, idx) => {
                        const selesaiHtml = item.selesai_at_formatted
                            ? item.selesai_at_formatted
                            : '<span class="badge badge-warning"><i class="fas fa-spinner fa-spin mr-1"></i>Sedang Berjalan</span>';
                        const userPinjam = item.user_pinjam ? `${item.user_pinjam.nama} <small class="text-muted">(${item.user_pinjam.role})</small>` : '-';
                        const userSelesai = item.user_selesai ? `${item.user_selesai.nama} <small class="text-muted">(${item.user_selesai.role})</small>` : (item.selesai_at_formatted ? '-' : '<span class="text-muted">-</span>');

                        html += `<tr>
                                        <td class="text-center">${idx + 1}</td>
                                        <td>${item.pinjam_at_formatted || '-'}</td>
                                        <td>${selesaiHtml}</td>
                                        <td><span class="badge badge-light border">${item.durasi_formatted || '-'}</span></td>
                                        <td>${item.alasan || '-'}</td>
                                        <td>${userPinjam}</td>
                                        <td>${userSelesai}</td>
                                    </tr>`;
                    });
                    $('#pinjam-history-tbody').html(html);
                },
                error: function (xhr) {
                    $('#pinjam-history-count').text('0 Sesi');
                    $('#pinjam-history-tbody').html(`<tr><td colspan="7" class="text-center py-3 text-danger"><i class="fas fa-exclamation-circle mr-1"></i>${xhr.responseJSON?.message || 'Gagal memuat riwayat peminjaman mesin.'}</td></tr>`);
                }
            });
        });

        // Pastikan backdrop & scroll modalDetailProses tetap aktif saat modalPinjamMesinHistory ditutup
        $('#modalPinjamMesinHistory').on('hidden.bs.modal', function () {
            if ($('#modalDetailProses').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });

        // Handler tombol Break (Toggle ON / OFF)
        $(document).on('click', '.btn-break-proses', function (e) {
            e.preventDefault();
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            const id = proses.id;
            const breakUrl = "{{ url('proses') }}/" + id + "/break";
            const isBreakActive = $(this).data('is-break-active') === true;

            if (isBreakActive) {
                // Konfirmasi untuk mematikan status Break (Lanjutkan proses)
                Swal.fire({
                    title: 'Lanjutkan Proses?',
                    text: 'Status Break akan dinonaktifkan dan cycle time proses akan dilanjutkan.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Lanjutkan Proses',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Memproses...',
                            text: 'Melanjutkan proses...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $.ajax({
                            url: breakUrl,
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                action: 'deactivate'
                            },
                            success: function (response) {
                                $('#modalDetailProses').modal('hide');
                                ToastSuccess.fire({
                                    title: response.message || 'Proses berhasil dilanjutkan.'
                                });
                                setTimeout(function () {
                                    if (response && response.redirect) {
                                        window.location.href = response.redirect;
                                    } else {
                                        window.location.reload();
                                    }
                                }, 500);
                            },
                            error: function (xhr) {
                                let errorMsg = 'Gagal melanjutkan proses.';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMsg = xhr.responseJSON.message;
                                }
                                ToastError.fire({
                                    title: errorMsg
                                });
                            }
                        });
                    }
                });
                return false;
            }

            $('#formBreakProses').attr('action', breakUrl);
            $('#breakProsesId').val(id);
            $('#breakAlasan').val('');

            // Set info proses
            const currentMesinId = proses.mesin_id ? parseInt(proses.mesin_id) : null;
            let currentMesinNama = 'Mesin ' + currentMesinId;
            if (proses.mesin && (proses.mesin.nama || proses.mesin.jenis_mesin)) {
                currentMesinNama = proses.mesin.nama || proses.mesin.jenis_mesin;
            } else if (window.mesinsData && Array.isArray(window.mesinsData)) {
                const found = window.mesinsData.find(m => parseInt(m.id || m.mesin_id) === currentMesinId);
                if (found) currentMesinNama = found.nama || found.jenis_mesin || ('Mesin ' + currentMesinId);
            }
            $('#breakMesinAsal').val(currentMesinNama);

            const firstDetail = getFirstDetailProses(proses);
            const noOpStr = firstDetail ? (firstDetail.no_op || '-') : '-';
            const noPartaiStr = firstDetail ? (firstDetail.no_partai || '-') : '-';
            const jenisStr = proses.jenis || '-';
            if (jenisStr === 'Maintenance') {
                $('#breakProsesInfo').html(`<strong>Jenis:</strong> Maintenance | <strong>Mesin:</strong> ${currentMesinNama}`);
            } else {
                $('#breakProsesInfo').html(`<strong>Jenis:</strong> ${jenisStr} | <strong>No OP:</strong> ${noOpStr} | <strong>No Partai:</strong> ${noPartaiStr}`);
            }

            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function () {
                $('#modalBreakProses').modal('show');
                $('body').addClass('modal-open');
            });
        });

        // Handler submit form break proses
        $('#formBreakProses').on('submit', function (e) {
            e.preventDefault();
            const form = $(this);
            const url = form.attr('action');
            const alasan = ($('#breakAlasan').val() || '').trim();

            if (!alasan) {
                ToastError.fire({
                    title: 'Alasan break proses wajib diisi.'
                });
                $('#breakAlasan').focus();
                return false;
            }

            const $submitBtn = form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Mengaktifkan...');

            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    action: 'activate',
                    alasan: alasan
                },
                success: function (response) {
                    $('#modalBreakProses').modal('hide');
                    if (response && response.status === 'success' && response.message) {
                        ToastSuccess.fire({
                            title: response.message
                        });
                    }
                    setTimeout(function () {
                        if (response && response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 500);
                },
                error: function (xhr) {
                    let errorMsg = 'Gagal mengaktifkan break proses.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    }
                    ToastError.fire({
                        title: errorMsg
                    });
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-pause mr-1"></i>Aktifkan Break');
                }
            });
        });

        // Handler Preview Riwayat Break Proses
        $(document).on('click', '.btn-preview-break-proses', function (e) {
            e.preventDefault();
            const proses = $('#modalDetailProses').data('proses') || {};
            const prosesId = proses.id || $('#modalDetailProses').data('prosesId');
            if (!prosesId) return;

            const jenisText = proses.jenis || 'Produksi';
            const noOp = proses.details && proses.details.length ? proses.details.map(d => d.no_op).filter(Boolean).join(', ') : (proses.no_op || '-');
            $('#break-history-proses-info').html(`<i class="fas fa-info-circle mr-1"></i> <strong>Proses #${prosesId}</strong> (${jenisText}) | OP: ${noOp}`);
            $('#break-history-count').text('Memuat...');
            $('#break-history-tbody').html('<tr><td colspan="7" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Memuat riwayat break proses...</td></tr>');

            $('#modalBreakProsesHistory').modal('show');

            $.ajax({
                url: `/proses/${prosesId}/break-history`,
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function (response) {
                    const data = response.data || [];
                    $('#break-history-count').text(`${data.length} Sesi`);

                    if (!data.length) {
                        $('#break-history-tbody').html('<tr><td colspan="7" class="text-center py-3 text-muted">Belum ada riwayat break untuk proses ini.</td></tr>');
                        return;
                    }

                    let html = '';
                    data.forEach((item, idx) => {
                        const selesaiHtml = item.selesai_at_formatted
                            ? item.selesai_at_formatted
                            : '<span class="badge badge-warning"><i class="fas fa-pause mr-1"></i>Sedang Break</span>';
                        const userBreak = item.user_break ? `${item.user_break.nama} <small class="text-muted">(${item.user_break.role})</small>` : '-';
                        const userSelesai = item.user_selesai ? `${item.user_selesai.nama} <small class="text-muted">(${item.user_selesai.role})</small>` : (item.selesai_at_formatted ? '-' : '<span class="text-muted">-</span>');

                        html += `<tr>
                                        <td class="text-center">${idx + 1}</td>
                                        <td>${item.break_at_formatted || '-'}</td>
                                        <td>${selesaiHtml}</td>
                                        <td><span class="badge badge-light border">${item.durasi_formatted || '-'}</span></td>
                                        <td>${item.alasan || '-'}</td>
                                        <td>${userBreak}</td>
                                        <td>${userSelesai}</td>
                                    </tr>`;
                    });
                    $('#break-history-tbody').html(html);
                },
                error: function (xhr) {
                    $('#break-history-count').text('0 Sesi');
                    $('#break-history-tbody').html(`<tr><td colspan="7" class="text-center py-3 text-danger"><i class="fas fa-exclamation-circle mr-1"></i>${xhr.responseJSON?.message || 'Gagal memuat riwayat break proses.'}</td></tr>`);
                }
            });
        });

        // Pastikan backdrop & scroll modalDetailProses tetap aktif saat modalBreakProsesHistory ditutup
        $('#modalBreakProsesHistory').on('hidden.bs.modal', function () {
            if ($('#modalDetailProses').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });

        // Handler tombol Hapus Proses (kirim permintaan delete ke approval FM)
        $(document).on('click', '.btn-delete-proses', function (e) {
            e.preventDefault();
            // Cek apakah tombol disabled atau user tidak punya hak akses
            if ($(this).prop('disabled') || $(this).hasClass('disabled') || window.canDeleteProses === false) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            // Validasi: cek apakah proses sudah dimulai
            if (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '') {
                ToastError.fire({
                    title: 'Tidak dapat menghapus proses. Proses sudah dimulai.'
                });
                return false;
            }

            // Validasi: cek pending approval VP untuk Reproses
            const hasPendingReprocess = hasPendingReprocessApproval(proses);
            if (hasPendingReprocess) {
                ToastError.fire({
                    title: 'Tidak dapat menghapus proses. Proses Reproses masih menunggu persetujuan FM/VP dan akan di-skip dari antrian start otomatis.'
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
            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function () {
                $('#modalDeleteProses').modal('show');
                // Tambahkan class modal-open secara manual jika scroll masih hilang
                $('body').addClass('modal-open');
            });
        });

        // Handler tombol Pause Proses (kirim permintaan pause ke approval FM)
        $(document).on('click', '.btn-pause-proses', function (e) {
            e.preventDefault();
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            const id = proses.id;
            const pauseUrl = "{{ url('proses') }}/" + id + "/pause";

            $('#formPauseProses').attr('action', pauseUrl);
            $('#pauseProsesId').val(id);

            // Tutup modal detail, lalu buka modal pause SETELAH detail tertutup sempurna
            $('#modalDetailProses').modal('hide').one('hidden.bs.modal', function () {
                $('#modalPauseProses').modal('show');
                $('body').addClass('modal-open');
            });
        });

        // Handler tombol Selesai Proses Maintenance (manual end maintenance)
        $(document).on('click', '.btn-finish-maintenance', function (e) {
            e.preventDefault();
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            Swal.fire({
                title: 'Selesaikan Maintenance?',
                text: 'Jika mesin masih berjalan, sistem akan menunggu mesin dimatikan oleh operator di lapangan sebelum maintenance resmi selesai.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check mr-1"></i>Ya, Selesaikan',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses permintaan...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: `{{ url('proses') }}/${proses.id}/selesai`,
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (res) {
                            $('#modalDetailProses').modal('hide');
                            const isWait = !!res.waiting_off;
                            Swal.fire({
                                icon: isWait ? 'info' : 'success',
                                title: isWait ? 'Menunggu Mesin Mati' : 'Berhasil',
                                text: res.message || 'Proses Maintenance berhasil diproses.',
                                timer: isWait ? 2500 : 1500,
                                showConfirmButton: false
                            });
                            // Update DOM card langsung tanpa reload
                            const $card = $(`.status-card[data-proses-id="${proses.id}"]`);
                            if ($card.length) {
                                if (isWait) {
                                    const cardProses = $card.data('proses') || {};
                                    cardProses.stop_requested_at = (res.data && res.data.stop_requested_at) ? res.data.stop_requested_at : new Date().toISOString();
                                    cardProses.stop_request_type = 'maintenance_finish';
                                    $card.data('proses', cardProses);
                                    if (!$card.find('.waiting-stop-banner').length) {
                                        const bannerHtml = `
                                                <div class="waiting-stop-banner" style="background: linear-gradient(90deg, #ff9800, #ffb74d); color: #111; font-weight: 800; font-size: 11px; padding: 3px 6px; text-align: center; border-bottom: 1px solid rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; gap: 5px;">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                    <span>Menunggu Mesin Mati (Sinyal 103 ON)</span>
                                                </div>
                                            `;
                                        $card.prepend(bannerHtml);
                                    }
                                } else {
                                    if (typeof moveToHistory === 'function') {
                                        moveToHistory($card, res.data || {});
                                    }
                                }
                            }
                        },
                        error: function (xhr) {
                            let errMsg = 'Gagal menyelesaikan proses Maintenance.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errMsg = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: errMsg
                            });
                        }
                    });
                }
            });
        });

        // Handler tombol Selesai Proses Paksa (Produksi / Reproses untuk Kepala Shift & Super Admin)
        $(document).on('click', '.btn-finish-force', function (e) {
            e.preventDefault();
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            Swal.fire({
                title: 'Selesaikan Proses Secara Paksa?',
                text: 'Jika mesin masih berjalan, sistem akan menunggu verifikasi unload atau mesin dimatikan di lapangan sebelum proses selesai dan dialihkan ke antrian berikutnya.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check mr-1"></i>Ya, Force End',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses permintaan...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: `{{ url('proses') }}/${proses.id}/force-finish`,
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (res) {
                            $('#modalDetailProses').modal('hide');
                            const isWait = !!res.waiting_off;
                            Swal.fire({
                                icon: isWait ? 'info' : 'success',
                                title: isWait ? 'Menunggu Unload / Mesin Mati' : 'Berhasil',
                                text: res.message || 'Perintah Force End berhasil diproses.',
                                timer: isWait ? 2500 : 1500,
                                showConfirmButton: false
                            });
                            // Update DOM card langsung tanpa reload
                            const $card = $(`.status-card[data-proses-id="${proses.id}"]`);
                            if ($card.length) {
                                if (isWait) {
                                    const cardProses = $card.data('proses') || {};
                                    cardProses.stop_requested_at = (res.data && res.data.stop_requested_at) ? res.data.stop_requested_at : new Date().toISOString();
                                    cardProses.stop_request_type = 'force_finish';
                                    $card.data('proses', cardProses);
                                    if (!$card.find('.waiting-stop-banner').length) {
                                        const bannerHtml = `
                                                <div class="waiting-stop-banner" style="background: linear-gradient(90deg, #ff9800, #ffb74d); color: #111; font-weight: 800; font-size: 11px; padding: 3px 6px; text-align: center; border-bottom: 1px solid rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; gap: 5px;">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                    <span>Menunggu Mesin Mati (Sinyal 103 ON)</span>
                                                </div>
                                            `;
                                        $card.prepend(bannerHtml);
                                    }
                                } else {
                                    if (typeof moveToHistory === 'function') {
                                        moveToHistory($card, res.data || {});
                                    }
                                }
                            }
                        },
                        error: function (xhr) {
                            let errMsg = 'Gagal menyelesaikan proses.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errMsg = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: errMsg
                            });
                        }
                    });
                }
            });
        });

        // Handler tombol Batal Selesai / Batal Force End
        $(document).on('click', '.btn-cancel-stop-request', function (e) {
            e.preventDefault();
            if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
                return false;
            }
            const proses = $('#modalDetailProses').data('proses');
            if (!proses) return;

            Swal.fire({
                title: 'Batalkan Perintah Selesai?',
                text: 'Apakah Anda yakin ingin membatalkan perintah selesai ini? Proses akan dilanjutkan dan tetap berjalan normal.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff9800',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-undo mr-1"></i>Ya, Batalkan',
                cancelButtonText: 'Kembali',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Membatalkan...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: `{{ url('proses') }}/${proses.id}/cancel-stop-request`,
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (res) {
                            $('#modalDetailProses').modal('hide');
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil Dibatalkan',
                                text: res.message || 'Perintah selesai berhasil dibatalkan.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            // Update DOM card langsung tanpa reload
                            const $card = $(`.status-card[data-proses-id="${proses.id}"]`);
                            if ($card.length) {
                                const cardProses = $card.data('proses') || {};
                                cardProses.stop_requested_at = null;
                                cardProses.stop_request_type = null;
                                $card.data('proses', cardProses);
                                $card.find('.waiting-stop-banner').remove();
                            }
                        },
                        error: function (xhr) {
                            let errMsg = 'Gagal membatalkan perintah selesai.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errMsg = xhr.responseJSON.message;
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: errMsg
                            });
                        }
                    });
                }
            });
        });

        // Submit handler untuk mencegah double click pada modal Pause
        $('#formPauseProses').on('submit', function (e) {
            const $btn = $(this).find('button[type="submit"]');
            if ($btn.prop('disabled') || $btn.hasClass('disabled')) {
                e.preventDefault();
                return false;
            }
            $btn.prop('disabled', true).addClass('disabled').html('<i class="fas fa-spinner fa-spin mr-1"></i>Memproses...');
        });

        // Modal scan barcode (HTML, append ke body jika belum ada)
        // 2 mode: Scan (kamera) dan Input Manual (ketik barcode)
        if (!document.getElementById('modalScanBarcode')) {
            $(document.body).append(`
                                                                                            <div class="modal fade" id="modalScanBarcode" tabindex="-1" aria-labelledby="modalScanBarcodeLabel" aria-hidden="true">
                                                                                                <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
                                                                                                    <div class="modal-content shadow-lg border-0 rounded-3">
                                                                                                        <form id="formScanBarcode" method="POST" action="">
                                                                                                            @csrf
                                                                                                            <input type="hidden" name="barcode" id="inputBarcodeValue">
                                                                                                            <input type="hidden" name="detail_proses_id" id="inputDetailProsesId">
                                                                                                            <input type="hidden" name="approval_id" id="inputApprovalId">
                                                                                                            <div class="modal-header bg-success text-white">
                                                                                                                <h5 class="modal-title fw-bold" id="modalScanBarcodeLabel">Input Barcode</h5>
                                                                                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                                                                                    <span aria-hidden="true">&times;</span>
                                                                                                                </button>
                                                                                                            </div>
                                                                                                            <div class="modal-body py-3 px-4 text-center">
                                                                                                                <ul class="nav nav-pills nav-fill mb-3" id="barcodeModeTabs" role="tablist">
                                                                                                                    <li class="nav-item" role="presentation">
                                                                                                                        <a class="nav-link active" id="mode-scan-tab" data-toggle="pill" href="#mode-scan-pane" role="tab" aria-controls="mode-scan-pane" aria-selected="true"><i class="fas fa-barcode"></i> Scan Barcode</a>
                                                                                                                    </li>
                                                                                                                    <li class="nav-item" role="presentation">
                                                                                                                        <a class="nav-link" id="mode-manual-tab" data-toggle="pill" href="#mode-manual-pane" role="tab" aria-controls="mode-manual-pane" aria-selected="false"><i class="fas fa-keyboard"></i> Input Manual</a>
                                                                                                                    </li>
                                                                                                                </ul>
                                                                                                                <div class="tab-content position-relative" id="barcodeModeContent" style="min-height:320px;">
                                                                                                                    <div id="barcode-submit-loading" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.9);align-items:center;justify-content:center;z-index:10;flex-direction:column;">
                                                                                                                        <div class="spinner-border text-success mb-2" style="width:3rem;height:3rem;"></div>
                                                                                                                        <p class="text-dark mb-0">Memproses barcode...</p>
                                                                                                                    </div>
                                                                                                                    <div class="tab-pane fade show active" id="mode-scan-pane" role="tabpanel">
                                                                                                                        <div id="barcode-scanner-container" style="width:100%;min-height:320px;display:flex;align-items:center;justify-content:center;"></div>
                                                                                                                    </div>
                                                                                                                    <div class="tab-pane fade" id="mode-manual-pane" role="tabpanel">
                                                                                                                         <div id="barcode-manual-container" class="py-3">
                                                                                                                             <label for="inputBarcodeManual" class="d-block text-left mb-2 font-weight-bold">Ketik kode barcode:</label>
                                                                                                                             <input type="text" class="form-control form-control-lg text-center" id="inputBarcodeManual" placeholder="Masukkan barcode" maxlength="255" autocomplete="off">
                                                                                                                             <small class="text-muted d-block mt-2">Tekan Enter atau klik Simpan setelah mengisi barcode.</small>
                                                                                                                             <button type="button" class="btn btn-success mt-3" id="btnSubmitManualBarcode"><i class="fas fa-check"></i> Simpan Barcode</button>
                                                                                                                         </div>
                                                                                                                         <div id="kain-material-makloon-container" class="py-3 text-left" style="display:none;">
                                                                                                                             <label for="selectMaterialMakloon" class="d-block font-weight-bold mb-1">
                                                                                                                                 <i class="fas fa-boxes text-success mr-1"></i> Pilih Material Kain Stock (SAP):
                                                                                                                             </label>
                                                                                                                             <select id="selectMaterialMakloon" class="form-control" style="width:100%;">
                                                                                                                                 <option value="">-- Cari Kode / Deskripsi Material --</option>
                                                                                                                             </select>
                                                                                                                             <div id="material-stock-info" class="mt-2 p-2 rounded" style="display:none; font-size:12px; background:#f8f9fa; border:1px solid #ddd;">
                                                                                                                                 <div><strong>Deskripsi:</strong> <span id="mkl-info-desc">-</span></div>
                                                                                                                                 <div><strong>Batch:</strong> <span id="mkl-info-batch">-</span> | <strong>Sloc:</strong> <span id="mkl-info-sloc">-</span></div>
                                                                                                                                 <div><strong>Available Qty:</strong> <span id="mkl-info-qty" class="text-success font-weight-bold">-</span></div>
                                                                                                                             </div>
                                                                                                                             <button type="button" class="btn btn-success mt-3" id="btnSubmitMaterialMakloon">
                                                                                                                                 <i class="fas fa-plus mr-1"></i> Tambah Material ke Daftar
                                                                                                                             </button>
                                                                                                                         </div>
                                                                                                                     </div>
                                                                                                                </div>
                                                                                                                {{-- Section pending list barcode kain (hanya tampil saat barcode_kain) --}}
                                                                                                                <div id="kain-pending-section" class="mt-3" style="display:none;">
                                                                                                                    <hr class="my-2">
                                                                                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                                                        <strong class="text-left">Daftar Barcode Kain</strong>
                                                                                                                        <span id="kain-pending-counter" class="badge badge-secondary" style="font-size:12px;">0/0</span>
                                                                                                                    </div>
                                                                                                                    <div id="kain-pending-list" style="max-height:220px;overflow-y:auto;padding:4px 2px;">
                                                                                                                        <span style="color:#888;font-size:12px;">Belum ada barcode. Scan atau ketik manual untuk menambah.</span>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                            <div class="modal-footer d-flex justify-content-between px-4">
                                                                                                                <button type="button" class="btn btn-success" id="btnSubmitKainBatch" style="display:none;">
                                                                                                                    <i class="fas fa-save"></i> Simpan Barcode (<span id="kain-pending-submit-count">0</span>)
                                                                                                                </button>
                                                                                                                <button type="button" class="btn btn-secondary ml-auto" data-dismiss="modal">Tutup</button>
                                                                                                            </div>
                                                                                                        </form>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                            `);
        }

        // ========================================================
        // STATE & HELPER untuk multi-barcode KAIN (batch scan)
        // ========================================================
        window.kainScanState = {
            active: false,
            prosesId: null,
            detailId: null,
            roll: 0,
            alreadySaved: 0,
            remaining: 0,
            pending: [] // [{barcode, container, raw}]
        };

        function resetKainScanState() {
            window.kainScanState = {
                active: false,
                prosesId: null,
                detailId: null,
                roll: 0,
                alreadySaved: 0,
                remaining: 0,
                pending: []
            };
            renderKainPendingList();
            $('#kain-pending-section').hide();
            $('#btnSubmitKainBatch').hide();

            // Reset manual input state agar tidak terbawa dari scan sebelumnya (misal Kain yang sudah lengkap)
            $('#inputBarcodeManual').prop('disabled', false).attr('placeholder', 'Masukkan barcode');
            $('#btnSubmitManualBarcode').prop('disabled', false);
        }

        function updateBarcodeScanUI() {
            const s = window.kainScanState;
            if (!s.active) {
                // Jika tidak sedang scan kain (misal LA/AUX), pastikan UI input aktif
                $('#inputBarcodeManual').prop('disabled', false).attr('placeholder', 'Masukkan barcode');
                $('#btnSubmitManualBarcode').prop('disabled', false);
                return;
            }

            const isComplete = s.remaining <= 0 || s.pending.length >= s.remaining;

            if (isComplete) {
                // Disable manual inputs
                $('#inputBarcodeManual').prop('disabled', true).val('').attr('placeholder', 'Barcode sudah lengkap');
                $('#btnSubmitManualBarcode').prop('disabled', true);

                // Disable batch submit if pending fulfills or already full
                if (s.remaining <= 0) {
                    $('#btnSubmitKainBatch').prop('disabled', true).addClass('btn-secondary').removeClass('btn-success');
                }

                // Protect Scan tab
                if ($('#mode-scan-pane').hasClass('active')) {
                    const isBatchFull = s.remaining > 0 && s.pending.length >= s.remaining;
                    if (isBatchFull || s.remaining <= 0) {
                        $('#barcode-scanner-container').html(
                            '<div class="d-flex align-items-center justify-content-center" style="height:320px; background:#fff;">' +
                            '<div class="text-center"><i class="fas fa-check-circle fa-4x mb-3" style="color:#28a745;"></i>' +
                            '<p class="mb-0 font-weight-bold" style="color:#28a745; font-size:18px;">Barcode sudah terpenuhi.</p></div>' +
                            '</div>'
                        );
                    }
                }

                // Stop scanner if running
                if (window.html5QrcodeScanner) {
                    try { window.html5QrcodeScanner.stop().catch(() => { }); } catch (e) { }
                }
            } else {
                // Enable manual inputs
                // Jika butuh lebih dari 0 dan pending belum penuh
                $('#inputBarcodeManual').prop('disabled', false).attr('placeholder', 'Masukkan barcode');
                $('#btnSubmitManualBarcode').prop('disabled', false);
                $('#btnSubmitKainBatch').prop('disabled', false).addClass('btn-success').removeClass('btn-secondary');

                // Jika scanner container sedang menampilkan pesan "Lengkap", bersihkan agar bisa dirender ulang/start scanner
                if ($('#barcode-scanner-container .alert-success').length || $('#barcode-scanner-container div:contains("lengkap")').length) {
                    $('#barcode-scanner-container').html('');
                }
            }
        }

        function renderKainPendingList() {
            const s = window.kainScanState;
            const $list = $('#kain-pending-list');
            const $counter = $('#kain-pending-counter');
            const $btn = $('#btnSubmitKainBatch');
            const $btnCount = $('#kain-pending-submit-count');

            if (!s.active) {
                return;
            }

            $counter.text(`${s.pending.length}/${s.remaining}`);

            if (!s.pending.length) {
                $list.html('<span style="color:#888;font-size:12px;">Belum ada barcode. Scan atau ketik manual untuk menambah.</span>');
            } else {
                let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                s.pending.forEach(function (item, idx) {
                    const cont = item.container ? `<br><span style='font-size:11px;color:#888;'>${item.container}</span>` : '';
                    html += `<div style="position:relative;flex:1 0 30%;max-width:32%;background:#f3f3f3;border-radius:6px;padding:8px 4px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">
                                                                                                        <span class="remove-pending-kain" data-idx="${idx}" style="position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;" title="Hapus">&times;</span>
                                                                                                        ${item.barcode}${cont}
                                                                                                    </div>`;
                });
                html += '</div>';
                $list.html(html);
            }

            // Tampilkan tombol Simpan Barcode batch bila ada barcode pending (tidak harus lengkap)
            if (s.pending.length > 0) {
                $btnCount.text(s.pending.length);
                $btn.show();
            } else {
                $btn.hide();
            }
        }

        // Tambah 1 barcode ke pending list (dipanggil dari scanner & input manual)
        function addPendingKainBarcode(rawInput) {
            const s = window.kainScanState;
            if (!s.active) return false;
            const raw = (rawInput || '').trim();
            if (!raw) return false;

            const isMkl = !!s.isMakloon;
            const bc = isMkl ? raw : raw.substring(0, 10);
            const container = (!isMkl && raw.length > 10) ? raw.substring(10, 20) : '';

            if (bc.length < 3) {
                showToastNotification('error', isMkl ? 'Kode material terlalu pendek.' : 'Barcode terlalu pendek.');
                return false;
            }

            // Cek duplikat di pending list
            if (s.pending.some(p => p.barcode === bc)) {
                showToastNotification('error', `${isMkl ? 'Material' : 'Barcode'} ${bc} sudah ada di daftar.`);
                return false;
            }

            // Cek kapasitas
            if (s.pending.length >= s.remaining) {
                showToastNotification('error', `Jumlah ${isMkl ? 'material' : 'barcode'} sudah mencapai kebutuhan (${s.remaining}). Klik Simpan Barcode untuk menyimpan.`);
                return false;
            }

            // Untuk Makloon: Material berasal dari stock SAP terverifikasi, langsung masukkan ke pending list tanpa cek barcode aktif fisik
            if (isMkl) {
                s.pending.push({ barcode: bc, container: container, raw: raw });
                renderKainPendingList();
                updateBarcodeScanUI();
                return true;
            }

            // REAL-TIME VALIDASI KE SERVER
            // Tampilkan loading saat mengecek
            const $btnManual = $('#btnSubmitManualBarcode');
            const $inputManual = $('#inputBarcodeManual');
            const originalBtnHtml = $btnManual.html();

            $btnManual.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Cek...');
            $inputManual.prop('disabled', true);

            // Bila scanner jalan, hentikan sementara agar tidak scan double saat delay network
            const scannerWasRunning = window.html5QrcodeScanner && window.html5QrcodeScanner.isScanning;
            if (scannerWasRunning) {
                try { window.html5QrcodeScanner.pause(); } catch (e) { }
            }

            $.ajax({
                url: '/api/check-barcode-active',
                method: 'POST',
                data: {
                    barcode: bc,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function (r) {
                    if (r && r.active) {
                        showToastNotification('error', `Barcode ${bc} sudah pernah digunakan dan aktif.`);
                        if (scannerWasRunning) {
                            try { window.html5QrcodeScanner.resume(); } catch (e) { }
                        }
                    } else {
                        // Semua aman, masukkan ke list
                        s.pending.push({ barcode: bc, container: container, raw: raw });
                        renderKainPendingList();
                        updateBarcodeScanUI();

                        // Bila sudah lengkap, scanner dihentikan permanen oleh updateBarcodeScanUI
                        if (s.pending.length < s.remaining && scannerWasRunning) {
                            try { window.html5QrcodeScanner.resume(); } catch (e) { }
                        }
                    }
                },
                error: function () {
                    showToastNotification('error', 'Gagal memvalidasi barcode ke server.');
                    if (scannerWasRunning) {
                        try { window.html5QrcodeScanner.resume(); } catch (e) { }
                    }
                },
                complete: function () {
                    $btnManual.prop('disabled', false).html(originalBtnHtml);
                    $inputManual.prop('disabled', false).focus();
                    updateBarcodeScanUI(); // Refresh state tombol simpan batch dsb
                }
            });

            return true;
        }

        // Hapus item dari pending list
        $(document).on('click', '.remove-pending-kain', function () {
            const idx = parseInt($(this).data('idx'), 10);
            const s = window.kainScanState;
            if (!s.active || isNaN(idx)) return;
            s.pending.splice(idx, 1);
            renderKainPendingList();
            // PENTING: Update UI agar input/kamera kembali aktif jika daftar jadi tidak lengkap
            updateBarcodeScanUI();

            // Restart scanner jika di mode Scan
            if ($('#mode-scan-pane').hasClass('active')) {
                startBarcodeScannerInModal();
            }
        });

        // Handler klik tombol scan barcode (hanya set data, scanner diinisialisasi saat modal tampil)
        $(document).on('click', '.scan-barcode-btn', function () {
            // Cek apakah tombol disabled
            if ($(this).prop('disabled')) {
                return false; // Jangan lakukan apapun jika tombol disabled
            }

            const barcodeType = $(this).data('barcode');
            const prosesId = $(this).data('id');
            const detailId = $(this).data('detail-id') || $('#modalDetailProses').data('detailProsesId') || '';
            const approvalId = $(this).data('approval-id') || '';
            let actionUrl = '';
            if (barcodeType === 'barcode_kain') {
                actionUrl = `/proses/${prosesId}/barcode/kain`;
            } else if (barcodeType === 'barcode_la') {
                actionUrl = `/proses/${prosesId}/barcode/la`;
            } else if (barcodeType === 'barcode_aux') {
                actionUrl = `/proses/${prosesId}/barcode/aux`;
            }
            $('#formScanBarcode').attr('action', actionUrl);
            $('#formScanBarcode').data('proses-id', prosesId);
            $('#formScanBarcode').data('barcode-type', barcodeType);
            $('#inputBarcodeValue').val('');
            $('#inputDetailProsesId').val(detailId);
            $('#inputApprovalId').val(approvalId);

            // Ambil data proses
            const $card = $(`.status-card[data-proses-id="${prosesId}"]`);
            const prosesData = $card.length ? $card.data('proses') : $('#modalDetailProses').data('proses');
            const isMakloon = prosesData && (prosesData.jenis === 'Proses Makloon' || prosesData.jenis === 'Reproses Makloon');

            // Update judul modal agar lebih jelas
            let title = barcodeType === 'barcode_kain' ? 'Input Barcode Kain' :
                (barcodeType === 'barcode_la' ? 'Input Barcode Dye Stuff' : 'Input Barcode AUX');
            if (barcodeType === 'barcode_kain' && isMakloon) {
                title = 'Input Material Kain (Makloon)';
            }
            $('#modalScanBarcodeLabel').text(title);

            // Reset state kain
            resetKainScanState();
            window.kainScanState.isMakloon = isMakloon;

            if (barcodeType === 'barcode_kain' && isMakloon) {
                $('#mode-scan-tab').parent().hide();
                $('#mode-manual-tab').tab('show');
                $('#barcode-manual-container').hide();
                $('#kain-material-makloon-container').show();
                initSelectMaterialMakloon();
            } else {
                $('#mode-scan-tab').parent().show();
                $('#barcode-manual-container').show();
                $('#kain-material-makloon-container').hide();
            }

            if (barcodeType === 'barcode_kain') {
                // Fetch progress roll saat ini untuk detail OP terpilih
                const progressUrl = `/proses/${prosesId}/barcodes${detailId ? '?detail_proses_id=' + encodeURIComponent(detailId) : ''}`;
                $.ajax({
                    url: progressUrl,
                    method: 'GET',
                    success: function (data) {
                        const progressList = data.barcode_kain_progress || [];
                        const progress = progressList.find(p => String(p.detail_id) === String(detailId)) || progressList[0] || {};
                        const roll = parseInt(progress.roll || 0, 10);
                        const scanned = parseInt(progress.scanned || 0, 10);
                        const remaining = Math.max(0, roll - scanned);

                        window.kainScanState = {
                            active: true,
                            prosesId: prosesId,
                            detailId: detailId,
                            roll: roll,
                            alreadySaved: scanned,
                            remaining: remaining,
                            pending: []
                        };
                        $('#kain-pending-section').show();
                        renderKainPendingList();

                        // Cek ketersediaan kuota dan disable UI jika perlu
                        updateBarcodeScanUI();

                        $('#modalScanBarcode').modal('show');
                    },
                    error: function () {
                        // Fallback: buka modal dengan state minimal, izinkan tanpa counter ketat
                        window.kainScanState = {
                            active: true,
                            prosesId: prosesId,
                            detailId: detailId,
                            roll: 0,
                            alreadySaved: 0,
                            remaining: 9999,
                            pending: []
                        };
                        $('#kain-pending-section').show();
                        renderKainPendingList();
                        $('#modalScanBarcode').modal('show');
                    }
                });
            } else {
                $('#modalScanBarcode').modal('show');
            }
        });

        // Handler klik tombol Request Topping LA/AUX dengan input durasi penambahan waktu
        $(document).on('click', '.request-topping-btn', function () {
            const type = $(this).data('type');
            const prosesId = $(this).data('id');
            const url = `/proses/${prosesId}/topping/${type}/request`;
            const label = type === 'la' ? 'Dye Stuff (LA)' : 'AUX';
            const $btn = $(this);

            Swal.fire({
                title: `Request Topping ${label}`,
                html: `
                                <div class="text-left" style="font-size: 14px;">
                                    <div class="alert alert-info py-2 px-3 mb-3" style="font-size: 13px;">
                                        <i class="fas fa-info-circle mr-1"></i> Request topping membutuhkan approval <strong>Kepala Shift</strong>. Jadwal input Dye Stuff / AUX normal berikutnya dan Cycle Time akan otomatis dimundurkan.
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="font-weight-bold mb-1">Durasi Topping <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" id="swal-topping-durasi" class="form-control" placeholder="Contoh: 1" min="0.1" step="any" value="1" required>
                                            <div class="input-group-append">
                                                <select id="swal-topping-unit" class="custom-select font-weight-bold" style="min-width: 100px;">
                                                    <option value="jam" selected>Jam</option>
                                                    <option value="menit">Menit</option>
                                                </select>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Menerima format jam (contoh: 1 atau 1.5) maupun menit (contoh: 30, 45, 60).</small>
                                    </div>
                                    <div class="p-2 rounded bg-light border mt-3" id="swal-topping-preview-box">
                                        <div class="text-secondary small font-weight-bold mb-1">Kalkulasi Kemunduran Jadwal & Cycle Time:</div>
                                        <div id="swal-topping-preview-text" class="text-dark font-weight-bold" style="font-size: 13px;">
                                            Durasi: 1 Jam + 45 Menit (toleransi scan) = <span class="text-primary">+1 Jam 45 Menit</span>
                                        </div>
                                    </div>
                                </div>
                            `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i>Kirim Request',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                didOpen: () => {
                    const updatePreview = () => {
                        const val = parseFloat($('#swal-topping-durasi').val()) || 0;
                        const unit = $('#swal-topping-unit').val();
                        if (val <= 0) {
                            $('#swal-topping-preview-text').html('<span class="text-danger">Harap masukkan durasi yang valid (> 0).</span>');
                            return;
                        }
                        const durasiSec = unit === 'menit' ? val * 60 : val * 3600;
                        const spareSec = 2700; // 45 menit
                        const totalSec = durasiSec + spareSec;

                        const durasiH = Math.floor(durasiSec / 3600);
                        const durasiM = Math.floor((durasiSec % 3600) / 60);
                        let durText = [];
                        if (durasiH > 0) durText.push(durasiH + ' Jam');
                        if (durasiM > 0) durText.push(durasiM + ' Menit');

                        const totH = Math.floor(totalSec / 3600);
                        const totM = Math.floor((totalSec % 3600) / 60);
                        let totText = [];
                        if (totH > 0) totText.push(totH + ' Jam');
                        if (totM > 0) totText.push(totM + ' Menit');

                        $('#swal-topping-preview-text').html(`
                                        Durasi: <strong>${durText.join(' ')}</strong> + <strong>45 Menit</strong> (toleransi scan) = <span class="text-primary font-weight-bold" style="font-size: 14px;">+${totText.join(' ')}</span>
                                    `);
                    };
                    $('#swal-topping-durasi, #swal-topping-unit').on('input change', updatePreview);
                },
                preConfirm: () => {
                    const val = parseFloat($('#swal-topping-durasi').val());
                    const unit = $('#swal-topping-unit').val();
                    if (!val || val <= 0) {
                        Swal.showValidationMessage('Harap masukkan durasi topping yang valid (> 0).');
                        return false;
                    }
                    return { durasi: val, unit: unit };
                }
            }).then((result) => {
                if (!result.isConfirmed || !result.value) return;

                const durasi = result.value.durasi;
                const unit = result.value.unit;

                $btn.prop('disabled', true);
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        durasi: durasi,
                        unit: unit
                    },
                    success: function (res) {
                        if (res.status === 'success') {
                            ToastSuccess.fire({ title: res.message });
                            const $modal = $('#modalDetailProses');
                            if ($modal.length && $modal.data('proses') && $('#modalDetailProses').hasClass('show')) {
                                const proses = $modal.data('proses');
                                const selectedDetailId = $('#detail-proses-select').val() || $modal.data('detailProsesId') || '';
                                if (window.loadBarcodesIntoDetailModal) {
                                    window.loadBarcodesIntoDetailModal(proses.id, selectedDetailId);
                                }
                            }
                            // Inject indikator TD/TA kuning di card dashboard (header + setiap blok per OP untuk multiple)
                            const $card = $('.status-card[data-proses-id="' + prosesId + '"]');
                            if ($card.length) {
                                const $header = $card.find('.card-header > div:nth-child(2)');
                                const $gdaContainers = $card.find('.op-list > div:has(.gda-block)');
                                const tdHtml = '<span class="topping-indicator topping-td" data-block-type="TD" title="Topping Dyes - Menunggu approval" style="display: inline-block; background:#fff9c4;color:#111;border:2.5px solid #f9a825; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TD</span>';
                                const taHtml = '<span class="topping-indicator topping-ta" data-block-type="TA" title="Topping Auxiliaries - Menunggu approval" style="display: inline-block; background:#fff9c4;color:#111;border:2.5px solid #f9a825; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TA</span>';
                                if (type === 'la' && !$card.find('.topping-td').length) {
                                    $header.append(tdHtml);
                                    $gdaContainers.each(function () { if (!$(this).find('.topping-td').length) $(this).append(tdHtml); });
                                } else if (type === 'aux' && !$card.find('.topping-ta').length) {
                                    $header.append(taHtml);
                                    $gdaContainers.each(function () { if (!$(this).find('.topping-ta').length) $(this).append(taHtml); });
                                }
                            }
                        } else {
                            ToastError.fire({ title: res.message || 'Gagal request topping' });
                        }
                    },
                    error: function (xhr) {
                        const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal request topping ' + label;
                        ToastError.fire({ title: msg });
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                    }
                });
            });
        });

        // Pastikan scroll modal detail tetap berfungsi saat modal scan dibuka
        $('#modalScanBarcode').on('show.bs.modal', function () {
            // Jika modal detail masih terbuka, pastikan body tetap memiliki class modal-open
            if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                // Pastikan body memiliki class modal-open untuk scroll
                if (!$('body').hasClass('modal-open')) {
                    $('body').addClass('modal-open');
                }
            }
        });

        // Fungsi stop scanner (untuk ganti ke mode manual)
        function stopBarcodeScanner() {
            if (window.html5QrcodeScanner) {
                try {
                    window.html5QrcodeScanner.stop().then(() => {
                        try { window.html5QrcodeScanner.clear(); } catch (e) { }
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
        }

        // Fungsi start scanner (hanya dipanggil saat mode Scan aktif)
        function startBarcodeScannerInModal() {
            if (!$('#mode-scan-pane').hasClass('active')) return;

            // CEK: Jangan jalankan kamera jika barcode sudah lengkap (baik di DB maupun di pending list)
            if (window.kainScanState && window.kainScanState.active) {
                const s = window.kainScanState;
                const isComplete = s.remaining <= 0 || (s.remaining > 0 && s.pending.length >= s.remaining);

                if (isComplete) {
                    $('#barcode-scanner-container').html(
                        '<div class="d-flex align-items-center justify-content-center" style="height:320px; background:#fff;">' +
                        '<div class="text-center"><i class="fas fa-check-circle fa-4x mb-3" style="color:#28a745;"></i>' +
                        '<p class="mb-0 font-weight-bold" style="color:#28a745; font-size:18px;">Barcode sudah terpenuhi.</p></div>' +
                        '</div>'
                    );
                    return;
                }
            }

            $('#barcode-scanner-container').html(
                '<div id="reader" style="width:100%;max-width:400px;margin:auto;"></div>');
            window.html5QrcodeScanner = new Html5Qrcode("reader");
            const beepSound = new Audio("{{ asset('sound/beep.mp3') }}");
            window.html5QrcodeScanner.start({
                facingMode: "environment"
            }, {
                fps: 10,
                qrbox: 250,
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE, Html5QrcodeSupportedFormats.CODE_128]
            },
                (decodedText, decodedResult) => {
                    beepSound.play().catch(e => { console.log("Tidak dapat memainkan suara beep:", e); });
                    const barcodeType = $('#formScanBarcode').data('barcode-type');
                    if (barcodeType === 'barcode_kain' && window.kainScanState && window.kainScanState.active) {
                        // Multi-scan: tambah ke pending list, scanner tetap jalan
                        const ok = addPendingKainBarcode(decodedText);
                        // Bila pending sudah mencapai kebutuhan, hentikan scanner agar user klik Simpan Barcode
                        if (ok && window.kainScanState.pending.length >= window.kainScanState.remaining) {
                            window.html5QrcodeScanner.stop().catch(() => { });
                        }
                        return;
                    }
                    // Flow LA/AUX (single): submit langsung seperti semula
                    $('#inputBarcodeValue').val(decodedText);
                    window.html5QrcodeScanner.stop().catch(() => { });
                    $('#formScanBarcode').trigger('submit');
                },
                (errorMessage) => { }).catch(err => {
                    $('#barcode-scanner-container').html(
                        '<span style="color:#666;">Tidak dapat mengakses kamera: ' + err + '</span>');
                });
        }

        // Ganti tab: Scan <-> Input Manual
        $('a[data-toggle="pill"]', '#modalScanBarcode').on('shown.bs.tab', function (e) {
            const targetId = $(e.target).attr('href');
            if (targetId === '#mode-manual-pane') {
                stopBarcodeScanner();
                setTimeout(function () { $('#inputBarcodeManual').focus(); }, 100);
            } else if (targetId === '#mode-scan-pane') {
                $('#inputBarcodeManual').val('');
                setTimeout(startBarcodeScannerInModal, 200);
            }
        });

        // Submit barcode dari input manual
        $('#modalScanBarcode').on('click', '#btnSubmitManualBarcode', function () {
            const manualVal = $('#inputBarcodeManual').val();
            if (!manualVal || manualVal.trim() === '') {
                showToastNotification('error', 'Barcode tidak boleh kosong!');
                $('#inputBarcodeManual').focus();
                return;
            }

            const barcodeType = $('#formScanBarcode').data('barcode-type');
            // Untuk barcode_kain: masukkan ke pending list, jangan submit langsung
            if (barcodeType === 'barcode_kain' && window.kainScanState && window.kainScanState.active) {
                const ok = addPendingKainBarcode(manualVal.trim());
                if (ok) {
                    $('#inputBarcodeManual').val('').focus();
                }
                return;
            }

            // Flow LA/AUX: submit langsung seperti semula
            $('#inputBarcodeValue').val(manualVal.trim());
            $('#formScanBarcode').trigger('submit');
        });
        $(document).on('keypress', '#inputBarcodeManual', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#btnSubmitManualBarcode').click();
            }
        });

        // Submit material dari select2 Makloon
        $('#modalScanBarcode').on('click', '#btnSubmitMaterialMakloon', function () {
            const selectedMat = $('#selectMaterialMakloon').val();
            if (!selectedMat || selectedMat.trim() === '') {
                showToastNotification('error', 'Silakan pilih material terlebih dahulu!');
                $('#selectMaterialMakloon').select2('open');
                return;
            }

            const ok = addPendingKainBarcode(selectedMat.trim());
            if (ok) {
                $('#selectMaterialMakloon').val('').trigger('change');
                $('#material-stock-info').hide();
            }
        });

        function initSelectMaterialMakloon() {
            const $select = $('#selectMaterialMakloon');
            if ($select.data('select2')) {
                $select.val('').trigger('change');
                $('#material-stock-info').hide();
                return;
            }

            $select.select2({
                dropdownParent: $('#modalScanBarcode'),
                placeholder: '-- Cari Kode / Deskripsi Material Stock --',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: '/api/proxy-material-stock',
                    type: 'POST',
                    dataType: 'json',
                    delay: 400,
                    data: function (params) {
                        return {
                            term: params.term || 'M-',
                            _token: '{{ csrf_token() }}'
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results || []
                        };
                    }
                }
            });

            $select.on('select2:select', function (e) {
                const data = e.params.data;
                if (data && data.material) {
                    $('#mkl-info-desc').text(data.mat_dec || '-');
                    $('#mkl-info-batch').text(data.batch || '-');
                    $('#mkl-info-sloc').text(data.sloc || '-');
                    $('#mkl-info-qty').text(data.quantity ? Number(data.quantity).toLocaleString('id-ID') : '0');
                    $('#material-stock-info').slideDown(150);
                }
            });

            $select.on('select2:clear', function () {
                $('#material-stock-info').slideUp(150);
            });
        }

        // ========================================================
        // Handler submit batch barcode kain (semua pending -> backend)
        // ========================================================
        $('#modalScanBarcode').on('click', '#btnSubmitKainBatch', function () {
            const s = window.kainScanState;
            if (!s.active || !s.pending.length) {
                showToastNotification('error', 'Tidak ada barcode untuk disimpan.');
                return;
            }
            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Menyimpan...');
            $('#modalScanBarcode .btn-secondary').prop('disabled', true);
            $('#btnSubmitManualBarcode').prop('disabled', true);
            $('#barcode-submit-loading').css('display', 'flex');

            // Hentikan scanner bila masih aktif
            try { if (window.html5QrcodeScanner) window.html5QrcodeScanner.stop().catch(() => { }); } catch (e) { }

            const actionUrl = `/proses/${s.prosesId}/barcode/kain`;
            const barcodes = s.pending.map(p => p.raw); // kirim raw, backend akan trim ke 10

            $.ajax({
                url: actionUrl,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                data: {
                    'barcodes[]': barcodes,
                    detail_proses_id: s.detailId || '',
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    const msg = (response && response.message) ? response.message : 'Barcode kain berhasil disimpan!';
                    showToastNotification('success', msg);

                    // Refresh list barcode di modal detail proses (bila terbuka)
                    if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                        const currentProsesId = $('#modalDetailProses').data('proses')?.id || s.prosesId;
                        const selectedDetailId = $('#modalDetailProses').data('detailProsesId') || s.detailId || '';
                        if (currentProsesId && window.loadBarcodesIntoDetailModal) {
                            window.loadBarcodesIntoDetailModal(currentProsesId, selectedDetailId);
                        } else if (currentProsesId) {
                            // Fallback: reload halaman
                            location.reload();
                        }
                    }

                    // Reset & tutup modal
                    resetKainScanState();
                    $('#modalScanBarcode').modal('hide');
                },
                error: function (xhr) {
                    let errMsg = 'Gagal menyimpan barcode kain.';
                    const res = xhr.responseJSON;
                    if (res && res.message) {
                        errMsg = res.message;
                    }

                    // Cek apakah terdapat error terkait QTY GI Over Limit dari SAP
                    if (res && res.is_over_limit && res.over_limit_barcodes && res.over_limit_barcodes.length > 0) {
                        const bcs = res.over_limit_barcodes;
                        const bcsListStr = bcs.join(', ');
                        const detailId = res.detail_proses_id || s.detailId;
                        const prosesId = s.prosesId;

                        Swal.fire({
                            icon: 'warning',
                            title: 'QTY GI Over Limit',
                            html: `
                                    <div style="text-align: left; font-size: 13px; line-height: 1.5;">
                                        <div class="alert alert-warning mb-2" style="font-size: 12px;">
                                            ${errMsg.replace(/\n/g, '<br>')}
                                        </div>
                                        <p class="mb-1">Barcode berikut terdeteksi <strong>QTY GI Over Limit</strong> oleh SAP:</p>
                                        <div class="p-2 mb-2 bg-light border rounded font-weight-bold text-danger" style="word-break: break-all;">
                                            ${bcsListStr}
                                        </div>
                                        <p class="mb-0 text-muted">Apakah Anda ingin mengirimkan <strong>Pengajuan Over GI</strong> ke SAP untuk barcode tersebut?</p>
                                    </div>
                                `,
                            showCancelButton: true,
                            confirmButtonColor: '#f39c12',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i> Ajukan Over GI ke SAP',
                            cancelButtonText: 'Batal',
                            showLoaderOnConfirm: true,
                            preConfirm: () => {
                                return $.ajax({
                                    url: `/proses/${prosesId}/barcode/kain/pengajuan-over-gi`,
                                    method: 'POST',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    },
                                    data: {
                                        detail_proses_id: detailId,
                                        barcodes: bcs,
                                        _token: $('meta[name="csrf-token"]').attr('content')
                                    }
                                }).then(response => {
                                    return response;
                                }).catch(error => {
                                    const submitErr = error.responseJSON && error.responseJSON.message ? error.responseJSON.message : 'Gagal mengirim pengajuan Over GI ke SAP';
                                    Swal.showValidationMessage(submitErr);
                                });
                            },
                            allowOutsideClick: () => !Swal.isLoading()
                        }).then((result) => {
                            if (result.isConfirmed && result.value) {
                                showToastNotification('success', result.value.message || 'Pengajuan Over GI berhasil dikirim ke SAP!');
                                resetKainScanState();
                                $('#modalScanBarcode').modal('hide');

                                if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                                    const currentProsesId = $('#modalDetailProses').data('proses')?.id || prosesId;
                                    const selectedDetailId = $('#modalDetailProses').data('detailProsesId') || detailId || '';
                                    if (currentProsesId && window.loadBarcodesIntoDetailModal) {
                                        window.loadBarcodesIntoDetailModal(currentProsesId, selectedDetailId);
                                    }
                                }
                            }
                        });
                        return;
                    }

                    showToastNotification('error', errMsg);
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                    $('#modalScanBarcode .btn-secondary').prop('disabled', false);
                    $('#btnSubmitManualBarcode').prop('disabled', false);
                    $('#barcode-submit-loading').css('display', 'none');
                }
            });
        });

        // Reset state kain saat modal scan ditutup
        $('#modalScanBarcode').on('hidden.bs.modal', function () {
            resetKainScanState();
        });

        // Inisialisasi saat modal tampil: mode Scan = start scanner; mode Manual = focus input
        $('#modalScanBarcode').on('shown.bs.modal', function () {
            $('#inputBarcodeManual').val('');
            $('#inputBarcodeValue').val('');
            const isManualActive = $('#mode-manual-pane').hasClass('active');
            if (isManualActive) {
                $('#inputBarcodeManual').focus();
                return;
            }
            setTimeout(function () {
                if (window.html5QrcodeScanner) {
                    try {
                        window.html5QrcodeScanner.stop().then(() => {
                            try { window.html5QrcodeScanner.clear(); } catch (ee) { }
                            window.html5QrcodeScanner = null;
                            $('#barcode-scanner-container').html('');
                            startBarcodeScannerInModal();
                        }).catch(() => {
                            window.html5QrcodeScanner = null;
                            $('#barcode-scanner-container').html('');
                            startBarcodeScannerInModal();
                        });
                        return;
                    } catch (e) {
                        window.html5QrcodeScanner = null;
                        $('#barcode-scanner-container').html('');
                    }
                }
                startBarcodeScannerInModal();
            }, 200);
        });

        // Handler form submit dengan AJAX untuk memastikan notifikasi selalu muncul
        $(document).on('submit', '#formScanBarcode', function (e) {
            e.preventDefault(); // Prevent default form submission

            const form = $(this);
            const actionUrl = form.attr('action');
            const barcode = $('#inputBarcodeValue').val();
            const detailProsesId = $('#inputDetailProsesId').val();
            const approvalId = $('#inputApprovalId').val();
            const prosesId = form.data('proses-id');
            const barcodeType = form.data('barcode-type');

            // Validasi barcode tidak kosong
            if (!barcode || barcode.trim() === '') {
                showToastNotification('error', 'Barcode tidak boleh kosong!');
                return false;
            }

            // Tampilkan loading overlay (terlihat di mode Scan maupun Input Manual)
            $('#barcode-submit-loading').css('display', 'flex');
            const $scannerContainer = $('#barcode-scanner-container');
            const originalContent = $scannerContainer.html();
            $scannerContainer.html(
                '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;">' +
                '<div class="spinner-border text-success" role="status" style="width:3rem;height:3rem;margin-bottom:15px;">' +
                '<span class="sr-only">Loading...</span></div>' +
                '<p style="color:#333;font-size:14px;margin:0;">Memproses barcode...</p>' +
                '</div>'
            );

            // Disable tombol tutup dan tombol manual saat processing
            $('#modalScanBarcode .btn-secondary').prop('disabled', true);
            $('#btnSubmitManualBarcode').prop('disabled', true);

            // Kirim AJAX request
            $.ajax({
                url: actionUrl,
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                data: Object.assign({
                    barcode: barcode,
                    detail_proses_id: detailProsesId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }, approvalId ? { approval_id: approvalId } : {}),
                success: function (response) {
                    // Tampilkan notifikasi success
                    let successMessage = 'Barcode berhasil disimpan!';
                    if (response && response.message) {
                        successMessage = response.message;
                    } else if (barcodeType === 'barcode_kain') {
                        successMessage = 'Barcode kain berhasil disimpan!';
                    } else if (barcodeType === 'barcode_la') {
                        successMessage = 'Barcode Dye Stuff berhasil disimpan untuk semua OP pada proses ini!';
                    } else if (barcodeType === 'barcode_aux') {
                        successMessage = 'Barcode AUX berhasil disimpan untuk semua OP pada proses ini!';
                    }
                    showToastNotification('success', successMessage);

                    // Refresh barcode list jika modal detail proses masih terbuka
                    if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                        const currentProsesId = $('#modalDetailProses').data('proses')?.id || prosesId;
                        const selectedDetailId = $('#modalDetailProses').data('detailProsesId') || detailProsesId || '';
                        if (currentProsesId && window.loadBarcodesIntoDetailModal) {
                            window.loadBarcodesIntoDetailModal(currentProsesId, selectedDetailId);
                        }
                    }

                    // Tutup modal setelah sukses (delay sedikit agar user melihat notifikasi)
                    // Pastikan scroll modal detail tetap berfungsi setelah modal scan ditutup
                    setTimeout(function () {
                        $('#modalScanBarcode').modal('hide');
                        // Pastikan body tetap memiliki class modal-open jika modal detail masih terbuka
                        if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                            // Restore modal-open class dan padding untuk scroll modal detail
                            if (!$('body').hasClass('modal-open')) {
                                $('body').addClass('modal-open');
                            }
                            // Pastikan overflow hidden hanya untuk modal yang sedang aktif
                            const modalBackdrop = $('.modal-backdrop');
                            if (modalBackdrop.length > 1) {
                                // Jika ada multiple backdrop, hapus yang terakhir (dari modal scan)
                                modalBackdrop.last().remove();
                            }
                        }
                    }, 500);
                },
                error: function (xhr) {
                    // Tampilkan notifikasi error
                    let errorMsg = 'Gagal menyimpan barcode.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    } else if (xhr.status === 0) {
                        errorMsg = 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Terjadi kesalahan pada server. Silakan coba lagi.';
                    } else if (xhr.status === 404) {
                        errorMsg = 'Endpoint tidak ditemukan.';
                    } else if (xhr.status === 419) {
                        errorMsg = 'Session expired. Silakan refresh halaman dan coba lagi.';
                    }

                    showToastNotification('error', errorMsg);

                    // Sembunyikan loading overlay
                    $('#barcode-submit-loading').hide();
                    $('#btnSubmitManualBarcode').prop('disabled', false);

                    // Restore scanner hanya jika mode Scan aktif
                    if ($('#mode-scan-pane').hasClass('active')) {
                        $scannerContainer.html(
                            '<div id="reader" style="width:100%;max-width:400px;margin:auto;"></div>'
                        );
                        if (window.html5QrcodeScanner) {
                            try { window.html5QrcodeScanner.clear(); } catch (e) { }
                        }
                        window.html5QrcodeScanner = new Html5Qrcode("reader");
                        const beepSound = new Audio("{{ asset('sound/beep.mp3') }}");
                        window.html5QrcodeScanner.start({
                            facingMode: "environment"
                        }, {
                            fps: 10,
                            qrbox: 250,
                            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE, Html5QrcodeSupportedFormats.CODE_128]
                        },
                            (decodedText, decodedResult) => {
                                beepSound.play().catch(e => { console.log("Tidak dapat memainkan suara beep:", e); });
                                $('#inputBarcodeValue').val(decodedText);
                                window.html5QrcodeScanner.stop().catch(() => { });
                                $('#formScanBarcode').trigger('submit');
                            },
                            (errorMessage) => { }).catch(err => {
                                $scannerContainer.html(
                                    '<span style="color:#666;">Tidak dapat mengakses kamera: ' + err + '</span>'
                                );
                            });
                    } else {
                        $scannerContainer.html('');
                        $('#inputBarcodeManual').focus();
                    }
                },
                complete: function () {
                    $('#barcode-submit-loading').hide();
                    $('#modalScanBarcode .btn-secondary').prop('disabled', false);
                    $('#btnSubmitManualBarcode').prop('disabled', false);
                }
            });

            return false;
        });

        // Stop scanner saat modal ditutup
        $('#modalScanBarcode').on('hidden.bs.modal', function () {
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

            // Reset form dan loading state
            $('#formScanBarcode').removeData('proses-id');
            $('#formScanBarcode').removeData('barcode-type');
            $('#inputBarcodeValue').val('');
            $('#inputBarcodeManual').val('');
            $('#barcode-submit-loading').hide();
            $('#btnSubmitManualBarcode').prop('disabled', false);

            // Pastikan scroll modal detail tetap berfungsi setelah modal scan ditutup
            // Jika modal detail masih terbuka, pastikan body tetap memiliki class modal-open
            if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                // Restore modal-open class untuk memastikan scroll berfungsi
                if (!$('body').hasClass('modal-open')) {
                    $('body').addClass('modal-open');
                }
                // Pastikan hanya ada satu backdrop (dari modal detail)
                const modalBackdrop = $('.modal-backdrop');
                if (modalBackdrop.length > 1) {
                    // Hapus backdrop tambahan yang mungkin tersisa dari modal scan
                    modalBackdrop.last().remove();
                }
                // Pastikan padding-right di body diatur dengan benar untuk scroll
                const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
                if (scrollbarWidth > 0) {
                    $('body').css('padding-right', scrollbarWidth + 'px');
                }
            }
        });

        // Inject data proses ke card
        $(document).ready(function () {
            $('.status-card').each(function () {
                const proses = $(this).data('proses');
                if (!proses) {
                    // Ambil data dari atribut blade
                    const prosesData = $(this).attr('data-proses-json');
                    if (prosesData) {
                        try {
                            $(this).data('proses', JSON.parse(prosesData));
                        } catch { }
                    }
                }
            });
        });

        $(document).ready(function () {
            function updateRunningTimes() {
                $('.status-card').each(function () {
                    const proses = $(this).data('proses');
                    if (!proses) return;
                    // Hanya update jika proses sedang berjalan (mulai ada, selesai null)
                    if (proses.mulai && !proses.selesai) {
                        // Parse tanggal dengan lebih robust
                        let mulai;
                        if (typeof proses.mulai === 'string') {
                            // Coba parse langsung (untuk format ISO seperti 2026-01-15T15:59:56.000000Z)
                            mulai = new Date(proses.mulai);
                            // Jika parsing gagal, coba dengan replace untuk format lama
                            if (isNaN(mulai.getTime())) {
                                mulai = new Date(proses.mulai.replace(/-/g, '/'));
                            }
                        } else {
                            mulai = new Date(proses.mulai);
                        }

                        // Validasi: pastikan tanggal valid sebelum menghitung
                        if (isNaN(mulai.getTime())) {
                            console.warn('Invalid date format:', proses.mulai);
                            return; // Skip jika tanggal tidak valid
                        }

                        let diff = 0;
                        if (proses.is_paused) {
                            const pausedAt = new Date(proses.updated_at.replace(' ', 'T'));
                            diff = Math.floor((pausedAt - mulai) / 1000);
                        } else if (proses.is_break) {
                            let breakAt = proses.break_at ? new Date(proses.break_at) : new Date();
                            if (isNaN(breakAt.getTime())) breakAt = new Date();
                            diff = Math.floor((breakAt - mulai) / 1000);
                        } else {
                            const now = new Date();
                            diff = Math.floor((now - mulai) / 1000);
                        }
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

            // Trigger background checks for auto-offline every 5 seconds
            // Ini untuk memastikan logic timeout tetap jalan meskipun menu Data Mesin tidak dibuka
            setInterval(function () {
                fetch('/mesin/statuses?_=' + new Date().getTime(), { cache: 'no-store' })
                    .then(res => res.json())
                    .then(data => {
                        if (data && typeof data === 'object') {
                            Object.keys(data).forEach(function (mesinId) {
                                const info = data[mesinId];
                                if (info && info.iot_signal !== undefined) {
                                    const isConnected = !!info.iot_signal;
                                    const $cards = $(`.card-dropzone[data-mesin-id="${mesinId}"] .status-card:not(.history-card)`);
                                    $cards.each(function () {
                                        const proses = $(this).data('proses');
                                        const isRunning = proses && proses.mulai !== null && (proses.selesai === null || proses.selesai === undefined);
                                        const $offlineIcon = $(this).find('.iot-offline-icon');
                                        if (isRunning && !isConnected) {
                                            $offlineIcon.show();
                                        } else {
                                            $offlineIcon.hide();
                                        }
                                    });
                                }
                            });
                        }
                    })
                    .catch(e => console.error('Error triggering statuses:', e));
            }, 5000);

            // Auto-update / background sync status proses (Pinjam Mesin, Mesin Mati/Nyala, Selesai, Topping) tanpa refresh
            function syncProsesStatuses() {
                if (typeof handleProsesStatusUpdate !== 'function') return;
                const currentParams = window.location.search || '';
                const separator = currentParams ? '&' : '?';
                const url = '{{ route("dashboard.proses-statuses") }}' + currentParams + separator + '_=' + new Date().getTime();

                fetch(url, { cache: 'no-store', headers: { 'Accept': 'application/json' } })
                    .then(res => res.json())
                    .then(data => {
                        if (data && typeof data === 'object' && !data.error) {
                            Object.keys(data).forEach(function (prosesId) {
                                const statusData = data[prosesId];
                                handleProsesStatusUpdate(prosesId, statusData);
                            });
                        }
                    })
                    .catch(e => { });
            }
            setInterval(syncProsesStatuses, 4000);
        });

        // Real-time update warna card berdasarkan status mulai/selesai
        $(document).ready(function () {
            // Format detik ke HH:MM:SS (sama seperti detikKeWaktu di PHP)
            function formatDetikToHMS(detik) {
                if (detik === null || detik === undefined || isNaN(detik)) return '-';
                const val = parseInt(detik, 10);
                const jam = Math.floor(val / 3600).toString().padStart(2, '0');
                const menit = Math.floor((val % 3600) / 60).toString().padStart(2, '0');
                const d = (val % 60).toString().padStart(2, '0');
                return jam + ':' + menit + ':' + d;
            }

            function formatDateTimeDisplay(val) {
                if (val === null || val === undefined || val === '') return '-';
                try {
                    let str = typeof val === 'string' ? val.trim() : val;
                    if (typeof str === 'string' && /^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?$/.test(str)) {
                        str = str.replace(' ', 'T');
                    }
                    const d = new Date(str);
                    if (isNaN(d.getTime())) return val;
                    const pad = n => String(n).padStart(2, '0');
                    return `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
                } catch (e) {
                    return val;
                }
            }

            // Bangun HTML body tabel detail proses (entries saja, untuk modal pending) dari objek proses
            function buildDetailProsesPendingBodyHtml(proses, selectedDetailId) {
                const hiddenFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'mesin_id', 'barcode_kain_optional'];
                const maintenanceFields = ['no_op', 'item_op', 'customer', 'marketing', 'kode_material', 'konstruksi', 'no_partai', 'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll', 'mode', 'jenis_op', 'qty_dye_stuff', 'qty_aux', 'dye_stuff_schedules', 'aux_schedules', 'barcode_kain_optional'];
                const firstDetail = getDetailProsesById(proses, selectedDetailId) || getFirstDetailProses(proses);
                const prosesData = { ...proses };
                if (firstDetail) {
                    Object.keys(firstDetail).forEach(function (key) {
                        if (maintenanceFields.indexOf(key) !== -1 || ['no_op', 'no_partai', 'item_op', 'customer', 'marketing', 'kode_material', 'konstruksi', 'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll'].indexOf(key) !== -1) {
                            prosesData[key] = firstDetail[key];
                        }
                    });
                }
                let jenisMesin = '-';
                try {
                    const mesinSelect = document.getElementById('mesin_id');
                    if (mesinSelect && proses.mesin_id) {
                        const opt = mesinSelect.querySelector('option[value="' + proses.mesin_id + '"]');
                        if (opt) jenisMesin = opt.textContent;
                    }
                } catch (e) { }
                const entries = Object.entries(prosesData)
                    .filter(function (pair) { return hiddenFields.indexOf(pair[0]) === -1 && pair[0] !== 'barcode_kains' && pair[0] !== 'barcode_las' && pair[0] !== 'barcode_auxs' && pair[0] !== 'mesin' && pair[0] !== 'approvals' && pair[0] !== 'details' && pair[0] !== 'pending_approvals'; })
                    .filter(function (pair) { return !(proses.jenis === 'Maintenance' && maintenanceFields.indexOf(pair[0]) !== -1); })
                    .map(function (pair) {
                        const key = pair[0], val = pair[1];
                        if (key === 'hfeel') return ['HAND FEEL', val];
                        if (key === 'matdok') return ['MATERIAL DOKUMEN', val];
                        if (key === 'cycle_time' || key === 'cycle_time_actual') return [key.replace(/_/g, ' ').toUpperCase(), formatDetikToHMS(val)];
                        if (key === 'mulai' || key === 'selesai') return [key.toUpperCase(), formatDateTimeDisplay(val)];
                        if (key === 'dye_stuff_schedules') {
                            let dsFormatted = '-';
                            if (Array.isArray(val) && val.length > 0) {
                                dsFormatted = val.map((t, idx) => `DS ${idx + 1}: ${t}`).join(', ');
                            } else if (typeof val === 'string' && val) {
                                try {
                                    const parsed = JSON.parse(val);
                                    if (Array.isArray(parsed) && parsed.length > 0) {
                                        dsFormatted = parsed.map((t, idx) => `DS ${idx + 1}: ${t}`).join(', ');
                                    }
                                } catch (e) { dsFormatted = val; }
                            }
                            return ['JADWAL DYE STUFF', dsFormatted];
                        }
                        if (key === 'aux_schedules') {
                            let auxFormatted = '-';
                            if (Array.isArray(val) && val.length > 0) {
                                auxFormatted = val.map((t, idx) => `AUX ${idx + 1}: ${t}`).join(', ');
                            } else if (typeof val === 'string' && val) {
                                try {
                                    const parsed = JSON.parse(val);
                                    if (Array.isArray(parsed) && parsed.length > 0) {
                                        auxFormatted = parsed.map((t, idx) => `AUX ${idx + 1}: ${t}`).join(', ');
                                    }
                                } catch (e) { auxFormatted = val; }
                            }
                            return ['JADWAL AUX', auxFormatted];
                        }
                        return [key.replace(/_/g, ' ').toUpperCase(), val];
                    });
                entries.unshift(['JENIS MESIN', jenisMesin]);
                let html = '';
                for (let i = 0; i < entries.length; i += 2) {
                    html += '<tr><th style="width:180px;">' + entries[i][0] + '</th><td>' + (entries[i][1] != null ? entries[i][1] : '-') + '</td>';
                    if (entries[i + 1]) {
                        html += '<th style="width:180px;">' + entries[i + 1][0] + '</th><td>' + (entries[i + 1][1] != null ? entries[i + 1][1] : '-') + '</td>';
                    } else {
                        html += '<th></th><td></td>';
                    }
                    html += '</tr>';
                }
                return html;
            }

            // Bangun HTML body tabel detail proses LENGKAP (entries + section Barcode Kain, LA, AUX) untuk modal normal
            function buildDetailProsesBodyHtml(proses, selectedDetailId) {
                const hiddenFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'mesin_id', 'barcode_kain_optional'];
                const maintenanceFields = ['no_op', 'item_op', 'customer', 'marketing', 'kode_material', 'konstruksi', 'no_partai', 'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll', 'mode', 'jenis_op', 'qty_dye_stuff', 'qty_aux', 'dye_stuff_schedules', 'aux_schedules', 'barcode_kain_optional'];
                const firstDetail = getDetailProsesById(proses, selectedDetailId) || getFirstDetailProses(proses);
                const prosesData = { ...proses };
                if (firstDetail) {
                    Object.keys(firstDetail).forEach(function (key) {
                        if (maintenanceFields.indexOf(key) !== -1 || ['no_op', 'no_partai', 'item_op', 'customer', 'marketing', 'kode_material', 'konstruksi', 'gramasi', 'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty', 'roll'].indexOf(key) !== -1) {
                            prosesData[key] = firstDetail[key];
                        }
                    });
                }
                let jenisMesin = '-';
                try {
                    const mesinSelect = document.getElementById('mesin_id');
                    if (mesinSelect && proses.mesin_id) {
                        const opt = mesinSelect.querySelector('option[value="' + proses.mesin_id + '"]');
                        if (opt) jenisMesin = opt.textContent;
                    }
                } catch (e) { }
                const entries = Object.entries(prosesData)
                    .filter(function (pair) { return hiddenFields.indexOf(pair[0]) === -1 && pair[0] !== 'barcode_kains' && pair[0] !== 'barcode_las' && pair[0] !== 'barcode_auxs' && pair[0] !== 'mesin' && pair[0] !== 'approvals' && pair[0] !== 'details' && pair[0] !== 'pending_approvals'; })
                    .filter(function (pair) { return !(proses.jenis === 'Maintenance' && maintenanceFields.indexOf(pair[0]) !== -1); })
                    .map(function (pair) {
                        const key = pair[0], val = pair[1];
                        if (key === 'hfeel') return ['HAND FEEL', val];
                        if (key === 'matdok') return ['MATERIAL DOKUMEN', val];
                        if (key === 'cycle_time' || key === 'cycle_time_actual') return [key.replace(/_/g, ' ').toUpperCase(), formatDetikToHMS(val)];
                        if (key === 'mulai' || key === 'selesai') return [key.toUpperCase(), formatDateTimeDisplay(val)];
                        if (key === 'dye_stuff_schedules') {
                            let dsFormatted = '-';
                            if (Array.isArray(val) && val.length > 0) {
                                dsFormatted = val.map((t, idx) => `DS ${idx + 1}: ${t}`).join(', ');
                            } else if (typeof val === 'string' && val) {
                                try {
                                    const parsed = JSON.parse(val);
                                    if (Array.isArray(parsed) && parsed.length > 0) {
                                        dsFormatted = parsed.map((t, idx) => `DS ${idx + 1}: ${t}`).join(', ');
                                    }
                                } catch (e) { dsFormatted = val; }
                            }
                            return ['JADWAL DYE STUFF', dsFormatted];
                        }
                        if (key === 'aux_schedules') {
                            let auxFormatted = '-';
                            if (Array.isArray(val) && val.length > 0) {
                                auxFormatted = val.map((t, idx) => `AUX ${idx + 1}: ${t}`).join(', ');
                            } else if (typeof val === 'string' && val) {
                                try {
                                    const parsed = JSON.parse(val);
                                    if (Array.isArray(parsed) && parsed.length > 0) {
                                        auxFormatted = parsed.map((t, idx) => `AUX ${idx + 1}: ${t}`).join(', ');
                                    }
                                } catch (e) { auxFormatted = val; }
                            }
                            return ['JADWAL AUX', auxFormatted];
                        }
                        return [key.replace(/_/g, ' ').toUpperCase(), val];
                    });
                entries.unshift(['JENIS MESIN', jenisMesin]);
                let html = '';
                for (let i = 0; i < entries.length; i += 2) {
                    html += '<tr><th style="width:180px;">' + entries[i][0] + '</th><td>' + (entries[i][1] != null ? entries[i][1] : '-') + '</td>';
                    if (entries[i + 1]) {
                        html += '<th style="width:180px;">' + entries[i + 1][0] + '</th><td>' + (entries[i + 1][1] != null ? entries[i + 1][1] : '-') + '</td>';
                    } else {
                        html += '<th></th><td></td>';
                    }
                    html += '</tr>';
                }
                if (proses.jenis !== 'Maintenance') {
                    const barcodeKainOptionalBuild = proses.barcode_kain_optional === true;
                    const showScanBtn = window.canScanBarcode !== false;
                    const detailIdAttr = selectedDetailId ? (selectedDetailId + '') : '';
                    if (!barcodeKainOptionalBuild) {
                        html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode Kain';
                        if (showScanBtn) {
                            html += ' <button type="button" id="btn-scan-kain" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_kain" data-id="' + proses.id + '" data-detail-id="' + detailIdAttr + '" style="float:right;"><i class="fas fa-barcode"></i> Scan</button>';
                        }
                        html += '</th></tr><tr><td colspan="4" id="barcode-kain-list">Loading...</td></tr><tr><td colspan="4" id="barcode-kain-progress" style="padding:8px;background:#f9f9f9;font-size:12px;"></td></tr>';
                    }
                    html += '<tr><th colspan="4" id="barcode-la-header" style="background:#f8f8f8;">Barcode Dye Stuff <span id="barcode-la-badges"></span><span id="barcode-la-buttons" style="float:right;"></span></th></tr><tr><td colspan="4" id="barcode-la-list">Loading...</td></tr><tr><td colspan="4" id="barcode-la-progress" style="padding:8px;background:#f9f9f9;font-size:12px;"></td></tr>';
                    html += '<tr><th colspan="4" id="barcode-aux-header" style="background:#f8f8f8;">Barcode AUX <span id="barcode-aux-badges"></span><span id="barcode-aux-buttons" style="float:right;"></span></th></tr><tr><td colspan="4" id="barcode-aux-list">Loading...</td></tr><tr><td colspan="4" id="barcode-aux-progress" style="padding:8px;background:#f9f9f9;font-size:12px;"></td></tr>';
                }
                return html;
            }

            // Muat data barcode ke modal detail proses (setelah body di-set) agar section Barcode Kain, LA, AUX terisi
            function loadBarcodesIntoDetailModal(prosesId, selectedDetailId) {
                const barcodesUrl = '/proses/' + prosesId + '/barcodes' + (selectedDetailId ? ('?detail_proses_id=' + encodeURIComponent(selectedDetailId)) : '');
                $.ajax({
                    url: barcodesUrl,
                    method: 'GET',
                    success: function (data) {
                        // Ambil proses dari modal atau card agar renderBarcodeGrid tidak error (realtime via WebSocket)
                        const proses = $('#modalDetailProses').data('proses') || $('.status-card[data-proses-id="' + prosesId + '"]').data('proses') || {};
                        const barcodeKainOptionalLoad = data.barcode_kain_optional === true;
                        function renderBarcodeGrid(barcodes, barcodeType, pid) {
                            const activeBarcodes = (barcodes || []).filter(function (bk) { return !bk.cancel; });
                            if (!activeBarcodes.length) return '<span style="color:#888;">Belum ada barcode.</span>';
                            const canCancel = window.canCancelBarcode !== false;
                            const canCancelByProses = !proses.mulai || !proses.selesai;

                            const allowCancel = canCancel && canCancelByProses;

                            let h = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                            activeBarcodes.forEach(function (bk) {
                                const isPending = bk.approval_status === 'pending';
                                const isRejected = bk.approval_status === 'rejected';

                                // Tombol cancel barcode dihilangkan jika pending approval QTY GI,
                                // dan hanya muncul ketika barcode sudah approved atau rejected dari SAP (atau jenis barcode lain)
                                const canCancelThis = allowCancel && !isPending;
                                const cancelBtn = canCancelThis ? '<span style="position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;" class="cancel-barcode-btn" data-type="' + barcodeType + '" data-proses="' + pid + '" data-id="' + bk.id + '" data-matdok="' + (bk.matdok || '') + '" data-item-document="' + (bk.item_document || '') + '" data-approval-status="' + (bk.approval_status || '') + '" data-barcode="' + bk.barcode + '" title="Cancel barcode">&times;</span>' : '';

                                let boxBg = '#f3f3f3';
                                let boxBorder = '';
                                let pendingBadge = '';

                                if (isPending) {
                                    boxBg = '#fff9c4';
                                    boxBorder = 'border:1.5px solid #fbc02d;';
                                    pendingBadge = '<br><span class="badge badge-warning" style="font-size:10px; background:#fff176; color:#856404; border:1px solid #fbc02d; margin-top:3px; padding:2px 4px;"><i class="fas fa-clock mr-1"></i>Menunggu Approval</span>';
                                } else if (isRejected) {
                                    boxBg = '#ffebee';
                                    boxBorder = 'border:1.5px solid #ef5350;';
                                    pendingBadge = '<br><span class="badge badge-danger" style="font-size:10px; background:#ef9a9a; color:#b71c1c; border:1px solid #ef5350; margin-top:3px; padding:2px 4px;"><i class="fas fa-times-circle mr-1"></i>Ditolak SAP</span>';
                                }

                                h += '<div style="position:relative;flex:1 0 30%;max-width:32%;min-width:130px;background:' + boxBg + ';' + boxBorder + 'border-radius:6px;padding:6px 4px;margin-bottom:6px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">' + cancelBtn + bk.barcode + (bk.matdok ? '<br><span style="font-size:11px;color:#888;">' + bk.matdok + '</span>' : '') + pendingBadge + '</div>';
                            });
                            h += '</div>';
                            return h;
                        }
                        if (!barcodeKainOptionalLoad && $('#barcode-kain-list').length) {
                            $('#barcode-kain-list').html(renderBarcodeGrid(data.barcode_kain, 'kain', prosesId));
                        }
                        $('#barcode-la-list').html(renderBarcodeGrid(data.barcode_la, 'la', prosesId));
                        $('#barcode-aux-list').html(renderBarcodeGrid(data.barcode_aux, 'aux', prosesId));

                        const userRoleLocal = window.userRole || '';
                        const canRequestToppingRoleLocal = (userRoleLocal === 'kepala_ruangan' || userRoleLocal === 'super_admin' || (userRoleLocal === 'kepala_shift' && window.isKaruOff));
                        const canRequestLa = data.can_request_topping_la && canRequestToppingRoleLocal;
                        const canRequestAux = data.can_request_topping_aux && canRequestToppingRoleLocal;
                        const canScanLa = data.can_scan_la === true;
                        const canScanAux = data.can_scan_aux === true;
                        const approvedToppingLaLocal = data.approved_topping_la || null;
                        const approvedToppingAuxLocal = data.approved_topping_aux || null;
                        const isMultipleOpLocal = data.is_multiple_op === true;
                        const detailIdAttrLocal = selectedDetailId ? (selectedDetailId + '') : '';
                        const reqToppingTitleLocal = isMultipleOpLocal ? 'Request Topping untuk semua OP. Kepala Shift hanya perlu approve sekali.' : '';

                        // Progress LA & AUX (Pindahkan ke atas agar bisa dipakai saat render button)
                        const laProgressLocal = data.la_progress || {};
                        const laReqLocal = laProgressLocal.required ?? 1;
                        const laScnLocal = laProgressLocal.scanned ?? 0;
                        const laCompleteLocal = laProgressLocal.is_complete === true;
                        const laToppingReqLocal = laProgressLocal.topping_required ?? 0;

                        const auxProgressLocal = data.aux_progress || {};
                        const auxReqLocal = auxProgressLocal.required ?? 1;
                        const auxScnLocal = auxProgressLocal.scanned ?? 0;
                        const auxCompleteLocal = auxProgressLocal.is_complete === true;
                        const auxToppingReqLocal = auxProgressLocal.topping_required ?? 0;

                        const canScanLaAuxLocal = data.can_scan_la_aux !== false;

                        let laBadgesLocal = '';
                        if (data.pending_topping_la) laBadgesLocal += ' <span class="badge badge-warning" title="Topping Dyes - Menunggu approval">TD</span>';
                        let laBtnsLocal = '';
                        if (canRequestLa) laBtnsLocal += ' <button type="button" class="btn btn-sm btn-info request-topping-btn mr-1" data-type="la" data-id="' + prosesId + '" title="' + reqToppingTitleLocal + '"><i class="fas fa-plus"></i> Request Topping' + (isMultipleOpLocal ? ' (semua OP)' : '') + '</button>';
                        if (canScanLa) {
                            const isLaDone = !canScanLaAuxLocal || laCompleteLocal;
                            const laBtnClass = isLaDone ? 'btn-secondary' : 'btn-success';
                            const laBtnDisabled = isLaDone ? ' disabled style="cursor:not-allowed;"' : '';
                            const laBtnTitle = laCompleteLocal ? 'Barcode Dye Stuff sudah lengkap sesuai kebutuhan' : (!canScanLaAuxLocal ? 'Detail OP belum lengkap' : '');
                            const approvalAttrLocal = approvedToppingLaLocal ? ' data-approval-id="' + approvedToppingLaLocal.id + '"' : '';

                            laBtnsLocal += ' <button type="button" class="btn btn-sm ' + laBtnClass + ' scan-barcode-btn" ' + laBtnDisabled + ' data-barcode="barcode_la" data-id="' + prosesId + '" data-detail-id="' + detailIdAttrLocal + '"' + approvalAttrLocal + ' title="' + laBtnTitle + '"><i class="fas fa-barcode"></i> ' + (approvedToppingLaLocal ? 'Scan (Topping)' : 'Scan') + '</button>';
                        }
                        $('#barcode-la-badges').html(laBadgesLocal);
                        $('#barcode-la-buttons').html(laBtnsLocal);

                        let auxBadgesLocal = '';
                        if (data.pending_topping_aux) auxBadgesLocal += ' <span class="badge badge-warning" title="Topping Auxiliaries - Menunggu approval">TA</span>';
                        let auxBtnsLocal = '';
                        if (canRequestAux) auxBtnsLocal += ' <button type="button" class="btn btn-sm btn-info request-topping-btn mr-1" data-type="aux" data-id="' + prosesId + '" title="' + reqToppingTitleLocal + '"><i class="fas fa-plus"></i> Request Topping' + (isMultipleOpLocal ? ' (semua OP)' : '') + '</button>';
                        if (canScanAux) {
                            const isAuxDone = !canScanLaAuxLocal || auxCompleteLocal;
                            const auxBtnClass = isAuxDone ? 'btn-secondary' : 'btn-success';
                            const auxBtnDisabled = isAuxDone ? ' disabled style="cursor:not-allowed;"' : '';
                            const auxBtnTitle = auxCompleteLocal ? 'Barcode AUX sudah lengkap sesuai kebutuhan' : (!canScanLaAuxLocal ? 'Detail OP belum lengkap' : '');
                            const approvalAttrAuxLocal = approvedToppingAuxLocal ? ' data-approval-id="' + approvedToppingAuxLocal.id + '"' : '';

                            auxBtnsLocal += ' <button type="button" class="btn btn-sm ' + auxBtnClass + ' scan-barcode-btn" ' + auxBtnDisabled + ' data-barcode="barcode_aux" data-id="' + prosesId + '" data-detail-id="' + detailIdAttrLocal + '"' + approvalAttrAuxLocal + ' title="' + auxBtnTitle + '"><i class="fas fa-barcode"></i> ' + (approvedToppingAuxLocal ? 'Scan (Topping)' : 'Scan') + '</button>';
                        }
                        $('#barcode-aux-badges').html(auxBadgesLocal);
                        $('#barcode-aux-buttons').html(auxBtnsLocal);

                        // Progress LA & AUX rendering
                        const laInitialReqLocal = laProgressLocal.initial_required ?? 0;
                        let laProgressHtmlLocal = (laInitialReqLocal === 0 && laToppingReqLocal === 0) ?
                            'Kebutuhan: 0 awal (Tidak Memerlukan Scanning) | <span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' :
                            (laToppingReqLocal > 0 ?
                                'Kebutuhan: ' + laInitialReqLocal + ' awal + ' + laToppingReqLocal + ' topping (TD) = ' + laReqLocal + ' total | Sudah: ' + laScnLocal + ' | ' + (laCompleteLocal ? '<span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' : '<span style="color:#c62828;">Kurang: ' + (laReqLocal - laScnLocal) + '</span>') :
                                'Kebutuhan: ' + laInitialReqLocal + ' awal | Sudah: ' + laScnLocal + ' | ' + (laCompleteLocal ? '<span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' : '<span style="color:#c62828;">Kurang: ' + (laInitialReqLocal - laScnLocal) + '</span>'));
                        $('#barcode-la-progress').html('<div style="padding:4px 8px;background:' + (laCompleteLocal ? '#e8f5e9' : '#fff3e0') + ';border-radius:4px;">' + laProgressHtmlLocal + '</div>').show();

                        const auxInitialReqLocal = auxProgressLocal.initial_required ?? 0;
                        let auxProgressHtmlLocal = (auxInitialReqLocal === 0 && auxToppingReqLocal === 0) ?
                            'Kebutuhan: 0 awal (Tidak Memerlukan Scanning) | <span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' :
                            (auxToppingReqLocal > 0 ?
                                'Kebutuhan: ' + auxInitialReqLocal + ' awal + ' + auxToppingReqLocal + ' topping (TA) = ' + auxReqLocal + ' total | Sudah: ' + auxScnLocal + ' | ' + (auxCompleteLocal ? '<span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' : '<span style="color:#c62828;">Kurang: ' + (auxReqLocal - auxScnLocal) + '</span>') :
                                'Kebutuhan: ' + auxInitialReqLocal + ' awal | Sudah: ' + auxScnLocal + ' | ' + (auxCompleteLocal ? '<span style="color:#43a047;"><i class="fas fa-check"></i> Lengkap</span>' : '<span style="color:#c62828;">Kurang: ' + (auxInitialReqLocal - auxScnLocal) + '</span>'));
                        $('#barcode-aux-progress').html('<div style="padding:4px 8px;background:' + (auxCompleteLocal ? '#e8f5e9' : '#fff3e0') + ';border-radius:4px;">' + auxProgressHtmlLocal + '</div>').show();

                        const selectedProgress = data.barcode_kain_progress || [];
                        const allProgress = data.all_barcode_kain_progress || [];
                        const incompleteDetails = data.incomplete_details || [];
                        let progressHtml = '';
                        if (selectedProgress.length > 0) {
                            progressHtml += '<div style="padding:4px 0;"><strong>Progress Barcode Kain (Detail yang Dipilih):</strong><br>';
                            selectedProgress.forEach(function (p) {
                                let statusIcon = '';
                                let statusText = '';
                                let bgColor = '';
                                let borderStyle = '';

                                if (p.is_complete) {
                                    statusIcon = '<span style="color:#43a047;"><i class="fas fa-check"></i></span>';
                                    statusText = '<span style="color:#43a047;font-weight:600;">Lengkap</span>';
                                    bgColor = '#e8f5e9';
                                } else if (p.has_pending) {
                                    statusIcon = '<span style="color:#f57f17;"><i class="fas fa-clock"></i></span>';
                                    const remaining = p.roll - p.scanned;
                                    if (remaining > 0) {
                                        statusText = '<span style="color:#b78103;font-weight:600;">Menunggu Approval SAP (Kurang ' + remaining + ' roll)</span>';
                                    } else {
                                        statusText = '<span style="color:#b78103;font-weight:600;">Menunggu Approval SAP</span>';
                                    }
                                    bgColor = '#fffde7';
                                    borderStyle = 'border:1px solid #ffe082;';
                                } else {
                                    statusIcon = '<span style="color:#c62828;"><i class="fas fa-times"></i></span>';
                                    statusText = '<span style="color:#c62828;">Kurang ' + (p.roll - p.scanned) + ' roll</span>';
                                    bgColor = '#ffebee';
                                }

                                progressHtml += `<div style="background:${bgColor};${borderStyle}padding:4px 8px;margin:2px 0;border-radius:4px;">`;
                                progressHtml += `${statusIcon} <strong>OP ${p.no_op || 'N/A'}:</strong> ${p.scanned}/${p.roll} roll - ${statusText}`;
                                progressHtml += '</div>';
                            });
                            progressHtml += '</div>';
                        }
                        if (barcodeKainOptionalLoad) {
                            const hintHtmlLocal = '<div style="padding:6px 8px;background:#e3f2fd;border-radius:4px;margin-bottom:8px;font-size:12px;color:#1565c0;"><i class="fas fa-info-circle"></i> <strong>Proses ini hanya wajib Barcode Dye Stuff &amp; AUX (D &amp; A).</strong> Barcode Kain (G/F) tidak wajib.</div>';
                            progressHtml = hintHtmlLocal + progressHtml;
                        } else if (allProgress.length > 0) {
                            const totalDetails = allProgress.length;
                            const completeCount = allProgress.filter(function (p) { return p.is_complete; }).length;
                            const allComplete = completeCount === totalDetails;
                            const hasAnyPending = allProgress.some(function (p) { return p.has_pending; });

                            if (allComplete) {
                                progressHtml = '<div style="padding:4px 0;background:#e8f5e9;border-radius:4px;margin-bottom:8px;">' + progressHtml + '<div style="padding:4px 0;background:#e8f5e9;border-radius:4px;margin-top:8px;"><strong style="color:#2e7d32;"><i class="fas fa-check-circle"></i> Semua Detail OP Sudah Lengkap!</strong><br><span style="color:#43a047;font-size:12px;">Scan Barcode Dye Stuff & AUX sudah diizinkan.</span></div></div>';
                            } else if (hasAnyPending) {
                                progressHtml = '<div style="padding:4px 0;background:#fffde7;border:1px solid #ffe082;border-radius:4px;margin-bottom:8px;">' + progressHtml + '<div style="padding:6px 8px;background:#fff9c4;border-radius:4px;margin-top:8px;"><strong style="color:#b78103;"><i class="fas fa-clock mr-1"></i> Barcode Kain Sedang Menunggu Approval SAP</strong><br><span style="color:#856404;font-size:12px;">Terdapat pengajuan QTY GI Over Limit yang sedang menunggu persetujuan dari SAP. Scan Barcode Dye Stuff & AUX dapat dilakukan setelah disetujui.</span></div></div>';
                            } else {
                                progressHtml = '<div style="padding:4px 0;background:#ffebee;border-radius:4px;margin-bottom:8px;">' + progressHtml + '<div style="padding:4px 0;background:#ffebee;border-radius:4px;margin-top:8px;"><strong style="color:#c62828;"><i class="fas fa-exclamation-triangle"></i> ' + completeCount + ' dari ' + totalDetails + ' Detail OP Lengkap</strong><br><span style="color:#c62828;font-size:12px;">Semua Detail OP harus lengkap sebelum scan Barcode Dye Stuff & AUX.</span></div></div>';
                            }
                        }
                        if ($('#barcode-kain-progress').length) {
                            $('#barcode-kain-progress').html(progressHtml);
                        }
                        const $btnScanKainLocal = $('#btn-scan-kain');
                        if ($btnScanKainLocal.length) {
                            const allProgressLocal = data.all_barcode_kain_progress || [];
                            const allRollCompleteLocal = allProgressLocal.length > 0 && allProgressLocal.every(function (p) { return p.is_complete; });
                            const anyPendingKainLocal = allProgressLocal.some(function (p) { return p.has_pending; });
                            const allScannedOrPendingLocal = allProgressLocal.length > 0 && allProgressLocal.every(function (p) { return p.scanned >= p.roll; });

                            if (allRollCompleteLocal) {
                                $btnScanKainLocal.prop('disabled', true).removeClass('btn-success').addClass('btn-secondary').css('cursor', 'not-allowed').attr('title', 'Barcode kain sudah lengkap sesuai roll');
                            } else if (allScannedOrPendingLocal && anyPendingKainLocal) {
                                $btnScanKainLocal.prop('disabled', true).removeClass('btn-success').addClass('btn-secondary').css('cursor', 'not-allowed').attr('title', 'Menunggu approval SAP untuk barcode kain');
                            } else {
                                $btnScanKainLocal.prop('disabled', false).removeClass('btn-secondary').addClass('btn-success').css('cursor', 'pointer').removeAttr('title');
                            }
                        }
                        // Update G/D/A indicators per OP
                        const laProgGlobal = data.la_progress || {};
                        const auxProgGlobal = data.aux_progress || {};
                        const hasLaActiveGlobal = laProgGlobal.initial_is_complete !== undefined ? laProgGlobal.initial_is_complete : (laProgGlobal.initial_scanned >= (laProgGlobal.initial_required !== undefined ? laProgGlobal.initial_required : 0));
                        const hasAuxActiveGlobal = auxProgGlobal.initial_is_complete !== undefined ? auxProgGlobal.initial_is_complete : (auxProgGlobal.initial_scanned >= (auxProgGlobal.initial_required !== undefined ? auxProgGlobal.initial_required : 0));

                        if (data.all_barcode_kain_progress && Array.isArray(data.all_barcode_kain_progress) && data.all_barcode_kain_progress.length > 0) {
                            data.all_barcode_kain_progress.forEach(function (p) {
                                const detailId = p.detail_proses_id;
                                const kainColor = p.has_pending ? 'yellow' : (p.is_complete ? 'green' : 'red');
                                window.updateGDAIndicators(prosesId, detailId, kainColor, hasLaActiveGlobal, hasAuxActiveGlobal);
                            });
                        } else if (data.barcode_kain_progress && Array.isArray(data.barcode_kain_progress) && data.barcode_kain_progress.length > 0) {
                            data.barcode_kain_progress.forEach(function (p) {
                                const detailId = p.detail_proses_id || selectedDetailId;
                                const kainColor = p.has_pending ? 'yellow' : (p.is_complete ? 'green' : 'red');
                                window.updateGDAIndicators(prosesId, detailId, kainColor, hasLaActiveGlobal, hasAuxActiveGlobal);
                            });
                        } else {
                            window.updateGDAIndicators(prosesId, selectedDetailId, 'red', hasLaActiveGlobal, hasAuxActiveGlobal);
                        }
                    },
                    error: function () {
                        if ($('#barcode-kain-list').length) $('#barcode-kain-list').html('<span style="color:#888;">Belum ada barcode kain.</span>');
                        if ($('#barcode-kain-progress').length) $('#barcode-kain-progress').html('');
                        $('#barcode-la-list').html('<span style="color:#888;">Belum ada barcode Dye Stuff.</span>');
                        $('#barcode-aux-list').html('<span style="color:#888;">Belum ada barcode AUX.</span>');
                        $('#barcode-la-badges').html('');
                        $('#barcode-la-buttons').html('');
                        $('#barcode-la-progress').html('');
                        $('#barcode-aux-badges').html('');
                        $('#barcode-aux-buttons').html('');
                        $('#barcode-aux-progress').html('');
                    }
                });
            }
            window.loadBarcodesIntoDetailModal = loadBarcodesIntoDetailModal;

            // Refresh modal detail proses jika sedang terbuka untuk proses ini (setelah approval FM/VP disetujui)
            function refreshDetailModalIfOpen(prosesId, statusData, prosesFromCard) {
                if (!prosesId || !prosesFromCard) return;
                const selectedDetailId = $('#modalDetailProsesPending').data('detailProsesId') || $('#modalDetailProses').data('detailProsesId') || null;
                const noLongerPending = statusData && statusData.bg_color !== '#ffeb3b';

                // Modal Pending terbuka untuk proses ini
                if ($('#modalDetailProsesPending').hasClass('show') && $('#modalDetailProsesPending').data('prosesId') == prosesId) {
                    $('#modalDetailProsesPending').data('proses', prosesFromCard);
                    if (noLongerPending) {
                        // Approval sudah disetujui/ditolak: langsung tutup modal pending (JANGAN update body dulu agar tidak sekilas tampil "Menunggu Approval"), lalu buka modal normal
                        $('#modalDetailProsesPending').modal('hide');
                        $('#modalDetailProsesPending').one('hidden.bs.modal', function () {
                            $('#modalDetailProses').data('proses', prosesFromCard).data('prosesId', prosesFromCard.id).data('detailProsesId', selectedDetailId);
                            $('#detail-proses-body').html(buildDetailProsesBodyHtml(prosesFromCard, selectedDetailId));
                            if (prosesFromCard.jenis !== 'Maintenance') {
                                loadBarcodesIntoDetailModal(prosesFromCard.id, selectedDetailId);
                            }
                            $('.btn-edit-proses').prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                            $('.btn-move-proses').prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                            $('.btn-delete-proses').prop('disabled', false).removeClass('disabled').css('cursor', 'pointer');
                            $('#modalDetailProses').modal('show');
                        });
                    } else {
                        // Masih pending: update tabel dan kotak "Menunggu persetujuan dari FM/VP" agar sinkron 2-step approval lintas browser
                        $('#detail-proses-pending-body').html(buildDetailProsesPendingBodyHtml(prosesFromCard, selectedDetailId));
                        let list = [];
                        if (statusData && statusData.pending_approvals && statusData.pending_approvals.length) {
                            list = mapPendingApprovalsFromStatus(statusData.pending_approvals);
                        } else {
                            list = getAllPendingApprovals(prosesFromCard);
                        }
                        $('#pending-approval-info').html(buildPendingApprovalInfoHtml(list));
                    }
                }

                // Modal normal terbuka untuk proses ini
                if ($('#modalDetailProses').hasClass('show') && $('#modalDetailProses').data('prosesId') == prosesId) {
                    $('#modalDetailProses').data('proses', prosesFromCard);
                    const $body = $('#detail-proses-body');
                    $body.find('th').each(function () {
                        const thText = $(this).text().trim();
                        const $td = $(this).next('td');
                        if (thText === 'CYCLE TIME' && $td.length) $td.text(formatDetikToHMS(prosesFromCard.cycle_time));
                        if (thText === 'CYCLE TIME ACTUAL' && $td.length) $td.text(formatDetikToHMS(prosesFromCard.cycle_time_actual));
                        if (thText === 'MULAI' && $td.length) $td.text(formatDateTimeDisplay(prosesFromCard.mulai));
                        if (thText === 'SELESAI' && $td.length) $td.text(formatDateTimeDisplay(prosesFromCard.selesai));
                        if (thText === 'ORDER' && $td.length) $td.text(prosesFromCard.order != null ? prosesFromCard.order : '-');
                        if (thText === 'JENIS MESIN' && $td.length) {
                            let jenisMesin = '-';
                            try {
                                const mesinSelect = document.getElementById('mesin_id');
                                if (mesinSelect && prosesFromCard.mesin_id) {
                                    const opt = mesinSelect.querySelector('option[value="' + prosesFromCard.mesin_id + '"]');
                                    if (opt) jenisMesin = opt.textContent;
                                }
                            } catch (e) { }
                            $td.text(jenisMesin);
                        }
                    });

                    const $btnPinjam = $('.btn-pinjam-mesin');
                    if ($btnPinjam.length && !$btnPinjam.hasClass('d-none')) {
                        const isPinjamActive = prosesFromCard.is_pinjam_mesin === true || prosesFromCard.is_pinjam_mesin === 1 || prosesFromCard.is_pinjam_mesin === '1';
                        $btnPinjam.data('is-pinjam-active', isPinjamActive);
                        if (isPinjamActive) {
                            $btnPinjam.removeClass('btn-info').addClass('btn-danger');
                            $btnPinjam.html('<i class="fas fa-power-off mr-1"></i>Matikan Pinjam Mesin');
                            $btnPinjam.attr('title', 'Klik untuk mematikan status Pinjam Mesin');
                        } else {
                            $btnPinjam.removeClass('btn-danger').addClass('btn-info');
                            $btnPinjam.html('<i class="fas fa-exchange-alt mr-1"></i>Pinjam Mesin');
                            $btnPinjam.attr('title', 'Klik untuk mengaktifkan status Pinjam Mesin');
                        }
                    }

                    const $btnBreak = $('.btn-break-proses');
                    if ($btnBreak.length && !$btnBreak.hasClass('d-none')) {
                        const isBreakActive = prosesFromCard.is_break === true || prosesFromCard.is_break === 1 || prosesFromCard.is_break === '1';
                        $btnBreak.data('is-break-active', isBreakActive);
                        if (isBreakActive) {
                            $btnBreak.removeClass('btn-secondary').addClass('btn-success');
                            $btnBreak.html('<i class="fas fa-play mr-1"></i>Lanjutkan Proses');
                            $btnBreak.attr('title', 'Klik untuk menonaktifkan status Break dan melanjutkan proses');
                        } else {
                            $btnBreak.removeClass('btn-success').addClass('btn-secondary');
                            $btnBreak.html('<i class="fas fa-pause mr-1"></i>Break');
                            $btnBreak.attr('title', 'Klik untuk mengaktifkan status Break (Freeze cycle time)');
                        }
                    }

                    // Update Status Menunggu Mesin Mati di modal detail jika terbuka
                    const isWaitingStopModal = !!(prosesFromCard.stop_requested_at && !prosesFromCard.selesai);
                    const $modalAlertStop = $('#alert-waiting-stop');
                    const $modalBtnCancelStop = $('.btn-cancel-stop-request');
                    const $modalBtnFinishMaint = $('.btn-finish-maintenance');
                    const $modalBtnFinishForce = $('.btn-finish-force');
                    const userRoleCur = (window.userRole || '').toLowerCase();

                    if (isWaitingStopModal) {
                        $modalAlertStop.removeClass('d-none');
                        if (prosesFromCard.stop_request_type === 'maintenance_finish') {
                            $('#alert-waiting-stop-title').text('Maintenance Sedang Menunggu Mesin Berhenti');
                            $('#alert-waiting-stop-desc').text('Instruksi selesai telah dikirim. Menunggu operator di lapangan mematikan mesin sebelum proses selesai.');
                        } else {
                            $('#alert-waiting-stop-title').text('Sedang Menunggu Mesin Berhenti / Unload');
                            $('#alert-waiting-stop-desc').text('Instruksi selesai telah dikirim. Menunggu verifikasi unload atau operator mematikan mesin sebelum proses selesai.');
                        }
                        const canCancel = ['super_admin', 'kepala_shift', 'kepala_ruangan'].includes(userRoleCur);
                        if (canCancel) {
                            $modalBtnCancelStop.removeClass('d-none');
                        } else {
                            $modalBtnCancelStop.addClass('d-none');
                        }
                        $modalBtnFinishMaint.addClass('d-none');
                        $modalBtnFinishForce.addClass('d-none');
                    } else {
                        $modalAlertStop.addClass('d-none');
                        $modalBtnCancelStop.addClass('d-none');
                        const isStartedModal = prosesFromCard.mulai !== null && !prosesFromCard.selesai;
                        if (isStartedModal) {
                            if (prosesFromCard.jenis === 'Maintenance') {
                                const canFinMaint = window.canFinishMaintenance === true || ['super_admin', 'kepala_shift', 'kepala_ruangan'].includes(userRoleCur);
                                if (canFinMaint) $modalBtnFinishMaint.removeClass('d-none');
                            } else {
                                const canFinForce = ['super_admin', 'kepala_shift'].includes(userRoleCur) || (userRoleCur === 'kepala_ruangan' && window.isKashiftOff);
                                if (canFinForce) $modalBtnFinishForce.removeClass('d-none');
                            }
                        }
                    }
                }
            }

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
                    // Merah tua (proses selesai dengan masalah: overtime/durasi singkat) - solid
                    '#e53935': '#e53935',
                    // Merah muda (proses berjalan dengan barcode belum lengkap) - solid
                    '#ef9a9a': '#ef9a9a'
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
                                                                                                    <div class="proses-history-wrapper" data-section="history" data-mesin-id="${mesinId}" style="margin-bottom: 8px;">
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

                // Pindahkan card ke history (prepend agar terbaru di atas) dengan animasi fade
                $card.fadeOut(400, function () {
                    $card.find('.waiting-stop-banner').remove();
                    $card.find('.iot-offline-icon').hide();
                    $card.addClass('history-card');
                    $card.attr('draggable', 'false');
                    $card.attr('data-can-move', '0');
                    $card.css('cursor', 'default');
                    historyContainer.prepend($card);
                    $card.fadeIn(400);

                    // Update count di button
                    const count = historyContainer.find('.status-card').length;
                    $(`.btn-toggle-history[data-mesin-id="${mesinId}"]`)
                        .html(`<i class="fas fa-history"></i> Tampilkan History (${count})`);
                    if (typeof window.applyDashboardMode === 'function') window.applyDashboardMode();
                });
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
                const sortedCards = $cards.toArray().sort(function (a, b) {
                    const prosesA = $(a).data('proses');
                    const prosesB = $(b).data('proses');

                    if (!prosesA || !prosesB) return 0;

                    const isRunningA = !!(prosesA.mulai && !prosesA.selesai);
                    const isRunningB = !!(prosesB.mulai && !prosesB.selesai);

                    // 1. Proses yang sedang berjalan HARUS selalu di paling atas
                    if (isRunningA && !isRunningB) return -1;
                    if (!isRunningA && isRunningB) return 1;

                    // 2. Jika keduanya belum mulai (pending), urutkan berdasarkan order
                    if (!prosesA.mulai && !prosesB.mulai) {
                        const orderA = parseInt(prosesA.order || 0);
                        const orderB = parseInt(prosesB.order || 0);
                        if (orderA !== orderB) {
                            return orderA - orderB;
                        }
                    }

                    // 3. Fallback ke created_at atau id
                    const createdA = prosesA.created_at ? new Date(prosesA.created_at).getTime() : 0;
                    const createdB = prosesB.created_at ? new Date(prosesB.created_at).getTime() : 0;
                    if (createdA !== createdB) {
                        return createdA - createdB;
                    }
                    return (prosesA.id || 0) - (prosesB.id || 0);
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
                        cardsToReorder.forEach(function ($card) {
                            $prosesAktifContainer.append($card);
                        });
                    }
                }
            }

            // Fungsi global untuk update warna GDA per detail proses
            // Bisa digunakan untuk update real-time dari API atau setelah scan barcode
            window.updateGDAIndicators = function (prosesId, detailId, hasKain, hasLa, hasAux) {
                const $card = $(`.status-card[data-proses-id="${prosesId}"]`);
                if (!$card.length) {
                    console.warn('Card not found for prosesId:', prosesId);
                    return;
                }

                const prosesData = $card.data('proses');
                if (!prosesData || prosesData.jenis === 'Maintenance') {
                    return; // Tidak ada G/D/A atau F/D/A untuk Maintenance
                }
                const barcodeKainOptGlobal = prosesData.barcode_kain_optional === true;
                const firstBlock = (prosesData.mode === 'finish') ? 'F' : 'G';

                // Fungsi helper untuk set warna blok GDA/FDA
                function setBlockColor($container, blockType, ok) {
                    if (!$container || !$container.length) return;
                    const $blocks = $container.find(`.gda-block[data-block-type="${blockType}"]`);
                    if (!$blocks.length) {
                        return;
                    }

                    const isYellow = ok === 'yellow' || ok === 'pending';
                    const isGreen = ok === true || ok === 'green';
                    const blockBg = isGreen ? '#d4f8e8' : (isYellow ? '#fff9c4' : '#ffb3b3');
                    const blockBorder = isGreen ? '#43a047' : (isYellow ? '#f9a825' : '#c62828');
                    $blocks.css({
                        background: blockBg,
                        borderColor: blockBorder
                    });
                    console.log(`Updated ${blockType} to ${isGreen ? 'green' : (isYellow ? 'yellow' : 'red')} for prosesId: ${prosesId}, detailId: ${detailId || 'header'}`);
                }

                // Convert detailId ke string untuk memastikan match dengan HTML
                const detailIdStr = detailId ? String(detailId) : null;

                // 1. Cari kontainer spesifik menggunakan class .op-gda-container[data-detail-id="..."]
                let $gdaContainer = null;
                if (detailIdStr) {
                    $gdaContainer = $card.find(`.op-gda-container[data-detail-id="${detailIdStr}"]`);
                }

                // 2. Jika tidak ditemukan lewat data-detail-id langsung:
                if (!$gdaContainer || !$gdaContainer.length) {
                    const $firstOpRow = $card.find('.op-row').first();
                    const firstOpDetailId = $firstOpRow.length ? String($firstOpRow.attr('data-detail-id') || '') : '';
                    const isFirstOp = !detailIdStr || (firstOpDetailId && firstOpDetailId === detailIdStr);

                    if (isFirstOp) {
                        // Targetkan HANYA header container, JANGAN menggunakan $card utuh!
                        $gdaContainer = $card.find('.status-header-center .op-gda-container');
                        if (!$gdaContainer.length) {
                            $gdaContainer = $card.find('.status-header-center');
                        }
                    } else {
                        // Sub-OP fallback: cari elemen sebelum .op-row yang memiliki gda-block
                        const $opRow = $card.find(`.op-row[data-detail-id="${detailIdStr}"]`);
                        if ($opRow.length) {
                            const $prevSibling = $opRow.prev();
                            if ($prevSibling.length && $prevSibling.find('.gda-block').length > 0) {
                                $gdaContainer = $prevSibling;
                            } else {
                                const $opList = $opRow.closest('.op-list');
                                if ($opList.length) {
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
                        }
                    }
                }

                // Jika kontainer ditemukan, update blok di kontainer tersebut (terisolasi per OP)
                if ($gdaContainer && $gdaContainer.length) {
                    console.log('Updating blocks in isolated container for OP:', detailIdStr || 'header');
                    if (!barcodeKainOptGlobal) setBlockColor($gdaContainer, firstBlock, hasKain);
                    setBlockColor($gdaContainer, 'D', !!hasLa);
                    setBlockColor($gdaContainer, 'A', !!hasAux);
                } else {
                    // Fallback terakhir: update di status-header-center saja (TIDAK pernah $card)
                    console.warn('GDA container not found for detailId:', detailIdStr, 'falling back to header-center only');
                    const $headerContainer = $card.find('.status-header-center');
                    if ($headerContainer.length) {
                        if (!barcodeKainOptGlobal) setBlockColor($headerContainer, firstBlock, hasKain);
                        setBlockColor($headerContainer, 'D', !!hasLa);
                        setBlockColor($headerContainer, 'A', !!hasAux);
                    }
                }
            }

            // Inisialisasi WebSocket untuk real-time update
            if (typeof Echo !== 'undefined') {
                // Subscribe ke channel dashboard
                Echo.channel('dashboard.proses-statuses')
                    .listen('.proses.status.updated', (e) => {
                        handleProsesStatusUpdate(e.proses_id, e.status);
                    })
                    .listen('.proses.paused', (e) => {
                        // TANGKAP EVENT EKSKLUSIF PAUSE
                        handleProsesStatusUpdate(e.proses_id, e.status);
                    })
                    .listen('.barcode.status.updated', (e) => {
                        handleProsesStatusUpdate(e.proses_id, e.status);

                        // Refresh barcode di modal detail proses jika modal sedang terbuka untuk proses ini
                        // Ini memungkinkan update real-time lintas browser saat barcode di-scan
                        const prosesId = e.proses_id;

                        // Jika modal normal terbuka untuk proses ini, refresh barcode
                        if ($('#modalDetailProses').hasClass('show') && $('#modalDetailProses').data('prosesId') == prosesId) {
                            const proses = $('#modalDetailProses').data('proses');
                            const selectedDetailId = $('#modalDetailProses').data('detailProsesId') || null;

                            if (proses && proses.jenis !== 'Maintenance') {
                                // Refresh barcode secara real-time untuk semua browser yang membuka modal ini
                                loadBarcodesIntoDetailModal(prosesId, selectedDetailId);
                            }
                        }
                    })
                    .listen('.approval.pending.created', (e) => {
                        // Approval pending baru: create reproses, pindah mesin, edit cycle time, delete, tukar posisi.
                        // Update card menjadi kuning secara real-time untuk semua proses yang terpengaruh.
                        // ProsesStatusUpdated akan di-fire oleh backend untuk update lengkap (pending_approvals, cycle_time, dll).
                        if (e.proses_ids && Array.isArray(e.proses_ids)) {
                            e.proses_ids.forEach(function (prosesId) {
                                const $card = $(`.status-card[data-proses-id="${prosesId}"]`);
                                if ($card.length) {
                                    const proses = $card.data('proses');
                                    if (proses) {
                                        // Update warna card menjadi kuning (menunggu approval) - real-time visual update
                                        const yellowGradient = getGradient('#ffeb3b');
                                        $card.css('background', yellowGradient);
                                        $card.attr('data-bg-color', '#ffeb3b');

                                        // Inisialisasi pending_approvals jika belum ada (akan diisi lengkap oleh ProsesStatusUpdated)
                                        if (!proses.pending_approvals) {
                                            proses.pending_approvals = [];
                                        }
                                        $card.data('proses', proses);

                                        // Catatan: Modal akan di-refresh oleh ProsesStatusUpdated dengan data lengkap
                                        // (pending_approvals, bg_color, cycle_time, dll) via handleProsesStatusUpdate â†’ refreshDetailModalIfOpen
                                    }
                                }
                            });
                        }
                        // Proses baru (create reproses) ditangani oleh .proses.created â†’ reload.
                    })
                    .listen('.proses.created', (e) => {
                        $.get('/dashboard/proses/' + e.proses_id + '/card-html', function (res) {
                            if (res.html) {
                                const container = $('.card-dropzone[data-mesin-id="' + e.mesin_id + '"] .proses-aktif-container');
                                if (container.length) {
                                    if (container.find('.status-card[data-proses-id="' + e.proses_id + '"]').length === 0) {
                                        const $newCard = $(res.html).hide();
                                        container.append($newCard);
                                        $newCard.fadeIn(500);
                                        if (typeof window.initDraggable === 'function') {
                                            container.find('.draggable').each(function () {
                                                window.initDraggable(this);
                                            });
                                        }
                                    }
                                }
                            }
                        }).fail(function () {
                            window.location.reload();
                        });
                    })
                    .listen('.proses.deleted', (e) => {
                        // Hapus card dari DOM
                        const $card = $(`.status-card[data-proses-id="${e.proses_id}"]`);
                        if ($card.length) {
                            $card.fadeOut(300, function () {
                                $(this).remove();
                            });
                        }

                        // Tutup modal jika sedang terbuka untuk proses yang dihapus
                        const openPendingId = $('#modalDetailProsesPending').data('prosesId');
                        const openNormalId = $('#modalDetailProses').data('prosesId');
                        if (openPendingId == e.proses_id && $('#modalDetailProsesPending').hasClass('show')) {
                            $('#modalDetailProsesPending').modal('hide');
                        }
                        if (openNormalId == e.proses_id && $('#modalDetailProses').hasClass('show')) {
                            $('#modalDetailProses').modal('hide');
                        }
                    })
                    .listen('.proses.moved', (e) => {
                        const oldCard = $('.status-card[data-proses-id="' + e.proses_id + '"]');
                        if (oldCard.length) {
                            oldCard.remove();
                        }
                        $.get('/dashboard/proses/' + e.proses_id + '/card-html', function (res) {
                            if (res.html) {
                                const container = $('.card-dropzone[data-mesin-id="' + e.new_mesin_id + '"] .proses-aktif-container');
                                if (container.length) {
                                    container.append(res.html);
                                    if (typeof window.initDraggable === 'function') {
                                        container.find('.draggable').each(function () {
                                            window.initDraggable(this);
                                        });
                                    }
                                }
                            }
                        }).fail(function () {
                            window.location.reload();
                        });
                    })
                    .listen('.mesin.created', (e) => {
                        // Mesin baru ditambahkan - update dropdown mesin secara real-time
                        console.log('MesinCreated event received:', e);
                        if (e.mesin && e.mesin.id) {
                            updateMesinDropdown(e.mesin);
                        }
                    })
                    .listen('.mesin.updated', (e) => {
                        // Mesin di-update - update dropdown mesin secara real-time
                        console.log('MesinUpdated event received:', e);
                        if (e.mesin && e.mesin.id) {
                            updateMesinDropdown(e.mesin);

                            // UPDATE: sinkronisasikan properti proses.mesin.status pada semua proses (cards) di mesin ini
                            $('.status-card').each(function () {
                                let proses = $(this).data('proses');
                                const cardMesinId = proses ? (proses.mesin_id || (proses.mesin ? proses.mesin.id : null)) : $(this).closest('.card-dropzone').data('mesin-id');
                                if (cardMesinId == e.mesin.id) {
                                    if (!proses) proses = {};
                                    if (!proses.mesin) proses.mesin = {};
                                    proses.mesin.status = !!e.mesin.status;
                                    if (e.mesin.status === false && e.mesin.auto_offline) {
                                        // Update last_off_at secara real-time jika mesin auto-offline
                                        proses.mesin.last_off_at = new Date().toISOString().slice(0, 19).replace('T', ' ');
                                    }
                                    $(this).data('proses', proses);

                                    // FIX: Update visual lampu secara real-time
                                    const isRunning = proses.mulai !== null && (proses.selesai === null || proses.selesai === undefined);
                                    let light = 'red';
                                    if (proses.jenis === 'Maintenance') {
                                        light = isRunning ? 'yellow' : 'red';
                                    } else {
                                        light = (e.mesin.status && isRunning) ? 'green' : 'red';
                                    }
                                    const $lamp = $(this).find('.status-light');
                                    $lamp.removeClass('running-light running-light-yellow');
                                    if (light === 'green') {
                                        $lamp.addClass('running-light').css('background', '#00ff1a');
                                    } else if (light === 'yellow') {
                                        $lamp.addClass('running-light-yellow').css('background', '#ffeb3b');
                                    } else {
                                        $lamp.css('background', '#ff2a2a');
                                    }

                                    // Update icon peringatan sinyal IoT terputus
                                    const $offlineIcon = $(this).find('.iot-offline-icon');
                                    if (e.mesin.auto_offline) {
                                        if (isRunning) {
                                            $offlineIcon.show();
                                        }
                                    } else if (e.mesin.status) {
                                        $offlineIcon.hide();
                                    }
                                }
                            });
                        }
                    })
                    .listen('.mesin.deleted', (e) => {
                        // Mesin dihapus - hapus dari dropdown mesin secara real-time
                        console.log('MesinDeleted event received:', e);
                        if (e.mesin_id) {
                            removeMesinFromDropdown(e.mesin_id);
                        }
                    });
            } else {
                console.warn('Echo tidak tersedia; update card/modal hanya dari render awal halaman.');
            }

            // Fungsi untuk update dropdown mesin saat mesin ditambahkan/di-update
            function updateMesinDropdown(mesinData) {
                if (!mesinData || !mesinData.id) {
                    console.warn('updateMesinDropdown: Invalid mesinData', mesinData);
                    return;
                }

                console.log('updateMesinDropdown called with:', mesinData);

                const $filterMesin = $('#filter-mesin');
                const $mesinIdSelect = $('#mesin_id');
                const $moveMesinIdSelect = $('#moveMesinId');

                const mesinId = mesinData.id;
                const mesinName = mesinData.jenis_mesin || '';

                // Helper function untuk refresh Select2 dengan benar
                function refreshSelect2($select) {
                    if (!$select.length || !$.fn.select2) return;

                    try {
                        // Simpan nilai yang dipilih
                        const currentValue = $select.val();
                        const selectId = $select.attr('id');
                        const placeholder = $select.data('placeholder') || $select.attr('data-placeholder') || '-- Pilih --';
                        const isMultiple = $select.prop('multiple');

                        // Destroy Select2 jika sudah diinisialisasi
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }

                        // Konfigurasi Select2 berdasarkan ID dropdown
                        let select2Config = {
                            placeholder: placeholder,
                            allowClear: true,
                            width: '100%'
                        };

                        // Konfigurasi khusus untuk mesin_id (di modal tambah proses)
                        if (selectId === 'mesin_id') {
                            select2Config = {
                                dropdownParent: $('#modalProses'),
                                placeholder: '-- Pilih Mesin --',
                                allowClear: false,
                                dropdownCssClass: 'select2-dropdown-modal',
                                width: '100%'
                            };
                        }
                        // Konfigurasi untuk filter-mesin (multiple select)
                        else if (selectId === 'filter-mesin') {
                            select2Config = {
                                placeholder: 'Semua Mesin',
                                allowClear: true,
                                width: '100%'
                            };
                        }

                        // Re-init Select2
                        $select.select2(select2Config);

                        // Restore nilai yang dipilih
                        if (currentValue) {
                            window.isRefreshingSelect2 = true;
                            $select.val(currentValue).trigger('change');
                            window.isRefreshingSelect2 = false;
                        }
                    } catch (e) {
                        console.warn('Error refreshing Select2:', e);
                        // Fallback: trigger change saja
                        window.isRefreshingSelect2 = true;
                        $select.trigger('change');
                        window.isRefreshingSelect2 = false;
                    }
                }

                // Update filter mesin dropdown (di header dashboard)
                if ($filterMesin.length) {
                    const existingOption = $filterMesin.find(`option[value="${mesinId}"]`);
                    if (existingOption.length) {
                        // Update text jika sudah ada
                        existingOption.text(mesinName);
                    } else {
                        // Tambahkan option baru (urutkan berdasarkan id)
                        let inserted = false;
                        $filterMesin.find('option').each(function () {
                            const optId = parseInt($(this).val());
                            if (!isNaN(optId) && optId > mesinId) {
                                $(this).before(`<option value="${mesinId}">${mesinName}</option>`);
                                inserted = true;
                                return false; // break loop
                            }
                        });
                        if (!inserted) {
                            // Jika belum di-insert, tambahkan di akhir
                            $filterMesin.append(`<option value="${mesinId}">${mesinName}</option>`);
                        }
                    }
                    // Refresh Select2
                    refreshSelect2($filterMesin);
                }

                // Update mesin_id dropdown (di modal tambah proses)
                if ($mesinIdSelect.length) {
                    const existingOption = $mesinIdSelect.find(`option[value="${mesinId}"]`);
                    if (existingOption.length) {
                        existingOption.text(mesinName);
                    } else {
                        let inserted = false;
                        $mesinIdSelect.find('option').each(function () {
                            const optId = parseInt($(this).val());
                            if (!isNaN(optId) && optId > mesinId) {
                                $(this).before(`<option value="${mesinId}">${mesinName}</option>`);
                                inserted = true;
                                return false;
                            }
                        });
                        if (!inserted) {
                            $mesinIdSelect.append(`<option value="${mesinId}">${mesinName}</option>`);
                        }
                    }
                    // Refresh Select2
                    refreshSelect2($mesinIdSelect);
                }

                // Update moveMesinId dropdown (di modal pindah mesin)
                if ($moveMesinIdSelect.length) {
                    const existingOption = $moveMesinIdSelect.find(`option[value="${mesinId}"]`);
                    if (existingOption.length) {
                        existingOption.text(mesinName);
                    } else {
                        let inserted = false;
                        $moveMesinIdSelect.find('option').each(function () {
                            const optId = parseInt($(this).val());
                            if (!isNaN(optId) && optId > mesinId) {
                                $(this).before(`<option value="${mesinId}">${mesinName}</option>`);
                                inserted = true;
                                return false;
                            }
                        });
                        if (!inserted) {
                            $moveMesinIdSelect.append(`<option value="${mesinId}">${mesinName}</option>`);
                        }
                    }
                    // Refresh Select2 jika sudah diinisialisasi
                    refreshSelect2($moveMesinIdSelect);
                }

                console.log('updateMesinDropdown completed for mesin:', mesinId, mesinName);
            }

            // Fungsi untuk menghapus mesin dari dropdown saat mesin dihapus
            function removeMesinFromDropdown(mesinId) {
                if (!mesinId) {
                    console.warn('removeMesinFromDropdown: Invalid mesinId', mesinId);
                    return;
                }

                console.log('removeMesinFromDropdown called for mesinId:', mesinId);

                const $filterMesin = $('#filter-mesin');
                const $mesinIdSelect = $('#mesin_id');
                const $moveMesinIdSelect = $('#moveMesinId');

                // Helper function untuk refresh Select2 setelah remove
                function refreshSelect2AfterRemove($select) {
                    if (!$select.length || !$.fn.select2) return;

                    try {
                        // Hapus selection jika mesin yang dihapus sedang dipilih
                        const selectedValues = $select.val();
                        let newValue = selectedValues;

                        if (selectedValues) {
                            if (Array.isArray(selectedValues)) {
                                newValue = selectedValues.filter(v => String(v) !== String(mesinId));
                            } else if (String(selectedValues) === String(mesinId)) {
                                newValue = null;
                            }
                        }

                        const selectId = $select.attr('id');

                        // Destroy Select2 jika sudah diinisialisasi
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }

                        // Set nilai baru sebelum re-init
                        if (newValue !== selectedValues) {
                            $select.val(newValue);
                        }

                        // Konfigurasi Select2 berdasarkan ID dropdown
                        let select2Config = {
                            placeholder: $select.data('placeholder') || $select.attr('data-placeholder') || '-- Pilih --',
                            allowClear: true,
                            width: '100%'
                        };

                        // Konfigurasi khusus untuk mesin_id
                        if (selectId === 'mesin_id') {
                            select2Config = {
                                dropdownParent: $('#modalProses'),
                                placeholder: '-- Pilih Mesin --',
                                allowClear: false,
                                dropdownCssClass: 'select2-dropdown-modal',
                                width: '100%'
                            };
                        }
                        // Konfigurasi untuk filter-mesin
                        else if (selectId === 'filter-mesin') {
                            select2Config = {
                                placeholder: 'Semua Mesin',
                                allowClear: true,
                                width: '100%'
                            };
                        }

                        // Re-init Select2
                        $select.select2(select2Config);

                        // Trigger change jika nilai berubah
                        if (newValue !== selectedValues) {
                            $select.trigger('change');
                        }
                    } catch (e) {
                        console.warn('Error refreshing Select2 after remove:', e);
                        $select.trigger('change');
                    }
                }

                // Hapus dari filter mesin dropdown
                if ($filterMesin.length) {
                    const optionToRemove = $filterMesin.find(`option[value="${mesinId}"]`);
                    if (optionToRemove.length) {
                        optionToRemove.remove();
                        refreshSelect2AfterRemove($filterMesin);
                    }
                }

                // Hapus dari mesin_id dropdown
                if ($mesinIdSelect.length) {
                    const optionToRemove = $mesinIdSelect.find(`option[value="${mesinId}"]`);
                    if (optionToRemove.length) {
                        optionToRemove.remove();
                        refreshSelect2AfterRemove($mesinIdSelect);
                    }
                }

                // Hapus dari moveMesinId dropdown
                if ($moveMesinIdSelect.length) {
                    const optionToRemove = $moveMesinIdSelect.find(`option[value="${mesinId}"]`);
                    if (optionToRemove.length) {
                        optionToRemove.remove();
                        refreshSelect2AfterRemove($moveMesinIdSelect);
                    }
                }

                console.log('removeMesinFromDropdown completed for mesinId:', mesinId);
            }

            // Fungsi untuk handle update status dari WebSocket (single proses)
            function handleProsesStatusUpdate(prosesId, statusData) {
                const $card = $(`.status-card[data-proses-id="${prosesId}"]`);
                if (!$card.length) {
                    // Card tidak ditemukan, mungkin perlu reload
                    return;
                }

                const currentBgColor = $card.attr('data-bg-color');
                const proses = $card.data('proses');

                if (!proses) return;

                // CEK: Jika proses baru selesai, pindahkan ke history
                const wasNotFinished = !proses.selesai || proses.selesai === null;
                const isNowFinished = statusData.selesai && statusData.selesai !== null;

                if (wasNotFinished && isNowFinished && !$card.hasClass('history-card')) {
                    // Proses baru selesai, pindahkan ke history container
                    $card.find('.iot-offline-icon').hide();
                    moveToHistory($card, statusData);
                    // JANGAN RETURN DI SINI! Kita harus tetap meng-update warna, cycle time, dll.
                }

                // Update data proses untuk mulai, selesai, order, dan cycle_time
                const oldOrder = parseInt(proses.order || 0);
                proses.mulai = statusData.mulai;
                proses.selesai = statusData.selesai;
                proses.order = statusData.order || 0;
                if (statusData.is_paused !== undefined) {
                    proses.is_paused = statusData.is_paused;
                }
                if (statusData.updated_at !== undefined && statusData.updated_at !== null) {
                    proses.updated_at = statusData.updated_at;
                }
                if (statusData.mesin_status !== undefined && statusData.mesin_status !== null) {
                    if (!proses.mesin) proses.mesin = {};
                    proses.mesin.status = statusData.mesin_status;
                }
                if (statusData.mesin_last_off_at !== undefined && statusData.mesin_last_off_at !== null) {
                    if (!proses.mesin) proses.mesin = {};
                    proses.mesin.last_off_at = statusData.mesin_last_off_at;
                }
                if (statusData.cycle_time !== undefined && statusData.cycle_time !== null) {
                    proses.cycle_time = statusData.cycle_time;
                }
                if (statusData.cycle_time_actual !== undefined && statusData.cycle_time_actual !== null) {
                    proses.cycle_time_actual = statusData.cycle_time_actual;
                }

                // PENTING: Update pending_approvals agar modal sinkron (mis. FM -> VP)
                if (statusData.pending_approvals) {
                    proses.pending_approvals = statusData.pending_approvals;
                }
                if (statusData.is_pinjam_mesin !== undefined) {
                    proses.is_pinjam_mesin = statusData.is_pinjam_mesin;
                }
                if (statusData.pinjam_mesin_alasan !== undefined) {
                    proses.pinjam_mesin_alasan = statusData.pinjam_mesin_alasan;
                }

                // Update stop_requested_at & stop_request_type dari WebSocket
                if (statusData.is_stop_requested !== undefined) {
                    proses.stop_requested_at = statusData.stop_requested_at;
                    proses.stop_request_type = statusData.stop_request_type;

                    const isStopReq = statusData.is_stop_requested && !proses.selesai;
                    const $existingBanner = $card.find('.waiting-stop-banner');
                    if (isStopReq) {
                        if (!$existingBanner.length) {
                            const bannerHtml = `
                                    <div class="waiting-stop-banner" style="background: linear-gradient(90deg, #ff9800, #ffb74d); color: #111; font-weight: 800; font-size: 11px; padding: 3px 6px; text-align: center; border-bottom: 1px solid rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; gap: 5px;">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        <span>Menunggu Mesin Berhenti</span>
                                    </div>
                                `;
                            $card.prepend(bannerHtml);
                        }
                    } else {
                        $existingBanner.remove();
                    }
                }

                $card.data('proses', proses);

                // Update visual lampu secara real-time
                const mesinIsOn = proses.mesin ? proses.mesin.status : false;
                const isRunning = proses.mulai !== null && proses.selesai === null;
                let light = 'red';
                if (proses.jenis === 'Maintenance') {
                    light = isRunning ? 'yellow' : 'red';
                } else {
                    light = (mesinIsOn && isRunning) ? 'green' : 'red';
                }
                const $lamp = $card.find('.status-light');
                $lamp.removeClass('running-light running-light-yellow');
                if (light === 'green') {
                    $lamp.addClass('running-light').css('background', '#00ff1a');
                } else if (light === 'yellow') {
                    $lamp.addClass('running-light-yellow').css('background', '#ffeb3b');
                } else {
                    $lamp.css('background', '#ff2a2a');
                }

                // Update tampilan Pinjam Mesin (PM) di bawah lampu indikator
                const isPinjamActive = proses.is_pinjam_mesin === true || proses.is_pinjam_mesin === 1 || proses.is_pinjam_mesin === '1';
                const $pinjamIndicator = $card.find('.pinjam-mesin-indicator');
                if ($pinjamIndicator.length) {
                    if (isPinjamActive) {
                        $pinjamIndicator.css('display', 'flex');
                        if (proses.pinjam_mesin_alasan) {
                            $pinjamIndicator.find('.badge-pinjam-mesin').attr('title', 'Pinjam Mesin Aktif: ' + proses.pinjam_mesin_alasan);
                        }
                    } else {
                        $pinjamIndicator.css('display', 'none');
                    }
                }

                // Update tampilan cycle_time di card (setelah edit cycle time di-approve FM)
                if (statusData.cycle_time !== undefined) {
                    const cycleTimeStr = formatDetikToHMS(statusData.cycle_time);
                    $card.find('.card-time').each(function () {
                        $(this).find('span').eq(2).text(cycleTimeStr);
                    });
                }

                // Refresh modal detail jika terbuka untuk proses ini (seragamkan tampilan setelah approval FM/VP)
                refreshDetailModalIfOpen(prosesId, statusData, proses);

                // Update warna jika berbeda
                if (currentBgColor !== statusData.bg_color) {
                    const gradient = getGradient(statusData.bg_color);
                    $card.css('background', gradient);
                    $card.attr('data-bg-color', statusData.bg_color);
                }

                // Update warna GDA per detail proses jika ada data gda_details
                if (statusData.gda_details && Array.isArray(statusData.gda_details)) {
                    console.log('Updating GDA details from WebSocket:', {
                        prosesId: prosesId,
                        gdaDetails: statusData.gda_details
                    });

                    statusData.gda_details.forEach(function (gdaDetail) {
                        // Convert detailId ke string untuk memastikan match dengan HTML
                        const detailId = gdaDetail.detail_id ? String(gdaDetail.detail_id) : null;
                        const hasKain = gdaDetail.has_kain || false;
                        const hasLa = gdaDetail.has_la || false;
                        const hasAux = gdaDetail.has_aux || false;

                        console.log('Processing GDA detail:', {
                            detailId: detailId,
                            hasKain: hasKain,
                            hasLa: hasLa,
                            hasAux: hasAux,
                            roll: gdaDetail.roll,
                            barcode_kain_count: gdaDetail.barcode_kain_count
                        });

                        // Update GDA untuk detail ini menggunakan fungsi global
                        window.updateGDAIndicators(prosesId, detailId, hasKain, hasLa, hasAux);
                    });
                } else {
                    console.warn('No gda_details in statusData:', statusData);
                }

                // Update TD/TA indicators dan D/A blocks dengan la_complete, aux_complete
                // Untuk multiple OP: update semua .topping-td/.topping-ta dalam card (header + per OP)
                if (statusData.has_topping_la !== undefined || statusData.has_topping_aux !== undefined ||
                    statusData.td_color !== undefined || statusData.ta_color !== undefined ||
                    statusData.la_complete !== undefined || statusData.aux_complete !== undefined ||
                    statusData.la_initial_complete !== undefined || statusData.aux_initial_complete !== undefined) {
                    const $header = $card.find('.card-header > div:nth-child(2)');
                    const $gdaContainers = $card.find('.op-list > div:has(.gda-block)');
                    const getTdStyle = (c) => c === 'yellow' ? 'background:#fff9c4;color:#111;border:2.5px solid #f9a825' : (c === 'red' ? 'background:#ffb3b3;color:#111;border:2.5px solid #c62828' : (c === 'green' ? 'background:#d4f8e8;color:#111;border:2.5px solid #43a047' : (c === 'inactive' ? 'background:#eceff1;color:#555;border:2.5px solid #90a4ae' : '')));
                    const getToppingTitle = (c, side) => {
                        if (c === 'yellow') return 'Menunggu approval';
                        if (c === 'red') return 'Menunggu scan barcode';
                        if (c === 'green') return 'Lengkap';
                        if (c === 'inactive') return side === 'td' ? 'Lengkap (hijau pada TA)' : 'Lengkap (hijau pada TD)';
                        return '';
                    };
                    if (statusData.has_topping_la && statusData.td_color) {
                        const style = getTdStyle(statusData.td_color);
                        const title = getToppingTitle(statusData.td_color, 'td');
                        const tdHtml = '<span class="topping-indicator topping-td" data-block-type="TD" title="Topping Dyes - ' + title + '" style="display: inline-block; ' + style + '; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TD</span>';
                        let $tdAll = $card.find('.topping-td');
                        if ($tdAll.length) {
                            $tdAll.attr('style', 'display: inline-block; ' + style + '; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;').attr('title', 'Topping Dyes - ' + title);
                        } else {
                            $header.append(tdHtml);
                            $gdaContainers.each(function () { if (!$(this).find('.topping-td').length) $(this).append(tdHtml); });
                        }
                    } else if (!statusData.has_topping_la) {
                        $card.find('.topping-td').remove();
                    }
                    if (statusData.has_topping_aux && statusData.ta_color) {
                        const style = getTdStyle(statusData.ta_color);
                        const title = getToppingTitle(statusData.ta_color, 'ta');
                        const taHtml = '<span class="topping-indicator topping-ta" data-block-type="TA" title="Topping Auxiliaries - ' + title + '" style="display: inline-block; ' + style + '; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;">TA</span>';
                        let $taAll = $card.find('.topping-ta');
                        if ($taAll.length) {
                            $taAll.attr('style', 'display: inline-block; ' + style + '; font-weight: bold; font-size: 18px; padding: 2px 8px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px;').attr('title', 'Topping Auxiliaries - ' + title);
                        } else {
                            $header.append(taHtml);
                            $gdaContainers.each(function () { if (!$(this).find('.topping-ta').length) $(this).append(taHtml); });
                        }
                    } else if (!statusData.has_topping_aux) {
                        $card.find('.topping-ta').remove();
                    }
                    if (statusData.la_initial_complete !== undefined || statusData.aux_initial_complete !== undefined ||
                        statusData.la_complete !== undefined || statusData.aux_complete !== undefined) {
                        const laD = statusData.la_initial_complete !== undefined ? statusData.la_initial_complete : statusData.la_complete;
                        const auxA = statusData.aux_initial_complete !== undefined ? statusData.aux_initial_complete : statusData.aux_complete;
                        const $blocks = $card.find('.gda-block');
                        $blocks.each(function () {
                            const t = $(this).data('block-type');
                            if (t === 'D' && laD !== undefined) {
                                $(this).css({ background: laD ? '#d4f8e8' : '#ffb3b3', borderColor: laD ? '#43a047' : '#c62828' });
                            } else if (t === 'A' && auxA !== undefined) {
                                $(this).css({ background: auxA ? '#d4f8e8' : '#ffb3b3', borderColor: auxA ? '#43a047' : '#c62828' });
                            }
                        });
                    }
                }

                // Reorder jika order berubah dan proses masih pending
                if (!proses.selesai && !proses.mulai && statusData.order !== undefined) {
                    const newOrder = parseInt(statusData.order || 0);
                    if (oldOrder !== newOrder) {
                        const mesinId = proses.mesin_id;
                        const container = document.querySelector(`[data-mesin-id="${mesinId}"]`);
                        if (container) {
                            reorderCardsByOrder(container);
                        }
                    }
                }
            }
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
                } catch (e) {
                    // Jika destroy gagal, ignore (mungkin sudah di-destroy sebelumnya)
                }
            }
            if ($noPartaiSelect.hasClass('select2-hidden-accessible')) {
                try {
                    $noPartaiSelect.select2('destroy');
                } catch (e) {
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
                    data: function (params) {
                        return {
                            no_op: params.term
                        };
                    },
                    processResults: function (data) {
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
                    error: function (xhr, status, error) {
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
            $noOpSelect.on('select2:select', function (e) {
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
                    success: function (res) {
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
                    error: function (xhr) {
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
            $noPartaiSelect.on('change', function () {
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
                    $item.find('[name*="[customer]"]').val(row.customer || row.Customer || '');
                    $item.find('[name*="[marketing]"]').val(row.marketing || row.Marketing || '');
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
            $newItem.find('[name]').each(function () {
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
            $newItem.find('.no-op-select, .no-partai-select').each(function () {
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
            $item.find('.no-op-select, .no-partai-select').each(function () {
                const $select = $(this);
                if ($select.hasClass('select2-hidden-accessible')) {
                    try {
                        $select.select2('destroy');
                    } catch (e) {
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

            $items.each(function (index) {
                const $item = $(this);
                const newIndex = index;

                // Update data-index
                $item.attr('data-index', newIndex);

                // Update teks nomor
                $item.find('.card-header h6').text('Detail OP #' + (index + 1));

                // Update semua name attributes
                $item.find('[name]').each(function () {
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

        $(document).ready(function () {
            // Inisialisasi select2 untuk detail item pertama
            initDetailSelect2($('#detail-proses-container .detail-proses-item').first(), 0);

            // Handler untuk Jenis OP (Single/Multiple)
            $('#jenis_op').on('change', function () {
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
                    $items.slice(1).each(function () {
                        removeDetailItem($(this));
                    });

                    // Reset index
                    detailItemIndex = 0;
                    $items.first().attr('data-index', 0);
                    updateDetailItemNumbers();
                }
            });

            // Tampilkan hint Reproses (mode Greige): No OP & No Partai harus pernah dipakai di Produksi
            function toggleReprocessHint() {
                var mode = $('input[name="proses_mode_radio"]:checked').val() || 'greige';
                var jenis = $('#jenis').val();
                var isReproses = (jenis === 'Reproses');
                var $hint = $('#reprocess-hint-greige');
                if (mode === 'greige' && isReproses) {
                    $hint.show();
                } else {
                    $hint.hide();
                }
            }

            function toggleMakloonHint() {
                var $hint = $('#makloon-hint-op');
                if ($hint.length) {
                    $hint.hide();
                }
            }

            // Handler untuk Jenis Proses
            $('[name="jenis"]').on('change', function () {
                var isMaintenance = $(this).val() === 'Maintenance';
                var $maintenanceBlocks = $('.hide-if-maintenance');

                // Jika Maintenance: sembunyikan blok & matikan required supaya
                // field tersembunyi (mis. no_op, no_partai, jenis_op) tidak
                // ikut divalidasi browser dan menyebabkan error "not focusable".
                if (isMaintenance) {
                    $maintenanceBlocks.find('input, select, textarea').each(function () {
                        // Simpan status required awal di data attribute
                        if ($(this).prop('required')) {
                            $(this).data('was-required', true);
                        }
                        $(this).prop('required', false);
                    });

                    $maintenanceBlocks.hide();
                } else {
                    // Jika bukan Maintenance: tampilkan kembali dan restore required
                    $maintenanceBlocks.show();

                    $maintenanceBlocks.find('input, select, textarea').each(function () {
                        if ($(this).data('was-required')) {
                            $(this).prop('required', true);
                        }
                    });
                }
                toggleReprocessHint();
                toggleMakloonHint();
            });

            // Handler untuk tombol tambah detail
            $(document).on('click', '#add-detail-btn', function () {
                addDetailItem();
            });

            // Handler untuk tombol hapus detail
            $(document).on('click', '.remove-detail-btn', function () {
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

            // Fungsi: terapkan mode (Greige/Finish) ke hidden input, judul, dan opsi Jenis Proses
            function applyProsesMode(mode) {
                mode = mode || $('input[name="proses_mode_radio"]:checked').val() || 'greige';
                $('#proses_mode').val(mode);
                $('#modalProsesLabel').text(mode === 'finish' ? 'Tambah Proses (Finish)' : 'Tambah Proses (Greige)');
                var $produksi = $('#jenis-option-produksi');
                var $maintenance = $('#jenis-option-maintenance');
                var $reproses = $('#jenis-option-reproses');
                var $prosesMakloon = $('#jenis-option-proses-makloon');
                var $reprosesMakloon = $('#jenis-option-reproses-makloon');

                // Makloon & Reproses Makloon dinonaktifkan sementara
                if ($prosesMakloon.length) $prosesMakloon.prop('disabled', true).hide();
                if ($reprosesMakloon.length) $reprosesMakloon.prop('disabled', true).hide();

                if (mode === 'finish') {
                    // Finish hanya boleh Reproses (Reproses Makloon dinonaktifkan sementara)
                    $produksi.prop('disabled', true).hide();
                    $maintenance.prop('disabled', true).hide();

                    $reproses.prop('disabled', false).show();

                    var curVal = $('#jenis').val();
                    if (curVal !== 'Reproses') {
                        $('#jenis').val('Reproses');
                    }
                    $('#jenis').css({ 'pointer-events': 'auto', 'background-color': '#fff' }).removeAttr('tabindex');
                } else {
                    // Greige: Produksi, Maintenance, Reproses (Makloon dinonaktifkan sementara)
                    $produksi.prop('disabled', false).show();
                    $maintenance.prop('disabled', false).show();
                    $reproses.prop('disabled', false).show();

                    var curVal = $('#jenis').val();
                    if (!curVal || curVal === 'Proses Makloon' || curVal === 'Reproses Makloon') {
                        $('#jenis').val('Produksi');
                    }
                    $('#jenis').css({ 'pointer-events': 'auto', 'background-color': '#fff' }).removeAttr('tabindex');
                }
                toggleReprocessHint();
                toggleMakloonHint();
            }

            // Saat modal dibuka: set default mode Greige dan terapkan (kecuali form di-reopen dengan error, jenis bisa Reproses)
            $('#modalProses').on('show.bs.modal', function () {
                $('#proses_mode_greige').prop('checked', true);
                applyProsesMode('greige');
                toggleReprocessHint();
            });

            // Saat user ganti mode di dalam modal (radio)
            $(document).on('change', 'input[name="proses_mode_radio"]', function () {
                var mode = $(this).val();
                applyProsesMode(mode);
            });

            // Inisialisasi Select2 saat modal dibuka
            $('#modalProses').on('shown.bs.modal', function () {
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
            $('#modalProses').on('hidden.bs.modal', function () {
                // Reset form
                $('#formProses')[0].reset();

                // Hapus semua detail item kecuali yang pertama
                const $items = $('#detail-proses-container .detail-proses-item');
                $items.slice(1).each(function () {
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

                // Bersihkan option yang ditambahkan secara dinamis di select2
                $('#detail-proses-container .select2-detail').each(function () {
                    $(this).find('option:not([disabled])').remove();
                    $(this).val('');
                });

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

                // Reset breakdown schedule container
                $('#dye_stuff_schedule_container').hide();
                $('#aux_schedule_container').hide();
                $('#dye_stuff_schedule_inputs').empty();
                $('#aux_schedule_inputs').empty();
            });

            // Helper konversi format JJ:MM:DD atau format durasi ke Total Detik
            function parseTimeToSeconds(timeStr) {
                if (!timeStr) return 0;
                const parts = timeStr.trim().split(':');
                if (parts.length === 3) {
                    return parseInt(parts[0], 10) * 3600 + parseInt(parts[1], 10) * 60 + parseInt(parts[2], 10);
                } else if (parts.length === 2) {
                    return parseInt(parts[0], 10) * 3600 + parseInt(parts[1], 10) * 60;
                } else if (parts.length === 1 && !isNaN(parts[0])) {
                    return parseInt(parts[0], 10) * 3600;
                }
                return 0;
            }

            // Fungsi Render Input Breakdown Waktu (Dye Stuff & AUX)
            function renderScheduleInputs(type, qty) {
                const container = $(`#${type}_schedule_container`);
                const inputsWrapper = $(`#${type}_schedule_inputs`);
                inputsWrapper.empty();

                const count = parseInt(qty, 10);
                if (count > 0) {
                    const labelName = type === 'dye_stuff' ? 'Dye Stuff' : 'AUX';
                    const badgeColor = type === 'dye_stuff' ? 'badge-primary' : 'badge-success';

                    for (let i = 1; i <= count; i++) {
                        const defaultHour = String(i * 2).padStart(2, '0');
                        const inputHtml = `
                                        <div class="form-group mb-2">
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text font-weight-bold" style="font-size: 12px; min-width: 110px;">
                                                        <span class="badge ${badgeColor} mr-1">${i}</span> ${labelName} #${i}
                                                    </span>
                                                </div>
                                                <input type="text" 
                                                       name="${type}_schedules[${i - 1}]" 
                                                       class="form-control form-control-sm schedule-time-input" 
                                                       placeholder="Contoh: ${defaultHour}:00:00 (Jam ke-${i * 2})" 
                                                       pattern="^[0-9]{2}:[0-9]{2}:[0-9]{2}$" 
                                                       title="Format durasi Jam:Menit:Detik (JJ:MM:DD)" 
                                                       required>
                                            </div>
                                        </div>
                                    `;
                        inputsWrapper.append(inputHtml);
                    }
                    container.slideDown(200);
                } else {
                    container.slideUp(200);
                }
            }

            // Event Listener Perubahan Qty Dye Stuff & AUX
            $('#qty_dye_stuff').on('change', function () {
                renderScheduleInputs('dye_stuff', $(this).val());
            });

            $('#qty_aux').on('change', function () {
                renderScheduleInputs('aux', $(this).val());
            });

            // Trigger di awal
            $('[name="jenis"]').trigger('change');
            $('#jenis_op').trigger('change');

            // Validasi tambahan saat submit form:
            // 1. Jika jenis_op = Multiple maka jumlah Detail OP harus >= 2
            // 2. Validasi breakdown jam input Dye Stuff & AUX terhadap Cycle Time
            // 3. Cek duplikasi (no_op, no_partai) dalam 1 proses
            // 4. Cek (no_op, no_partai) sudah terpakai di proses lain via API
            $('#formProses').on('submit', async function (e) {
                if (window._formProsesSkipValidation) {
                    window._formProsesSkipValidation = false;
                    return;
                }
                e.preventDefault();

                const jenisOp = $('#jenis_op').val();
                const jenisProses = $('[name="jenis"]').val();
                const $form = $(this);
                const $btn = $form.find('button[type="submit"]');

                // Validasi Multiple OP
                if (jenisOp === 'Multiple') {
                    const detailCount = $('#detail-proses-container .detail-proses-item').length;
                    if (detailCount < 2) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Detail OP kurang',
                            text: 'Jenis OP dengan jenis Multiple, minimal harus ada 2 Detail OP.',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                }

                // Validasi Breakdown Waktu terhadap Cycle Time
                if (jenisProses !== 'Maintenance') {
                    const cycleTimeStr = $('input[name="cycle_time"]').val();
                    const cycleTimeSec = parseTimeToSeconds(cycleTimeStr);

                    if (cycleTimeSec <= 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Cycle Time Tidak Valid',
                            text: 'Harap isi Cycle Time terlebih dahulu dengan format Jam:Menit:Detik (JJ:MM:DD).',
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }

                    // Validasi Breakdown Dye Stuff
                    const qtyDs = parseInt($('#qty_dye_stuff').val(), 10);
                    if (qtyDs > 0) {
                        let lastSec = -1;
                        for (let i = 0; i < qtyDs; i++) {
                            const $input = $(`input[name="dye_stuff_schedules[${i}]"]`);
                            const val = $input.val() ? $input.val().trim() : '';
                            if (!val || !/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/.test(val)) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Format Waktu Belum Tepat',
                                    text: `Jam input Dye Stuff #${i + 1} harus diisi dengan format JJ:MM:DD (contoh: 03:00:00).`,
                                    confirmButtonText: 'OK'
                                });
                                $input.focus();
                                return false;
                            }
                            const curSec = parseTimeToSeconds(val);
                            if (curSec > cycleTimeSec) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Waktu Input Melebihi Cycle Time',
                                    text: `Jam input Dye Stuff #${i + 1} (${val}) melebihi total durasi Cycle Time (${cycleTimeStr}).`,
                                    confirmButtonText: 'OK'
                                });
                                $input.focus();
                                return false;
                            }
                            if (curSec <= lastSec) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Urutan Waktu Tidak Tepat',
                                    text: `Jam input Dye Stuff #${i + 1} harus lebih besar dari Dye Stuff #${i}.`,
                                    confirmButtonText: 'OK'
                                });
                                $input.focus();
                                return false;
                            }
                            lastSec = curSec;
                        }
                    }

                    // Validasi Breakdown AUX
                    const qtyAux = parseInt($('#qty_aux').val(), 10);
                    if (qtyAux > 0) {
                        let lastSec = -1;
                        for (let i = 0; i < qtyAux; i++) {
                            const $input = $(`input[name="aux_schedules[${i}]"]`);
                            const val = $input.val() ? $input.val().trim() : '';
                            if (!val || !/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/.test(val)) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Format Waktu Belum Tepat',
                                    text: `Jam input AUX #${i + 1} harus diisi dengan format JJ:MM:DD (contoh: 01:30:00).`,
                                    confirmButtonText: 'OK'
                                });
                                $input.focus();
                                return false;
                            }
                            const curSec = parseTimeToSeconds(val);
                            if (curSec > cycleTimeSec) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Waktu Input Melebihi Cycle Time',
                                    text: `Jam input AUX #${i + 1} (${val}) melebihi total durasi Cycle Time (${cycleTimeStr}).`,
                                    confirmButtonText: 'OK'
                                });
                                $input.focus();
                                return false;
                            }
                            if (curSec <= lastSec) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Urutan Waktu Tidak Tepat',
                                    text: `Jam input AUX #${i + 1} harus lebih besar dari AUX #${i}.`,
                                    confirmButtonText: 'OK'
                                });
                                $input.focus();
                                return false;
                            }
                            lastSec = curSec;
                        }
                    }
                }

                // Validasi duplikasi (no_op, no_partai) dalam 1 proses. No Partai sama boleh jika No OP beda.
                if (jenisProses !== 'Maintenance') {
                    const isMakloon = (jenisProses === 'Proses Makloon' || jenisProses === 'Reproses Makloon');
                    const modeVal = $('#proses_mode').val() || 'greige';
                    let opError = null;

                    $('#detail-proses-container .detail-proses-item').each(function (idx) {
                        const noOp = $(this).find('[name*=\"[no_op]\"]').val() || '';
                        const trimmedOp = noOp.trim();
                        if (!trimmedOp) return;

                        if (isMakloon) {
                            if (!trimmedOp.startsWith('007')) {
                                opError = `No OP pada item #${idx + 1} (${trimmedOp}) harus berawalan "007" untuk jenis proses ${jenisProses}.`;
                                return false;
                            }
                        } else if (modeVal === 'finish') {
                            if (!trimmedOp.startsWith('010')) {
                                opError = `No OP pada item #${idx + 1} (${trimmedOp}) harus berawalan "010" untuk mode Finish.`;
                                return false;
                            }
                        } else {
                            if (!trimmedOp.startsWith('066')) {
                                opError = `No OP pada item #${idx + 1} (${trimmedOp}) harus berawalan "066" untuk mode Greige.`;
                                return false;
                            }
                        }
                    });

                    if (opError) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Format No OP Tidak Sesuai',
                            text: opError,
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }

                    const pairs = {};
                    const duplicatePairs = [];

                    $('#detail-proses-container .detail-proses-item').each(function () {
                        const noOp = $(this).find('[name*=\"[no_op]\"]').val();
                        const noPartai = $(this).find('[name*=\"[no_partai]\"]').val();
                        if (noOp && noOp.trim() !== '' && noPartai && noPartai.trim() !== '') {
                            const key = noOp.trim() + '|' + noPartai.trim();
                            if (pairs[key]) {
                                if (duplicatePairs.indexOf(key) === -1) duplicatePairs.push(key);
                            } else {
                                pairs[key] = true;
                            }
                        }
                    });

                    if (duplicatePairs.length > 0) {
                        const labels = duplicatePairs.map(k => k.replace('|', ' / '));
                        Swal.fire({
                            icon: 'error',
                            title: 'Kombinasi No OP + No Partai duplikat',
                            html: 'Kombinasi No OP + No Partai tidak boleh duplikat dalam satu proses.<br><strong>Duplikat:</strong> ' + labels.join(', '),
                            confirmButtonText: 'OK'
                        });
                        return false;
                    }
                }

                // Cek (no_op, no_partai) sudah terpakai di proses lain
                if (jenisProses !== 'Maintenance') {
                    const pairList = [];
                    const seen = {};
                    $('#detail-proses-container .detail-proses-item').each(function () {
                        const noOp = $(this).find('[name*=\"[no_op]\"]').val();
                        const noPartai = $(this).find('[name*=\"[no_partai]\"]').val();
                        if (noOp && noOp.trim() !== '' && noPartai && noPartai.trim() !== '') {
                            const key = noOp.trim() + '|' + noPartai.trim();
                            if (!seen[key]) {
                                seen[key] = true;
                                pairList.push({ noOp: noOp.trim(), noPartai: noPartai.trim() });
                            }
                        }
                    });

                    if (pairList.length > 0) {
                        $btn.prop('disabled', true);
                        try {
                            const promises = pairList.map(function (p) {
                                return $.ajax({
                                    url: '/api/check-partai-used',
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    data: { no_op: p.noOp, no_partai: p.noPartai, jenis: jenisProses, mode: $('#proses_mode').val() },
                                    dataType: 'json'
                                });
                            });
                            const results = await Promise.all(promises);
                            for (let i = 0; i < results.length; i++) {
                                if (!results[i].ok) {
                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'No Partai sudah dipakai',
                                        text: results[i].message || 'No Partai untuk No OP tersebut sudah dipakai di proses lain.',
                                        confirmButtonText: 'OK'
                                    });
                                    $btn.prop('disabled', false);
                                    return false;
                                }
                            }
                        } catch (err) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Validasi gagal',
                                text: 'Gagal mengecek ketersediaan No Partai. Silakan coba lagi.',
                                confirmButtonText: 'OK'
                            });
                            $btn.prop('disabled', false);
                            return false;
                        }
                        $btn.prop('disabled', false);
                    }
                }

                // Submit form dengan AJAX agar halaman tidak ter-refresh
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

                $.ajax({
                    url: $form.attr('action'),
                    type: 'POST',
                    data: $form.serialize(),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    success: function (response) {
                        $btn.prop('disabled', false).text('Simpan Proses');
                        if (response.status === 'success') {
                            showToastNotification('success', response.message || 'Proses berhasil ditambahkan');
                            $('#modalProses').modal('hide');
                            $form[0].reset();
                            // Hapus semua detail item kecuali yang pertama
                            const $items = $('#detail-proses-container .detail-proses-item');
                            $items.slice(1).each(function () {
                                $(this).remove();
                            });

                            // Instant UI Update
                            if (response.html && response.mesin_id) {
                                const container = $('.card-dropzone[data-mesin-id="' + response.mesin_id + '"] .proses-aktif-container');
                                // Cek apakah card sudah di-render oleh websocket
                                if (container.length && container.find('.status-card[data-proses-id="' + response.proses_id + '"]').length === 0) {
                                    // PENTING: Gunakan $.parseHTML dan pastikan card terlihat
                                    const cardHtmlStr = $.trim(response.html);
                                    if (cardHtmlStr) {
                                        const $newCard = $($.parseHTML(cardHtmlStr));
                                        $newCard.hide();
                                        container.append($newCard);
                                        $newCard.fadeIn(500);
                                        if (typeof window.initDraggable === 'function') {
                                            container.find('.draggable').each(function () {
                                                window.initDraggable(this);
                                            });
                                        }
                                    }
                                }
                            }
                        } else {
                            showToastNotification('success', 'Proses berhasil ditambahkan');
                            $('#modalProses').modal('hide');
                            $form[0].reset();
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).text('Simpan Proses');
                        let msg = 'Gagal menyimpan proses';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).map(e => e.join(', ')).join('<br>');
                        }
                        showToastNotification('error', msg);
                    }
                });
            });

            // Validasi realtime: cegah duplikat (no_op, no_partai). No Partai sama diperbolehkan jika No OP beda.
            $(document).on('change', '#detail-proses-container [name*="[no_partai]"]', function () {
                const $select = $(this);
                const selectedPartai = $select.val();
                const jenisProses = $('[name=\"jenis\"]').val();

                if (!selectedPartai || jenisProses === 'Maintenance') {
                    return;
                }

                const $item = $select.closest('.detail-proses-item');
                const currentNoOp = $item.find('[name*=\"[no_op]\"]').val();
                if (!currentNoOp || !currentNoOp.trim()) {
                    return;
                }

                let isDuplicate = false;
                $('#detail-proses-container .detail-proses-item').each(function () {
                    const $other = $(this);
                    const $otherPartai = $other.find('[name*=\"[no_partai]\"]');
                    if (!$otherPartai.length || $otherPartai[0] === $select[0]) return;
                    const otherNoOp = $other.find('[name*=\"[no_op]\"]').val();
                    const otherPartai = $otherPartai.val();
                    if (otherNoOp && otherPartai && otherNoOp === currentNoOp && otherPartai === selectedPartai) {
                        isDuplicate = true;
                        return false;
                    }
                });

                if (isDuplicate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Kombinasi No OP + No Partai duplikat',
                        text: 'Kombinasi No OP \"' + currentNoOp + '\" + No Partai \"' + selectedPartai + '\" sudah dipakai di Detail OP lain dalam proses ini.',
                        confirmButtonText: 'OK'
                    });
                    $select.val('').trigger('change');
                }
            });
        });

        // Helper function untuk notifikasi Swal Toast (tetap muncul hingga user tutup)
        function showToastNotification(type, message) {
            const toastMixin = type === 'success' ? window.ToastSuccess : window.ToastError;
            if (typeof message === 'string' && (message.includes('\n') || message.includes('<br>'))) {
                const formattedHtml = message.replace(/\n/g, '<br>');
                toastMixin.fire({
                    html: `<div style="text-align: left; font-size: 13px; line-height: 1.4; word-break: break-word;">${formattedHtml}</div>`
                });
            } else {
                toastMixin.fire({
                    title: message
                });
            }
        }

        $(document).on('click', '.cancel-barcode-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const barcodeType = $(this).data('type');
            const prosesId = $(this).data('proses');
            const barcodeId = $(this).data('id');
            const matdok = $(this).data('matdok');
            const barcode = $(this).data('barcode') || '';
            const approvalStatus = $(this).data('approval-status') || '';

            if (barcodeType === 'kain' && approvalStatus === 'pending') {
                showToastNotification('warning', 'Barcode sedang menunggu approval SAP dan belum dapat dibatalkan.');
                return;
            }

            if (!matdok && approvalStatus !== 'rejected') {
                showToastNotification('error', 'Material document tidak tersedia!');
                return;
            }

            // Simpan data untuk konfirmasi
            cancelBarcodeData = {
                barcodeType,
                prosesId,
                barcodeId,
                matdok,
                itemDocument: $(this).data('item-document') || '',
                barcode
            };

            // Update informasi di modal
            const typeLabel = barcodeType === 'kain' ? 'Barcode Kain' :
                barcodeType === 'la' ? 'Barcode Dye Stuff' : 'Barcode AUX';
            const barcodeText = barcode ? `<strong>${barcode}</strong>` : 'barcode ini';
            const itemDoc = cancelBarcodeData.itemDocument;
            const matdokText = matdok ? `<br><small class="text-muted">Material Document: ${matdok}${itemDoc ? ' ; ' + itemDoc : ''}</small>` : '';

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

        // Pastikan scroll modal detail tetap berfungsi saat modal cancel barcode dibuka
        $('#modalConfirmCancelBarcode').on('show.bs.modal', function () {
            // Jika modal detail masih terbuka, pastikan body tetap memiliki class modal-open
            if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                // Pastikan body memiliki class modal-open untuk scroll
                if (!$('body').hasClass('modal-open')) {
                    $('body').addClass('modal-open');
                }
            }
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

        $(document).on('click', '#btnConfirmCancelBarcode', function () {
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
                success: function (r) {
                    // Tutup modal dan pastikan scroll modal detail tetap berfungsi
                    $('#modalConfirmCancelBarcode').modal('hide');
                    // Pastikan body tetap memiliki class modal-open jika modal detail masih terbuka
                    if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                        // Restore modal-open class dan padding untuk scroll modal detail
                        if (!$('body').hasClass('modal-open')) {
                            $('body').addClass('modal-open');
                        }
                        // Pastikan overflow hidden hanya untuk modal yang sedang aktif
                        const modalBackdrop = $('.modal-backdrop');
                        if (modalBackdrop.length > 1) {
                            // Jika ada multiple backdrop, hapus yang terakhir (dari modal cancel)
                            modalBackdrop.last().remove();
                        }
                    }

                    if (r && r.status === 'success') {
                        // Show success message
                        showToastNotification('success', 'Barcode berhasil di-cancel!');

                        // Refresh barcode list di modal detail proses jika masih terbuka
                        // Refresh barcode list di modal detail proses jika masih terbuka
                        if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                            const currentProsesId = $('#modalDetailProses').data('proses')?.id || currentProsesForRefresh;
                            const selectedDetailId = $('#modalDetailProses').data('detailProsesId') || currentDetailForRefresh || '';
                            if (currentProsesId && window.loadBarcodesIntoDetailModal) {
                                window.loadBarcodesIntoDetailModal(currentProsesId, selectedDetailId);
                            }
                        }
                    } else {
                        const errorMsg = 'Cancel barcode gagal: ' + (r && r.message ? r.message :
                            'Unknown error');
                        showToastNotification('error', errorMsg);
                    }
                },
                error: function (xhr) {
                    // Tutup modal dan pastikan scroll modal detail tetap berfungsi
                    $('#modalConfirmCancelBarcode').modal('hide');
                    // Pastikan body tetap memiliki class modal-open jika modal detail masih terbuka
                    if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                        // Restore modal-open class dan padding untuk scroll modal detail
                        if (!$('body').hasClass('modal-open')) {
                            $('body').addClass('modal-open');
                        }
                        // Pastikan overflow hidden hanya untuk modal yang sedang aktif
                        const modalBackdrop = $('.modal-backdrop');
                        if (modalBackdrop.length > 1) {
                            // Jika ada multiple backdrop, hapus yang terakhir (dari modal cancel)
                            modalBackdrop.last().remove();
                        }
                    }
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
                complete: function () {
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
        $('#modalConfirmCancelBarcode').on('hidden.bs.modal', function () {
            cancelBarcodeData = null;
            $('#btnConfirmCancelBarcode').prop('disabled', false).html(
                '<i class="fas fa-check mr-1"></i>Ya, Cancel');
            $('#btnCancelCancelBarcode').prop('disabled', false);
            $('#cancelBarcodeLoading').hide();

            // Pastikan scroll modal detail tetap berfungsi setelah modal cancel ditutup
            // Jika modal detail masih terbuka, pastikan body tetap memiliki class modal-open
            if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                // Restore modal-open class untuk memastikan scroll berfungsi
                if (!$('body').hasClass('modal-open')) {
                    $('body').addClass('modal-open');
                }
                // Pastikan hanya ada satu backdrop (dari modal detail)
                const modalBackdrop = $('.modal-backdrop');
                if (modalBackdrop.length > 1) {
                    // Hapus backdrop tambahan yang mungkin tersisa dari modal cancel
                    modalBackdrop.last().remove();
                }
                // Pastikan padding-right di body diatur dengan benar untuk scroll
                const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
                if (scrollbarWidth > 0) {
                    $('body').css('padding-right', scrollbarWidth + 'px');
                }
            }
        });

        window.filterOptionsByMode = @json($filterOptions ?? []);
        window.allMesins = @json($mesins ?? []);

        function updateFilterModalDropdowns(mode) {
            const currentMode = mode || window.dashboardViewMode || 'produksi';
            const isProd = currentMode === 'produksi';
            const optionsData = (window.filterOptionsByMode && window.filterOptionsByMode[currentMode]) ? window.filterOptionsByMode[currentMode] : (window.filterOptionsByMode ? window.filterOptionsByMode.produksi : {});

            // Update badge dan label mode otomatis di dalam modal filter
            if (isProd) {
                $('#filter-modal-active-badge').html('<i class="fas fa-industry mr-1"></i> Mode Production').removeClass('text-secondary').addClass('text-primary');
                $('#filter-modal-current-mode-label').removeClass('text-secondary').addClass('text-primary').text('Mode Production (Proses Berjalan / Belum Selesai)');
            } else {
                $('#filter-modal-active-badge').html('<i class="fas fa-history mr-1"></i> Mode History').removeClass('text-primary').addClass('text-secondary');
                $('#filter-modal-current-mode-label').removeClass('text-primary').addClass('text-secondary').text('Mode History (Proses Selesai)');
            }

            if (!optionsData) return;

            const fieldMapping = {
                '#filter_modal_customer': optionsData.customers || [],
                '#filter_modal_marketing': optionsData.marketings || [],
                '#filter_modal_warna': optionsData.warnas || [],
                '#filter_modal_kategori_warna': optionsData.kategori_warnas || [],
                '#filter_modal_kode_warna': optionsData.kode_warnas || [],
                '#filter_modal_hfeel': optionsData.hfeels || [],
                '#filter_modal_gramasi': optionsData.gramasis || [],
                '#filter_modal_no_op': optionsData.no_ops || [],
                '#filter_modal_no_partai': optionsData.no_partais || [],
                '#filter_modal_konstruksi': optionsData.konstruksis || [],
                '#filter_modal_kode_material': optionsData.kode_materials || []
            };

            // Update dropdown detail proses
            Object.keys(fieldMapping).forEach(function (selector) {
                const $sel = $(selector);
                if (!$sel.length) return;
                const currentVals = ($sel.val() || []).map(String);
                const list = (fieldMapping[selector] || []).map(String);

                // Gabungkan list unik dan pastikan nilai yang sedang dipilih user tidak hilang
                const mergedList = Array.from(new Set([...currentVals, ...list])).filter(Boolean);

                $sel.empty();
                mergedList.forEach(function (item) {
                    const isSelected = currentVals.includes(String(item));
                    $sel.append(new Option(item, item, isSelected, isSelected));
                });

                if ($sel.hasClass('select2-hidden-accessible')) {
                    $sel.trigger('change.select2');
                }
            });

            // Update dropdown mesin
            const $mesinSel = $('#filter_modal_mesin');
            if ($mesinSel.length && window.allMesins) {
                const currentMesinVals = ($mesinSel.val() || []).map(String);
                const activeMesinIds = (optionsData.mesin_ids || []).map(String);

                $mesinSel.empty();
                window.allMesins.forEach(function (m) {
                    const mIdStr = String(m.id);
                    const isSelected = currentMesinVals.includes(mIdStr);
                    const isRelevant = activeMesinIds.includes(mIdStr);

                    const optText = isRelevant ? m.jenis_mesin : `${m.jenis_mesin} (0 proses)`;
                    $mesinSel.append(new Option(optText, m.id, isSelected, isSelected));
                });

                if ($mesinSel.hasClass('select2-hidden-accessible')) {
                    $mesinSel.trigger('change.select2');
                }
            }
        }

        $(document).ready(function () {
            // Inisialisasi Select2 di dalam Modal Filter Dashboard saat modal dibuka
            $('#modalFilterDashboard').on('show.bs.modal', function () {
                const activeMode = window.dashboardViewMode || 'produksi';
                updateFilterModalDropdowns(activeMode);
            });

            $('#modalFilterDashboard').on('shown.bs.modal', function () {
                $(this).find('.select2-dashboard-filter').each(function () {
                    const $select = $(this);
                    if (!$select.hasClass('select2-hidden-accessible')) {
                        $select.select2({
                            dropdownParent: $('#modalFilterDashboard'),
                            placeholder: $select.data('placeholder') || '-- Pilih --',
                            allowClear: true,
                            width: '100%',
                            closeOnSelect: false
                        });
                    }
                });
            });

            // Tombol Pilih Semua per kriteria di Modal Filter
            $(document).on('click', '.btn-filter-select-all', function (e) {
                e.preventDefault();
                const target = $(this).data('target');
                const $select = $(target);
                if ($select.length) {
                    const allVals = $select.find('option').map(function () {
                        return $(this).val();
                    }).get();
                    $select.val(allVals).trigger('change');
                }
            });

            // Tombol Clear per kriteria di Modal Filter
            $(document).on('click', '.btn-filter-clear', function (e) {
                e.preventDefault();
                const target = $(this).data('target');
                const $select = $(target);
                if ($select.length) {
                    $select.val(null).trigger('change');
                }
            });

            // Tombol Kosongkan Semua Pilihan di Modal Filter
            $('#btn-clear-all-filter-inputs').on('click', function () {
                $('#modalFilterDashboard .select2-dashboard-filter').val(null).trigger('change');
            });
        });

        // Toggle history per mesin (hanya berlaku di Mode Produksi)
        $(document).on('click', '.btn-toggle-history', function () {
            if (window.dashboardViewMode === 'history') return;
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

        // Toggle Mode Produksi / History
        (function () {
            const MODE_KEY = 'dashboard_view_mode';

            function getMode() {
                return localStorage.getItem(MODE_KEY) || 'produksi';
            }
            function setMode(mode) {
                localStorage.setItem(MODE_KEY, mode);
                window.dashboardViewMode = mode;
            }

            function applyMode(mode) {
                const isProduksi = mode === 'produksi';

                $('[data-section="produksi"]').css('display', isProduksi ? '' : 'none');
                $('[data-section="history"]').each(function () {
                    const $wrapper = $(this);
                    const $container = $wrapper.find('.proses-history-container');
                    const $btn = $wrapper.find('.btn-toggle-history');
                    if (isProduksi) {
                        $wrapper.hide();
                    } else {
                        $wrapper.show();
                        $btn.hide();
                        $container.show();
                    }
                });

                $('#mode-produksi-btn, #mode-history-btn').removeClass('active btn-primary btn-outline-primary btn-outline-secondary')
                    .addClass('btn-outline-secondary');
                if (isProduksi) {
                    $('#mode-produksi-btn').addClass('active btn-primary').removeClass('btn-outline-secondary');
                } else {
                    $('#mode-history-btn').addClass('active btn-primary').removeClass('btn-outline-secondary');
                }

                if (typeof updateFilterModalDropdowns === 'function') {
                    updateFilterModalDropdowns(mode);
                }
            }

            $('#mode-produksi-btn, #mode-history-btn').on('click', function () {
                const mode = $(this).data('mode');
                setMode(mode);
                applyMode(mode);
            });

            window.dashboardViewMode = getMode();
            applyMode(window.dashboardViewMode);

            window.applyDashboardMode = function () {
                applyMode(getMode());
            };
        })();

        // Handler tombol Resync Sinyal IoT (Address 100, 103, 105) ke seluruh PLC
        $('#btn-resync-iot').on('click', function () {
            const $btn = $(this);
            const $icon = $('#icon-resync-iot');

            Swal.fire({
                title: 'Resync Sinyal IoT?',
                text: 'Sistem akan membersihkan cache dan memaksa penulisan ulang seluruh register sinyal (100, 103, 105) ke semua PLC.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-sync-alt mr-1"></i> Ya, Sinkronkan!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) return;

                $btn.prop('disabled', true);
                $icon.addClass('fa-spin');

                $.ajax({
                    url: '{{ url("/dashboard/resync-iot") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    dataType: 'json',
                    success: function (res) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message || 'Sinyal IoT berhasil disinkronkan ulang ke seluruh PLC.',
                            timer: 3000,
                            timerProgressBar: true,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false
                        });
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Gagal melakukan resync sinyal IoT.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: msg
                        });
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                        $icon.removeClass('fa-spin');
                    }
                });
            });
        });


        // --- DASHBOARD AUTO-SCROLL REVERSE (BOUNCE) LOGIC ---
        $(document).ready(function () {
            const container = document.getElementById('machines-container');
            if (!container || container.dataset.autoscrollInit) return;
            container.dataset.autoscrollInit = "true";
            function getMachinesPerPage() {
                if (!container) return 5;
                const containerWidth = container.clientWidth;
                const GAP = 8;
                const TARGET_CARD_WIDTH = 280;
                let count = Math.floor((containerWidth + GAP) / (TARGET_CARD_WIDTH + GAP));
                count = Math.max(1, Math.min(count, 15));
                container.style.setProperty('--cols-per-page', count);
                return count;
            }

            // INSTANT Layout Sync: Set columns BEFORE any timers to avoid "flash of 5 machines"
            getMachinesPerPage();

            // Use setTimeout to ensure machines have been rendered and sized for secondary logic (scrolling)
            setTimeout(function () {
                const $container = $(container);

                const checkScroll = () => {
                    const scrollWidth = container.scrollWidth;
                    const clientWidth = container.clientWidth;
                    return scrollWidth > clientWidth + 5; // 5px threshold
                };

                if (!checkScroll()) {
                    console.log('Dashboard content fits on screen. Running restricted setup.');
                    // Don't RETURN here, so event listeners for manual wheel/interaction still attach
                }

                let currentPageIndex = 0; // State to track current group of machines
                let isPaused = false;
                let isModalOpen = false;
                let isTransitioning = false;
                const SLIDE_DELAY = 30000; // 30 seconds per slide

                // Sync scroll position early
                syncScrollToIndex();

                // Listen for any Bootstrap modal opening
                $(document).on('show.bs.modal', '.modal', function () {
                    isModalOpen = true;
                    stopAutoSlide();
                });

                $(document).on('hidden.bs.modal', '.modal', function () {
                    // Check if there are ANY modals still open
                    if ($('.modal.show').length === 0) {
                        isModalOpen = false;
                        handleInteraction(); // Resume after a brief delay
                    }
                });
                // getMachinesPerPage is now defined outside to be available immediately


                function syncScrollToIndex() {
                    const columns = container.querySelectorAll('.machine-column');
                    const machinesPerPage = getMachinesPerPage();
                    const targetIndex = currentPageIndex * machinesPerPage;
                    if (columns[targetIndex]) {
                        container.scrollLeft = columns[targetIndex].offsetLeft;
                    }
                }

                function changePage(direction) {
                    if (isTransitioning) return;
                    // Don't auto-slide if paused or modal is open
                    if ((isPaused || isModalOpen) && direction === 1 && !arguments[1]) return;

                    const allColumns = container.querySelectorAll('.machine-column');
                    const realColumns = container.querySelectorAll('.machine-column:not(.placeholder-column)');

                    const totalMachines = allColumns.length;
                    const realMachines = realColumns.length;
                    const machinesPerPage = getMachinesPerPage();

                    // Determine max pages based on REAL machines
                    const totalPages = Math.ceil(realMachines / machinesPerPage);
                    const maxPages = totalPages - 1;

                    if (direction === 1) {
                        // GO FORWARD
                        currentPageIndex++;
                        if (currentPageIndex > maxPages) {
                            currentPageIndex = 0;
                        }
                    } else if (direction === -1) {
                        // GO BACKWARD
                        currentPageIndex--;
                        if (currentPageIndex < 0) {
                            currentPageIndex = maxPages;
                        }
                    }

                    // START TRANSITION
                    isTransitioning = true;
                    $container.addClass('fade-out');

                    setTimeout(() => {
                        syncScrollToIndex();

                        setTimeout(() => {
                            $container.removeClass('fade-out');
                            // Unlock after fade-in is mostly done
                            setTimeout(() => {
                                isTransitioning = false;
                            }, 500);
                        }, 50);
                    }, 500);
                }

                function startAutoSlide() {
                    if (!checkScroll()) return; // Don't start timer if content fits
                    stopAutoSlide();
                    slideTimer = setInterval(() => {
                        changePage(1);
                    }, SLIDE_DELAY);
                }

                function stopAutoSlide() {
                    if (slideTimer) clearInterval(slideTimer);
                }

                // Initial start
                let slideTimer = null; // Defined here to avoid immediate errors
                startAutoSlide();

                function handleInteraction() {
                    isPaused = true;
                    stopAutoSlide(); // Suspend timer during interaction

                    if (container._resumeTimer) clearTimeout(container._resumeTimer);

                    container._resumeTimer = setTimeout(() => {
                        isPaused = false;
                        if (!isModalOpen) startAutoSlide(); // Resume timer only if modal closed
                    }, 500); // Resume after 0.5 seconds of inactivity
                }

                // Handle Floating Buttons
                $('#btn-slide-prev').on('click', function () {
                    handleInteraction();
                    changePage(-1, true);
                });

                $('#btn-slide-next').on('click', function () {
                    handleInteraction();
                    changePage(1, true);
                });

                // General activity tracking
                $container.on('mousedown touchstart mousemove', function () {
                    handleInteraction();
                });

                // Page visibility
                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        isPaused = true;
                    } else {
                        handleInteraction();
                    }
                });

                // Resize check
                function handleResize() {
                    if (!checkScroll()) {
                        isPaused = true;
                        container.scrollLeft = 0;
                        currentPageIndex = 0;
                    } else {
                        // Use rAF to ensure layout has settled before snapping
                        requestAnimationFrame(() => {
                            syncScrollToIndex();
                        });
                        handleInteraction();
                    }
                }

                window.addEventListener('resize', handleResize);
                document.addEventListener('fullscreenchange', handleResize);
                document.addEventListener('webkitfullscreenchange', handleResize);
                document.addEventListener('mozfullscreenchange', handleResize);
                document.addEventListener('MSFullscreenChange', handleResize);
            }, 1500);
        });
    </script>

@endsection