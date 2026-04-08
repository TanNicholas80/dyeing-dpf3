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
                                                <th>Dipakai Proses</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
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

    @if ($isSuperAdmin)
    <!-- Modal Hapus -->
    <div class="modal fade" id="modal-hapus-global">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Konfirmasi Hapus Data</h4>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Yakin ingin menghapus data auxiliary <b id="hapus-aux-barcode"></b>?</p>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <form id="form-hapus-global" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        document.title = "Data Auxiliary";
        
        @if ($isSuperAdmin)
        function showDeleteModal(url, barcode) {
            $('#hapus-aux-barcode').text(barcode);
            $('#form-hapus-global').attr('action', url);
            $('#modal-hapus-global').modal('show');
        }
        @endif

        // Select all functionality
        document.addEventListener('DOMContentLoaded', function() {
            var selectAllEl = document.getElementById('selectAll');
            if (selectAllEl) {
                selectAllEl.addEventListener('change', function() {
                    let checked = this.checked;
                    document.querySelectorAll('.barcode-checkbox').forEach(function(cb) {
                        cb.checked = checked;
                    });
                });
            }

            if ($.fn.DataTable.isDataTable('#auxl')) {
                $('#auxl').DataTable().clear().destroy();
                $('#auxl_wrapper').empty();
            }
            $('#auxl').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('aux.index') }}",
                responsive: false,
                autoWidth: false,
                scrollX: true,
                order: [[1, 'asc']], // Order by Barcode default
                columns: [
                    { data: 'checkbox', name: 'checkbox', orderable: false, searchable: false },
                    { data: 'barcode', name: 'barcode' },
                    { data: 'jenis', name: 'jenis' },
                    { data: 'code', name: 'code' },
                    { data: 'konstruksi', name: 'konstruksi' },
                    { data: 'customer', name: 'customer' },
                    { data: 'marketing', name: 'marketing' },
                    { data: 'date', name: 'date' },
                    { data: 'color', name: 'color' },
                    { data: 'dipakai', name: 'dipakai', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            }).buttons().container().appendTo('#auxl_wrapper .col-md-6:eq(0)');
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
                Swal.fire({
                    icon: 'warning',
                    title: 'Validasi',
                    text: 'Pilih data barcode yang ingin di-print!'
                });
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
