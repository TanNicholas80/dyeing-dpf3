@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">User</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item active"><a>User</a></li>
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
                                <h3 class="card-title">Data User</h3>

                                @php
                                    $canManageUsers = (Auth::user()->role ?? null) !== 'owner';
                                @endphp

                                @if ($canManageUsers)
                                    <div class="d-flex justify-content-end">
                                        <a href="{{ route('user.create') }}" class="btn-sm btn-primary">
                                            <i class="fas fa-plus"></i> Tambah
                                        </a>
                                    </div>
                                @endif
                            </div>

                            <div class="card-body">
                                <table id="user" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Mesin</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            @if ($canManageUsers)
                                            <th>Aksi</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div>
        </section>
    </div>

    @if ($canManageUsers)
    <!-- Modal Hapus -->
    <div class="modal fade" id="modal-hapus-global">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Konfirmasi Hapus Data</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah anda yakin ingin menghapus user <b id="hapus-user-nama"></b>?</p>
                </div>
                <div class="modal-footer justify-content-between">
                    <form id="form-hapus-global" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

@endsection

@section('scripts')
<script>
    document.title = "Data User";

    @if ($canManageUsers)
    function showDeleteModal(url, nama) {
        $('#hapus-user-nama').text(nama);
        $('#form-hapus-global').attr('action', url);
        $('#modal-hapus-global').modal('show');
    }
    @endif

    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#user')) {
            $('#user').DataTable().clear().destroy();
            $('#user_wrapper').empty(); // Handle buttons appended container if any
        }
        
        $('#user').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('user.index') }}",
            responsive: false,
            autoWidth: false,
            scrollX: true,
            columns: [
                { data: 'nama', name: 'nama' },
                { data: 'mesin_badge', name: 'mesin' },
                { data: 'username', name: 'username' },
                { data: 'role_formatted', name: 'role' },
                @if ($canManageUsers)
                { data: 'action', name: 'action', orderable: false, searchable: false }
                @endif
            ]
        }).buttons().container().appendTo('#user_wrapper .col-md-6:eq(0)');
    });
</script>
@endsection
