@extends('layout.main')
@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Approval VP</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active"><a>Approval VP</a></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        @php
        // Helper function untuk konversi detik ke format waktu
        if (!function_exists('detikKeWaktu')) {
            function detikKeWaktu($detik) {
                if ($detik === null || $detik === '') return '-';
                $detik = (int) $detik;
                $jam = floor($detik / 3600);
                $menit = floor(($detik % 3600) / 60);
                $detik = $detik % 60;
                return sprintf('%02d:%02d:%02d', $jam, $menit, $detik);
            }
        }
        // Permission check untuk owner
        $userRole = Auth::user()->role ?? null;
        $canManageApproval = strtolower($userRole) !== 'owner';
        @endphp
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Daftar Approval VP</h3>
                        </div>
                        <div class="card-body">
                            <table id="approval_vp" class="table table-head-fixed text-nowrap table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No OP</th>
                                        <th>Status</th>
                                        <th>Action Type</th>
                                        <th>History Data</th>
                                        <th>Dilakukan Oleh</th>
                                        <th>Approve Oleh</th>
                                        <th>Tanggal Request</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>


<!-- DataTables configuration -->
<script>
    document.title = "Approval VP";
    document.addEventListener('DOMContentLoaded', function() {
        if ($.fn.DataTable.isDataTable('#approval_vp')) {
            $('#approval_vp').DataTable().destroy();
        }
        
        let table = $('#approval_vp').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('approval.vp') }}',
            columns: [
                { data: 'no_op_display', name: 'no_op_display', orderable: false, searchable: false },
                { data: 'status_badge', name: 'status', orderable: false, searchable: false },
                { data: 'action_label', name: 'action', orderable: false, searchable: false },
                { data: 'history_btn', name: 'history_data', orderable: false, searchable: false },
                { data: 'requester_info', name: 'requested_by', orderable: false, searchable: false },
                { data: 'approver_info', name: 'approved_by', orderable: false, searchable: false },
                { data: 'tanggal_request', name: 'created_at' },
                { data: 'aksi', name: 'aksi', orderable: false, searchable: false }
            ],
            order: [[6, 'desc']],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
        
        // Modal inside datatables handled natively by Bootstrap because we rendered them in the column.
    });
</script>

@endsection
