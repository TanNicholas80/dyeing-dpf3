@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Data Dye Stuff (LA)</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item active">Dye Stuff (LA)</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <!-- Filter & Search Card -->
                <div class="card card-outline card-primary shadow-sm mb-3">
                    <div class="card-body">
                        <form method="GET" action="{{ route('dye-stuff.index') }}" class="form-inline row">
                            <div class="form-group col-md-4 mb-2">
                                <label for="search" class="mr-2 font-weight-normal">Pencarian:</label>
                                <div class="input-group w-100">
                                    <input type="text" name="search" id="search" class="form-control form-control-sm"
                                        placeholder="Cari Barcode, Resep, Mesin, Lot..." value="{{ request('search') }}">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary btn-sm" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label for="status_timbang" class="mr-2 font-weight-normal">Status Timbang:</label>
                                <select name="status_timbang" id="status_timbang" class="form-control form-control-sm w-100" onchange="this.form.submit()">
                                    <option value="">-- Semua Status --</option>
                                    <option value="sudah" {{ request('status_timbang') === 'sudah' ? 'selected' : '' }}>Sudah Ditimbang</option>
                                    <option value="parsial" {{ request('status_timbang') === 'parsial' ? 'selected' : '' }}>Sebagian Ditimbang (Parsial)</option>
                                    <option value="belum" {{ request('status_timbang') === 'belum' ? 'selected' : '' }}>Belum Ditimbang</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-2">
                                <label for="per_page" class="mr-2 font-weight-normal">Per Halaman:</label>
                                <select name="per_page" id="per_page" class="form-control form-control-sm w-100" onchange="this.form.submit()">
                                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                                    <option value="25" {{ request('per_page') == 25 || !request('per_page') ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3 mb-2 d-flex align-items-end justify-content-end" style="gap: 0.5rem;">
                                @if (request()->hasAny(['search', 'status_timbang', 'per_page']))
                                    <a href="{{ route('dye-stuff.index') }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-undo"></i> Reset
                                    </a>
                                @endif
                                <button type="button" class="btn btn-info btn-sm shadow-sm" id="bulkPrintBtn">
                                    <i class="fas fa-print mr-1"></i> Print Barcode
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Main Data Table Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                        <h3 class="card-title text-bold mb-0">
                            <i class="fas fa-flask text-primary mr-2"></i>Daftar Barcode Dye Stuff
                        </h3>
                        <div class="card-tools ml-auto text-right">
                            <small class="text-muted">Total Barcode: <strong>{{ number_format($dyeStuffs->total()) }}</strong></small>
                        </div>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover table-striped text-nowrap mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 35px;" class="text-center">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Barcode (ID NO)</th>
                                    <th>Kode Resep</th>
                                    <th>Mesin</th>
                                    <th>Product Lot</th>
                                    <th>Tgl & Jam Timbang</th>
                                    <th>Jml Kimia</th>
                                    <th class="text-right">Target Wt (g)</th>
                                    <th class="text-right">Actual Wt (g)</th>
                                    <th class="text-center">Status Timbang</th>
                                    <th class="text-center">Status Pakai OP</th>
                                    <th class="text-center" style="width: 130px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dyeStuffs as $item)
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="barcode-checkbox" value="{{ $item->barcode }}">
                                        </td>
                                        <td>
                                            <span class="badge badge-dark p-2" style="font-size: 0.9rem; letter-spacing: 0.5px;">{{ $item->barcode }}</span>
                                        </td>
                                        <td><span class="badge badge-light border">{{ $item->recipe_code ?: '-' }}</span></td>
                                        <td><strong>{{ $item->machine ?: '-' }}</strong></td>
                                        <td><small class="text-muted">{{ $item->product_lot ?: '-' }}</small></td>
                                        <td>
                                            <small><i class="far fa-calendar-alt text-muted mr-1"></i>{{ $item->comp_date }} {{ $item->comp_time }}</small>
                                        </td>
                                        <td><span class="badge badge-info">{{ $item->items_count }} item</span></td>
                                        <td class="text-right">{{ number_format($item->total_target_wt, 2) }} g</td>
                                        <td class="text-right">
                                            <strong class="{{ $item->total_actual_wt > 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($item->total_actual_wt, 2) }} g
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            @if ($item->status_timbang === 'sudah')
                                                <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Sudah Ditimbang</span>
                                            @elseif ($item->status_timbang === 'parsial')
                                                <span class="badge badge-warning text-dark" title="{{ $item->weighed_count }}/{{ $item->items_count }} item ditimbang">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>Parsial ({{ $item->weighed_count }}/{{ $item->items_count }})
                                                </span>
                                            @else
                                                <span class="badge badge-danger"><i class="fas fa-hourglass-half mr-1"></i>Belum Ditimbang</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($item->isUsedByProses)
                                                <span class="badge badge-success"><i class="fas fa-link mr-1"></i>Sudah Dipakai di OP ({{ $item->usedCount }})</span>
                                            @else
                                                <span class="badge badge-secondary"><i class="fas fa-times-circle mr-1"></i>Belum Dipakai</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('dye-stuff.show', $item->barcode) }}" class="btn btn-info btn-sm shadow-sm" title="Detail">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                            <a href="{{ route('dye-stuff.print', $item->barcode) }}" target="_blank" class="btn btn-secondary btn-sm shadow-sm" title="Print">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-2 text-secondary"></i><br>
                                            Tidak ada data Dye Stuff yang ditemukan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination Footer -->
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                        <small class="text-muted">
                            Menampilkan {{ $dyeStuffs->firstItem() ?? 0 }} - {{ $dyeStuffs->lastItem() ?? 0 }} dari {{ number_format($dyeStuffs->total()) }} data
                        </small>
                        <div class="ml-auto text-right">
                            {{ $dyeStuffs->links('pagination::bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function () {
                $('#selectAll').on('change', function () {
                    $('.barcode-checkbox').prop('checked', this.checked);
                });

                $('#bulkPrintBtn').on('click', function () {
                    var selected = [];
                    $('.barcode-checkbox:checked').each(function () {
                        selected.push($(this).val());
                    });

                    if (selected.length === 0) {
                        Swal.fire('Peringatan', 'Silakan pilih setidaknya satu barcode untuk dicetak.', 'warning');
                        return;
                    }

                    var url = "{{ route('dye-stuff.print-bulk') }}?ids=" + selected.join(',');
                    window.open(url, '_blank');
                });
            });
        </script>
    @endpush
@endsection
