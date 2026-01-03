@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Tambah Auxiliary</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('aux.index') }}">Auxiliary</a></li>
                            <li class="breadcrumb-item active">Tambah Auxiliary</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('aux.store') }}" method="POST">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Form Tambah Auxiliary</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label>Jenis</label>
                                    <select name="jenis" class="form-control" required>
                                        <option value="" disabled {{ old('jenis') ? '' : 'selected' }}>-- Pilih Jenis
                                            --</option>
                                        @foreach (\App\Models\Auxl::getJenisOptions() as $key => $val)
                                            <option value="{{ $key }}"
                                                {{ old('jenis') == $key ? 'selected' : '' }}>{{ $val }}</option>
                                        @endforeach
                                    </select>
                                    @error('jenis')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label>Code</label>
                                    <input type="text" name="code" class="form-control" placeholder="Code"
                                        value="{{ old('code') }}" required>
                                    @error('code')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label>Konstruksi</label>
                                    <input type="text" name="konstruksi" class="form-control" placeholder="Konstruksi"
                                        value="{{ old('konstruksi') }}">
                                    @error('konstruksi')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label>Customer</label>
                                    <input type="text" name="customer" class="form-control" placeholder="Customer"
                                        value="{{ old('customer') }}">
                                    @error('customer')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label>Marketing</label>
                                    <input type="text" name="marketing" class="form-control" placeholder="Marketing"
                                        value="{{ old('marketing') }}">
                                    @error('marketing')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label>Date</label>
                                    <input type="date" name="date" class="form-control"
                                        value="{{ old('date', date('Y-m-d')) }}">
                                    @error('date')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-2">
                                    <label>Color</label>
                                    <input type="text" name="color" class="form-control" placeholder="Color"
                                        value="{{ old('color') }}">
                                    @error('color')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
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
                                @php
                                    $oldDetails = old('details', []);
                                @endphp

                                @if (count($oldDetails) > 0)
                                    @foreach ($oldDetails as $i => $d)
                                        <div class="row detail-row mb-2">
                                            <div class="col-md-6 col-12 mb-2 mb-md-0">
                                                <input type="text" name="details[{{ $i }}][auxiliary]"
                                                    class="form-control" placeholder="Nama Auxiliary"
                                                    value="{{ $d['auxiliary'] ?? '' }}" required>
                                            </div>
                                            <div class="col-md-6 col-12">
                                                <div class="input-group">
                                                    <input type="number" step="0.01"
                                                        name="details[{{ $i }}][konsentrasi]"
                                                        class="form-control" placeholder="Konsentrasi (kg)"
                                                        value="{{ $d['konsentrasi'] ?? '' }}" readonly required>
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-danger btn-remove-detail ms-2"
                                                            title="Hapus"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="row detail-row mb-2">
                                        <div class="col-md-6 col-12 mb-2 mb-md-0">
                                            <input type="text" name="details[0][auxiliary]" class="form-control"
                                                placeholder="Nama Auxiliary" required>
                                        </div>
                                        <div class="col-md-6 col-12">
                                            <div class="input-group">
                                                <input type="number" step="0.01" name="details[0][konsentrasi]"
                                                    class="form-control" placeholder="Konsentrasi (kg)" readonly required>
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-danger btn-remove-detail ms-2"
                                                        title="Hapus"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <a href="{{ route('aux.index') }}" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        let detailIndex = {{ max(1, count(old('details', []))) }};
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
                        <input type=\"number\" step=\"0.01\" name=\"details[${detailIndex}][konsentrasi]\" class=\"form-control\" placeholder=\"Konsentrasi (kg)\" readonly required>
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
        document.title = "Tambah Auxiliary";

        // === POLLING DATA TIMBANGAN ===
        async function fetchWeight() {
            try {
                const res = await fetch('http://localhost:8000/api/weight');
                if (!res.ok) return;
                const data = await res.json();
                if (typeof data.weight !== 'undefined') {
                    // Cari input konsentrasi terakhir yang readonly
                    const konsInputs = document.querySelectorAll('input[name^="details"][name$="[konsentrasi]"]');
                    if (konsInputs.length > 0) {
                        konsInputs[konsInputs.length - 1].value = data.weight;
                    }
                }
            } catch {}
        }
        setInterval(fetchWeight, 1000); // polling tiap 1 detik
    </script>
@endsection
