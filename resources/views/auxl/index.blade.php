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

                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('aux.create') }}" class="btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Tambah
                                    </a>
                                </div>
                            </div>

                            <div class="card-body">
                                <table id="user" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
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
                                        @foreach ($auxls as $auxl)
                                            <tr>
                                                <td>{{ $auxl->barcode }}</td>
                                                <td>{{ ucfirst($auxl->jenis) }}</td>
                                                <td>{{ $auxl->code }}</td>
                                                <td>{{ $auxl->konstruksi }}</td>
                                                <td>{{ $auxl->customer }}</td>
                                                <td>{{ $auxl->marketing }}</td>
                                                <td>{{ $auxl->date }}</td>
                                                <td>{{ $auxl->color }}</td>
                                                <td>
                                                    <a href="{{ route('aux.show', $auxl->id) }}"
                                                        class="btn btn-info btn-sm mr-1">
                                                        <i class="fas fa-eye"></i> Detail
                                                    </a>
                                                    <a href="{{ route('aux.edit', $auxl->id) }}"
                                                        class="btn btn-warning btn-sm mr-1">
                                                        <i class="fas fa-pen"></i> Edit
                                                    </a>
                                                    <a href="#" data-toggle="modal"
                                                        data-target="#modal-hapus{{ $auxl->id }}"
                                                        class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash-alt"></i> Hapus
                                                    </a>
                                                    <!-- Modal Hapus -->
                                                    <div class="modal fade" id="modal-hapus{{ $auxl->id }}">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h4 class="modal-title">Konfirmasi Hapus Data</h4>
                                                                    <button type="button" class="close"
                                                                        data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Yakin ingin menghapus data auxiliary
                                                                        <b>{{ $auxl->barcode }}</b>?</p>
                                                                </div>
                                                                <div class="modal-footer justify-content-between">
                                                                    <button type="button" class="btn btn-secondary"
                                                                        data-dismiss="modal">Batal</button>
                                                                    <form action="{{ route('aux.destroy', $auxl->id) }}"
                                                                        method="POST" style="display:inline-block;">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit"
                                                                            class="btn btn-danger">Hapus</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
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
        document.title = "Data Auxiliary";
    </script>
@endsection
