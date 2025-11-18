@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Mesin</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item active"><a>Mesin</a></li>
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
                                <h3 class="card-title">Data Mesin</h3>

                                @if (Auth::user()->role != 'owner')
                                    <div class="d-flex justify-content-end">
                                        <a href="{{ route('mesin.create') }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Tambah
                                        </a>
                                    </div>
                                @endif
                            </div>

                            <div class="card-body">
                                <table id="mesin" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Jenis Mesin</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($mesins as $i => $mesin)
                                            <tr>
                                                <td>{{ $mesin->jenis_mesin }}</td>
                                                <td>
                                                    <button
                                                        class="btn btn-sm toggle-status {{ $mesin->status ? 'btn-success' : 'btn-secondary' }}"
                                                        data-id="{{ $mesin->id }}">
                                                        {{ $mesin->status ? 'Hidup' : 'Mati' }}
                                                    </button>
                                                </td>
                                                <td>
                                                    <a href="{{ route('mesin.edit', $mesin->id) }}"
                                                        class="btn btn-warning btn-sm mr-2">
                                                        <i class="fas fa-pen"></i> Edit
                                                    </a>
                                                    <a href="#" data-toggle="modal"
                                                        data-target="#modal-hapus{{ $mesin->id }}"
                                                        class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash-alt"></i> Hapus
                                                    </a>
                                                </td>
                                            </tr>

                                            <!-- Modal Hapus -->
                                            <div class="modal fade" id="modal-hapus{{ $mesin->id }}">
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
                                                            <p>Apakah anda yakin ingin menghapus mesin
                                                                <b>{{ $mesin->jenis_mesin }}</b>?
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer justify-content-between">
                                                            <form action="{{ route('mesin.destroy', $mesin->id) }}"
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
        document.title = "Data Mesin";

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.toggle-status').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var mesinId = this.getAttribute('data-id');
                    var button = this;
                    fetch('/mesin/' + mesinId + '/toggle-status', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({})
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            button.textContent = data.label;
                            button.classList.toggle('btn-success', data.status);
                            button.classList.toggle('btn-secondary', !data.status);
                        }
                    });
                });
            });

            // Perbarui status mesin setiap detik
            setInterval(function () {
                document.querySelectorAll('.toggle-status').forEach(function (btn) {
                    var mesinId = btn.getAttribute('data-id');
                    fetch('/mesin/' + mesinId + '/status')
                        .then(response => response.json())
                        .then(data => {
                            if (typeof data.status !== 'undefined') {
                                btn.textContent = data.label;
                                btn.classList.toggle('btn-success', data.status);
                                btn.classList.toggle('btn-secondary', !data.status);
                            }
                        });
                });
            }, 1000);
        });
    </script>
@endsection
