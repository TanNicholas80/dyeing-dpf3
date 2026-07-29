@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Detail Dye Stuff (LA)</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('dye-stuff.index') }}">Dye Stuff</a></li>
                            <li class="breadcrumb-item active">Detail Dye Stuff</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                @php
                    $firstDetail = optional(optional($dyeStuff->proses)->details)->first();
                    $noOp = $firstDetail->no_op ?? '-';
                    $noPartai = $firstDetail->no_partai ?? '-';
                    $customer = $firstDetail->customer ?? '-';
                    $material = $firstDetail->konstruksi ?? '-';
                    $color = $firstDetail->warna ?? $firstDetail->color ?? '-';
                    $mesin = optional(optional($dyeStuff->proses)->mesin)->jenis_mesin ?? '-';
                    $proses = $dyeStuff->proses;
                    $statusLabel = $proses ? (!is_null($proses->selesai) ? 'Selesai' : (!is_null($proses->mulai) ? 'Sedang Berjalan' : 'Belum Berjalan')) : '-';
                @endphp

                <div class="d-flex justify-content-end mb-3" style="gap: 0.75rem;">
                    <a href="{{ route('dye-stuff.print', $dyeStuff->id) }}" target="_blank" class="btn btn-info shadow-sm">
                        <i class="fas fa-print"></i> Print Barcode
                    </a>
                    <a href="{{ route('dye-stuff.index') }}" class="btn btn-outline-secondary shadow-sm">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center w-100">
                        <h3 class="card-title mb-0"><i class="fas fa-info-circle"></i> Informasi Utama Dye Stuff</h3>
                        <div class="card-tools ml-auto">
                            <span class="badge badge-light text-dark font-weight-bold" style="font-size: 1.1rem;">{{ $dyeStuff->barcode }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <strong>Dye Stuff Type:</strong><br>
                                        @if(($dyeStuff->tipe ?? 'normal') === 'additional')
                                            <span class="badge badge-warning">Addition (Topping)</span>
                                        @else
                                            <span class="badge badge-info">Normal (Utama)</span>
                                        @endif
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Jenis:</strong><br>
                                        {{ \App\Models\DyeStuff::getJenisOptions()[$dyeStuff->jenis] ?? ucfirst($dyeStuff->jenis ?? '-') }}
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Step Process:</strong><br>
                                        @if(($dyeStuff->tipe ?? 'normal') === 'additional')
                                            {{ $dyeStuff->step_proses == 1 ? 'Reactive' : ($dyeStuff->step_proses == 2 ? 'Dispers' : 'Step ' . ($dyeStuff->step_proses ?? 1)) }}
                                        @else
                                            {{ $dyeStuff->step_proses ? 'Step ' . $dyeStuff->step_proses : 'Step 1' }}
                                        @endif
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Liquor Ratio:</strong><br>
                                        <span>1 : {{ number_format($dyeStuff->liquor_ratio, 1) }}</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Total Wt. (Kg):</strong><br>
                                        <span>{{ number_format($dyeStuff->total_wt, 1) }} kg</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Volume (Litres):</strong><br>
                                        <span class="text-primary font-weight-bold">{{ number_format($dyeStuff->volume_litres, 1) }} L</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 border-left d-flex flex-column align-items-center justify-content-center py-2">
                                <canvas id="qr-code-canvas"></canvas>
                                <div class="font-weight-bold text-dark mt-2" style="letter-spacing: 1px; font-size: 1.1rem;">
                                    {{ $dyeStuff->barcode }}
                                </div>
                                <small class="text-muted">QR Code Dye Stuff</small>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-4 mb-2"><strong>Batch / JO:</strong> {{ $noOp }}</div>
                            <div class="col-md-4 mb-2"><strong>Order No / Partai:</strong> {{ $noPartai }}</div>
                            <div class="col-md-4 mb-2"><strong>Customer:</strong> {{ $customer }}</div>
                            <div class="col-md-4 mb-2"><strong>Fabric / Material:</strong> {{ $material }}</div>
                            <div class="col-md-4 mb-2"><strong>Color Name:</strong> {{ $color }}</div>
                            <div class="col-md-4 mb-2"><strong>M/C (Mesin):</strong> {{ $mesin }}</div>
                            <div class="col-md-4 mb-2"><strong>Status Proses:</strong> 
                                @if($statusLabel === 'Sedang Berjalan')
                                    <span class="badge badge-primary"><i class="fas fa-play"></i> Sedang Berjalan</span>
                                @elseif($statusLabel === 'Selesai')
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Selesai</span>
                                @else
                                    <span class="badge badge-secondary"><i class="fas fa-clock"></i> Belum Berjalan</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- List Chemical Details -->
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h3 class="card-title mb-0"><i class="fas fa-list-alt"></i> Detail List Kimia / Dye Stuff</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 5%">#</th>
                                        <th style="width: 45%">Chemical Name</th>
                                        <th style="width: 20%">Conc. (%)</th>
                                        <th style="width: 20%">Weight</th>
                                        <th style="width: 10%">Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($dyeStuff->details as $idx => $d)
                                        <tr>
                                            <td>{{ $idx + 1 }}</td>
                                            <td class="font-weight-bold">{{ $d->chemical_name }}</td>
                                            <td>{{ number_format($d->konsentrasi, 5) }} %</td>
                                            <td class="text-success font-weight-bold">{{ number_format($d->weight, 2) }} {{ $d->unit ?? 'g' }}</td>
                                            <td>{{ $d->remark ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Tidak ada detail kimia.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            try {
                const canvas = document.getElementById('qr-code-canvas');
                const size = 130;
                new QRious({
                    element: canvas,
                    value: "{{ $dyeStuff->barcode }}",
                    size: size,
                    level: 'H'
                });
                const logo = new Image();
                logo.src = "{{ asset('images/logo.png') }}";
                logo.onload = function() {
                    const ctx = canvas.getContext('2d');
                    const logoSize = Math.floor(size * 0.22);
                    const center = (size - logoSize) / 2;
                    const padding = Math.max(2, Math.floor(logoSize * 0.15));

                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(center - padding / 2, center - padding / 2, logoSize + padding, logoSize + padding);
                    ctx.drawImage(logo, center, center, logoSize, logoSize);
                };
            } catch(e) {
                console.error("QRious error:", e);
            }
        });
    </script>
@endsection
