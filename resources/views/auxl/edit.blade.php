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
                <form action="{{ route('aux.update', $auxl->id) }}" method="POST" id="form-aux">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title"><i class="fas fa-edit"></i> Form Edit Auxiliary</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Pilih Planning Proses -->
                                <div class="col-md-6 mb-3">
                                    <label>Planning Proses <span class="text-danger">*</span></label>
                                    <select name="proses_id" id="proses_id" class="form-control select2" required>
                                        <option value="" disabled>-- Pilih Planning Proses --</option>

                                        @if(isset($prosesRunning) && $prosesRunning->isNotEmpty())
                                            <optgroup label="⚡ --- Sedang Berjalan ---">
                                                @foreach ($prosesRunning as $p)
                                                    @php $f = optional($p->details)->first(); @endphp
                                                    <option value="{{ $p->id }}" {{ old('proses_id', $auxl->proses_id) == $p->id ? 'selected' : '' }}>
                                                        [Berjalan] OP: {{ $f->no_op ?? '-' }} | Partai: {{ $f->no_partai ?? '-' }} |
                                                        Mesin: {{ optional($p->mesin)->jenis_mesin ?? '-' }} (Normal:
                                                        {{ $p->normal_aux_count }}/{{ $p->qty_aux ?? 0 }}{{ $p->normal_aux_count >= ($p->qty_aux ?? 0) ? ' - Penuh' : '' }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif

                                        @if(isset($prosesPending) && $prosesPending->isNotEmpty())
                                            <optgroup label="⏳ --- Belum Berjalan ---">
                                                @foreach ($prosesPending as $p)
                                                    @php $f = optional($p->details)->first(); @endphp
                                                    <option value="{{ $p->id }}" {{ old('proses_id', $auxl->proses_id) == $p->id ? 'selected' : '' }}>
                                                        [Belum Berjalan] OP: {{ $f->no_op ?? '-' }} | Partai:
                                                        {{ $f->no_partai ?? '-' }} | Customer: {{ $f->customer ?? '-' }} (Normal:
                                                        {{ $p->normal_aux_count }}/{{ $p->qty_aux ?? 0 }}{{ $p->normal_aux_count >= ($p->qty_aux ?? 0) ? ' - Penuh' : '' }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                    @error('proses_id')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Tipe Aux -->
                                <div class="col-md-3 mb-3">
                                    <label>Tipe Aux <span class="text-danger">*</span></label>
                                    <select name="tipe" id="tipe" class="form-control" required>
                                        @foreach (\App\Models\Auxl::getTipeOptions() as $key => $val)
                                            <option value="{{ $key }}" {{ old('tipe', $auxl->tipe ?? 'normal') == $key ? 'selected' : '' }}>
                                                {{ $val }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('tipe')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Jenis (Locked - Otomatis dari Planning) -->
                                <div class="col-md-3 mb-3">
                                    <label>Jenis <span class="text-danger">*</span></label>
                                    <select name="jenis" id="jenis" class="form-control"
                                        style="pointer-events: none; background-color: #e9ecef;" tabindex="-1" required>
                                        @foreach (\App\Models\Auxl::getJenisOptions() as $key => $val)
                                            <option value="{{ $key }}" {{ old('jenis', $auxl->jenis) == $key ? 'selected' : '' }}>
                                                {{ $val }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('jenis')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Pick List Step Process -->
                                <div class="col-md-4 mb-3">
                                    <label>Pick List Step <span class="text-danger" id="step_proses_star"
                                            style="display: none;">*</span></label>
                                    <select name="step_proses" id="step_proses" class="form-control"
                                        data-initial-val="{{ old('step_proses', $auxl->step_proses) }}"
                                        style="pointer-events: none; background-color: #e9ecef;" tabindex="-1">
                                        <option value="" disabled {{ old('step_proses', $auxl->step_proses) ? '' : 'selected' }}>-- Pilih Step --</option>
                                    </select>
                                    @error('step_proses')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Liquor Ratio (Depan 1 :, belakang diisi user) -->
                                <div class="col-md-4 mb-3">
                                    <label>Liquor Ratio <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">1 :</span>
                                        </div>
                                        <input type="number" step="1" name="liquor_ratio" id="liquor_ratio"
                                            class="form-control" placeholder="10"
                                            value="{{ old('liquor_ratio', isset($auxl->liquor_ratio) ? round($auxl->liquor_ratio) : '10') }}" required>
                                    </div>
                                    @error('liquor_ratio')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Total Wt. (Kg) -->
                                <div class="col-md-4 mb-3">
                                    <label>Total Wt. (Kg) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="total_wt" id="total_wt" class="form-control"
                                            placeholder="Total WT (Kg)"
                                            value="{{ old('total_wt', $auxl->total_wt ?? '0.00') }}" readonly required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">Kg</span>
                                        </div>
                                    </div>
                                    @error('total_wt')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Volume (Litres) -->
                                <div class="col-md-4 mb-3">
                                    <label>Volume (Litres) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="volume_litres" id="volume_litres"
                                            class="form-control" placeholder="Volume Litres"
                                            value="{{ old('volume_litres', $auxl->volume_litres ?? '0.00') }}" readonly
                                            required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">L</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Dihitung otomatis dari total Weight (kg) List
                                        Auxiliary</small>
                                    @error('volume_litres')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Code -->
                                <div class="col-md-4 mb-3">
                                    <label>Code <span class="text-danger">*</span></label>
                                    <input type="text" name="code" id="code" class="form-control"
                                        placeholder="Code Auxiliary" value="{{ old('code', $auxl->code) }}" required>
                                    @error('code')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Konstruksi -->
                                <div class="col-md-4 mb-3">
                                    <label>Konstruksi <span class="text-danger">*</span></label>
                                    <input type="text" name="konstruksi" id="konstruksi" class="form-control"
                                        placeholder="Konstruksi" value="{{ old('konstruksi', $auxl->konstruksi) }}" readonly
                                        required>
                                    @error('konstruksi')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Customer -->
                                <div class="col-md-4 mb-3">
                                    <label>Customer <span class="text-danger">*</span></label>
                                    <input type="text" name="customer" id="customer" class="form-control"
                                        placeholder="Customer" value="{{ old('customer', $auxl->customer) }}" readonly
                                        required>
                                    @error('customer')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Marketing -->
                                <div class="col-md-4 mb-3">
                                    <label>Marketing <span class="text-danger">*</span></label>
                                    <input type="text" name="marketing" id="marketing" class="form-control"
                                        placeholder="Marketing" value="{{ old('marketing', $auxl->marketing) }}" readonly
                                        required>
                                    @error('marketing')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Date -->
                                <div class="col-md-4 mb-3">
                                    <label>Date <span class="text-danger">*</span></label>
                                    <input type="date" name="date" id="date" class="form-control"
                                        value="{{ old('date', $auxl->date) }}" required>
                                    @error('date')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Color -->
                                <div class="col-md-4 mb-3">
                                    <label>Color <span class="text-danger">*</span></label>
                                    <input type="text" name="color" id="color" class="form-control" placeholder="Color"
                                        value="{{ old('color', $auxl->color) }}" readonly required>
                                    @error('color')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Card Preview Info Proses -->
                                <div class="col-md-12 mb-3">
                                    <div class="p-3 bg-light rounded border" id="proses-info-box" style="display: none;">
                                        <strong>Detail Info Proses:</strong> <span id="info-status-badge"
                                            class="badge badge-info ml-2">-</span><br>
                                        <span id="info-op">Batch / JO: -</span> | <span id="info-partai">Order No:
                                            -</span><br>
                                        <span id="info-customer">Customer: -</span> | <span id="info-material">Fabric:
                                            -</span><br>
                                        <span id="info-color">Color: -</span> | <span id="info-mesin">M/C: -</span>
                                        <div id="info-aux-quota" class="mt-2 text-primary font-weight-bold">AUX Normal: 0 /
                                            1</div>
                                    </div>
                                    <div id="tipe-warning-alert" class="alert alert-warning mt-2 mb-0"
                                        style="display: none;">
                                        <i class="fas fa-exclamation-triangle"></i> <span id="tipe-warning-text"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Detail List Auxiliary -->
                    <div class="card mt-3">
                        <div
                            class="card-header bg-secondary text-white d-flex justify-content-between align-items-center w-100">
                            <h3 class="card-title mb-0"><i class="fas fa-vials"></i> Data Detail List Auxiliary</h3>
                            <button type="button" class="btn btn-success btn-sm ml-auto" id="btn-add-detail"
                                title="Tambah List Auxiliary">
                                <i class="fas fa-plus"></i> Tambah Auxiliary
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="table-details">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 60%">Nama Auxiliary (List SAP) <span
                                                    class="text-danger">*</span></th>
                                            <th style="width: 30%">Weight (kg) <span class="text-danger">*</span></th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="details-list">
                                        @php $details = old('details', $auxl->details); @endphp
                                        @foreach ($details as $i => $d)
                                            @php
                                                $auxName = is_array($d) ? ($d['auxiliary'] ?? '') : ($d->auxiliary ?? '');
                                                $weightVal = is_array($d) ? ($d['konsentrasi'] ?? '') : ($d->konsentrasi ?? '');
                                            @endphp
                                            <tr class="detail-row">
                                                <td>
                                                    <select name="details[{{ $i }}][auxiliary]"
                                                        class="form-control select2-auxiliary" required>
                                                        @if(!empty($auxName))
                                                            <option value="{{ $auxName }}" selected>{{ $auxName }}</option>
                                                        @endif
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="details[{{ $i }}][konsentrasi]"
                                                        class="form-control form-control-sm weight-input"
                                                        placeholder="Weight (kg)" value="{{ $weightVal }}" required>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i
                                                            class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('aux.index') }}" class="btn btn-secondary mr-2">Kembali</a>
                            <button type="submit" id="btn-submit-form" class="btn btn-primary"><i class="fas fa-save"></i>
                                Update Data Auxiliary</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            $('.select2').select2({ width: '100%' });

            function initAuxiliarySelect2(selector) {
                $(selector).select2({
                    placeholder: '-- Cari & Pilih Nama Auxiliary (min. 3 karakter) --',
                    minimumInputLength: 3,
                    width: '100%',
                    ajax: {
                        url: '/api/proxy-auxiliary',
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
                        },
                        error: function (xhr, status, error) {
                            console.error('Error loading auxiliary data:', error);
                            return { results: [] };
                        }
                    }
                });
            }

            $('.select2-auxiliary').each(function () {
                initAuxiliarySelect2(this);
            });

            let detailIndex = {{ count($auxl->details) }};
            let currentProsesInfo = null;

            function calcVolume() {
                const totalWt = parseFloat($('#total_wt').val()) || 0;
                const ratio = parseFloat($('#liquor_ratio').val()) || 0;
                const volume = totalWt * ratio;
                $('#volume_litres').val(volume.toFixed(2));
            }

            $(document).on('input change', '#liquor_ratio, #total_wt', calcVolume);

            function updateStepAndQuotaState() {
                if (!currentProsesInfo) return;

                const data = currentProsesInfo;
                const selectedTipe = $('#tipe').val() || 'normal';
                const qtyAux = parseInt(data.qty_aux) || 1;
                const existingNormalAuxCount = parseInt(data.existing_normal_aux_count) || 0;
                const usedNormalAuxSteps = data.used_normal_aux_steps || [];
                const usedAdditionAuxSteps = data.used_addition_aux_steps || [];
                const canCreateNormalAux = data.can_create_normal_aux;

                // Update Info Kuota
                if (existingNormalAuxCount >= qtyAux) {
                    $('#info-aux-quota').html(`AUX Normal Dibuat: <span class="badge badge-danger">${existingNormalAuxCount} / ${qtyAux} (Kuota Penuh)</span>`);
                } else {
                    $('#info-aux-quota').html(`AUX Normal Dibuat: <span class="badge badge-success">${existingNormalAuxCount} / ${qtyAux} (Sisa ${qtyAux - existingNormalAuxCount}x)</span>`);
                }

                const $stepSelect = $('#step_proses');
                const prevVal = $stepSelect.data('initial-val') || $stepSelect.val();
                $stepSelect.empty();

                if (selectedTipe === 'addition' || selectedTipe === 'additional') {
                    // Tipe Addition (Topping): 2 Pilihan Step (1 - Reactive, 2 - Dispers)
                    const isAdditionFull = usedAdditionAuxSteps.includes(1) && usedAdditionAuxSteps.includes(2);

                    if (isAdditionFull && (!prevVal || !usedAdditionAuxSteps.includes(parseInt(prevVal)))) {
                        $('#tipe-warning-text').text(`AUX Type Addition (Topping) untuk proses ini sudah mencapai batas maksimum (2x: Reactive & Dispers). Silakan pilih Tipe Normal atau pilih proses lain.`);
                        $('#tipe-warning-alert').slideDown(200);
                        $('#btn-submit-form').prop('disabled', true).addClass('disabled');
                    } else {
                        $('#tipe-warning-alert').slideUp(200);
                        $('#btn-submit-form').prop('disabled', false).removeClass('disabled');
                    }

                    $('#step_proses_star').show();
                    $stepSelect.attr('required', 'required');
                    $stepSelect.css('pointer-events', 'auto').css('background-color', '#fff').removeAttr('tabindex');

                    $stepSelect.append('<option value="" disabled>-- Pilih Step Topping --</option>');

                    const stepsObj = { 1: '1 - Reactive', 2: '2 - Dispers' };
                    $.each(stepsObj, function (val, label) {
                        const isUsed = usedAdditionAuxSteps.includes(parseInt(val));
                        const isSel = (prevVal == val) ? 'selected' : '';
                        if (isUsed && prevVal != val) {
                            $stepSelect.append(`<option value="${val}" disabled style="color: #aaa;">${label} (Sudah Dibuat)</option>`);
                        } else {
                            $stepSelect.append(`<option value="${val}" ${isSel}>${label}</option>`);
                        }
                    });

                    if (prevVal && [1, 2].includes(parseInt(prevVal))) {
                        $stepSelect.val(prevVal);
                    }
                } else {
                    // Tipe Normal
                    if (selectedTipe === 'normal' && !canCreateNormalAux) {
                        $('#tipe-warning-text').text(`AUX Type Normal untuk proses ini sudah mencapai batas maksimum (${qtyAux}x). Silakan pilih Tipe Addition (Topping) atau pilih proses lain.`);
                        $('#tipe-warning-alert').slideDown(200);
                        $('#btn-submit-form').prop('disabled', true).addClass('disabled');
                    } else {
                        $('#tipe-warning-alert').slideUp(200);
                        $('#btn-submit-form').prop('disabled', false).removeClass('disabled');
                    }

                    if (qtyAux <= 1) {
                        $('#step_proses_star').hide();
                        $stepSelect.removeAttr('required');
                        const isUsed = (selectedTipe === 'normal' && usedNormalAuxSteps.includes(1));
                        if (isUsed && prevVal != 1) {
                            $stepSelect.append('<option value="1" selected disabled>Step 1 (Sudah Dibuat)</option>');
                        } else {
                            $stepSelect.append('<option value="1" selected>Step 1 (Default)</option>');
                        }
                        $stepSelect.val('1');
                        $stepSelect.css('pointer-events', 'none').css('background-color', '#e9ecef').attr('tabindex', '-1');
                    } else {
                        $('#step_proses_star').show();
                        $stepSelect.attr('required', 'required');
                        $stepSelect.css('pointer-events', 'auto').css('background-color', '#fff').removeAttr('tabindex');

                        const isPlaceholderSelected = !prevVal || (usedNormalAuxSteps.includes(parseInt(prevVal)) && prevVal != $stepSelect.data('initial-val'));
                        $stepSelect.append('<option value="" disabled ' + (isPlaceholderSelected ? 'selected' : '') + '>-- Pilih Step --</option>');
                        
                        for (let i = 1; i <= qtyAux; i++) {
                            const isUsed = (selectedTipe === 'normal' && usedNormalAuxSteps.includes(i));
                            if (isUsed && prevVal != i) {
                                $stepSelect.append(`<option value="${i}" disabled style="color: #aaa;">Step ${i} (Sudah Dibuat)</option>`);
                            } else {
                                const isSel = (prevVal == i) ? 'selected' : '';
                                $stepSelect.append(`<option value="${i}" ${isSel}>Step ${i}</option>`);
                            }
                        }

                        if (prevVal && (!usedNormalAuxSteps.includes(parseInt(prevVal)) || prevVal == $stepSelect.data('initial-val'))) {
                            $stepSelect.val(prevVal);
                        } else {
                            $stepSelect.val('');
                        }
                    }
                }
            }

            $('#tipe').on('change', function () {
                updateStepAndQuotaState();
            });

            $('#proses_id').on('change', function () {
                const id = $(this).val();
                if (!id) return;
                $.ajax({
                    url: '/api/proses-info/' + id + '?exclude_aux_id={{ $auxl->id }}',
                    type: 'GET',
                    success: function (data) {
                        currentProsesInfo = data;
                        $('#proses-info-box').show();
                        $('#info-status-badge').text(data.status_proses || 'Status -').removeClass('badge-primary badge-secondary badge-success').addClass(data.status_proses === 'Sedang Berjalan' ? 'badge-primary' : 'badge-secondary');
                        $('#info-op').text('Batch / JO: ' + (data.no_jo || '-'));
                        $('#info-partai').text('Order No: ' + (data.no_partai || '-'));
                        $('#info-customer').text('Customer: ' + (data.customer || '-'));
                        $('#info-material').text('Fabric: ' + (data.material || '-'));
                        $('#info-color').text('Color: ' + (data.color || '-'));
                        $('#info-mesin').text('M/C: ' + (data.mesin || '-'));

                        if (data.auto_jenis) {
                            $('#jenis').val(data.auto_jenis);
                        }
                        if (typeof data.customer !== 'undefined') {
                            $('#customer').val(data.customer);
                        }
                        if (typeof data.material !== 'undefined') {
                            $('#konstruksi').val(data.material);
                        }
                        if (typeof data.color !== 'undefined') {
                            $('#color').val(data.color);
                        }
                        if (typeof data.marketing !== 'undefined') {
                            $('#marketing').val(data.marketing || '-');
                        }

                        if (typeof data.total_wt !== 'undefined' && parseFloat(data.total_wt) > 0) {
                            $('#total_wt').val(parseFloat(data.total_wt).toFixed(2));
                        }

                        calcVolume();

                        updateStepAndQuotaState();
                    }
                });
            });

            if ($('#proses_id').val()) {
                $('#proses_id').trigger('change');
            }

            $('#btn-add-detail').on('click', function () {
                const tr = `
                            <tr class="detail-row">
                                <td>
                                    <select name="details[${detailIndex}][auxiliary]" class="form-control select2-auxiliary" required></select>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="details[${detailIndex}][konsentrasi]" class="form-control form-control-sm weight-input" placeholder="Weight (kg)" required>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                $('#details-list').append(tr);
                initAuxiliarySelect2($('#details-list tr:last .select2-auxiliary'));
                detailIndex++;
                calcVolume();
            });

            $(document).on('click', '.btn-remove-detail', function () {
                if ($('#details-list .detail-row').length > 1) {
                    $(this).closest('tr').remove();
                    calcVolume();
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Minimal harus ada 1 detail list auxiliary.'
                    });
                }
            });

            async function fetchWeight() {
                try {
                    const res = await fetch('https://dpf3dunia.com/api/weight');
                    if (!res.ok) return;

                    const data = await res.json();
                    if (typeof data.weight !== 'undefined' && !isNaN(parseFloat(data.weight))) {
                        const weightInputs = document.querySelectorAll('.weight-input');
                        if (weightInputs.length > 0) {
                            weightInputs[weightInputs.length - 1].value = parseFloat(data.weight).toFixed(2);
                            calcVolume();
                        }
                    }
                } catch (error) {
                    console.error('Error fetching weight:', error);
                }
            }

            setInterval(fetchWeight, 1000);
        });
    </script>
@endsection