@extends('layout.main')

@section('content')
    <style>
        .select2-container .select2-selection--single {
            height: 38px;
            padding: 5px 12px;
            font-size: 14px;
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
                        @if ($mesins->lastPage() > 1)
                            <nav aria-label="Mesin pagination">
                                <ul class="pagination justify-content-center mb-0">
                                    {{-- Previous --}}
                                    <li class="page-item {{ $mesins->onFirstPage() ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ $mesins->previousPageUrl() }}">&laquo;</a>
                                    </li>
                                    {{-- Numbered --}}
                                    @for ($i = 1; $i <= $mesins->lastPage(); $i++)
                                        <li class="page-item {{ $mesins->currentPage() == $i ? 'active' : '' }}">
                                            <a class="page-link" href="{{ $mesins->url($i) }}">{{ $i }}</a>
                                        </li>
                                    @endfor
                                    {{-- Next --}}
                                    <li
                                        class="page-item {{ $mesins->currentPage() == $mesins->lastPage() ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ $mesins->nextPageUrl() }}">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                        @endif
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
                    @foreach ($mesins as $mesin)
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
                                            $blockColors = [
                                                $proses->barcode_kain ? 'green' : 'red',
                                                $proses->barcode_la ? 'green' : 'red',
                                                $proses->barcode_aux ? 'green' : 'red',
                                            ];
                                            $blocks = ['G', 'D', 'A'];
                                            // Lampu indikator: hijau jika mulai ada dan selesai null, merah jika mulai dan selesai ada, atau mulai null
                                            if ($proses->mulai && !$proses->selesai) {
                                                $light = 'green';
                                            } else {
                                                $light = 'red';
                                            }
                                            // Background card sesuai status proses
                                            $bg = '#757575';
                                            if (!$proses->mulai) {
                                                $bg = '#757575'; // abu2
                                            } elseif ($proses->selesai) {
                                                $mulai = \Carbon\Carbon::parse($proses->mulai);
                                                $selesai = \Carbon\Carbon::parse($proses->selesai);
                                                $cycle_time_actual = max(0, $mulai->diffInSeconds($selesai, false));
                                                $cycle_time = $proses->cycle_time ? (int) $proses->cycle_time : 0;
                                                $cycle_time_actual = $proses->cycle_time_actual
                                                    ? (int) $proses->cycle_time_actual
                                                    : 0;
                                                if ($cycle_time_actual > $cycle_time + 3600) {
                                                    $bg = '#e53935'; // merah
                                                } else {
                                                    $bg = '#00c853'; // hijau
                                                }
                                            } else {
                                                $bg = '#002b80'; // biru
                                            }
                                            $gradient = getGradient($bg);
                                            // Tambahkan inisialisasi variabel agar tidak undefined
                                            $estimasi_selesai = null;
                                            if ($proses->mulai && !$proses->selesai && $proses->cycle_time) {
                                                $estimasi_selesai = \Carbon\Carbon::parse($proses->mulai)->addSeconds(
                                                    (int) $proses->cycle_time,
                                                );
                                            }
                                            $cycle_time_actual_str = '00:00:00';
                                            if ($proses->mulai && $proses->selesai) {
                                                $mulai = \Carbon\Carbon::parse($proses->mulai);
                                                $selesai = \Carbon\Carbon::parse($proses->selesai);
                                                $cycle_time_actual = max(0, $mulai->diffInSeconds($selesai, false));
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
                                                            $blockBg = $color === 'green' ? '#d4f8e8' : '#ffb3b3';
                                                            $blockBorder = $color === 'green' ? '#43a047' : '#c62828';
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
                                                    {{ $proses->no_op }}
                                                </div>
                                                <div class="card-time"
                                                    style="display: flex; justify-content: space-between; font-size: 12px; margin: 2px 0; color: #fff; text-shadow: 0 1px 2px #0008;">
                                                    <span>
                                                        @php
                                                            // Logic: jika sudah ada cycle_time_actual, tampilkan itu
                                                            $showTime = '00:00:00';
                                                            if ($proses->cycle_time_actual) {
                                                                $showTime = detikKeWaktu($proses->cycle_time_actual);
                                                            } elseif ($proses->mulai && $proses->selesai) {
                                                                $mulai = \Carbon\Carbon::parse($proses->mulai);
                                                                $selesai = \Carbon\Carbon::parse($proses->selesai);
                                                                $showTime = detikKeWaktu(max(0, $mulai->diffInSeconds($selesai, false)));
                                                            } elseif ($proses->mulai && !$proses->selesai) {
                                                                $now = \Carbon\Carbon::now();
                                                                $mulai = \Carbon\Carbon::parse($proses->mulai);
                                                                $showTime = detikKeWaktu(max(0, $mulai->diffInSeconds($now)));
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
                                                    <div>{{ $proses->warna }} - {{ $proses->kategori_warna }} -
                                                        {{ $proses->kode_warna }}</div>
                                                    <div>{{ $proses->konstruksi }}</div>
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
                    @endforeach
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
                                        <label class="form-label fw-semibold">Cycle Time (jam:menit:detik)</label>
                                        <input type="time" name="cycle_time" step="1" class="form-control"
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
            const entries = Object.entries(proses)
                .filter(([key]) => !hiddenFields.includes(key))
                .map(([key, val]) => {
                    // Barcode scan button logic
                    if ((key === 'barcode_kain' || key === 'barcode_la' || key === 'barcode_aux') && (!val || val === '')) {
                        let label = key === 'barcode_kain' ? 'Barcode Kain' : (key === 'barcode_la' ? 'Barcode LA' : 'Barcode AUX');
                        let btnId = `btn-scan-${key}`;
                        // Ganti button: hanya icon barcode
                        return [label.toUpperCase(), `<button type=\"button\" class=\"btn btn-sm btn-success scan-barcode-btn\" id=\"${btnId}\" data-barcode=\"${key}\" data-id=\"${proses.id}\" title=\"Scan Barcode\"><i class=\"fas fa-barcode\"></i></button>`];
                    }
                    if (key === 'hfeel') return ['HAND FEEL', val];
                    if (key === 'cycle_time' || key === 'cycle_time_actual') return [key.replace(/_/g, ' ').toUpperCase(), formatDetikToHMS(val)];
                    return [key.replace(/_/g, ' ').toUpperCase(), val];
                });
            entries.unshift(['JENIS MESIN', jenisMesin]);
            let html = '';
            for (let i = 0; i < entries.length; i += 2) {
                html += '<tr>';
                html += `<th style=\"width:180px;\">${entries[i][0]}</th><td>${entries[i][1] ?? '-'}</td>`;
                if (entries[i + 1]) {
                    html += `<th style=\"width:180px;\">${entries[i+1][0]}</th><td>${entries[i+1][1] ?? '-'}</td>`;
                } else {
                    html += '<th></th><td></td>';
                }
                html += '</tr>';
            }
            $('#detail-proses-body').html(html);
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
        $('#modalScanBarcode').on('shown.bs.modal', function () {
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
                    $('#barcode-scanner-container').html('<div id="reader" style="width:100%;max-width:400px;margin:auto;"></div>');
                    window.html5QrcodeScanner = new Html5Qrcode("reader");
                    window.html5QrcodeScanner.start(
                        { facingMode: "environment" },
                        {
                            fps: 10,
                            qrbox: 250,
                            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE, Html5QrcodeSupportedFormats.CODE_128]
                        },
                        (decodedText, decodedResult) => {
                            // Setelah scan, isi input hidden dan submit form
                            $('#inputBarcodeValue').val(decodedText);
                            // Submit form POST ke endpoint barcode terkait
                            $('#formScanBarcode').submit();
                            window.html5QrcodeScanner.stop().catch(()=>{});
                        },
                        (errorMessage) => {}
                    ).catch(err => {
                        $('#barcode-scanner-container').html('<span style="color:#fff;">Tidak dapat mengakses kamera: ' + err + '</span>');
                    });
                }
            }, 200);
        });
        // Stop scanner saat modal ditutup
        $('#modalScanBarcode').on('hidden.bs.modal', function() {
            if (window.html5QrcodeScanner) {
                try {
                    window.html5QrcodeScanner.stop().then(()=>{
                        window.html5QrcodeScanner.clear();
                        window.html5QrcodeScanner = null;
                        $('#barcode-scanner-container').html('');
                    }).catch(()=>{
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
    </script>
@endsection
