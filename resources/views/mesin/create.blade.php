@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Tambah Mesin</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}">Home</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('mesin.index') }}">Mesin</a>
                            </li>
                            <li class="breadcrumb-item active">Tambah Mesin</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Form Tambah Mesin</h3>
                    </div>

                    <form action="{{ route('mesin.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="form-group">
                                <label for="jenis_mesin">Jenis Mesin</label>
                                <input type="text" id="jenis_mesin" name="jenis_mesin" class="form-control"
                                    placeholder="Masukkan jenis mesin" required>
                                @error('jenis_mesin')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control" required>
                                    <option value="1">Aktif</option>
                                    <option value="0" selected>Nonaktif</option>
                                </select>
                            </div>
                        </div>

                        <div class="card-footer text-right">
                            <a href="{{ route('mesin.index') }}" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <script>
        document.title = "Tambah Mesin";
    </script>
@endsection
