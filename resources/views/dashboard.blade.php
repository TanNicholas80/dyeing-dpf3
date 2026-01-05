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
                        <button id="add-card-btn" class="btn btn-success" style="font-weight:bold;" data-toggle="modal"
                            data-target="#modalProses">
                            + Tambah Proses
                        </button>
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

                // --- Fungsi helper untuk menentukan gradient dan warna teks otomatis ---
                function getGradient($bg)
                {
                $bg = trim($bg);
                $map = [
                // Abu (belum mulai)
                '#757575' => 'linear-gradient(180deg, #bdbdbd 0%, #757575 100%)',
                // Kuning (menunggu approval perubahan)
                '#ffeb3b' => 'linear-gradient(180deg, #fff9c4 0%,rgb(183, 168, 33) 60%,rgb(202, 161, 57) 100%)',
                // Biru (berjalan)
                '#002b80' => 'linear-gradient(180deg, #6dd5ed 0%, #2193b0 60%, #002b80 100%)',
                // Hijau (selesai normal)
                '#00c853' => 'linear-gradient(180deg, #b2f7c1 0%, #56ab2f 60%, #378a1b 100%)',
                // Merah (selesai overtime)
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
                                <div class="card-dropzone" data-machine="{{ $mesin->jenis_mesin }}" data-mesin-id="{{ $mesin->id }}"
                                    style="background: #fff; padding: 0; min-height: 0;">
                                    <div style="height: 2px; background: #fff;"></div>
                                    @php
                                    $prosesMesin = $prosesList->where('mesin_id', $mesin->id);
                                    @endphp
                                    @foreach ($prosesMesin as $proses)
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
                                    $hasBarcodeKain =
                                    isset($proses->barcodeKains) &&
                                    is_iterable($proses->barcodeKains)
                                    ? collect($proses->barcodeKains)
                                    ->where('cancel', false)
                                    ->count() > 0
                                    : isset($proses->barcode_kain) && $proses->barcode_kain;
                                    // D: hijau jika ada minimal 1 barcode LA (cancel=false)
                                    $hasBarcodeLa =
                                    isset($proses->barcodeLas) &&
                                    is_iterable($proses->barcodeLas)
                                    ? collect($proses->barcodeLas)
                                    ->where('cancel', false)
                                    ->count() > 0
                                    : isset($proses->barcode_la) && $proses->barcode_la;
                                    // A: hijau jika ada minimal 1 barcode AUX (cancel=false)
                                    $hasBarcodeAux =
                                    isset($proses->barcodeAuxs) &&
                                    is_iterable($proses->barcodeAuxs)
                                    ? collect($proses->barcodeAuxs)
                                    ->where('cancel', false)
                                    ->count() > 0
                                    : isset($proses->barcode_aux) && $proses->barcode_aux;
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
                                    if (isset($proses->approvals) && is_iterable($proses->approvals)) {
                                    // Cek pending approval FM untuk edit/delete/move
                                    $hasPendingChange = collect($proses->approvals)->contains(function ($appr) {
                                    return $appr->status === 'pending'
                                    && $appr->type === 'FM'
                                    && in_array($appr->action, ['edit_cycle_time', 'delete_proses', 'move_machine']);
                                    });
                                    // Cek pending approval VP untuk Reproses
                                    if ($proses->jenis === 'Reproses') {
                                    $hasPendingReprocessApproval = collect($proses->approvals)->contains(function ($appr) {
                                    return $appr->status === 'pending'
                                    && $appr->type === 'VP'
                                    && $appr->action === 'create_reprocess';
                                    });
                                    }
                                    }
                                    if ($hasPendingChange || $hasPendingReprocessApproval) {
                                    $bg = '#ffeb3b'; // kuning untuk menandai ada perubahan yang menunggu approval
                                    } elseif ($proses->jenis === 'Maintenance') {
                                    $bg = '#757575'; // selalu abu-abu untuk Maintenance
                                    } elseif (!$proses->mulai) {
                                    $bg = '#757575'; // abu2
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
                                    $bg = '#e53935'; // merah
                                    } else {
                                    $bg = '#00c853'; // hijau
                                    }
                                    } else {
                                    if (
                                    !$proses->barcode_kain ||
                                    !$proses->barcode_la ||
                                    !$proses->barcode_aux
                                    ) {
                                    $bg = '#e53935'; // merah
                                    } else {
                                    $bg = '#002b80'; // biru
                                    }
                                    }
                                    $gradient = getGradient($bg);
                                    // Tambahkan inisialisasi variabel agar tidak undefined
                                    $estimasi_selesai = null;
                                    if ($proses->mulai && !$proses->selesai && $proses->cycle_time) {
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
                                    <div class="status-card draggable" draggable="{{ ($bg === '#757575' && !$proses->mulai && !$hasPendingChange && !$hasPendingReprocessApproval) ? 'true' : 'false' }}"
                                        style="background: {{ $gradient }}; background-repeat: no-repeat; background-size: cover; border-radius: 0; color: #fff; margin: 5px 0 0 0; padding: 2px 2px; cursor: {{ ($bg === '#757575' && !$proses->mulai && !$hasPendingChange && !$hasPendingReprocessApproval) ? 'grab' : 'default' }}; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"
                                        data-proses='@json($proses)' data-can-move="{{ ($bg === '#757575' && !$proses->mulai && !$hasPendingChange && !$hasPendingReprocessApproval) ? '1' : '0' }}" data-has-pending-reprocess="{{ $hasPendingReprocessApproval ? '1' : '0' }}">
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
                                                $color === 'green' ? '#d4f8e8' : '#ffb3b3';
                                                $blockBorder =
                                                $color === 'green' ? '#43a047' : '#c62828';
                                                }
                                                @endphp
                                                <span
                                                    style="display: inline-block; background: {{ $blockBg }}; color: #111; font-weight: bold; font-size: 22px; padding: 2px 10px; border-radius: 6px; border: 2.5px solid {{ $blockBorder }}; box-shadow: 0 1px 4px rgba(0,0,0,0.10); letter-spacing: 1px; text-shadow: 0 1px 2px #fff8;">
                                                    {{ $b }}
                                                </span>
                                                @endforeach
                                            </div>
                                            <div style="flex: 1; text-align: right;">
                                                <div class="status-light"
                                                    style="width: 24px; height: 24px; border-radius: 50%; background: {{ $light == 'green' ? '#00ff1a' : '#ff2a2a' }}; display: inline-block; border: 3px solid #fff; box-shadow: 0 0 0 0 transparent; transition: background 0.2s;">
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Body --}}
                                        <div class="card-body"
                                            style="text-align: center; font-size: 12px; padding: 2px 10px; color: #fff;">
                                            <div class="card-id"
                                                style="font-weight: bold; color: #111; font-size: 22px; letter-spacing: 2px; text-shadow: 0 1px 4px #fff8;">
                                                {{ $proses->no_op ? $proses->no_op : 'MAINTENANCE' }}
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
                                                    $mulai = \Carbon\Carbon::parse($proses->mulai);
                                                    $selesai = \Carbon\Carbon::parse(
                                                    $proses->selesai,
                                                    );
                                                    $showTime = detikKeWaktu(
                                                    max(
                                                    0,
                                                    $mulai->diffInSeconds($selesai, false),
                                                    ),
                                                    );
                                                    } elseif ($proses->mulai && !$proses->selesai) {
                                                    $now = \Carbon\Carbon::now();
                                                    $mulai = \Carbon\Carbon::parse($proses->mulai);
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
                                            <div class="card-info"
                                                style="font-size: 9px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
                                                <div>{{ $proses->warna ? $proses->warna : 'Warna' }} -
                                                    {{ $proses->kategori_warna ? $proses->kategori_warna : 'Kategori' }}
                                                    -
                                                    {{ $proses->kode_warna ? $proses->kode_warna : 'Kode' }}
                                                </div>
                                                <div>
                                                    {{ $proses->konstruksi ? $proses->konstruksi : 'Konstruksi' }}
                                                </div>
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
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    <!-- Modal Tambah Proses -->
    <div class="modal fade" id="modalProses" tabindex="-1" aria-labelledby="modalProsesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
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
                                    <select name="jenis" class="form-control" required>
                                        <option value="Produksi" selected>Produksi</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Reproses">Reproses</option>
                                    </select>
                                </div>
                            </div>

                            <!-- No OP -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">No. OP</label>
                                    <select name="no_op" id="no_op" class="form-control select2"
                                        style="width: 100%;" required>
                                        <option value="" disabled>-- Pilih No. OP --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- No Partai -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">No. Partai</label>
                                    <select name="no_partai" id="no_partai" class="form-control select2"
                                        style="width: 100%;" required>
                                        <option value="" disabled>-- Pilih No. Partai --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Mesin -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Mesin</label>
                                    <select name="mesin_id" id="mesin_id" class="form-control" required>
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

                            <!-- Item OP (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Item OP</label>
                                    <input type="text" name="item_op" class="form-control auto-field" readonly>
                                </div>
                            </div>

                            <!-- Kode Material (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Kode Material</label>
                                    <input type="text" name="kode_material" class="form-control auto-field"
                                        readonly>
                                </div>
                            </div>

                            <!-- Konstruksi (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Konstruksi</label>
                                    <input type="text" name="konstruksi" class="form-control auto-field" readonly>
                                </div>
                            </div>

                            <!-- Gramasi (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Gramasi</label>
                                    <input type="text" name="gramasi" class="form-control auto-field" readonly>
                                </div>
                            </div>

                            <!-- Lebar (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Lebar</label>
                                    <input type="text" name="lebar" class="form-control auto-field" readonly>
                                </div>
                            </div>

                            <!-- Hand Feel (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Hand Feel</label>
                                    <input type="text" name="hfeel" class="form-control auto-field" readonly>
                                </div>
                            </div>

                            <!-- Warna (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Warna</label>
                                    <input type="text" name="warna" class="form-control auto-field" readonly>
                                </div>
                            </div>

                            <!-- Kode Warna (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Kode Warna</label>
                                    <input type="text" name="kode_warna" class="form-control auto-field" readonly>
                                </div>
                            </div>

                            <!-- Kategori Warna (readonly) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Kategori Warna</label>
                                    <input type="text" name="kategori_warna" class="form-control auto-field"
                                        readonly>
                                </div>
                            </div>

                            <!-- QTY (readonly, 2 digit koma) -->
                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Quantity</label>
                                    <input type="text" name="qty" class="form-control auto-field" readonly>
                                </div>
                            </div>

                            <div class="col-md-6 hide-if-maintenance">
                                <div class="form-group">
                                    <label class="form-label fw-semibold">Roll</label>
                                    <input type="text" name="roll" class="form-control auto-field" readonly>
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
                        <button type="button" class="btn btn-primary btn-edit-proses mr-2">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button type="button" class="btn btn-warning btn-move-proses mr-2">
                            <i class="fas fa-random mr-1"></i>Pindah Mesin
                        </button>
                        <button type="button" class="btn btn-danger btn-delete-proses">
                            <i class="fas fa-trash mr-1"></i>Hapus
                        </button>
                    </div>
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


</div>
@endsection

@section('scripts')
{{-- Script drag & drop dan select2 dashboard tanpa log console --}}
<script>
    // Simpan data mesin ke variabel global untuk digunakan di modal pindah mesin
    window.mesinsData = @json($mesins->map(function($m) { return ['id' => $m->id, 'jenis_mesin' => $m->jenis_mesin]; }));
    document.addEventListener('DOMContentLoaded', () => {
        const draggables = document.querySelectorAll('.draggable');
        const containers = document.querySelectorAll('.card-dropzone');
        let draggedCard = null;
        let sourceMesinId = null;
        let targetMesinId = null;

        draggables.forEach(draggable => {
            draggable.addEventListener('dragstart', (e) => {
                const canMove = draggable.getAttribute('data-can-move') === '1';
                if (!canMove) {
                    e.preventDefault();
                    return false;
                }
                
                // Validasi tambahan: cek apakah proses sudah dimulai atau ada pending approval
                const proses = $(draggable).data('proses');
                if (proses && (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '')) {
                    e.preventDefault();
                    return false;
                }
                // Cek pending approval VP untuk Reproses
                const hasPendingReprocess = draggable.getAttribute('data-has-pending-reprocess') === '1';
                if (hasPendingReprocess) {
                    e.preventDefault();
                    return false;
                }
                
                draggable.classList.add('dragging');
                draggedCard = draggable;
                if (proses && proses.mesin_id) {
                    sourceMesinId = proses.mesin_id;
                }
                // Store original position dan next sibling untuk fallback
                const parent = draggedCard.parentElement;
                draggedCard.setAttribute('data-original-parent', parent.getAttribute('data-mesin-id'));
                const nextSibling = draggedCard.nextSibling;
                if (nextSibling) {
                    draggedCard.setAttribute('data-original-next-sibling-id', nextSibling.id || '');
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
                }
            });
        });

        containers.forEach(container => {
            container.addEventListener('dragover', e => {
                e.preventDefault();
                const dragging = document.querySelector('.draggable.dragging');
                if (!dragging) return;
                
                const canMove = dragging.getAttribute('data-can-move') === '1';
                if (!canMove) return;

                const targetMesin = container.getAttribute('data-mesin-id');
                targetMesinId = targetMesin ? parseInt(targetMesin) : null;

                const afterElement = getDragAfterElement(container, e.clientY);
                if (afterElement == null) {
                    container.appendChild(dragging);
                } else {
                    container.insertBefore(dragging, afterElement);
                }
            });

            container.addEventListener('drop', (e) => {
                e.preventDefault();
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
                if (proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '') {
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
                const hasPendingReprocess = dragging.getAttribute('data-has-pending-reprocess') === '1';
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

                const newMesinId = parseInt(container.getAttribute('data-mesin-id'));
                const oldMesinId = proses.mesin_id;

                // Jika dipindah ke mesin yang sama, kembalikan ke posisi semula
                if (newMesinId === oldMesinId) {
                    restoreCardToOriginalPosition(dragging);
                    dragging.classList.remove('dragging');
                    return;
                }

                // Simpan data untuk modal konfirmasi
                const originalParentId = dragging.getAttribute('data-original-parent');
                const originalParent = document.querySelector(`[data-mesin-id="${originalParentId}"]`);
                const originalNextSibling = dragging._originalNextSibling;
                
                // Cari nama mesin sumber dan tujuan
                const sourceMesinName = originalParent ? originalParent.closest('.machine-column').querySelector('.machine-header').textContent.trim() : 'Mesin Sumber';
                const targetMesinName = container.closest('.machine-column').querySelector('.machine-header').textContent.trim();
                
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

                // Tampilkan modal konfirmasi
                const infoText = `Apakah Anda yakin ingin memindahkan proses <strong>${proses.no_op || 'MAINTENANCE'}</strong> dari <strong>${sourceMesinName}</strong> ke <strong>${targetMesinName}</strong>?`;
                $('#confirmMoveDragDropInfo').html(infoText);
                $('#modalConfirmMoveDragDrop').modal('show');
            });
        });

        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.draggable:not(.dragging)')];
            return draggableElements.reduce((closest, child) => {
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
            if (!originalParentId) return;
            
            const originalParent = document.querySelector(`[data-mesin-id="${originalParentId}"]`);
            if (!originalParent) return;
            
            // Jika card sudah di parent yang benar, tidak perlu restore
            if (card.parentElement === originalParent) {
                return;
            }
            
            const originalNextSibling = card._originalNextSibling;
            // Cek apakah next sibling masih ada dan masih di parent yang sama
            if (originalNextSibling && 
                originalNextSibling.parentElement === originalParent && 
                originalParent.contains(originalNextSibling)) {
                originalParent.insertBefore(card, originalNextSibling);
            } else {
                // Jika next sibling tidak ada atau sudah pindah, append di akhir
                originalParent.appendChild(card);
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

        const { dragging, proses, newMesinId, originalParentId, originalParent, originalNextSibling } = moveData;

        // Disable tombol untuk mencegah double click
        $('#btnConfirmMoveDragDrop').prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Memproses...');
        $('#btnCancelMoveDragDrop').prop('disabled', true);

        // Tandai bahwa sedang dalam proses AJAX
        dragging.setAttribute('data-ajax-pending', 'true');

        // Kirim request ke server untuk membuat approval
        $.ajax({
            url: `/proses/${proses.id}/move`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
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
                const originalParent = document.querySelector(`[data-mesin-id="${originalParentId}"]`);
                if (originalParent && dragging.parentElement !== originalParent) {
                    const originalNextSibling = dragging._originalNextSibling;
                    if (originalNextSibling && originalNextSibling.parentElement === originalParent) {
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
                $('#btnConfirmMoveDragDrop').prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Ya, Pindahkan');
                $('#btnCancelMoveDragDrop').prop('disabled', false);
                window.pendingMoveData = null;
            }
        });
    });

    // Handler cancel konfirmasi pindah mesin (Drag & Drop)
    $(document).on('click', '#btnCancelMoveDragDrop', function() {
        const moveData = window.pendingMoveData;
        if (moveData && moveData.dragging) {
            moveData.dragging.classList.remove('dragging');
            const originalParentId = moveData.dragging.getAttribute('data-original-parent');
            const originalParent = document.querySelector(`[data-mesin-id="${originalParentId}"]`);
            if (originalParent && moveData.dragging.parentElement !== originalParent) {
                const originalNextSibling = moveData.dragging._originalNextSibling;
                if (originalNextSibling && originalNextSibling.parentElement === originalParent) {
                    originalParent.insertBefore(moveData.dragging, originalNextSibling);
                } else {
                    originalParent.appendChild(moveData.dragging);
                }
            }
        }
        window.pendingMoveData = null;
        $('#modalConfirmMoveDragDrop').modal('hide');
    });

    // Handler saat modal ditutup (untuk memastikan card dikembalikan jika modal ditutup dengan cara lain)
    $('#modalConfirmMoveDragDrop').on('hidden.bs.modal', function() {
        const moveData = window.pendingMoveData;
        if (moveData && moveData.dragging) {
            // Pastikan dragging class dihapus
            moveData.dragging.classList.remove('dragging');
            
            // Kembalikan card ke posisi asli jika belum dikembalikan
            const originalParentId = moveData.dragging.getAttribute('data-original-parent');
            const originalParent = document.querySelector(`[data-mesin-id="${originalParentId}"]`);
            if (originalParent && moveData.dragging.parentElement !== originalParent) {
                const originalNextSibling = moveData.dragging._originalNextSibling;
                if (originalNextSibling && originalNextSibling.parentElement === originalParent) {
                    originalParent.insertBefore(moveData.dragging, originalNextSibling);
                } else {
                    originalParent.appendChild(moveData.dragging);
                }
            }
            
            // Reset tombol
            $('#btnConfirmMoveDragDrop').prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Ya, Pindahkan');
            $('#btnCancelMoveDragDrop').prop('disabled', false);
        }
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
        // Ambil data dropdown dari server untuk Mesin dan No Partai
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
                // Isi dropdown no_partai
                const partaiSelect = document.getElementById('no_partai');
                partaiSelect.innerHTML = '<option value="">-- Pilih No. Partai --</option>';
                if (data.no_partais) {
                    data.no_partais.forEach(partai => {
                        const opt = document.createElement('option');
                        opt.value = partai;
                        opt.textContent = partai;
                        partaiSelect.appendChild(opt);
                    });
                }
                // Destroy & init select2 setelah data masuk (No Partai saja)
                if ($.fn.select2) {
                    if ($('#no_partai').hasClass('select2-hidden-accessible')) {
                        $('#no_partai').select2('destroy');
                    }
                    $('#no_partai').select2({
                        dropdownParent: $('#modalProses'),
                        placeholder: '-- Pilih No. Partai --',
                        allowClear: true
                    });
                }
            });
        // Inisialisasi select2 No. OP hanya sekali, logic seperti sebelumnya
        if ($.fn.select2) {
            $('#no_op').select2({
                dropdownParent: $('#modalProses'),
                placeholder: '-- Pilih No. OP --',
                minimumInputLength: 3,
                ajax: {
                    url: '/api/proxy-op',
                    type: 'POST',
                    dataType: 'json',
                    delay: 500,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
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
        }
        // Event setelah user memilih No OP
        let globalOpData = [];
        $('#no_op').on('select2:select', function(e) {
            const selectedOp = e.params.data.id;
            $('.auto-field').val('');
            if ($('#no_partai').hasClass('select2-hidden-accessible')) {
                $('#no_partai').select2('destroy');
            }
            // Disable select No Partai saat proses AJAX
            $('#no_partai').prop('disabled', true);
            $('#no_partai').empty().append('<option value="">-- Pilih No. Partai --</option>');
            globalOpData = [];
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
                    globalOpData = res.raw || [];
                    const filtered = globalOpData.filter(x => (x.no_op || x.noOp || x
                        .NO_OP) == selectedOp);
                    const uniquePartai = [...new Set(filtered.map(x => x.no_partai || x
                        .noPartai || x.NO_PARTAI))].filter(Boolean);
                    if (uniquePartai.length === 0) {
                        $('#no_partai').append(
                            '<option value="">(Tidak ada partai ditemukan)</option>');
                    } else {
                        uniquePartai.forEach(partai => {
                            $('#no_partai').append(
                                `<option value="${partai}">${partai}</option>`);
                        });
                    }
                    // Re-init select2
                    $('#no_partai').select2({
                        dropdownParent: $('#modalProses'),
                        placeholder: '-- Pilih No. Partai --',
                        allowClear: false
                    });
                    $('#no_partai').val('').trigger('change');
                    $('#no_partai').prop('disabled', false);
                },
                error: function(xhr) {
                    $('#no_partai').append(
                        '<option value="">(Gagal mengambil data partai)</option>');
                    $('#no_partai').select2({
                        dropdownParent: $('#modalProses'),
                        placeholder: '-- Pilih No. Partai --',
                        allowClear: false
                    });
                    $('#no_partai').val('').trigger('change');
                    $('#no_partai').prop('disabled', false);
                }
            });
        });
        // Event setelah user memilih No Partai
        $('#no_partai').on('change', function() {
            const partai = $(this).val();
            if (!partai) {
                $('.auto-field').val('');
                return;
            }
            const op = $('#no_op').val();
            const row = globalOpData.find(x => (x.no_op || x.noOp || x.NO_OP) === op && (x.no_partai ||
                x.noPartai || x.NO_PARTAI) === partai);
            if (row) {
                $('[name="item_op"]').val(row.item_op || '');
                $('[name="kode_material"]').val(row.kode_material || '');
                $('[name="konstruksi"]').val(row.konstruksi || '');
                $('[name="gramasi"]').val(row.gramasi || '');
                $('[name="lebar"]').val(row.lebar || '');
                $('[name="hfeel"]').val(row.hfeel || '');
                $('[name="warna"]').val(row.warna || '');
                $('[name="kode_warna"]').val(row.kode_warna || '');
                $('[name="kategori_warna"]').val(row.kat_warna || '');
                // Format qty 2 digit koma
                var qtyVal = row.qty || '';
                if (qtyVal !== '' && !isNaN(qtyVal)) {
                    qtyVal = parseFloat(qtyVal).toFixed(2);
                }
                $('[name="qty"]').val(qtyVal);
                // Roll dari API
                var rollVal = row.roll || '';
                $('[name="roll"]').val(rollVal);
            } else {
                $('.auto-field').val('');
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
            return approval.status === 'pending' 
                && approval.type === 'FM' 
                && ['edit_cycle_time', 'delete_proses', 'move_machine'].includes(approval.action);
        });
    }

    // Fungsi helper untuk mengecek apakah ada pending approval VP untuk Reproses
    function hasPendingReprocessApproval(proses) {
        if (!proses || !proses.approvals || !Array.isArray(proses.approvals)) {
            return false;
        }
        if (proses.jenis !== 'Reproses') {
            return false;
        }
        return proses.approvals.some(function(approval) {
            return approval.status === 'pending' 
                && approval.type === 'VP' 
                && approval.action === 'create_reprocess';
        });
    }

    // Fungsi untuk mendapatkan informasi pending approval
    function getPendingApprovalInfo(proses) {
        if (!proses || !proses.approvals || !Array.isArray(proses.approvals)) {
            return null;
        }
        const pendingApproval = proses.approvals.find(function(approval) {
            return approval.status === 'pending' 
                && approval.type === 'FM' 
                && ['edit_cycle_time', 'delete_proses', 'move_machine'].includes(approval.action);
        });
        if (!pendingApproval) return null;
        
        const actionLabels = {
            'edit_cycle_time': 'perubahan cycle time',
            'delete_proses': 'penghapusan proses',
            'move_machine': 'pemindahan mesin'
        };
        return {
            action: pendingApproval.action,
            label: actionLabels[pendingApproval.action] || 'perubahan'
        };
    }

    // Double click card proses untuk detail
    $(document).on('dblclick', '.status-card', function() {
        const proses = $(this).data('proses');
        if (!proses) return;
        // Simpan proses aktif ke modal detail untuk kebutuhan edit/delete
        $('#modalDetailProses').data('proses', proses);
        
        // Cek apakah ada pending approval dan disable tombol jika ada
        const hasPending = hasPendingApprovalFM(proses);
        const hasPendingReprocess = hasPendingReprocessApproval(proses);
        const pendingInfo = getPendingApprovalInfo(proses);
        
        // Cek apakah proses sudah dimulai (mulai tidak null)
        const isStarted = proses.mulai !== null && proses.mulai !== undefined && proses.mulai !== '';
        
        // Disable/enable tombol action
        const $btnEdit = $('.btn-edit-proses');
        const $btnMove = $('.btn-move-proses');
        const $btnDelete = $('.btn-delete-proses');
        
        // Disable jika ada pending approval FM ATAU pending approval VP Reproses ATAU proses sudah dimulai
        if (hasPending || hasPendingReprocess || isStarted) {
            // Disable tombol
            $btnEdit.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
            $btnMove.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
            $btnDelete.prop('disabled', true).addClass('disabled').css('cursor', 'not-allowed');
            
            // Tentukan pesan tooltip berdasarkan kondisi
            let tooltipText = '';
            if (isStarted) {
                tooltipText = 'Tidak dapat melakukan aksi. Proses sudah dimulai.';
            } else if (hasPendingReprocess) {
                tooltipText = 'Tidak dapat melakukan aksi. Proses Reproses masih menunggu persetujuan VP.';
            } else if (hasPending) {
                tooltipText = pendingInfo 
                    ? `Tidak dapat melakukan aksi. Masih ada permintaan ${pendingInfo.label} yang menunggu persetujuan FM.`
                    : 'Tidak dapat melakukan aksi. Masih ada permintaan yang menunggu persetujuan FM.';
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
        // Hapus BARCODE KAINS, APPROVALS dari detail proses
        const entries = Object.entries(proses)
            .filter(([key]) => !hiddenFields.includes(key) && key !== 'barcode_kains' && key !==
                'barcode_las' && key !== 'barcode_auxs' && key !== 'mesin' && key !== 'approvals')
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
            const showScanBtn = true;
            html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode Kain';
            if (showScanBtn) {
                html +=
                    ' <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_kain" data-id="' +
                    proses.id + '" style="float:right;"><i class="fas fa-barcode"></i> Scan</button>';
            }
            html += '</th></tr>';
            html += '<tr><td colspan="4" id="barcode-kain-list">Loading...</td></tr>';
            html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode LA';
            if (showScanBtn) {
                html +=
                    ' <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_la" data-id="' +
                    proses.id + '" style="float:right;"><i class="fas fa-barcode"></i> Scan</button>';
            }
            html += '</th></tr>';
            html += '<tr><td colspan="4" id="barcode-la-list">Loading...</td></tr>';
            html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode AUX';
            if (showScanBtn) {
                html +=
                    ' <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_aux" data-id="' +
                    proses.id + '" style="float:right;"><i class="fas fa-barcode"></i> Scan</button>';
            }
            html += '</th></tr>';
            html += '<tr><td colspan="4" id="barcode-aux-list">Loading...</td></tr>';
        }
        $('#detail-proses-body').html(html);
        // Ambil barcode dari relasi dan render di modal detail proses hanya jika bukan Maintenance
        if (proses.jenis !== 'Maintenance') {
            $.ajax({
                url: '/proses/' + proses.id + '/barcodes',
                method: 'GET',
                success: function(data) {
                    function renderBarcodeGrid(barcodes, barcodeType, prosesId) {
                        // Filter barcode yang belum cancel
                        const activeBarcodes = (barcodes || []).filter(bk => !bk.cancel);
                        if (!activeBarcodes.length) {
                            return '<span style="color:#888;">Belum ada barcode.</span>';
                        }
                        let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                        activeBarcodes.forEach(function(bk, idx) {
                            html += `<div style="position:relative;flex:1 0 30%;max-width:32%;background:#f3f3f3;border-radius:6px;padding:6px 4px;margin-bottom:6px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">
                                    <span style='position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;' class='cancel-barcode-btn' data-type='${barcodeType}' data-proses='${prosesId}' data-id='${bk.id}' data-matdok='${bk.matdok}' title='Cancel barcode'>&times;</span>
                                    ${bk.barcode} ${(bk.matdok ? '<br><span style=\'font-size:11px;color:#888;\'>' + bk.matdok + '</span>' : '')}
                                </div>`;
                        });
                        html += '</div>';
                        return html;
                    }
                    $('#barcode-kain-list').html(renderBarcodeGrid(data.barcode_kain, 'kain', proses
                        .id));
                    $('#barcode-la-list').html(renderBarcodeGrid(data.barcode_la, 'la', proses.id));
                    $('#barcode-aux-list').html(renderBarcodeGrid(data.barcode_aux, 'aux', proses
                        .id));
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

        // Isi data ke Form Edit sebelum modal dibuka
        $('#formEditProses').attr('action', updateUrl);
        $('#editProsesId').val(id);
        $('#editCycleTime').val(hms);
        $('#editJenis').val(proses.jenis || '');
        $('#editNoOp').val(proses.no_op || '');
        $('#editNoPartai').val(proses.no_partai || '');
        $('#editItemOp').val(proses.item_op || '');
        $('#editKodeMaterial').val(proses.kode_material || '');
        $('#editKonstruksi').val(proses.konstruksi || '');
        $('#editGramasi').val(proses.gramasi || '');
        $('#editLebar').val(proses.lebar || '');
        $('#editHfeel').val(proses.hfeel || '');
        $('#editWarna').val(proses.warna || '');
        $('#editKodeWarna').val(proses.kode_warna || '');
        $('#editKategoriWarna').val(proses.kategori_warna || '');
        $('#editQty').val(proses.qty || '');
        $('#editRoll').val(proses.roll || '');

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
                    const mesinNama = mesin.jenis_mesin || mesin.nama || mesin.text || 'Mesin ' + mesinId;
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
                                $('#moveMesinId').append(`<option value="${mesinId}">${mesin.jenis_mesin}</option>`);
                            }
                        });
                    }
                })
                .catch(function(error) {
                    console.error('Error loading mesins:', error);
                });
        }
        
        $('#moveMesinId').val('').trigger('change');

        let infoText = 'Pilih mesin tujuan untuk memindahkan proses ini.';
        if (proses.no_op) {
            infoText += `<br><br><strong>No OP:</strong> ${proses.no_op || '-'}`;
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
            alert('Silakan pilih mesin tujuan terlebih dahulu.');
            return;
        }

        $.ajax({
            url: url,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
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

        let infoText = 'Apakah Anda yakin ingin mengajukan penghapusan proses ini?';
        if (proses.no_op || proses.no_partai) {
            infoText += `<br><br><strong>No OP:</strong> ${proses.no_op || '-'}<br>` +
                `<strong>No Partai:</strong> ${proses.no_partai || '-'}`;
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

    $(document).ready(function() {
        $('[name="jenis"]').on('change', function() {
            var isMaintenance = $(this).val() === 'Maintenance';

            // Modifikasi: Handle No OP dan No Partai (Disable jika Maintenance)
            var $noOp = $('#no_op');
            var $noPartai = $('#no_partai');

            if (isMaintenance) {
                $noOp.val(null).trigger('change').prop('disabled', true).removeAttr('required');
                $noPartai.val(null).trigger('change').prop('disabled', true).removeAttr('required');
            } else {
                $noOp.prop('disabled', false).attr('required', 'required');
                // No Partai di-enable logika-nya ada di event listener no_op select, tapi kita pastikan required-nya kembali
                $noPartai.attr('required', 'required');
            }

            // Sisa field auto-fill
            var fields = [
                'item_op', 'kode_material', 'konstruksi', 'gramasi',
                'lebar', 'hfeel', 'warna', 'kode_warna', 'kategori_warna', 'qty'
            ];
            fields.forEach(function(name) {
                var $field = $('[name="' + name + '"]');
                if (isMaintenance) {
                    $field.removeAttr('required');
                } else {
                    $field.attr('required', 'required');
                }
            });
            // Mesin dan cycle_time tetap required
            $('[name="mesin_id"]').attr('required', 'required');
            $('[name="cycle_time"]').attr('required', 'required');

            // Sembunyikan field tertentu jika Maintenance
            if (isMaintenance) {
                $('.hide-if-maintenance').hide();
            } else {
                $('.hide-if-maintenance').show();
            }
        });
        // Trigger di awal
        $('[name="jenis"]').trigger('change');
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

        // Tampilkan loading dan disable button
        $('#btnConfirmCancelBarcode').prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm mr-1"></span>Memproses...');
        $('#btnCancelCancelBarcode').prop('disabled', true);
        $('#cancelBarcodeLoading').show();

        // Request ke backend Laravel
        $.ajax({
            url: `/proses/${prosesId}/barcode/${barcodeType}/${barcodeId}/cancel`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
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
                                url: '/proses/' + currentProsesForRefresh + '/barcodes',
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
                                        let html =
                                            '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                                        activeBarcodes.forEach(function(bk, idx) {
                                            html += `<div style="position:relative;flex:1 0 30%;max-width:32%;background:#f3f3f3;border-radius:6px;padding:6px 4px;margin-bottom:6px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">
                                                    <span style='position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;' class='cancel-barcode-btn' data-type='${barcodeType}' data-proses='${prosesId}' data-id='${bk.id}' data-matdok='${bk.matdok}' title='Cancel barcode'>&times;</span>
                                                    ${bk.barcode} ${(bk.matdok ? '<br><span style=\'font-size:11px;color:#888;\'>' + bk.matdok + '</span>' : '')}
                                                </div>`;
                                        });
                                        html += '</div>';
                                        return html;
                                    }
                                    $('#barcode-kain-list').html(renderBarcodeGrid(data
                                        .barcode_kain, 'kain',
                                        currentProsesForRefresh));
                                    $('#barcode-la-list').html(renderBarcodeGrid(data
                                        .barcode_la, 'la',
                                        currentProsesForRefresh));
                                    $('#barcode-aux-list').html(renderBarcodeGrid(data
                                        .barcode_aux, 'aux',
                                        currentProsesForRefresh));
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
</script>
@endsection