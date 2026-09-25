@extends('layout.main')

@push('styles')
<style>
    /* Styling agar pagination rata kanan rapi dan compact */
    .card-footer .pagination {
        margin: 0 !important;
        justify-content: flex-end !important;
    }
    .card-footer nav {
        display: flex;
        justify-content: flex-end;
        margin: 0;
    }
    .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
    }
    /* Styling tabel modal rincian kimia */
    #table-modal-chemical thead.sticky-top th {
        top: 0;
        position: sticky;
        background-color: #f4f6f9;
        z-index: 2;
        border-bottom: 2px solid #dee2e6;
    }
    #table-modal-chemical td, #table-modal-chemical th {
        vertical-align: middle !important;
    }
    .card-header::after {
        display: none !important;
    }
</style>
@endpush

@section('content')
<div class="content-wrapper">
    {{-- Content Header --}}
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-file-invoice text-primary mr-2"></i>Report Goods Issue (GI)
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right bg-transparent p-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"><i class="fas fa-home mr-1"></i>Home</a></li>
                        <li class="breadcrumb-item active">Report GI</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <section class="content">
        <div class="container-fluid">
            {{-- KPI Summary Cards --}}
            <div class="row">
                {{-- Card 1: Total Transaksi GI --}}
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm border-0" style="border-radius: 8px;">
                        <span class="info-box-icon bg-primary elevation-1" style="border-radius: 8px;">
                            <i class="fas fa-barcode"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted text-uppercase" style="font-size: 11px; font-weight: 700;">Total Transaksi GI</span>
                            <span class="info-box-number text-dark" style="font-size: 20px; font-weight: 800;">
                                {{ number_format($summary->total_transaksi ?? 0, 0, ',', '.') }}
                                <small class="text-muted" style="font-size: 12px; font-weight: normal;">Barcode</small>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Card 2: Total Kain (Greige / Finish) --}}
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm border-0" style="border-radius: 8px;">
                        <span class="info-box-icon bg-info elevation-1" style="border-radius: 8px;">
                            <i class="fas fa-scroll"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted text-uppercase" style="font-size: 11px; font-weight: 700;">Total Kain (Greige/Finish)</span>
                            <span class="info-box-number text-dark" style="font-size: 20px; font-weight: 800;">
                                {{ number_format($summary->total_kain_kg ?? 0, 2, ',', '.') }}
                                <small class="text-muted" style="font-size: 12px; font-weight: normal;">KG</small>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Card 3: Total Dye Stuff --}}
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm border-0" style="border-radius: 8px;">
                        <span class="info-box-icon bg-warning elevation-1 text-white" style="border-radius: 8px; background: linear-gradient(135deg, #f39c12, #e67e22) !important;">
                            <i class="fas fa-palette"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted text-uppercase" style="font-size: 11px; font-weight: 700;">Total Dye Stuff</span>
                            <span class="info-box-number text-dark" style="font-size: 20px; font-weight: 800;">
                                {{ number_format($summary->total_dyes_gr ?? 0, 2, ',', '.') }}
                                <small class="text-muted" style="font-size: 12px; font-weight: normal;">Gram</small>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Card 4: Total Auxiliaries --}}
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm border-0" style="border-radius: 8px;">
                        <span class="info-box-icon bg-success elevation-1" style="border-radius: 8px; background: linear-gradient(135deg, #27ae60, #2ecc71) !important;">
                            <i class="fas fa-vial"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted text-uppercase" style="font-size: 11px; font-weight: 700;">Total Auxiliaries</span>
                            <span class="info-box-number text-dark" style="font-size: 20px; font-weight: 800;">
                                {{ number_format($summary->total_aux_kg ?? 0, 2, ',', '.') }}
                                <small class="text-muted" style="font-size: 12px; font-weight: normal;">KG</small>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter Box --}}
            <div class="card card-outline card-primary shadow-sm mb-4" style="border-radius: 8px;">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold" style="font-size: 15px;">
                        <i class="fas fa-filter text-primary mr-1"></i>Filter Laporan GI
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body py-3">
                    <form action="{{ route('report-gi.index') }}" method="GET" id="form-filter-gi">
                        <div class="row">
                            {{-- Tanggal Mulai --}}
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control form-control-sm"
                                    value="{{ $filters['startDate'] }}">
                            </div>

                            {{-- Tanggal Selesai --}}
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">Tanggal Selesai</label>
                                <input type="date" name="end_date" class="form-control form-control-sm"
                                    value="{{ $filters['endDate'] }}">
                            </div>

                            {{-- Filter Jenis --}}
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">Jenis Bahan / GI</label>
                                <select name="jenis" class="form-control form-control-sm">
                                    <option value="all" {{ ($filters['jenis'] ?? 'all') === 'all' ? 'selected' : '' }}>-- Semua Jenis --</option>
                                    <option value="Greige" {{ ($filters['jenis'] ?? '') === 'Greige' ? 'selected' : '' }}>Greige (Kain)</option>
                                    <option value="Finish" {{ ($filters['jenis'] ?? '') === 'Finish' ? 'selected' : '' }}>Finish (Kain)</option>
                                    <option value="Dye Stuff" {{ ($filters['jenis'] ?? '') === 'Dye Stuff' ? 'selected' : '' }}>Dye Stuff (Utama)</option>
                                    <option value="Topping Dye Stuff" {{ ($filters['jenis'] ?? '') === 'Topping Dye Stuff' ? 'selected' : '' }}>Topping Dye Stuff (TD)</option>
                                    <option value="Aux" {{ ($filters['jenis'] ?? '') === 'Aux' ? 'selected' : '' }}>Aux (Utama)</option>
                                    <option value="Topping Aux" {{ ($filters['jenis'] ?? '') === 'Topping Aux' ? 'selected' : '' }}>Topping Aux (TA)</option>
                                </select>
                            </div>

                            {{-- Status Barcode --}}
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">Status Barcode</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="active" {{ ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' }}>Aktif (Valid)</option>
                                    <option value="cancel" {{ ($filters['status'] ?? '') === 'cancel' ? 'selected' : '' }}>Dibatalkan (Cancel)</option>
                                    <option value="all" {{ ($filters['status'] ?? '') === 'all' ? 'selected' : '' }}>Semua Status</option>
                                </select>
                            </div>

                            {{-- Per Page --}}
                            <div class="col-md-3 col-sm-12 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">Pencarian</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search" class="form-control"
                                        placeholder="Cari No OP / Partai / Konstruksi / Barcode..." value="{{ $filters['search'] }}">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="submit" title="Terapkan Filter">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <a href="{{ route('report-gi.index') }}" class="btn btn-default" title="Reset Filter">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <div class="text-muted small">
                                <i class="fas fa-info-circle mr-1"></i>Menampilkan data berdasarkan tanggal scan barcode GI.
                            </div>
                            <div class="d-flex align-items-center" style="gap: 8px;">
                                <label class="small text-muted mb-0 mr-1">Tampilkan per halaman:</label>
                                <select name="per_page" class="form-control form-control-sm" style="width: 75px;" onchange="document.getElementById('form-filter-gi').submit();">
                                    <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Table Card --}}
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 8px;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fas fa-table mr-2 text-primary"></i>Data Transaksi Goods Issue
                    </h3>
                    <div class="card-tools ml-auto">
                        <span class="badge badge-light border px-2 py-1" style="font-size: 13px;">
                            Total: <strong>{{ number_format($records->total(), 0, ',', '.') }}</strong> Data
                        </span>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0 text-nowrap">
                        <thead class="thead-light">
                            <tr style="font-size: 13px;">
                                <th style="width: 50px;" class="text-center">#</th>
                                <th style="width: 140px;">Jenis</th>
                                <th style="width: 130px;">No OP</th>
                                <th style="width: 100px;">Partai</th>
                                <th style="min-width: 140px;">Konstruksi</th>
                                <th>Barcode</th>
                                <th style="width: 130px;" class="text-right">Qty</th>
                                <th style="width: 75px;" class="text-center">UoM</th>
                                <th style="width: 150px;">Tanggal GI</th>
                                <th style="width: 85px;" class="text-center">Status</th>
                                <th style="width: 110px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 13px;">
                            @forelse ($records as $index => $row)
                                <tr>
                                    {{-- 1. Index --}}
                                    <td class="text-center text-muted font-weight-bold">
                                        {{ ($records->currentPage() - 1) * $records->perPage() + $loop->iteration }}
                                    </td>

                                    {{-- 2. Jenis --}}
                                    <td>
                                        @if ($row->jenis === 'Greige')
                                            <span class="badge badge-secondary px-2 py-1" style="font-size: 11.5px; font-weight: 600;">
                                                <i class="fas fa-layer-group mr-1"></i>Greige
                                            </span>
                                        @elseif ($row->jenis === 'Finish')
                                            <span class="badge badge-primary px-2 py-1" style="font-size: 11.5px; font-weight: 600; background-color: #0288d1;">
                                                <i class="fas fa-check-circle mr-1"></i>Finish
                                            </span>
                                        @elseif ($row->jenis === 'Dye Stuff')
                                            <span class="badge px-2 py-1 text-white" style="font-size: 11.5px; font-weight: 600; background-color: #5c6bc0;">
                                                <i class="fas fa-palette mr-1"></i>Dye Stuff
                                            </span>
                                        @elseif ($row->jenis === 'Topping Dye Stuff')
                                            <span class="badge badge-warning px-2 py-1 text-dark" style="font-size: 11.5px; font-weight: 700; background-color: #ffb74d;">
                                                <i class="fas fa-fill-drip mr-1"></i>Topping Dyes (TD)
                                            </span>
                                        @elseif ($row->jenis === 'Aux')
                                            <span class="badge badge-success px-2 py-1" style="font-size: 11.5px; font-weight: 600; background-color: #2e7d32;">
                                                <i class="fas fa-flask mr-1"></i>Aux
                                            </span>
                                        @elseif ($row->jenis === 'Topping Aux')
                                            <span class="badge px-2 py-1 text-white" style="font-size: 11.5px; font-weight: 700; background-color: #00897b;">
                                                <i class="fas fa-vial mr-1"></i>Topping Aux (TA)
                                            </span>
                                        @else
                                            <span class="badge badge-light border">{{ $row->jenis }}</span>
                                        @endif
                                    </td>

                                    {{-- 3. OP --}}
                                    <td class="font-weight-bold text-dark">{{ $row->no_op ?: '-' }}</td>

                                    {{-- 4. Partai --}}
                                    <td>{{ $row->no_partai ?: '-' }}</td>

                                    {{-- 5. Konstruksi --}}
                                    <td>
                                        <span class="text-dark font-weight-medium">{{ $row->konstruksi ?: '-' }}</span>
                                    </td>

                                    {{-- 6. Barcode --}}
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <code class="text-primary font-weight-bold mr-2" style="font-size: 13px; font-family: Consolas, monospace;">{{ $row->barcode }}</code>
                                            <button type="button" class="btn btn-xs btn-outline-secondary btn-copy"
                                                data-clipboard="{{ $row->barcode }}" title="Salin Barcode" style="padding: 1px 5px; font-size: 10px;">
                                                <i class="far fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>

                                    {{-- 7. Qty --}}
                                    <td class="text-right font-weight-bold text-dark">
                                        {{ number_format((float) $row->qty, 2, ',', '.') }}
                                    </td>

                                    {{-- 8. UoM --}}
                                    <td class="text-center">
                                        <span class="badge badge-light border px-2 py-1" style="font-size: 11px;">
                                            {{ $row->uom }}
                                        </span>
                                    </td>

                                    {{-- 9. Tanggal GI --}}
                                    <td>
                                        <span class="text-muted" style="font-size: 12.5px;">
                                            <i class="far fa-clock mr-1"></i>{{ \Carbon\Carbon::parse($row->tanggal_gi)->format('d/m/Y H:i') }}
                                        </span>
                                    </td>

                                    {{-- 10. Status --}}
                                    <td class="text-center">
                                        @if ($row->is_cancel)
                                            <span class="badge badge-danger px-2 py-1" style="font-size: 11px;">Batal</span>
                                        @else
                                            <span class="badge badge-success px-2 py-1" style="font-size: 11px;">Aktif</span>
                                        @endif
                                    </td>

                                    {{-- 11. Aksi --}}
                                    <td class="text-center">
                                        @if ($row->source_type === 'kain')
                                            {{-- Greige & Finish: Direct Redirect ke Dashboard Detail Proses --}}
                                            <a href="{{ route('dashboard', ['open_proses_id' => $row->proses_id, 'detail_id' => $row->detail_proses_id]) }}"
                                                class="btn btn-sm btn-outline-primary py-1 px-2 shadow-sm"
                                                title="Lihat Detail Proses di Dashboard">
                                                <i class="fas fa-external-link-alt mr-1"></i>Detail
                                            </a>
                                        @else
                                            {{-- Dye Stuff, Topping Dyes, Aux, Topping Aux: Modal List Kimia --}}
                                            <button type="button"
                                                class="btn btn-sm btn-outline-info py-1 px-2 shadow-sm btn-show-chemical"
                                                data-toggle="modal"
                                                data-target="#modalChemicalDetails"
                                                data-type="{{ $row->source_type }}"
                                                data-barcode="{{ $row->barcode }}"
                                                data-jenis="{{ $row->jenis }}"
                                                data-op="{{ $row->no_op }}"
                                                data-partai="{{ $row->no_partai }}"
                                                data-konstruksi="{{ $row->konstruksi }}"
                                                data-qty="{{ number_format((float) $row->qty, 2, ',', '.') }}"
                                                data-uom="{{ $row->uom }}"
                                                data-tanggal="{{ \Carbon\Carbon::parse($row->tanggal_gi)->format('d/m/Y H:i') }}"
                                                data-proses-id="{{ $row->proses_id }}"
                                                data-detail-id="{{ $row->detail_proses_id }}"
                                                title="Lihat Rincian Kimia">
                                                <i class="fas fa-flask mr-1"></i>Detail Kimia
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <div class="py-4">
                                            <i class="fas fa-search fa-3x text-muted mb-3 d-block" style="opacity: 0.5;"></i>
                                            <h5 class="font-weight-bold text-dark">Tidak ada data GI yang ditemukan</h5>
                                            <p class="small text-muted mb-0">Coba ubah filter periode tanggal atau kata kunci pencarian Anda.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Footer --}}
                @if ($records->hasPages() || $records->total() > 0)
                    <div class="card-footer bg-white d-flex flex-column flex-md-row justify-content-between align-items-center py-3">
                        <div class="text-muted small mb-2 mb-md-0">
                            Menampilkan <strong>{{ $records->firstItem() ?? 0 }}</strong> sampai <strong>{{ $records->lastItem() ?? 0 }}</strong> dari <strong>{{ number_format($records->total(), 0, ',', '.') }}</strong> total transaksi
                        </div>
                        <div class="ml-auto d-flex justify-content-end align-items-center">
                            {{ $records->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>

{{-- MODAL RINCIAN KIMIA (Dye Stuff / Aux) --}}
<div class="modal fade" id="modalChemicalDetails" tabindex="-1" aria-labelledby="modalChemicalDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 10px;">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title font-weight-bold" id="modalChemicalDetailsLabel">
                    <i class="fas fa-flask mr-2"></i>Rincian Kimia Hasil GI
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                {{-- Header Info Kartu --}}
                <div class="card bg-light border-0 shadow-none mb-3" style="border-radius: 8px;">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <span class="text-muted small text-uppercase font-weight-bold d-block">Informasi Barcode:</span>
                                <div class="d-flex align-items-center mt-1">
                                    <code class="text-primary font-weight-bold mr-2" id="modal-info-barcode" style="font-size: 15px;">-</code>
                                    <span class="badge badge-primary px-2 py-1" id="modal-info-jenis">-</span>
                                </div>
                                <div class="text-muted small mt-1">
                                    Tanggal GI: <span class="font-weight-bold text-dark" id="modal-info-tanggal">-</span>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-right">
                                <span class="text-muted small text-uppercase font-weight-bold d-block">Order Produksi (OP) & Partai:</span>
                                <div class="h6 font-weight-bold text-dark mt-1 mb-0">
                                    OP: <span id="modal-info-op">-</span> | Partai: <span id="modal-info-partai">-</span>
                                </div>
                                <div class="text-muted small mt-1">
                                    Konstruksi: <span class="font-weight-bold text-dark" id="modal-info-konstruksi">-</span> | Total GI: <span class="badge badge-success px-2 py-1" style="font-size: 12px;" id="modal-info-qty">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Loading Spinner Container --}}
                <div id="modal-chemical-loading" class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                    <p class="text-muted small mb-0">Memuat rincian daftar bahan kimia...</p>
                </div>

                {{-- Error Message Container --}}
                <div id="modal-chemical-error" class="alert alert-danger d-none py-2 px-3 small">
                    <i class="fas fa-exclamation-triangle mr-1"></i><span id="modal-chemical-error-text">Gagal memuat rincian bahan kimia.</span>
                </div>

                {{-- Content Container --}}
                <div id="modal-chemical-content" class="d-none">
                    {{-- Detail Aux Tambahan (Volume / Liquor Ratio / Total WT) --}}
                    <div id="modal-aux-extra-info" class="p-2 mb-3 bg-white rounded border small text-center shadow-none" style="display: none;">
                        <div class="row m-0 align-items-center">
                            <div class="col-4">
                                <span class="text-muted d-block small text-uppercase font-weight-bold">Volume</span>
                                <strong id="modal-aux-volume" class="h6 font-weight-bold text-dark mb-0">-</strong>
                            </div>
                            <div class="col-4 border-left">
                                <span class="text-muted d-block small text-uppercase font-weight-bold">Liquor Ratio</span>
                                <strong id="modal-aux-lr" class="h6 font-weight-bold text-dark mb-0">-</strong>
                            </div>
                            <div class="col-4 border-left">
                                <span class="text-muted d-block small text-uppercase font-weight-bold">Total WT</span>
                                <strong id="modal-aux-total-wt" class="h6 font-weight-bold text-success mb-0">-</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Detail Dye Stuff Tambahan (Total Target / Actual / Selisih) --}}
                    <div id="modal-la-extra-info" class="p-2 mb-3 bg-white rounded border small text-center shadow-none" style="display: none;">
                        <div class="row m-0 align-items-center">
                            <div class="col-4">
                                <span class="text-muted d-block small text-uppercase font-weight-bold">Total Target WT</span>
                                <strong id="modal-la-target-wt" class="h6 font-weight-bold text-dark mb-0">-</strong>
                            </div>
                            <div class="col-4 border-left">
                                <span class="text-muted d-block small text-uppercase font-weight-bold">Total Actual WT</span>
                                <strong id="modal-la-actual-wt" class="h6 font-weight-bold text-primary mb-0">-</strong>
                            </div>
                            <div class="col-4 border-left">
                                <span class="text-muted d-block small text-uppercase font-weight-bold">Total Selisih</span>
                                <strong id="modal-la-diff-wt" class="h6 font-weight-bold mb-0">-</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Tabel Bahan Kimia --}}
                    <div class="table-responsive border rounded" style="max-height: 400px;">
                        <table class="table table-sm table-striped table-hover mb-0 text-nowrap" id="table-modal-chemical">
                            <thead class="thead-light sticky-top" id="table-modal-chemical-head" style="font-size: 13px;">
                                {{-- Diisi secara dinamis via JS --}}
                            </thead>
                            <tbody id="table-modal-chemical-body" style="font-size: 13px;">
                                {{-- Diisi secara dinamis via JS --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
                {{-- Tombol Direct ke Detail Proses Dashboard (Di tab yang sama) --}}
                <a href="#" id="btn-modal-open-proses" class="btn btn-primary shadow-sm">
                    <i class="fas fa-external-link-alt mr-1"></i>Buka Detail Proses
                </a>
                <button type="button" class="btn btn-secondary shadow-sm" data-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function () {
        // Handler Copy Barcode to Clipboard
        $(document).on('click', '.btn-copy', function () {
            const textToCopy = $(this).data('clipboard');
            if (!textToCopy) return;

            navigator.clipboard.writeText(textToCopy).then(function () {
                if (window.ToastSuccess) {
                    ToastSuccess.fire({
                        title: 'Barcode disalin: ' + textToCopy,
                        timer: 2000
                    });
                } else {
                    alert('Barcode disalin: ' + textToCopy);
                }
            }).catch(function () {
                // Fallback jika navigator.clipboard tidak diizinkan
                const tempInput = document.createElement('input');
                tempInput.value = textToCopy;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                if (window.ToastSuccess) {
                    ToastSuccess.fire({
                        title: 'Barcode disalin: ' + textToCopy,
                        timer: 2000
                    });
                }
            });
        });

        // Handler Modal Detail Kimia (Dye Stuff & Aux)
        $(document).on('click', '.btn-show-chemical', function () {
            const $btn = $(this);
            const type = $btn.data('type');
            const barcode = $btn.data('barcode');
            const jenis = $btn.data('jenis');
            const noOp = $btn.data('op') || '-';
            const noPartai = $btn.data('partai') || '-';
            const konstruksi = $btn.data('konstruksi') || '-';
            const qty = $btn.data('qty') || '-';
            const uom = $btn.data('uom') || '';
            const tanggal = $btn.data('tanggal') || '-';
            const prosesId = $btn.data('proses-id');
            const detailId = $btn.data('detail-id');

            // Set info header modal
            $('#modal-info-barcode').text(barcode);
            $('#modal-info-jenis').text(jenis);
            $('#modal-info-op').text(noOp);
            $('#modal-info-partai').text(noPartai);
            $('#modal-info-konstruksi').text(konstruksi);
            $('#modal-info-qty').text(qty + ' ' + uom);
            $('#modal-info-tanggal').text(tanggal);

            // Set Link Buka Detail Proses ke Dashboard
            if (prosesId && prosesId !== '-') {
                const dashboardUrl = `{{ url('dashboard') }}?open_proses_id=${prosesId}&detail_id=${detailId || ''}`;
                $('#btn-modal-open-proses').attr('href', dashboardUrl).show();
            } else {
                $('#btn-modal-open-proses').hide();
            }

            // Reset modal states
            $('#modal-chemical-loading').removeClass('d-none');
            $('#modal-chemical-error').addClass('d-none');
            $('#modal-chemical-content').addClass('d-none');
            $('#modal-aux-extra-info').hide();
            $('#modal-la-extra-info').hide();
            $('#table-modal-chemical-head').empty();
            $('#table-modal-chemical-body').empty();

            $('#modalChemicalDetails').modal('show');

            // Fetch AJAX rincian bahan kimia
            const requestUrl = `{{ url('report-gi/chemicals') }}/${type}/${encodeURIComponent(barcode)}`;
            $.ajax({
                url: requestUrl,
                method: 'GET',
                dataType: 'json',
                success: function (res) {
                    $('#modal-chemical-loading').addClass('d-none');
                    if (res.status === 'success' && res.items) {
                        $('#modal-chemical-content').removeClass('d-none');

                        if (type === 'la') {
                            // Info Card Dye Stuff
                            const totalTarget = res.total_target_wt !== undefined ? parseFloat(res.total_target_wt) : 0;
                            const totalActual = res.total_actual_wt !== undefined ? parseFloat(res.total_actual_wt) : 0;
                            const totalDiff = totalActual - totalTarget;
                            const totalDiffText = totalDiff > 0 ? ('+' + totalDiff.toFixed(2)) : totalDiff.toFixed(2);
                            const totalDiffClass = Math.abs(totalDiff) > 0.05 ? 'text-warning font-weight-bold' : 'text-success font-weight-bold';

                            $('#modal-la-target-wt').text(totalTarget.toFixed(2) + ' Gram');
                            $('#modal-la-actual-wt').text(totalActual.toFixed(2) + ' Gram');
                            $('#modal-la-diff-wt').text(totalDiffText + ' Gram').removeClass('text-warning text-success font-weight-bold').addClass(totalDiffClass);
                            $('#modal-la-extra-info').show();

                            // Tampilan Header Dye Stuff
                            $('#table-modal-chemical-head').html(`
                                <tr>
                                    <th class="text-center align-middle" style="width: 45px;">#</th>
                                    <th class="align-middle" style="width: 140px;">Kode Bahan</th>
                                    <th class="align-middle" style="min-width: 200px;">Nama Bahan Kimia</th>
                                    <th class="text-right align-middle" style="width: 120px;">Target WT</th>
                                    <th class="text-right align-middle" style="width: 120px;">Actual WT</th>
                                    <th class="text-right align-middle" style="width: 110px;">Selisih</th>
                                    <th class="text-center align-middle" style="width: 90px;">Satuan</th>
                                    <th class="text-center align-middle" style="width: 160px;">Waktu Timbang</th>
                                </tr>
                            `);

                            let rowsHtml = '';
                            if (res.items.length === 0) {
                                rowsHtml = `<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data penimbangan kimia pada barcode ini.</td></tr>`;
                            } else {
                                res.items.forEach(function (item, idx) {
                                    const target = item.target_wt !== null ? parseFloat(item.target_wt) : 0;
                                    const actual = item.actual_wt !== null ? parseFloat(item.actual_wt) : 0;
                                    const diff = actual - target;
                                    const diffText = diff > 0 ? ('+' + diff.toFixed(2)) : diff.toFixed(2);
                                    const diffClass = Math.abs(diff) > 0.05 ? 'text-warning font-weight-bold' : 'text-success font-weight-bold';

                                    // Format waktu timbang agar rapi (DD/MM/YYYY HH:mm)
                                    let dateDisplay = '-';
                                    if (item.comp_date) {
                                        const dStr = String(item.comp_date).trim();
                                        if (dStr.length === 8) {
                                            dateDisplay = `${dStr.substring(6, 8)}/${dStr.substring(4, 6)}/${dStr.substring(0, 4)}`;
                                        } else {
                                            dateDisplay = dStr;
                                        }
                                    }
                                    let timeDisplay = item.comp_time ? String(item.comp_time).trim() : '';
                                    if (timeDisplay.length >= 5) {
                                        timeDisplay = timeDisplay.substring(0, 5);
                                    }
                                    const fullTimeStr = dateDisplay !== '-' ? (dateDisplay + (timeDisplay ? ' ' + timeDisplay : '')) : (timeDisplay || '-');

                                    rowsHtml += `
                                        <tr>
                                            <td class="text-center align-middle text-muted">${idx + 1}</td>
                                            <td class="align-middle"><code class="text-primary font-weight-bold">${item.product_code || '-'}</code></td>
                                            <td class="align-middle font-weight-bold text-dark">${item.product_name || '-'}</td>
                                            <td class="text-right align-middle">${target.toFixed(2)}</td>
                                            <td class="text-right align-middle font-weight-bold text-primary">${actual.toFixed(2)}</td>
                                            <td class="text-right align-middle ${diffClass}">${diffText}</td>
                                            <td class="text-center align-middle"><span class="badge badge-light border">${item.unit || 'Gram'}</span></td>
                                            <td class="text-center align-middle small text-muted font-weight-bold">${fullTimeStr}</td>
                                        </tr>
                                    `;
                                });
                            }
                            $('#table-modal-chemical-body').html(rowsHtml);

                        } else if (type === 'aux') {
                            // Info Card Auxiliary
                            const volumeVal = res.volume_litres !== null ? (parseFloat(res.volume_litres).toFixed(2) + ' L') : '-';
                            const lrVal = res.liquor_ratio ? (parseFloat(res.liquor_ratio).toFixed(2)) : '-';
                            const totalWtVal = res.total_wt !== null ? (parseFloat(res.total_wt).toFixed(2) + ' KG') : '-';

                            $('#modal-aux-volume').text(volumeVal);
                            $('#modal-aux-lr').text(lrVal);
                            $('#modal-aux-total-wt').text(totalWtVal);
                            $('#modal-aux-extra-info').show();

                            // Tampilan Header AUX
                            $('#table-modal-chemical-head').html(`
                                <tr>
                                    <th class="text-center align-middle" style="width: 50px;">#</th>
                                    <th class="align-middle">Nama Auxiliary</th>
                                    <th class="text-right align-middle" style="width: 200px;">Konsentrasi</th>
                                </tr>
                            `);

                            let rowsHtml = '';
                            if (res.items.length === 0) {
                                rowsHtml = `<tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data rincian auxiliary pada barcode ini.</td></tr>`;
                            } else {
                                res.items.forEach(function (item, idx) {
                                    const konsentrasiVal = item.konsentrasi !== null ? parseFloat(item.konsentrasi).toFixed(4) : '-';
                                    rowsHtml += `
                                        <tr>
                                            <td class="text-center align-middle text-muted">${idx + 1}</td>
                                            <td class="align-middle font-weight-bold text-dark">${item.auxiliary || '-'}</td>
                                            <td class="text-right align-middle">
                                                <span class="badge badge-light border text-success font-weight-bold px-2 py-1" style="font-size: 12px;">
                                                    ${konsentrasiVal} g/L
                                                </span>
                                            </td>
                                        </tr>
                                    `;
                                });
                            }
                            $('#table-modal-chemical-body').html(rowsHtml);
                        }
                    } else {
                        $('#modal-chemical-error').removeClass('d-none');
                        $('#modal-chemical-error-text').text(res.message || 'Tidak ada rincian bahan kimia.');
                    }
                },
                error: function (xhr) {
                    $('#modal-chemical-loading').addClass('d-none');
                    $('#modal-chemical-error').removeClass('d-none');
                    const err = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan saat memuat data dari server.';
                    $('#modal-chemical-error-text').text(err);
                }
            });
        });
    });
</script>
@endsection
