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
                        <h1 class="m-0">Tambah Dye Stuff (LA)</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('dye-stuff.index') }}">Dye Stuff</a></li>
                            <li class="breadcrumb-item active">Tambah Dye Stuff</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('dye-stuff.store') }}" method="POST" id="form-dye-stuff">
                    @csrf
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title"><i class="fas fa-flask"></i> Form Tambah Dye Stuff</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Pilih Planning Proses (Dikategorikan: Sedang Berjalan & Belum Berjalan) -->
                                <div class="col-md-6 mb-3">
                                    <label>Planning Proses <span class="text-danger">*</span></label>
                                    <select name="proses_id" id="proses_id" class="form-control select2" required>
                                        <option value="" disabled {{ old('proses_id') ? '' : 'selected' }}>-- Pilih Planning
                                            Proses --</option>

                                        @if(isset($prosesRunning) && $prosesRunning->isNotEmpty())
                                            <optgroup label="⚡ --- Sedang Berjalan ---">
                                                @foreach ($prosesRunning as $p)
                                                    @php $f = optional($p->details)->first(); @endphp
                                                    <option value="{{ $p->id }}" {{ old('proses_id') == $p->id ? 'selected' : '' }}>
                                                        [Berjalan] OP: {{ $f->no_op ?? '-' }} | Partai: {{ $f->no_partai ?? '-' }} |
                                                        Mesin: {{ optional($p->mesin)->jenis_mesin ?? '-' }} (Normal: {{ $p->normal_dyestuff_count }}/{{ $p->qty_dye_stuff ?? 0 }}{{ $p->normal_dyestuff_count >= ($p->qty_dye_stuff ?? 0) ? ' - Penuh' : '' }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif

                                        @if(isset($prosesPending) && $prosesPending->isNotEmpty())
                                            <optgroup label="⏳ --- Belum Berjalan ---">
                                                @foreach ($prosesPending as $p)
                                                    @php $f = optional($p->details)->first(); @endphp
                                                    <option value="{{ $p->id }}" {{ old('proses_id') == $p->id ? 'selected' : '' }}>
                                                        [Belum Berjalan] OP: {{ $f->no_op ?? '-' }} | Partai:
                                                        {{ $f->no_partai ?? '-' }} | Customer: {{ $f->customer ?? '-' }} (Normal: {{ $p->normal_dyestuff_count }}/{{ $p->qty_dye_stuff ?? 0 }}{{ $p->normal_dyestuff_count >= ($p->qty_dye_stuff ?? 0) ? ' - Penuh' : '' }})
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                    @error('proses_id')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Tipe Dye Stuff -->
                                <div class="col-md-3 mb-3">
                                    <label>Dye Stuff Type <span class="text-danger">*</span></label>
                                    <select name="tipe" id="tipe" class="form-control" required>
                                        @foreach (\App\Models\DyeStuff::getTipeOptions() as $key => $val)
                                            <option value="{{ $key }}" {{ old('tipe', 'normal') == $key ? 'selected' : '' }}>
                                                {{ $val }}</option>
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
                                        @foreach (\App\Models\DyeStuff::getJenisOptions() as $key => $val)
                                            <option value="{{ $key }}" {{ old('jenis') == $key ? 'selected' : '' }}>{{ $val }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('jenis')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Step Process (Pick List) - Locked by default until process with qty_dye_stuff > 1 selected -->
                                <div class="col-md-4 mb-3">
                                    <label>Pick List Step <span class="text-danger" id="step_proses_star" style="display: none;">*</span></label>
                                    <select name="step_proses" id="step_proses" class="form-control"
                                        data-initial-val="{{ old('step_proses') }}"
                                        style="pointer-events: none; background-color: #e9ecef;" tabindex="-1">
                                        <option value="" disabled selected>-- Pilih Step --</option>
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
                                        <input type="number" step="0.1" name="liquor_ratio" id="liquor_ratio"
                                            class="form-control" placeholder="10.0"
                                            value="{{ old('liquor_ratio', '10.0') }}" required>
                                    </div>
                                    @error('liquor_ratio')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Total Wt. (Kg) - Readonly / Locked -->
                                <div class="col-md-4 mb-3">
                                    <label>Total Wt. (Kg) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="total_wt" id="total_wt" class="form-control"
                                            placeholder="Total WT (Kg)" value="{{ old('total_wt', '0.00') }}" readonly
                                            required>
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
                                            value="{{ old('volume_litres', '0.00') }}" readonly required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">L</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Dihitung otomatis dari total Weight (Timbangan) List
                                        Kimia</small>
                                    @error('volume_litres')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <!-- Card Preview Info Proses -->
                                <div class="col-md-8 mb-3">
                                    <div class="p-3 bg-light rounded border" id="proses-info-box" style="display: none;">
                                        <strong>Detail Info Proses:</strong> <span id="info-status-badge"
                                            class="badge badge-info ml-2">-</span><br>
                                        <span id="info-op">Batch / JO: -</span> | <span id="info-partai">Order No:
                                            -</span><br>
                                        <span id="info-customer">Customer: -</span> | <span id="info-material">Fabric:
                                             -</span><br>
                                        <span id="info-color">Color: -</span> | <span id="info-mesin">M/C: -</span>
                                        <div id="info-dye-stuff-quota" class="mt-2 text-primary font-weight-bold">Dye Stuff Normal: 0 / 1</div>
                                    </div>
                                    <div id="tipe-warning-alert" class="alert alert-warning mt-2 mb-0" style="display: none;">
                                        <i class="fas fa-exclamation-triangle"></i> <span id="tipe-warning-text"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Detail List Kimia -->
                    <div class="card mt-3">
                        <div
                            class="card-header bg-secondary text-white d-flex justify-content-between align-items-center w-100">
                            <h3 class="card-title mb-0"><i class="fas fa-vials"></i> Data Detail List Kimia / Dye Stuff</h3>
                            <button type="button" class="btn btn-success btn-sm ml-auto" id="btn-add-detail"
                                title="Tambah List Kimia">
                                <i class="fas fa-plus"></i> Tambah Kimia
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="table-details">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 35%">Chemical Name / Zat Warna (List SAP) <span
                                                    class="text-danger">*</span></th>
                                            <th style="width: 15%">Concentrate (%) <span class="text-danger">*</span></th>
                                            <th style="width: 15%">Weight (Timbangan) <span class="text-danger">*</span>
                                            </th>
                                            <th style="width: 25%">Remark / Catatan</th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="details-list">
                                        @php $oldDetails = old('details', []); @endphp
                                        @if (count($oldDetails) > 0)
                                            @foreach ($oldDetails as $i => $d)
                                                <tr class="detail-row">
                                                    <td>
                                                        <select name="details[{{ $i }}][chemical_name]"
                                                            class="form-control select2-chemical" required>
                                                            @if(!empty($d['chemical_name']))
                                                                <option value="{{ $d['chemical_name'] }}" selected>
                                                                    {{ $d['chemical_name'] }}</option>
                                                            @endif
                                                        </select>
                                                        <input type="hidden" name="details[{{ $i }}][unit]" value="g">
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.0001" name="details[{{ $i }}][konsentrasi]"
                                                            class="form-control form-control-sm conc-input" placeholder="Conc. %"
                                                            value="{{ $d['konsentrasi'] ?? '' }}" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" name="details[{{ $i }}][weight]"
                                                            class="form-control form-control-sm weight-input"
                                                            placeholder="Weight (g)" value="{{ $d['weight'] ?? '' }}"
                                                            required>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="details[{{ $i }}][remark]"
                                                            class="form-control form-control-sm"
                                                            placeholder="Remark / Catatan" value="{{ $d['remark'] ?? '' }}">
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i
                                                                class="fas fa-trash"></i></button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr class="detail-row">
                                                <td>
                                                    <select name="details[0][chemical_name]"
                                                        class="form-control select2-chemical" required></select>
                                                    <input type="hidden" name="details[0][unit]" value="g">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" name="details[0][konsentrasi]"
                                                        class="form-control form-control-sm conc-input" placeholder="Conc. %"
                                                        required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="details[0][weight]"
                                                        class="form-control form-control-sm weight-input"
                                                        placeholder="Weight (g)" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="details[0][remark]"
                                                        class="form-control form-control-sm"
                                                        placeholder="Remark / Catatan">
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i
                                                            class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('dye-stuff.index') }}" class="btn btn-secondary mr-2">Kembali</a>
                            <button type="submit" id="btn-submit-form" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Data Dye Stuff</button>
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

            // Inisialisasi Select2 AJAX untuk List Kimia / Chemical Name dari API SAP
            function initChemicalSelect2(selector) {
                $(selector).select2({
                    placeholder: '-- Cari & Pilih Nama Kimia / Zat Warna (min. 3 karakter) --',
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
                            console.error('Error loading chemical data:', error);
                            return { results: [] };
                        }
                    }
                });
            }

            $('.select2-chemical').each(function () {
                initChemicalSelect2(this);
            });

            let detailIndex = {{ max(1, count(old('details', []))) }};
            let currentProsesInfo = null;

            function calcVolume() {
                let totalWeight = 0;
                $('.weight-input').each(function () {
                    const w = parseFloat($(this).val()) || 0;
                    totalWeight += w;
                });
                $('#volume_litres').val(totalWeight.toFixed(2));
            }

            $(document).on('input change', '.weight-input', calcVolume);

            function updateStepAndQuotaState() {
                if (!currentProsesInfo) return;

                const data = currentProsesInfo;
                const selectedTipe = $('#tipe').val() || 'normal';
                const qtyDyeStuff = parseInt(data.qty_dye_stuff) || 1;
                const existingNormalCount = parseInt(data.existing_normal_count) || 0;
                const usedNormalSteps = data.used_normal_steps || [];
                const usedAdditionSteps = data.used_addition_steps || [];
                const canCreateNormal = data.can_create_normal;

                // Update Info Kuota
                if (existingNormalCount >= qtyDyeStuff) {
                    $('#info-dye-stuff-quota').html(`Dye Stuff Normal Dibuat: <span class="badge badge-danger">${existingNormalCount} / ${qtyDyeStuff} (Kuota Penuh)</span>`);
                } else {
                    $('#info-dye-stuff-quota').html(`Dye Stuff Normal Dibuat: <span class="badge badge-success">${existingNormalCount} / ${qtyDyeStuff} (Sisa ${qtyDyeStuff - existingNormalCount}x)</span>`);
                }

                const $stepSelect = $('#step_proses');
                const prevVal = $stepSelect.data('initial-val') || $stepSelect.val();
                $stepSelect.empty();

                if (selectedTipe === 'additional' || selectedTipe === 'addition') {
                    // Tipe Addition (Topping): 2 Pilihan Step (1 - Reactive, 2 - Dispers)
                    const isAdditionFull = usedAdditionSteps.includes(1) && usedAdditionSteps.includes(2);

                    if (isAdditionFull) {
                        $('#tipe-warning-text').text(`Dye Stuff Type Addition (Topping) untuk proses ini sudah mencapai batas maksimum (2x: Reactive & Dispers). Silakan pilih Tipe Normal atau pilih proses lain.`);
                        $('#tipe-warning-alert').slideDown(200);
                        $('#btn-submit-form').prop('disabled', true).addClass('disabled');
                    } else {
                        $('#tipe-warning-alert').slideUp(200);
                        $('#btn-submit-form').prop('disabled', false).removeClass('disabled');
                    }

                    $('#step_proses_star').show();
                    $stepSelect.attr('required', 'required');
                    $stepSelect.css('pointer-events', 'auto').css('background-color', '#fff').removeAttr('tabindex');

                    $stepSelect.append('<option value="" disabled selected>-- Pilih Step Topping --</option>');

                    const stepsObj = { 1: '1 - Reactive', 2: '2 - Dispers' };
                    $.each(stepsObj, function (val, label) {
                        const isUsed = usedAdditionSteps.includes(parseInt(val));
                        const isSel = (prevVal == val) ? 'selected' : '';
                        if (isUsed) {
                            $stepSelect.append(`<option value="${val}" disabled style="color: #aaa;">${label} (Sudah Dibuat)</option>`);
                        } else {
                            $stepSelect.append(`<option value="${val}" ${isSel}>${label}</option>`);
                        }
                    });

                    if (prevVal && [1, 2].includes(parseInt(prevVal)) && !usedAdditionSteps.includes(parseInt(prevVal))) {
                        $stepSelect.val(prevVal);
                    }
                } else {
                    // Tipe Normal
                    if (selectedTipe === 'normal' && !canCreateNormal) {
                        $('#tipe-warning-text').text(`Dye Stuff Type Normal untuk proses ini sudah mencapai batas maksimum (${qtyDyeStuff}x). Silakan pilih Tipe Addition (Topping) atau pilih proses lain.`);
                        $('#tipe-warning-alert').slideDown(200);
                        $('#btn-submit-form').prop('disabled', true).addClass('disabled');
                    } else {
                        $('#tipe-warning-alert').slideUp(200);
                        $('#btn-submit-form').prop('disabled', false).removeClass('disabled');
                    }

                    if (qtyDyeStuff <= 1) {
                        $('#step_proses_star').hide();
                        $stepSelect.removeAttr('required');
                        const isUsed = (selectedTipe === 'normal' && usedNormalSteps.includes(1));
                        if (isUsed) {
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

                        const isPlaceholderSelected = !prevVal || usedNormalSteps.includes(parseInt(prevVal));
                        $stepSelect.append('<option value="" disabled ' + (isPlaceholderSelected ? 'selected' : '') + '>-- Pilih Step --</option>');
                        
                        for (let i = 1; i <= qtyDyeStuff; i++) {
                            const isUsed = (selectedTipe === 'normal' && usedNormalSteps.includes(i));
                            if (isUsed) {
                                $stepSelect.append(`<option value="${i}" disabled style="color: #aaa;">Step ${i} (Sudah Dibuat)</option>`);
                            } else {
                                const isSel = (prevVal == i) ? 'selected' : '';
                                $stepSelect.append(`<option value="${i}" ${isSel}>Step ${i}</option>`);
                            }
                        }

                        if (prevVal && !usedNormalSteps.includes(parseInt(prevVal))) {
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
                    url: '/api/proses-info/' + id,
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

                        // 1. Jenis Otomatis Berdasarkan Planning
                        if (data.auto_jenis) {
                            $('select[name="jenis"]').val(data.auto_jenis);
                        }

                        // 2. Total WT (Kg) dari DetailProses QTY
                        if (typeof data.total_wt !== 'undefined' && parseFloat(data.total_wt) > 0) {
                            $('#total_wt').val(parseFloat(data.total_wt).toFixed(2));
                        }

                        // 3. Update Kuota & Pick List Step State
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
                                <select name="details[${detailIndex}][chemical_name]" class="form-control select2-chemical" required></select>
                                <input type="hidden" name="details[${detailIndex}][unit]" value="g">
                            </td>
                            <td>
                                <input type="number" step="0.0001" name="details[${detailIndex}][konsentrasi]" class="form-control form-control-sm conc-input" placeholder="Conc. %" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="details[${detailIndex}][weight]" class="form-control form-control-sm weight-input" placeholder="Weight (g)" required>
                            </td>
                            <td>
                                <input type="text" name="details[${detailIndex}][remark]" class="form-control form-control-sm" placeholder="Remark / Catatan">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `;
                $('#details-list').append(tr);
                initChemicalSelect2($('#details-list tr:last .select2-chemical'));
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
                        text: 'Minimal harus ada 1 detail list kimia.'
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