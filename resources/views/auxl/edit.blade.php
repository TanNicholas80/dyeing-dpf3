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

        /* Styling Lock & Allow Edit */
        .select2-locked .select2-container .select2-selection--single {
            background-color: #e9ecef !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
        }

        .input-locked {
            background-color: #e9ecef !important;
            cursor: not-allowed !important;
            color: #495057 !important;
        }

        .input-unlocked {
            background-color: #ffffff !important;
            cursor: text !important;
            border-color: #28a745 !important;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15) !important;
        }

        .btn-toggle-lock-btn {
            font-weight: 600;
            transition: all 0.2s ease-in-out;
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
                            class="card-header bg-secondary text-white d-flex justify-content-between align-items-center w-100 flex-wrap">
                            <div class="d-flex align-items-center">
                                <h3 class="card-title mb-0"><i class="fas fa-vials"></i> Data Detail List Auxiliary</h3>
                                <span id="lock-status-badge" class="badge badge-warning ml-2 font-weight-bold">
                                    <i class="fas fa-lock"></i> Terkunci
                                </span>
                            </div>
                            <div class="ml-auto d-flex align-items-center">
                                <button type="button" class="btn btn-warning btn-sm mr-2 btn-toggle-lock-btn" id="btn-toggle-lock"
                                    title="Klik untuk membuka/mengunci input kuantitas kimia">
                                    <i class="fas fa-unlock" id="lock-btn-icon"></i> <span id="lock-btn-text">Allow Edit</span>
                                </button>
                                <button type="button" class="btn btn-success btn-sm" id="btn-add-detail"
                                    title="Tambah List Auxiliary">
                                    <i class="fas fa-plus"></i> Tambah Auxiliary
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Alert info lock status -->
                            <div id="lock-info-alert" class="alert alert-info py-2 px-3 mb-3 small d-flex align-items-center">
                                <i class="fas fa-info-circle mr-2 text-info" id="lock-alert-icon" style="font-size: 1.1rem;"></i>
                                <span id="lock-info-text">
                                    Kuantitas kimia yang sudah tersimpan dalam keadaan <strong>terkunci</strong>. Klik tombol <strong>"Allow Edit"</strong> jika Anda ingin mengubah kuantitasnya (Gram / KG).
                                </span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered" id="table-details">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 60%">Nama Auxiliary (List SAP) <span
                                                    class="text-danger">*</span></th>
                                            <th style="width: 30%">Weight <span class="text-danger">*</span></th>
                                            <th style="width: 10%">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="details-list">
                                        @php $details = old('details', $auxl->details); @endphp
                                        @foreach ($details as $i => $d)
                                            @php
                                                $auxName = is_array($d) ? ($d['auxiliary'] ?? '') : ($d->auxiliary ?? '');
                                                $weightVal = is_array($d) ? ($d['konsentrasi'] ?? '') : ($d->konsentrasi ?? '');
                                                $extwgVal = is_array($d) ? ($d['extwg'] ?? '') : ($d->extwg ?? '');
                                                
                                                // Jika belum ada old unit, cek dari extwg
                                                if (is_array($d) && isset($d['unit'])) {
                                                    $unitVal = $d['unit'];
                                                } else {
                                                    $unitVal = ($extwgVal === 'AUX SPC') ? 'gram' : 'kg';
                                                }
                                                
                                                // Jika unit gram dan nilai berasal dari database (kg), konversi ke gram untuk tampilan input
                                                if ($unitVal === 'gram' && !is_array($d) && is_numeric($weightVal)) {
                                                    $displayWeight = floatval($weightVal) * 1000;
                                                } else {
                                                    $displayWeight = $weightVal;
                                                }
                                                $isSpc = ($unitVal === 'gram' || $extwgVal === 'AUX SPC');
                                            @endphp
                                            <tr class="detail-row existing-row" data-current-unit="{{ $unitVal }}" data-is-existing="true">
                                                <td>
                                                    <div class="select2-locked">
                                                        <select name="details[{{ $i }}][auxiliary]"
                                                            class="form-control select2-auxiliary" required>
                                                            @if(!empty($auxName))
                                                                <option value="{{ $auxName }}" data-extwg="{{ $extwgVal }}" selected>{{ $auxName }}</option>
                                                            @endif
                                                        </select>
                                                    </div>
                                                    <input type="hidden" name="details[{{ $i }}][unit]" class="unit-input" value="{{ $unitVal }}">
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="{{ $isSpc ? '0.01' : '0.0001' }}" name="details[{{ $i }}][konsentrasi]"
                                                            class="form-control form-control-sm weight-input input-locked"
                                                            placeholder="Weight ({{ $unitVal }})" value="{{ $displayWeight }}" readonly required>
                                                        <div class="input-group-append">
                                                            <span class="input-group-text unit-label">{{ $unitVal }}</span>
                                                        </div>
                                                    </div>
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

            let allowEdit = false;
            let detailIndex = {{ count($auxl->details) }};
            let currentProsesInfo = null;

            function applyLockState() {
                if (allowEdit) {
                    $('#btn-toggle-lock')
                        .removeClass('btn-warning')
                        .addClass('btn-secondary')
                        .attr('title', 'Klik untuk mengunci kembali input kuantitas');
                    $('#lock-btn-icon').removeClass('fa-unlock').addClass('fa-lock text-warning');
                    $('#lock-btn-text').text('Kunci Kembali');
                    
                    $('#lock-status-badge')
                        .removeClass('badge-warning')
                        .addClass('badge-success')
                        .html('<i class="fas fa-unlock"></i> Mode Edit Aktif');
                        
                    $('#lock-info-alert')
                        .removeClass('alert-info')
                        .addClass('alert-success');
                    $('#lock-alert-icon')
                        .removeClass('fa-info-circle text-info')
                        .addClass('fa-unlock-alt text-success');
                    $('#lock-info-text').html(
                        '<strong>Mode Edit Aktif:</strong> Anda dapat mengedit kuantitas baik <strong>Gram (Aux Special)</strong> maupun <strong>KG (Aux Biasa)</strong>. Pastikan data sudah benar sebelum disimpan.'
                    );

                    $('.existing-row').each(function () {
                        const $row = $(this);
                        const $weightInput = $row.find('.weight-input');
                        $weightInput.prop('readonly', false)
                            .removeClass('input-locked')
                            .addClass('input-unlocked');
                    });
                } else {
                    $('#btn-toggle-lock')
                        .removeClass('btn-secondary')
                        .addClass('btn-warning')
                        .attr('title', 'Klik untuk membuka input kuantitas kimia');
                    $('#lock-btn-icon').removeClass('fa-lock text-warning').addClass('fa-unlock');
                    $('#lock-btn-text').text('Allow Edit');
                    
                    $('#lock-status-badge')
                        .removeClass('badge-success')
                        .addClass('badge-warning')
                        .html('<i class="fas fa-lock"></i> Terkunci');
                        
                    $('#lock-info-alert')
                        .removeClass('alert-success')
                        .addClass('alert-info');
                    $('#lock-alert-icon')
                        .removeClass('fa-unlock-alt text-success')
                        .addClass('fa-info-circle text-info');
                    $('#lock-info-text').html(
                        'Kuantitas kimia yang sudah tersimpan dalam keadaan <strong>terkunci</strong>. Klik tombol <strong>"Allow Edit"</strong> jika Anda ingin mengubah kuantitasnya (Gram / KG).'
                    );

                    $('.existing-row').each(function () {
                        const $row = $(this);
                        const $weightInput = $row.find('.weight-input');
                        $weightInput.prop('readonly', true)
                            .removeClass('input-unlocked')
                            .addClass('input-locked');
                    });
                }
            }

            $('#btn-toggle-lock').on('click', function () {
                if (!allowEdit) {
                    Swal.fire({
                        title: 'Buka Kunci Pengeditan?',
                        text: 'Kuantitas kimia (Gram / KG) yang sudah tersimpan akan dapat diedit.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#ffc107',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-unlock"></i> Ya, Allow Edit',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            allowEdit = true;
                            applyLockState();
                            Swal.fire({
                                icon: 'success',
                                title: 'Mode Edit Aktif',
                                text: 'Silakan edit kuantitas kimia yang diperlukan.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    });
                } else {
                    allowEdit = false;
                    applyLockState();
                    Swal.fire({
                        icon: 'info',
                        title: 'Input Dikunci',
                        text: 'Kuantitas kimia telah dikunci kembali.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });

            function updateRowWeightState($tr, isInitialLoad = false) {
                const $select = $tr.find('.select2-auxiliary');
                const selectedData = $select.select2('data')[0] || {};
                let extwg = selectedData.extwg;
                if (typeof extwg === 'undefined' || extwg === null || extwg === '') {
                    extwg = $select.find('option:selected').attr('data-extwg') || $select.find('option:selected').data('extwg');
                }

                const $weightInput = $tr.find('.weight-input');
                const $unitLabel = $tr.find('.unit-label');
                const $unitInput = $tr.find('.unit-input');
                const prevUnit = $tr.data('current-unit') || 'kg';
                const isExisting = $tr.hasClass('existing-row') || $tr.data('is-existing');

                if (extwg === 'AUX SPC') {
                    $weightInput.attr('placeholder', 'Weight (gram)');
                    $weightInput.attr('step', '0.01');
                    $unitLabel.text('gram');
                    $unitInput.val('gram');

                    if (!isInitialLoad && prevUnit === 'kg') {
                        const currentVal = parseFloat($weightInput.val());
                        if (!isNaN(currentVal) && currentVal > 0) {
                            $weightInput.val(parseFloat((currentVal * 1000).toFixed(2)));
                        }
                    }
                    $tr.data('current-unit', 'gram');

                    if (isExisting) {
                        if (allowEdit) {
                            $weightInput.prop('readonly', false).removeClass('input-locked').addClass('input-unlocked');
                        } else {
                            $weightInput.prop('readonly', true).removeClass('input-unlocked').addClass('input-locked');
                        }
                    } else {
                        $weightInput.prop('readonly', false);
                    }
                } else {
                    $weightInput.attr('placeholder', 'Weight (kg)');
                    $weightInput.attr('step', '0.0001');
                    $unitLabel.text('kg');
                    $unitInput.val('kg');

                    if (!isInitialLoad && prevUnit === 'gram') {
                        const currentVal = parseFloat($weightInput.val());
                        if (!isNaN(currentVal) && currentVal > 0) {
                            $weightInput.val(parseFloat((currentVal / 1000).toFixed(4)));
                        }
                    }
                    $tr.data('current-unit', 'kg');

                    if (isExisting) {
                        if (allowEdit) {
                            $weightInput.prop('readonly', false).removeClass('input-locked').addClass('input-unlocked');
                        } else {
                            $weightInput.prop('readonly', true).removeClass('input-unlocked').addClass('input-locked');
                        }
                    } else {
                        // For new row, editable if allowEdit is active, otherwise readonly (scale)
                        $weightInput.prop('readonly', !allowEdit);
                    }
                }
                calcVolume();
            }

            function initAuxiliarySelect2(selector) {
                const $el = $(selector);
                $el.select2({
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
                }).on('select2:select', function (e) {
                    const data = e.params.data;
                    if (data && data.extwg) {
                        $(this).find('option:selected').attr('data-extwg', data.extwg);
                    }
                    updateRowWeightState($(this).closest('.detail-row'), false);
                }).on('select2:clear change', function () {
                    updateRowWeightState($(this).closest('.detail-row'), false);
                });
            }

            $('.select2-auxiliary').each(function () {
                initAuxiliarySelect2(this);
                updateRowWeightState($(this).closest('.detail-row'), true);
            });

            function calcVolume() {
                let sumWeight = 0;
                let hasDetailWeight = false;

                $('.detail-row').each(function () {
                    const $row = $(this);
                    const $input = $row.find('.weight-input');
                    const val = parseFloat($input.val());
                    if (!isNaN(val) && val > 0) {
                        const isGram = $row.find('.unit-label').text().trim().toLowerCase() === 'gram';
                        const weightInKg = isGram ? (val / 1000) : val;
                        sumWeight += weightInKg;
                        hasDetailWeight = true;
                    }
                });

                if (hasDetailWeight) {
                    $('#total_wt').val(sumWeight.toFixed(4));
                }

                const totalWt = parseFloat($('#total_wt').val()) || 0;
                const ratio = parseFloat($('#liquor_ratio').val()) || 0;
                const volume = totalWt * ratio;
                $('#volume_litres').val(volume.toFixed(2));
            }

            $(document).on('input change', '#liquor_ratio, #total_wt, .weight-input', calcVolume);

            function updateStepAndQuotaState() {
                if (!currentProsesInfo) return;

                const data = currentProsesInfo;
                const qtyAux = (typeof data.qty_aux !== 'undefined') ? parseInt(data.qty_aux) : 0;
                const existingNormalAuxCount = parseInt(data.existing_normal_aux_count) || 0;
                const usedNormalAuxSteps = data.used_normal_aux_steps || [];
                const usedAdditionAuxSteps = data.used_addition_aux_steps || [];

                const canCreateNormalAux = (qtyAux > 0) && data.can_create_normal_aux;

                // Jika Kebutuhan Aux QTY = 0 atau Kuota Normal Penuh
                if (qtyAux === 0 || !canCreateNormalAux) {
                    $('#tipe option[value="normal"]').prop('disabled', true);
                    $('#tipe').val('addition');
                    $('#tipe option[value="addition"]').prop('selected', true);
                    $('#info-aux-quota').html('').hide();
                } else {
                    $('#tipe option[value="normal"]').prop('disabled', false);
                    $('#info-aux-quota').show();
                    if (existingNormalAuxCount >= qtyAux) {
                        $('#info-aux-quota').html(`AUX Normal Dibuat: <span class="badge badge-danger">${existingNormalAuxCount} / ${qtyAux} (Kuota Penuh)</span>`);
                    } else {
                        $('#info-aux-quota').html(`AUX Normal Dibuat: <span class="badge badge-success">${existingNormalAuxCount} / ${qtyAux} (Sisa ${qtyAux - existingNormalAuxCount}x)</span>`);
                    }
                }

                const selectedTipe = $('#tipe').val() || (qtyAux === 0 || !canCreateNormalAux ? 'addition' : 'normal');

                // Auto-determine Jenis Aux (Normal, Perbaikan BDP, Reproses FG)
                const jenisProses = (data.jenis_proses || '').toLowerCase();
                const modeProses = (data.mode_proses || '').toLowerCase();
                let autoJenis = 'normal';
                if (jenisProses === 'produksi') {
                    if (selectedTipe === 'addition' || selectedTipe === 'additional') {
                        autoJenis = 'perbaikan';
                    } else {
                        autoJenis = 'normal';
                    }
                } else if (jenisProses === 'reproses') {
                    if (modeProses === 'finish') {
                        autoJenis = 'reproses';
                    } else {
                        autoJenis = 'perbaikan';
                    }
                }
                $('#jenis').val(autoJenis);

                const $stepSelect = $('#step_proses');
                const prevVal = $stepSelect.data('initial-val') || $stepSelect.val();
                $stepSelect.empty();

                if (selectedTipe === 'addition' || selectedTipe === 'additional') {
                    // Tipe Addition (Topping): 2 Pilihan Step (1 - Reactive, 2 - Dispers)
                    const isAdditionFull = usedAdditionAuxSteps.includes(1) && usedAdditionAuxSteps.includes(2);

                    if (isAdditionFull && (!prevVal || !usedAdditionAuxSteps.includes(parseInt(prevVal)))) {
                        $('#tipe-warning-text').text(`AUX Type Addition (Topping) untuk proses ini sudah mencapai batas maksimum (2x: Reactive & Dispers). Silakan pilih proses lain.`);
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
                const isWeightReadonly = allowEdit ? '' : 'readonly';
                const tr = `
                    <tr class="detail-row new-row" data-current-unit="kg" data-is-new="true">
                        <td>
                            <div>
                                <select name="details[${detailIndex}][auxiliary]" class="form-control select2-auxiliary" required></select>
                            </div>
                            <input type="hidden" name="details[${detailIndex}][unit]" class="unit-input" value="kg">
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.0001" name="details[${detailIndex}][konsentrasi]" class="form-control form-control-sm weight-input" placeholder="Weight (kg)" ${isWeightReadonly} required>
                                <div class="input-group-append">
                                    <span class="input-group-text unit-label">kg</span>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm btn-remove-detail"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
                $('#details-list').append(tr);
                const $newSelect = $('#details-list tr:last .select2-auxiliary');
                initAuxiliarySelect2($newSelect);
                updateRowWeightState($newSelect.closest('.detail-row'));
                detailIndex++;
                calcVolume();
            });

            $(document).on('click', '.btn-remove-detail', function () {
                const $row = $(this).closest('tr');
                const isExisting = $row.hasClass('existing-row') || $row.data('is-existing');

                if (isExisting && !allowEdit) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pengeditan Terkunci',
                        text: 'Silakan klik "Allow Edit" terlebih dahulu jika ingin menghapus baris kimia yang sudah tersimpan.'
                    });
                    return;
                }

                if ($('#details-list .detail-row').length > 1) {
                    $row.remove();
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
                        // ONLY target new rows that are readonly (waiting for scale), never overwrite existing saved rows!
                        const newReadonlyInputs = document.querySelectorAll('.new-row .weight-input[readonly]');
                        if (newReadonlyInputs.length > 0) {
                            newReadonlyInputs[newReadonlyInputs.length - 1].value = parseFloat(data.weight).toFixed(2);
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