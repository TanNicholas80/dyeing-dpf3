@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Edit Auxiliary</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('aux.index') }}">Auxiliary</a></li>
                            <li class="breadcrumb-item active">Edit Auxiliary</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('aux.update', $auxl->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Form Edit Auxiliary</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label>Jenis</label>
                                    <select name="jenis" class="form-control" required>
                                        @foreach(\App\Models\Auxl::getJenisOptions() as $key => $val)
                                            <option value="{{ $key }}" {{ $auxl->jenis == $key ? 'selected' : '' }}>{{ $val }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label>Code</label>
                                    <input type="text" name="code" class="form-control" value="{{ $auxl->code }}" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label>Konstruksi</label>
                                    <input type="text" name="konstruksi" class="form-control" value="{{ $auxl->konstruksi }}">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label>Customer</label>
                                    <input type="text" name="customer" class="form-control" value="{{ $auxl->customer }}">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label>Marketing</label>
                                    <input type="text" name="marketing" class="form-control" value="{{ $auxl->marketing }}">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label>Date</label>
                                    <input type="date" name="date" class="form-control" value="{{ $auxl->date }}">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label>Color</label>
                                    <input type="text" name="color" class="form-control" value="{{ $auxl->color }}">
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0">Data Detail Auxiliary</h5>
                                <button type="button" class="btn btn-success" id="btn-add-detail" title="Tambah Detail">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div id="details-list">
                                @foreach($auxl->details as $i => $detail)
                                    <div class="row detail-row mb-2">
                                        <div class="col-md-6 col-12 mb-2 mb-md-0">
                                            <input type="text" name="details[{{ $i }}][auxiliary]" class="form-control" value="{{ $detail->auxiliary }}" required>
                                        </div>
                                        <div class="col-md-6 col-12">
                                            <div class="input-group">
                                                <input type="number" step="0.01" name="details[{{ $i }}][konsentrasi]" class="form-control" value="{{ $detail->konsentrasi }}" required>
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-danger btn-remove-detail ms-2" title="Hapus"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('aux.index') }}" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
    <script>
        let detailIndex = {{ count($auxl->details) }};
        document.getElementById('btn-add-detail').onclick = function() {
            const list = document.getElementById('details-list');
            const row = document.createElement('div');
            row.className = 'row detail-row mb-2';
            row.innerHTML = `
                <div class=\"col-md-6 col-12 mb-2 mb-md-0\">
                    <input type=\"text\" name=\"details[${detailIndex}][auxiliary]\" class=\"form-control\" placeholder=\"Nama Auxiliary\" required>
                </div>
                <div class=\"col-md-6 col-12\">
                    <div class=\"input-group\">
                        <input type=\"number\" step=\"0.01\" name=\"details[${detailIndex}][konsentrasi]\" class=\"form-control\" placeholder=\"Konsentrasi (kg)\" required>
                        <div class=\"input-group-append\">
                            <button type=\"button\" class=\"btn btn-danger btn-remove-detail ms-2\" title=\"Hapus\"><i class=\"fas fa-trash\"></i></button>
                        </div>
                    </div>
                </div>
            `;
            list.appendChild(row);
            detailIndex++;
        };
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-detail')) {
                const row = e.target.closest('.detail-row');
                if (row) row.remove();
            }
        });
        document.title = "Edit Auxiliary";
    </script>
@endsection
