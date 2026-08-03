@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Auxiliary</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item active"><a>Auxiliary</a></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Data Auxiliary </h3>
                                @php
                                    $userRole = strtolower(Auth::user()->role ?? '');
                                    $canManageAuxl = !in_array($userRole, ['owner', 'scm']);
                                    $isSuperAdmin = $userRole === 'super_admin';
                                @endphp
                                <div class="d-flex justify-content-end" style="gap: 0.5rem;">
                                    <button type="button" class="btn btn-info btn-sm" id="bulkPrintBtn">
                                        <i class="fas fa-print"></i> Print Barcode
                                    </button>
                                    @if ($canManageAuxl)
                                        <a href="{{ route('aux.create') }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-plus"></i> Tambah Auxiliary
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <table id="auxl" class="table table-head-fixed text-nowrap table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 30px;"><input type="checkbox" id="selectAll"></th>
                                            <th>Barcode</th>
                                            <th>Jenis</th>
                                            <th>Tipe</th>
                                            <th>Step Process</th>
                                            <th>Total Wt (Kg)</th>
                                            <th>Volume (L)</th>
                                            <th>Code</th>
                                            <th>Konstruksi</th>
                                            <th>Customer</th>
                                            <th>Marketing</th>
                                            <th>Date</th>
                                            <th>Color</th>
                                            <th>Dipakai Proses</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($auxls->sortBy('barcode') as $auxl)
                                            @php
                                                $pendingApproval = $auxl->pendingApproval ?? null;
                                                $waitingLabel = $pendingApproval
                                                    ? strtoupper($pendingApproval->type)
                                                    : null;
                                            @endphp
                                            <tr class="{{ $pendingApproval ? 'table-warning' : '' }}">
                                                <td><input type="checkbox" class="barcode-checkbox"
                                                        value="{{ $auxl->barcode }}" data-id="{{ $auxl->id }}"
                                                        data-code="{{ $auxl->code }}" data-customer="{{ $auxl->customer }}"
                                                        data-marketing="{{ $auxl->marketing }}"></td>
                                                <td><strong>{{ $auxl->barcode }}</strong></td>
                                                <td>{{ \App\Models\Auxl::getJenisOptions()[$auxl->jenis] ?? ucfirst($auxl->jenis ?? '-') }}
                                                </td>
                                                <td>
                                                    @if(($auxl->tipe ?? 'normal') === 'addition')
                                                        <span class="badge badge-warning">Addition (Topping)</span>
                                                    @else
                                                        <span class="badge badge-info">Normal (Utama)</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($auxl->step_proses)
                                                        @if(($auxl->tipe ?? 'normal') === 'addition')
                                                            <span
                                                                class="badge badge-secondary">{{ $auxl->step_proses == 1 ? 'Reactive' : ($auxl->step_proses == 2 ? 'Dispers' : 'Step ' . $auxl->step_proses) }}</span>
                                                        @else
                                                            <span class="badge badge-secondary">Step {{ $auxl->step_proses }}</span>
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ number_format($auxl->total_wt, 1) }} kg</td>
                                                <td><span
                                                        class="text-primary font-weight-bold">{{ number_format($auxl->volume_litres, 1) }}
                                                        L</span></td>
                                                <td>{{ $auxl->code }}</td>
                                                <td>{{ $auxl->konstruksi }}</td>
                                                <td>{{ $auxl->customer }}</td>
                                                <td>{{ $auxl->marketing }}</td>
                                                <td>{{ $auxl->date }}</td>
                                                <td>{{ $auxl->color }}</td>
                                                <td>
                                                    @if ($auxl->isUsedByProses ?? false)
                                                        <span class="badge badge-success">Sudah dipakai
                                                            ({{ $auxl->usedCount ?? 0 }})</span>
                                                    @else
                                                        <span class="badge badge-secondary">Belum dipakai</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($pendingApproval)
                                                        <span class="badge badge-warning text-dark">
                                                            Menunggu approval {{ $waitingLabel }}
                                                        </span>
                                                    @else
                                                        <a href="{{ route('aux.show', $auxl->id) }}"
                                                            class="btn btn-info btn-sm mr-1">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </a>

                                                        @if ($canManageAuxl)
                                                            <a href="{{ route('aux.edit', $auxl->id) }}"
                                                                class="btn btn-warning btn-sm mr-1">
                                                                <i class="fas fa-pen"></i> Edit
                                                            </a>
                                                        @endif

                                                        @if ($isSuperAdmin)
                                                            <button type="button" data-toggle="modal"
                                                                data-target="#modal-hapus{{ $auxl->id }}"
                                                                class="btn btn-danger btn-sm">
                                                                <i class="fas fa-trash-alt"></i> Hapus
                                                            </button>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <!-- Modal Hapus List -->
                                @if ($isSuperAdmin)
                                    @foreach ($auxls as $auxl)
                                        <div class="modal fade" id="modal-hapus{{ $auxl->id }}">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">Konfirmasi Hapus Data</h4>
                                                        <button type="button" class="close" data-dismiss="modal">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body text-left" style="white-space: normal;">
                                                        <p>Yakin ingin menghapus data auxiliary <b>{{ $auxl->barcode }}</b>?</p>
                                                    </div>
                                                    <div class="modal-footer justify-content-between">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                        <form action="{{ route('aux.destroy', $auxl->id) }}" method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger">Hapus</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        document.title = "Data Auxiliary";
        document.addEventListener('DOMContentLoaded', function () {
            const selectAllEl = document.getElementById('selectAll');
            if (selectAllEl) {
                selectAllEl.addEventListener('change', function () {
                    let checked = this.checked;
                    document.querySelectorAll('.barcode-checkbox').forEach(function (cb) {
                        cb.checked = checked;
                    });
                });
            }

            // Print Bulk Barcode / Ticket
            const bulkPrintBtn = document.getElementById('bulkPrintBtn');
            if (bulkPrintBtn) {
                bulkPrintBtn.addEventListener('click', function () {
                    let selected = Array.from(document.querySelectorAll('.barcode-checkbox:checked'));
                    if (selected.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validasi',
                            text: 'Pilih data Auxiliary yang ingin di-print!'
                        });
                        return;
                    }
                    let ids = selected.map(cb => cb.getAttribute('data-id')).filter(Boolean).join(',');
                    window.open("{{ route('aux.print-bulk') }}?ids=" + ids, '_blank');
                });
            }
        });
    </script>
@endsection