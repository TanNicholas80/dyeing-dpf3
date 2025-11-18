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

                                @if (Auth::user()->role != 'owner')
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
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($user as $u)
                                            <tr>
                                                <td>{{ $u->nama }}</td>
                                                <td>
                                                    @if (empty($u->mesin))
                                                        <span class="badge bg-success">Semua Mesin</span>
                                                    @else
                                                        <span class="badge bg-info">{{ $u->mesin }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $u->username }}</td>
                                                <td>
                                                    @if ($u->role === 'super_admin')
                                                        Super Admin
                                                    @else
                                                        {{ ucfirst($u->role) }}
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('user.edit', ['id' => $u->id]) }}"
                                                        class="btn btn-warning btn-sm mr-2">
                                                        <i class="fas fa-pen"></i> Edit
                                                    </a>
                                                    <a href="" data-toggle="modal"
                                                        data-target="#modal-hapus{{ $u->id }}"
                                                        class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash-alt"></i> Hapus
                                                    </a>
                                                </td>

                                            </tr>

                                            <!-- Modal Hapus -->
                                            <div class="modal fade" id="modal-hapus{{ $u->id }}">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title">Konfirmasi Hapus Data</h4>
                                                            <button type="button" class="close" data-dismiss="modal"
                                                                aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Apakah anda yakin ingin menghapus user
                                                                <b>{{ $u->nama }} - {{ $u->username }}</b>?
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer justify-content-between">
                                                            <form action="{{ route('user.delete', ['id' => $u->id]) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="button" class="btn btn-default"
                                                                    data-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-danger">Ya,
                                                                    Hapus</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
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

    <script>
        document.title = "Data User";
    </script>
@endsection
