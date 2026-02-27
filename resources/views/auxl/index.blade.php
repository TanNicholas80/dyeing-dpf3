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
                                    $canManageAuxl = $userRole !== 'owner';
                                    $isSuperAdmin = $userRole === 'super_admin';
                                @endphp
                                <div class="d-flex justify-content-end" style="gap: 0.75rem;">
                                    <button type="button" class="btn btn-info btn-sm" id="bulkPrintBtn"><i
                                            class="fas fa-print"></i> Print Barcode</button>
                                    @if ($canManageAuxl)
                                        <a href="{{ route('aux.create') }}" class="btn-sm btn-primary">
                                            <i class="fas fa-plus"></i> Tambah
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <form id="bulkPrintForm">
                                    <table id="auxl" class="table table-head-fixed text-nowrap">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th>Barcode</th>
                                                <th>Jenis</th>
                                                <th>Code</th>
                                                <th>Konstruksi</th>
                                                <th>Customer</th>
                                                <th>Marketing</th>
                                                <th>Date</th>
                                                <th>Color</th>
                                                <th>Action</th>
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
                                                            value="{{ $auxl->barcode }}" data-code="{{ $auxl->code }}"
                                                            data-customer="{{ $auxl->customer }}"
                                                            data-marketing="{{ $auxl->marketing }}"></td>
                                                    <td>{{ $auxl->barcode }}</td>
                                                    <td>{{ \App\Models\Auxl::getJenisOptions()[$auxl->jenis] ?? ucfirst($auxl->jenis ?? '-') }}</td>
                                                    <td>{{ $auxl->code }}</td>
                                                    <td>{{ $auxl->konstruksi }}</td>
                                                    <td>{{ $auxl->customer }}</td>
                                                    <td>{{ $auxl->marketing }}</td>
                                                    <td>{{ $auxl->date }}</td>
                                                    <td>{{ $auxl->color }}</td>
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
                                                                <a href="#" data-toggle="modal"
                                                                    data-target="#modal-hapus{{ $auxl->id }}"
                                                                    class="btn btn-danger btn-sm">
                                                                    <i class="fas fa-trash-alt"></i> Hapus
                                                                </a>

                                                                <!-- Modal Hapus -->
                                                                <div class="modal fade"
                                                                    id="modal-hapus{{ $auxl->id }}">
                                                                    <div class="modal-dialog">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h4 class="modal-title">Konfirmasi Hapus
                                                                                    Data</h4>
                                                                                <button type="button" class="close"
                                                                                    data-dismiss="modal">
                                                                                    <span>&times;</span>
                                                                                </button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <p>Yakin ingin menghapus data auxiliary
                                                                                    <b>{{ $auxl->barcode }}</b>?
                                                                                </p>
                                                                            </div>
                                                                            <div
                                                                                class="modal-footer justify-content-between">
                                                                                <button type="button"
                                                                                    class="btn btn-secondary"
                                                                                    data-dismiss="modal">Batal</button>
                                                                                <form
                                                                                    action="{{ route('aux.destroy', $auxl->id) }}"
                                                                                    method="POST">
                                                                                    @csrf
                                                                                    @method('DELETE')
                                                                                    <button type="submit"
                                                                                        class="btn btn-danger">Hapus</button>
                                                                                </form>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </form>
                            </div>
                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        document.title = "Data Auxiliary";
        // Select all functionality
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('selectAll').addEventListener('change', function() {
                let checked = this.checked;
                document.querySelectorAll('.barcode-checkbox').forEach(function(cb) {
                    cb.checked = checked;
                });
            });
        });
        // Bulk print barcode
        function generateInspectPDF(barcode, code, customer, marketing) {
            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: [65, 25]
            });
            pdf.setFont("Courier", "Bold");
            pdf.setFontSize(9);
            // QR code besar dan tajam
            const qrCode = new QRious({
                value: barcode,
                size: 200
            });
            const qrDataUrl = qrCode.toDataURL();
            pdf.addImage(qrDataUrl, 'PNG', 2, 2, 21, 21, undefined, 'FAST');
            // Cetak barcode lengkap
            let kodeCetak = barcode;
            // Data di kanan QR, urutan: barcode, code, customer, marketing
            let startX = 25;
            let startY = 6;
            let lineGap = 5;
            pdf.text(kodeCetak, startX, startY);
            pdf.text(code, startX, startY + lineGap);
            pdf.text(`${customer}`, startX, startY + lineGap * 2);
            pdf.text(`${marketing}`, startX, startY + lineGap * 3);
            return pdf;
        }

        function generateInspectPDFPage(pdf, barcode, code, customer, marketing, isFirstPage) {
            if (!isFirstPage) pdf.addPage([65, 25], 'landscape');
            pdf.setFont("Courier", "Bold");
            pdf.setFontSize(9);
            // QR code besar dan tajam
            const qrCode = new QRious({
                value: barcode,
                size: 200
            });
            const qrDataUrl = qrCode.toDataURL();
            pdf.addImage(qrDataUrl, 'PNG', 2, 2, 21, 21, undefined, 'FAST');
            // Data di kanan QR, urutan: barcode, code, customer, marketing
            let startX = 25;
            let startY = 6;
            let lineGap = 5;
            pdf.text(barcode, startX, startY);
            pdf.text(code, startX, startY + lineGap);
            pdf.text(`${customer}`, startX, startY + lineGap * 2);
            pdf.text(`${marketing}`, startX, startY + lineGap * 3);
        }
        document.getElementById('bulkPrintBtn').addEventListener('click', function() {
            let selected = Array.from(document.querySelectorAll('.barcode-checkbox:checked'));
            if (selected.length === 0) {
                alert('Pilih data barcode yang ingin di-print!');
                return;
            }
            const {
                jsPDF
            } = window.jspdf;
            let pdf = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: [65, 25]
            });
            selected.forEach(function(cb, idx) {
                let barcode = cb.value;
                let code = cb.getAttribute('data-code');
                let customer = cb.getAttribute('data-customer');
                let marketing = cb.getAttribute('data-marketing');
                generateInspectPDFPage(pdf, barcode, code, customer, marketing, idx === 0);
            });
            pdf.autoPrint();
            window.open(pdf.output('bloburl'), '_blank');
        });
    </script>
@endsection
