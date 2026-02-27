@extends('layout.main')
@section('content')
    <style>
        .select2-container .select2-selection--single {
            height: 38px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        .detail-row {
            align-items: center;
        }

        .btn-remove-detail {
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>

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
                                    <select name="customer" class="form-control select2-customer">
                                        @php $customerVal = old('customer', $auxl->customer); @endphp
                                        @if($customerVal)
                                            <option value="{{ $customerVal }}" selected>{{ $customerVal }}</option>
                                        @else
                                            <option value="">-- Pilih atau cari Customer --</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label>Marketing</label>
                                    <select name="marketing" class="form-control select2-marketing">
                                        @php $marketingVal = old('marketing', $auxl->marketing); @endphp
                                        @if($marketingVal)
                                            <option value="{{ $marketingVal }}" selected>{{ $marketingVal }}</option>
                                        @else
                                            <option value="">-- Pilih atau cari Marketing --</option>
                                        @endif
                                    </select>
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
                                            <select name="details[{{ $i }}][auxiliary]"
                                                    class="form-control select2-auxiliary"
                                                    data-selected="{{ $detail->auxiliary }}"
                                                    required>
                                                <option value="{{ $detail->auxiliary }}" selected>
                                                    {{ $detail->auxiliary }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 col-12">
                                            <div class="input-group">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="details[{{ $i }}][konsentrasi]"
                                                    class="form-control"
                                                    value="{{ $detail->konsentrasi }}"
                                                    placeholder="Konsentrasi (kg)"
                                                    readonly
                                                    required
                                                >
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-danger btn-remove-detail ms-2" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
    <!-- <script>
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
    </script> -->
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            // Select2 Customer dari API SAP
            $('.select2-customer').select2({
                placeholder: '-- Pilih atau cari Customer --',
                allowClear: true,
                minimumInputLength: 3,
                ajax: {
                    url: '/api/proxy-customer',
                    type: 'POST',
                    dataType: 'json',
                    delay: 500,
                    data: function (params) {
                        return {
                            q: params.term,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: Array.isArray(data.results) ? data.results : []
                        };
                    }
                }
            });

            // Select2 Marketing dari API SAP
            $('.select2-marketing').select2({
                placeholder: '-- Pilih atau cari Marketing --',
                allowClear: true,
                minimumInputLength: 3,
                ajax: {
                    url: '/api/proxy-marketing',
                    type: 'POST',
                    dataType: 'json',
                    delay: 500,
                    data: function (params) {
                        return {
                            q: params.term,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: Array.isArray(data.results) ? data.results : []
                        };
                    }
                }
            });

            // Fungsi untuk inisialisasi Select2 pada detail rows (disamakan dengan create)
            function initAuxiliarySelect2(selector) {
                $(selector).select2({
                    placeholder: '-- Pilih Auxiliary --',
                    minimumInputLength: 3,
                    ajax: {
                        url: '/api/proxy-auxiliary',
                        type: 'POST',
                        dataType: 'json',
                        delay: 500,
                        data: function (params) {
                            return { q: params.term };
                        },
                        processResults: function (data) {
                            if (Array.isArray(data.results)) {
                                return { results: data.results };
                            } else {
                                return { results: [] };
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error loading auxiliary data:', error);
                            return { results: [] };
                        }
                    }
                });
            }

            // Inisialisasi Select2 untuk detail rows yang sudah ada
            $('.select2-auxiliary').each(function () {
                initAuxiliarySelect2(this);
            });

            let detailIndex = parseInt('{{ count($auxl->details) }}', 10);

            // Handler untuk menambah detail row (disamakan dengan create)
            $('#btn-add-detail').on('click', function () {
                const row = $(`
                    <div class="row detail-row mb-2">
                        <div class="col-md-6 col-12 mb-2 mb-md-0">
                            <select name="details[${detailIndex}][auxiliary]" class="form-control select2-auxiliary" required></select>
                        </div>
                        <div class="col-md-6 col-12">
                            <div class="input-group">
                                <input
                                    type="number"
                                    step="0.01"
                                    name="details[${detailIndex}][konsentrasi]"
                                    class="form-control"
                                    placeholder="Konsentrasi (kg)"
                                    readonly
                                    required
                                >
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-danger btn-remove-detail ms-2" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);

                $('#details-list').append(row);
                initAuxiliarySelect2(row.find('.select2-auxiliary'));
                detailIndex++;
            });

            // Handler untuk menghapus detail row
            $(document).on('click', '.btn-remove-detail', function () {
                $(this).closest('.detail-row').remove();
            });

            // Fungsi untuk mengambil data berat dari timbangan (disamakan dengan create)
            async function fetchWeight() {
                try {
                    const res = await fetch('https://dpf3dunia.com/api/weight');
                    if (!res.ok) return;

                    const data = await res.json();
                    if (typeof data.weight !== 'undefined' && !isNaN(parseFloat(data.weight))) {
                        // Cari input konsentrasi terakhir (untuk baris yang sedang diisi)
                        const konsInputs = document.querySelectorAll(
                            'input[name^="details"][name$="[konsentrasi]"]'
                        );
                        if (konsInputs.length > 0) {
                            konsInputs[konsInputs.length - 1].value = parseFloat(data.weight).toFixed(2);
                        }
                    }
                } catch (error) {
                    console.error('Error fetching weight:', error);
                }
            }

            // Mulai polling setiap 1 detik
            const weightPolling = setInterval(fetchWeight, 1000);

            // Set document title
            document.title = "Edit Auxiliary";

            // Validasi form sebelum submit (disamakan dengan create)
            $('form').on('submit', function (e) {
                const detailRows = $('.detail-row').length;
                if (detailRows === 0) {
                    e.preventDefault();
                    alert('Harap tambahkan minimal satu auxiliary detail.');
                    return false;
                }

                let isValid = true;
                $('.select2-auxiliary').each(function () {
                    if (!$(this).val()) {
                        isValid = false;
                        $(this).closest('.col-md-6').find('.select2-container').css('border-color', '#dc3545');
                    } else {
                        $(this).closest('.col-md-6').find('.select2-container').css('border-color', '');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Harap pilih auxiliary untuk semua detail.');
                    return false;
                }
            });

            // Clear error styling ketika user memilih auxiliary
            $(document).on('select2:select', '.select2-auxiliary', function () {
                $(this).closest('.col-md-6').find('.select2-container').css('border-color', '');
            });
        });
    </script>
@endsection