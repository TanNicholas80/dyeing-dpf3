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
                        {{-- Pagination Mesin --}}

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
                    <div class="row" id="machines-container" style="margin-left:0;margin-right:0;">
                        @foreach ($mesins as $mesin)
                            <div class="machine-column">
                                <div class="col-lg col-md-2 col-sm-4 col-6" style="padding: 2px;">
                                    <div class="machine-column" style="border-radius: 0; box-shadow: none;">
                                        <div class="machine-header"
                                            style="background: #002bff; color: white; font-weight: bold; text-align: center; padding: 2px 0; font-size: 1.3rem; letter-spacing: 1px; user-select: none;">
                                            {{ $mesin->jenis_mesin }}
                                        </div>
                                        <div class="card-dropzone" data-machine="{{ $mesin->jenis_mesin }}"
                                            style="background: #fff; padding: 0; min-height: 0;">
                                            <div style="height: 2px; background: #fff;"></div>
                                            @php
                                                $prosesList = \App\Models\Proses::where('mesin_id', $mesin->id)
                                                    ->orderBy('id') // ascending: id kecil di atas
                                                    ->get();
                                            @endphp
                                            @foreach ($prosesList as $proses)
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
                                                        $hasBarcodeKain = isset($proses->barcodeKains) && is_iterable($proses->barcodeKains)
                                                            ? collect($proses->barcodeKains)->where('cancel', false)->count() > 0
                                                            : (isset($proses->barcode_kain) && $proses->barcode_kain);
                                                        $blockColors = [
                                                            $hasBarcodeKain ? 'green' : 'red',
                                                            $proses->barcode_la ? 'green' : 'red',
                                                            $proses->barcode_aux ? 'green' : 'red',
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
                                                    if ($proses->jenis === 'Maintenance') {
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
                                                <div class="status-card draggable" draggable="true"
                                                    style="background: {{ $gradient }}; background-repeat: no-repeat; background-size: cover; border-radius: 0; color: #fff; margin: 2px 0 0 0; padding: 2px 2px; cursor: grab; box-shadow: 0 2px 6px rgba(0,0,0,0.2);"
                                                    data-proses='@json($proses)'>
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
                                                            <div>{{ $proses->warna ? $proses->warna : 'Warna' }} - {{ $proses->kategori_warna ? $proses->kategori_warna : 'Kategori' }} -
                                                                {{ $proses->kode_warna ? $proses->kode_warna : 'Kode' }}</div>
                                                            <div>{{ $proses->konstruksi ? $proses->konstruksi : 'Konstruksi' }}</div>
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
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">No. OP</label>
                                        <select name="no_op" id="no_op" class="form-control select2"
                                            style="width: 100%;" required>
                                            <option value="" disabled>-- Pilih No. OP --</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- No Partai -->
                                <div class="col-md-6">
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
                                            <i class="fas fa-info-circle text-info ml-1"
                                                data-toggle="tooltip"
                                                data-placement="top"
                                                title="Isi dengan durasi proses, bukan jam selesai mesin.">
                                            </i>
                                        </label>
                                        <input type="text"
                                            name="cycle_time"
                                            class="form-control"
                                            placeholder="Jam:Menit:Detik"
                                            pattern="^[0-9]{2}:[0-9]{2}:[0-9]{2}$"
                                            title="Format durasi Jam:Menit:Detik"
                                            required>
                                    </div>
                                </div>

                                <!-- Item OP (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Item OP</label>
                                        <input type="text" name="item_op" class="form-control auto-field" readonly>
                                    </div>
                                </div>

                                <!-- Kode Material (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Kode Material</label>
                                        <input type="text" name="kode_material" class="form-control auto-field"
                                            readonly>
                                    </div>
                                </div>

                                <!-- Konstruksi (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Konstruksi</label>
                                        <input type="text" name="konstruksi" class="form-control auto-field" readonly>
                                    </div>
                                </div>

                                <!-- Gramasi (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Gramasi</label>
                                        <input type="text" name="gramasi" class="form-control auto-field" readonly>
                                    </div>
                                </div>

                                <!-- Lebar (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Lebar</label>
                                        <input type="text" name="lebar" class="form-control auto-field" readonly>
                                    </div>
                                </div>

                                <!-- HFeel (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Hand Feel</label>
                                        <input type="text" name="hfeel" class="form-control auto-field" readonly>
                                    </div>
                                </div>

                                <!-- Warna (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Warna</label>
                                        <input type="text" name="warna" class="form-control auto-field" readonly>
                                    </div>
                                </div>

                                <!-- Kode Warna (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Kode Warna</label>
                                        <input type="text" name="kode_warna" class="form-control auto-field" readonly>
                                    </div>
                                </div>

                                <!-- Kategori Warna (readonly) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Kategori Warna</label>
                                        <input type="text" name="kategori_warna" class="form-control auto-field"
                                            readonly>
                                    </div>
                                </div>

                                <!-- QTY (readonly, 2 digit koma) -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label fw-semibold">Quantity</label>
                                        <input type="text" name="qty" class="form-control auto-field" readonly>
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


    </div>
@endsection

@section('scripts')
    {{-- Script drag & drop dan select2 dashboard tanpa log console --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const draggables = document.querySelectorAll('.draggable');
            const containers = document.querySelectorAll('.card-dropzone');

            draggables.forEach(draggable => {
                draggable.addEventListener('dragstart', () => draggable.classList.add('dragging'));
                draggable.addEventListener('dragend', () => draggable.classList.remove('dragging'));
            });

            containers.forEach(container => {
                container.addEventListener('dragover', e => {
                    e.preventDefault();
                    const dragging = document.querySelector('.dragging');
                    if (!dragging) return;

                    const afterElement = getDragAfterElement(container, e.clientY);
                    if (afterElement == null) {
                        container.appendChild(dragging);
                    } else {
                        container.insertBefore(dragging, afterElement);
                    }
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
        // Double click card proses untuk detail
        $(document).on('dblclick', '.status-card', function() {
            const proses = $(this).data('proses');
            if (!proses) return;
            const hiddenFields = ['id', 'created_at', 'updated_at', 'mesin_id'];
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
            // Hapus BARCODE KAINS dari detail proses
            const entries = Object.entries(proses)
                .filter(([key]) => !hiddenFields.includes(key) && key !== 'barcode_kains')
                .map(([key, val]) => {
                    if (key === 'hfeel') return ['HAND FEEL', val];
                    if (key === 'matdok') return ['MATERIAL DOKUMEN', val];
                    if (key === 'cycle_time' || key === 'cycle_time_actual') return [key.replace(/_/g, ' ').toUpperCase(), formatDetikToHMS(val)];
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
            // Barcode section
            html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode Kain <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_kain" data-id="'+proses.id+'" style="float:right;"><i class="fas fa-barcode"></i> Scan</button></th></tr>';
            html += '<tr><td colspan="4" id="barcode-kain-list">Loading...</td></tr>';
            html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode LA <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_la" data-id="'+proses.id+'" style="float:right;"><i class="fas fa-barcode"></i> Scan</button></th></tr>';
            html += '<tr><td colspan="4" id="barcode-la-list">Loading...</td></tr>';
            html += '<tr><th colspan="4" style="background:#f8f8f8;">Barcode AUX <button type="button" class="btn btn-sm btn-success scan-barcode-btn" data-barcode="barcode_aux" data-id="'+proses.id+'" style="float:right;"><i class="fas fa-barcode"></i> Scan</button></th></tr>';
            html += '<tr><td colspan="4" id="barcode-aux-list">Loading...</td></tr>';
            $('#detail-proses-body').html(html);
            // Ambil barcode dari relasi dan render di modal detail proses
            $.ajax({
                url: '/proses/' + proses.id + '/barcodes',
                method: 'GET',
                success: function(data) {
                    function renderBarcodeGrid(barcodes, barcodeType, prosesId) {
                        if (!barcodes || !barcodes.length) {
                            return '<span style="color:#888;">Belum ada barcode.</span>';
                        }
                        let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                        barcodes.forEach(function(bk, idx) {
                            if (bk.cancel) return; // Jangan tampilkan barcode yang sudah cancel
                            html += `<div style="position:relative;flex:1 0 30%;max-width:32%;background:#f3f3f3;border-radius:6px;padding:6px 4px;margin-bottom:6px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">
                                <span style='position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;' class='cancel-barcode-btn' data-type='${barcodeType}' data-proses='${prosesId}' data-id='${bk.id}' data-matdok='${bk.matdok}' data-barcode='${bk.barcode}' title='Cancel barcode'>&times;</span>
                                ${bk.barcode} ${(bk.matdok ? '<br><span style=\'font-size:11px;color:#888;\'>' + bk.matdok + '</span>' : '')}
                            </div>`;
                        });
                        html += '</div>';
                        return html;
                    }
                    $('#barcode-kain-list').html(renderBarcodeGrid(data.barcode_kain, 'kain', proses.id));
                    $('#barcode-la-list').html(renderBarcodeGrid(data.barcode_la, 'la', proses.id));
                    $('#barcode-aux-list').html(renderBarcodeGrid(data.barcode_aux, 'aux', proses.id));
                },
                error: function() {
                    $('#barcode-kain-list').html('<span style="color:#888;">Belum ada barcode kain.</span>');
                    $('#barcode-la-list').html('<span style="color:#888;">Belum ada barcode LA.</span>');
                    $('#barcode-aux-list').html('<span style="color:#888;">Belum ada barcode AUX.</span>');
                }
            });
            $('#modalDetailProses').modal('show');
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
            cancelBarcodeData = { barcodeType, prosesId, barcodeId, matdok, barcode };
            
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
            $('#btnConfirmCancelBarcode').prop('disabled', false).html('<i class="fas fa-times mr-1"></i>Ya, Cancel');
            
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
            
            const { barcodeType, prosesId, barcodeId, matdok, barcode } = cancelBarcodeData;
            
            // Simpan proses ID untuk refresh
            currentProsesForRefresh = prosesId;
            
            // Tampilkan loading dan disable button
            $('#btnConfirmCancelBarcode').prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Memproses...');
            $('#btnCancelCancelBarcode').prop('disabled', true);
            $('#cancelBarcodeLoading').show();
            
            // Request ke backend Laravel
            $.ajax({
                url: `/proses/${prosesId}/barcode/${barcodeType}/${barcodeId}/cancel`,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: { matdok: matdok },
                success: function(r) {
                    $('#modalConfirmCancelBarcode').modal('hide');
                    
                    if (r && r.status === 'success') {
                        // Show success message
                        showToastNotification('success', 'Barcode berhasil di-cancel!');
                        
                        // Refresh barcode list di modal detail proses jika masih terbuka
                        if ($('#modalDetailProses').hasClass('show') || $('#modalDetailProses').is(':visible')) {
                            // Simulasi double click pada card proses yang aktif untuk refresh
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
                                        function renderBarcodeGrid(barcodes, barcodeType, prosesId) {
                                            if (!barcodes || !barcodes.length) {
                                                return '<span style="color:#888;">Belum ada barcode.</span>';
                                            }
                                            let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                                            barcodes.forEach(function(bk, idx) {
                                                if (bk.cancel) return;
                                                html += `<div style="position:relative;flex:1 0 30%;max-width:32%;background:#f3f3f3;border-radius:6px;padding:6px 4px;margin-bottom:6px;text-align:center;font-weight:bold;font-size:13px;color:#222;box-shadow:0 1px 2px #0001;">
                                                    <span style='position:absolute;top:2px;right:6px;cursor:pointer;font-weight:bold;color:#b00;font-size:16px;z-index:2;' class='cancel-barcode-btn' data-type='${barcodeType}' data-proses='${prosesId}' data-id='${bk.id}' data-matdok='${bk.matdok}' data-barcode='${bk.barcode}' title='Cancel barcode'>&times;</span>
                                                    ${bk.barcode} ${(bk.matdok ? '<br><span style=\'font-size:11px;color:#888;\'>' + bk.matdok + '</span>' : '')}
                                                </div>`;
                                            });
                                            html += '</div>';
                                            return html;
                                        }
                                        $('#barcode-kain-list').html(renderBarcodeGrid(data.barcode_kain, 'kain', currentProsesForRefresh));
                                        $('#barcode-la-list').html(renderBarcodeGrid(data.barcode_la, 'la', currentProsesForRefresh));
                                        $('#barcode-aux-list').html(renderBarcodeGrid(data.barcode_aux, 'aux', currentProsesForRefresh));
                                    },
                                    error: function() {
                                        showToastNotification('error', 'Gagal me-refresh data barcode. Silakan tutup dan buka kembali modal detail.');
                                    }
                                });
                            }
                        }
                    } else {
                        const errorMsg = 'Cancel barcode gagal: ' + (r && r.message ? r.message : 'Unknown error');
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
                    $('#btnConfirmCancelBarcode').prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Ya, Cancel');
                    $('#btnCancelCancelBarcode').prop('disabled', false);
                    $('#cancelBarcodeLoading').hide();
                    cancelBarcodeData = null;
                }
            });
        });
        
        // Reset data saat modal ditutup
        $('#modalConfirmCancelBarcode').on('hidden.bs.modal', function() {
            cancelBarcodeData = null;
            $('#btnConfirmCancelBarcode').prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Ya, Cancel');
            $('#btnCancelCancelBarcode').prop('disabled', false);
            $('#cancelBarcodeLoading').hide();
        });
    </script>
@endsection
