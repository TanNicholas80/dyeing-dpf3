@extends('layout.main')
@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Activity Log</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active"><a>Activity Log</a></li>
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
                            <h3 class="card-title">Data Activity Log</h3>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="activity_log" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>User</th>
                                            <th>Log Name</th>
                                            <th>Description</th>
                                            <th>Event</th>
                                            <th>Properties</th>
                                            <th>Created At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal untuk melihat detail data -->
<div class="modal fade" id="dataModal" tabindex="-1" role="dialog" aria-labelledby="dataModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataModalLabel">Detail Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="dataContent" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.title = "Activity Log";

    function formatKey(key) {
        return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function formatValueByKey(key, value, indent = '') {
        if (value === null || value === undefined) {
            return '-';
        }

        if (key === 'status' && (value === 0 || value === 1 || value === '0' || value === '1')) {
            const statusValue = parseInt(value);
            return statusValue === 1 ? 'Nyala' : 'Mati';
        }

        if (Array.isArray(value)) {
            if (!value.length) return '[]';
            return value.map(item => {
                if (typeof item === 'object' && item !== null) {
                    return `\n${indent}- ${formatObject(item, indent + '  ')}`;
                }
                return `\n${indent}- ${formatValueByKey(key, item, indent + '  ')}`;
            }).join('');
        }

        if (typeof value === 'object') return `\n${formatObject(value, indent + '  ')}`;
        if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
        if (typeof value === 'number' && Math.abs(value) >= 1000) return new Intl.NumberFormat('id-ID').format(value);

        return value;
    }

    function formatObject(obj, indent = '') {
        return Object.keys(obj).map(childKey => {
            const formattedValue = formatValueByKey(childKey, obj[childKey], indent);
            return `${indent}${formatKey(childKey)}: ${formattedValue}`;
        }).join('\n');
    }

    function showDataModal(button) {
        const content = button.getAttribute('data-content');
        const title = button.getAttribute('data-title');

        try {
            const data = JSON.parse(content);
            document.getElementById('dataModalLabel').textContent = title;

            let formattedContent = '';
            if (typeof data === 'object' && data !== null) {
                formattedContent = formatObject(data);
            } else {
                formattedContent = data?.toString?.() ?? '';
            }

            document.getElementById('dataContent').textContent = formattedContent;
            $('#dataModal').modal('show');
        } catch (e) {
            console.error('Error parsing JSON:', e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal memuat data' });
        }
    }

    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#activity_log')) {
            $('#activity_log').DataTable().clear().destroy();
            $('#activity_log_wrapper').empty();
        }
        $('#activity_log').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('activity-log.index') }}",
            responsive: false,
            autoWidth: false,
            scrollX: true,
            order: [[6, 'desc']], // order by created_at by default
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'user', name: 'user', orderable: false, searchable: false },
                { data: 'log_name', name: 'log_name' },
                { data: 'description', name: 'description' },
                { data: 'event', name: 'event' },
                { data: 'properties', name: 'properties', orderable: false, searchable: false },
                { data: 'created_at', name: 'created_at' }
            ]
        }).buttons().container().appendTo('#activity_log_wrapper .col-md-6:eq(0)');

        // Gunakan event delegation agar tombol showDataModal pada Pagination Datatables tetap jalan
        $(document).on('click', 'button[onclick="showDataModal(this)"]', function(e) {
            e.preventDefault();
            // karena button memiliki attribute onclick dari addColumn, kita biarkan onclick jalan
            // tanpa memblokir
        });
    });
</script>
@endsection

