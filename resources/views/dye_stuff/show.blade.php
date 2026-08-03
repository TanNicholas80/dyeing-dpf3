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
                            <li class="breadcrumb-item active">Detail Barcode</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="d-flex justify-content-end mb-3" style="gap: 0.75rem;">
                    <a href="{{ route('dye-stuff.print', $summary->barcode) }}" target="_blank"
                        class="btn btn-info shadow-sm">
                        <i class="fas fa-print"></i> Print Barcode Label
                    </a>
                    <a href="{{ route('dye-stuff.index') }}" class="btn btn-outline-secondary shadow-sm">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center w-100">
                        <h3 class="card-title mb-0"><i class="fas fa-info-circle mr-2"></i>Informasi Utama Barcode (ID NO)
                        </h3>
                        <div class="card-tools ml-auto">
                            <span class="badge badge-light text-dark font-weight-bold"
                                style="font-size: 1.1rem;">{{ $summary->barcode }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <strong>Batch / No JO:</strong><br>
                                        <span>{{ $summary->batch_no }}</span>
                                        @if($summary->no_jo && $summary->no_jo !== '-')
                                            <small class="text-muted d-block">(JO: {{ $summary->no_jo }})</small>
                                        @endif
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Fabric Name (Konstruksi):</strong><br>
                                        <span class="text-bold">{{ $summary->fabric_name }}</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Customer Name:</strong><br>
                                        <span>{{ $summary->customer_name }}</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Color Name (Warna):</strong><br>
                                        <span>{{ $summary->color_name }}</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Order No / No Partai:</strong><br>
                                        <span>{{ $summary->order_no }}</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Mesin (M/C):</strong><br>
                                        <span class="text-bold">{{ $summary->machine }}</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Total Wt. (Kg):</strong><br>
                                        <span>{{ number_format((float) $summary->total_wt_kg, 1) }} Kg</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Volume (Litres):</strong><br>
                                        <span>{{ number_format((float) $summary->volume, 1) }} L</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Kode Resep & LR:</strong><br>
                                        <span class="badge badge-secondary p-2">{{ $summary->recipe_code }} (LR 1:{{ $summary->lr }})</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Tgl & Jam Timbang:</strong><br>
                                        <span><i class="far fa-calendar-alt text-muted mr-1"></i>{{ $summary->comp_date }} {{ $summary->comp_time }}</span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Status Penimbangan:</strong><br>
                                        @if ($summary->status_timbang === 'sudah')
                                            <span class="badge badge-success p-2"><i class="fas fa-check-circle mr-1"></i>Sudah Ditimbang</span>
                                        @elseif ($summary->status_timbang === 'parsial')
                                            <span class="badge badge-warning text-dark p-2"><i class="fas fa-exclamation-triangle mr-1"></i>Sebagian Ditimbang ({{ $summary->weighed_count }}/{{ $summary->items_count }} Item)</span>
                                        @else
                                            <span class="badge badge-danger p-2"><i class="fas fa-hourglass-half mr-1"></i>Belum Ditimbang</span>
                                        @endif
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Status Pemakaian OP:</strong><br>
                                        @if ($summary->isUsedByProses)
                                            <span class="badge badge-success p-2"><i class="fas fa-check-circle mr-1"></i>Sudah Dipakai ({{ $summary->usedCount }}x)</span>
                                        @else
                                            <span class="badge badge-secondary p-2"><i class="fas fa-times-circle mr-1"></i>Belum Dipakai</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div
                                class="col-md-3 border-left d-flex flex-column align-items-center justify-content-center py-2">
                                <canvas id="qr-code-canvas"></canvas>
                                <div class="font-weight-bold text-dark mt-2"
                                    style="letter-spacing: 1px; font-size: 1.1rem;">
                                    {{ $summary->barcode }}
                                </div>
                                <small class="text-muted">Barcode (ID NO)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Chemical Details -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h3 class="card-title text-bold"><i class="fas fa-vials text-primary mr-2"></i>Daftar Bahan Kimia /
                            Dye Stuff ({{ $ticketDetails->count() }} Items)</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Step</th>
                                    <th>Kode Kimia</th>
                                    <th>Nama Kimia (PRODUCT_NAME)</th>
                                    <th>Target Wt</th>
                                    <th>Actual Wt</th>
                                    <th>Unit</th>
                                    <th>Konsentrasi</th>
                                    <th>Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ticketDetails as $row)
                                    <tr>
                                        <td><span class="badge badge-light border">{{ $row->step_no }}</span></td>
                                        <td><code>{{ $row->product_code }}</code></td>
                                        <td><strong>{{ $row->product_name ?? '-' }}</strong></td>
                                        <td>{{ number_format((float) $row->target_wt, 2) }}</td>
                                        <td>
                                            <strong class="{{ (float) $row->actual_wt > 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format((float) $row->actual_wt, 2) }}
                                            </strong>
                                        </td>
                                        <td>{{ $row->unit ?? 'g' }}</td>
                                        <td>{{ $row->conc ? number_format((float) $row->conc, 2) . ' ' . $row->conc_unit : '-' }}
                                        </td>
                                        <td><small class="text-muted">{{ $row->remark ?? '-' }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @section('scripts')
        <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
        <script>
            $(document).ready(function () {
                var canvas = document.getElementById('qr-code-canvas');
                var size = 130;
                new QRious({
                    element: canvas,
                    value: "{{ $summary->barcode }}",
                    size: size,
                    level: 'H'
                });
                var logo = new Image();
                logo.src = "{{ asset('images/logo.png') }}";
                logo.onload = function () {
                    var ctx = canvas.getContext('2d');
                    var logoSize = Math.floor(size * 0.22);
                    var center = (size - logoSize) / 2;
                    var padding = Math.max(2, Math.floor(logoSize * 0.15));

                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(center - padding / 2, center - padding / 2, logoSize + padding, logoSize + padding);
                    ctx.drawImage(logo, center, center, logoSize, logoSize);
                };
            });
        </script>
    @endsection
@endsection