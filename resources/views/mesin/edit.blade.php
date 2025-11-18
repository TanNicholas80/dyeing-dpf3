@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Edit Mesin</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('mesin.index') }}">Mesin</a>
                            </li>
                            <li class="breadcrumb-item active">Edit Mesin</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('mesin.update', $mesin->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Form Edit Mesin</h3>
                        </div>

                        <div class="card-body">
                            <div class="form-group">
                                <label>Jenis Mesin</label>
                                <input type="text" name="jenis_mesin" class="form-control"
                                    value="{{ old('jenis_mesin', $mesin->jenis_mesin) }}" required>
                                @error('jenis_mesin')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="1" {{ $mesin->status ? 'selected' : '' }}>Aktif</option>
                                    <option value="0" {{ !$mesin->status ? 'selected' : '' }}>Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        document.title = "Edit Mesin";
    </script>
@endsection
