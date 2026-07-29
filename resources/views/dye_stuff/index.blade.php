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
                            <li class="breadcrumb-item active">Dye Stuff</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daftar Dye Stuff (Zat Warna)</h3>
                        <div class="d-flex justify-content-end" style="gap: 0.75rem;">
                            <button type="button" class="btn btn-info btn-sm" id="bulkPrintBtn">
                                <i class="fas fa-print"></i> Print Barcode
                            </button>
                            @if (!in_array(Auth::user()->role ?? '', ['scm']))
                                <a href="{{ route('dye-stuff.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Tambah Dye Stuff
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="dye-stuff-table" class="table table-head-fixed text-nowrap table-hover table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 30px;"><input type="checkbox" id="selectAll"></th>
                                    <th>Barcode</th>
                                    <th>Tipe</th>
                                    <th>Jenis</th>
                                    <th>Planning Proses</th>
                                    <th>Status Proses</th>
                                    <th>Step</th>
                                    <th>Total Wt (Kg)</th>
                                    <th>Volume (L)</th>
                                    <th>Status Pakai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dyeStuffs as $item)
                                    @php
                                        $firstDetail = optional(optional($item->proses)->details)->first();
                                        $noOp = $firstDetail->no_op ?? '-';
                                        $noPartai = $firstDetail->no_partai ?? '-';
                                        $customer = $firstDetail->customer ?? '-';
                                        $marketing = $firstDetail->marketing ?? '-';
                                        $proses = $item->proses;
                                        $pendingApproval = $item->pendingApproval ?? null;
                                        $waitingLabel = $pendingApproval ? strtoupper($pendingApproval->type) : null;
                                    @endphp
                                    <tr class="{{ $pendingApproval ? 'table-warning' : '' }}">
                                        <td>
                                            <input type="checkbox" class="barcode-checkbox"
                                                value="{{ $item->barcode }}"
                                                data-id="{{ $item->id }}"
                                                data-op="{{ $noOp }}"
                                                data-partai="{{ $noPartai }}"
                                                data-customer="{{ $customer }}">
                                        </td>
                                        <td><span class="badge badge-dark">{{ $item->barcode }}</span></td>
                                        <td>
                                            @if(($item->tipe ?? 'normal') === 'additional')
                                                <span class="badge badge-warning">Addition (Topping)</span>
                                            @else
                                                <span class="badge badge-info">Normal (Utama)</span>
                                            @endif
                                        </td>
                                        <td>{{ \App\Models\DyeStuff::getJenisOptions()[$item->jenis] ?? ucfirst($item->jenis ?? '-') }}</td>
                                        <td>
                                            @if($proses)
                                                <strong>OP:</strong> {{ $noOp }} | <strong>Partai:</strong> {{ $noPartai }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($proses)
                                                @if(!is_null($proses->selesai))
                                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Selesai</span>
                                                @elseif(!is_null($proses->mulai))
                                                    <span class="badge badge-primary"><i class="fas fa-play"></i> Sedang Berjalan</span>
                                                @else
                                                    <span class="badge badge-secondary"><i class="fas fa-clock"></i> Belum Berjalan</span>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($item->step_proses)
                                                @if(($item->tipe ?? 'normal') === 'additional')
                                                    <span class="badge badge-secondary">{{ $item->step_proses == 1 ? 'Reactive' : ($item->step_proses == 2 ? 'Dispers' : 'Step ' . $item->step_proses) }}</span>
                                                @else
                                                    <span class="badge badge-secondary">Step {{ $item->step_proses }}</span>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ number_format($item->total_wt, 1) }} kg</td>
                                        <td>{{ number_format($item->volume_litres, 1) }} L</td>
                                        <td>
                                            @if ($item->isUsedByProses ?? false)
                                                <span class="badge badge-success">Sudah dipakai ({{ $item->usedCount ?? 0 }})</span>
                                            @else
                                                <span class="badge badge-secondary">Belum dipakai</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($pendingApproval)
                                                <span class="badge badge-warning text-dark p-2">
                                                    <i class="fas fa-hourglass-half mr-1"></i> Menunggu approval {{ $waitingLabel }}
                                                </span>
                                            @else
                                                <a href="{{ route('dye-stuff.show', $item->id) }}" class="btn btn-info btn-sm mr-1" title="Detail">
                                                    <i class="fas fa-eye"></i> Detail
                                                </a>
                                                @if (!in_array(Auth::user()->role ?? '', ['scm']))
                                                    <a href="{{ route('dye-stuff.edit', $item->id) }}" class="btn btn-warning btn-sm mr-1 text-white" title="Edit">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <form action="{{ route('dye-stuff.destroy', $item->id) }}" method="POST" class="d-inline form-delete-dyestuff">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-danger btn-sm btn-delete-dyestuff" title="Hapus">
                                                            <i class="fas fa-trash"></i> Hapus
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#dye-stuff-table').DataTable({
                "responsive": false,
                "scrollX": true,
                "autoWidth": false,
                "order": []
            });

            // Select all checkbox handler
            $('#selectAll').on('change', function() {
                var checked = this.checked;
                $('.barcode-checkbox').prop('checked', checked);
            });

            // Delete confirmation
            $(document).on('click', '.btn-delete-dyestuff', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');
                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: "Data Dye Stuff yang dihapus tidak dapat dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            function generateQRCodeWithLogo(value, size = 200) {
                return new Promise((resolve) => {
                    const tempCanvas = document.createElement('canvas');
                    const qrCode = new QRious({
                        element: tempCanvas,
                        value: value,
                        size: size,
                        level: 'H'
                    });
                    const logo = new Image();
                    logo.src = "{{ asset('images/logo.png') }}";
                    logo.onload = function() {
                        const ctx = tempCanvas.getContext('2d');
                        const logoSize = Math.floor(size * 0.22);
                        const center = (size - logoSize) / 2;
                        const padding = Math.max(2, Math.floor(logoSize * 0.15));

                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(center - padding / 2, center - padding / 2, logoSize + padding, logoSize + padding);
                        ctx.drawImage(logo, center, center, logoSize, logoSize);
                        resolve(tempCanvas.toDataURL('image/png'));
                    };
                    logo.onerror = function() {
                        resolve(tempCanvas.toDataURL('image/png'));
                    };
                });
            }

            // Bulk Print PDF generator (Page per barcode)
            async function generateInspectPDFPage(pdf, barcode, op, partai, customer, isFirstPage) {
                if (!isFirstPage) pdf.addPage([65, 25], 'landscape');
                pdf.setFont("Courier", "Bold");
                pdf.setFontSize(9);

                const qrDataUrl = await generateQRCodeWithLogo(barcode, 200);
                pdf.addImage(qrDataUrl, 'PNG', 2, 2, 21, 21, undefined, 'FAST');

                let startX = 25;
                let startY = 6;
                let lineGap = 5;
                pdf.text(barcode, startX, startY);
                pdf.text(op !== '-' ? op : '', startX, startY + lineGap);
                pdf.text(partai !== '-' ? `Partai: ${partai}` : '', startX, startY + lineGap * 2);
                pdf.text(customer !== '-' ? customer : '', startX, startY + lineGap * 3);
            }

            $('#bulkPrintBtn').on('click', function() {
                let selected = $('.barcode-checkbox:checked');
                if (selected.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Validasi',
                        text: 'Pilih data Dye Stuff yang ingin di-print!'
                    });
                    return;
                }

                let ids = selected.map(function() {
                    return $(this).data('id');
                }).get().join(',');

                window.open("{{ route('dye-stuff.print-bulk') }}?ids=" + ids, '_blank');
            });
        });
    </script>
@endsection
