@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-user-clock text-primary mr-2"></i>Menu Absen & Delegasi Wewenang
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Absen & Delegasi</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            {{-- Flash Notification Alerts --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><strong>Berhasil!</strong> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i><strong>Peringatan!</strong> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            {{-- Status Operasional & Auto-Reset Callout --}}
            <div class="card shadow-sm border-0 mb-4" style="border-left: 5px solid #17a2b8 !important;">
                <div class="card-body p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div class="mb-2 mb-md-0">
                            <div class="d-flex align-items-center flex-wrap mb-1">
                                <h5 class="font-weight-bold text-info mb-0 mr-2">
                                    <i class="fas fa-calendar-day mr-1"></i> Jadwal Operasional & Pendelegasian Wewenang
                                </h5>
                                @if(!empty($scheduleConfig['is_custom']))
                                    <span class="badge badge-warning text-dark px-2 py-1 shadow-xs font-weight-bold" title="Sedang berlaku jadwal kustom FM">
                                        <i class="fas fa-calendar-check mr-1"></i> Jadwal Kustom FM: {{ $scheduleConfig['custom_schedule_name'] ?? 'Kustom' }}
                                    </span>
                                    <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold ml-2 shadow-xs"
                                        onclick="confirmCancelSchedule({{ $scheduleConfig['custom_schedule_id'] }}, '{{ addslashes($scheduleConfig['custom_schedule_name'] ?? 'Jadwal Kustom') }}')">
                                        <i class="fas fa-undo mr-1"></i> Batalkan Jadwal Ini
                                    </button>
                                @else
                                    <span class="badge badge-light border text-muted px-2 py-1" title="Menggunakan jadwal standar operasional pabrik">
                                        <i class="fas fa-industry mr-1"></i> Jadwal Standar Pabrik (Default)
                                    </span>
                                @endif
                            </div>
                            <div class="text-muted" style="font-size: 0.9rem;">
                                <span><i class="far fa-calendar-alt text-secondary mr-1"></i> Tanggal Ditampilkan: <strong>{{ \Carbon\Carbon::parse($activeDate)->locale('id')->isoFormat('dddd, DD MMMM YYYY') }}</strong></span>
                                <span class="mx-2">|</span>
                                <span><i class="fas fa-clock text-secondary mr-1"></i> Jadwal: <strong>{{ $scheduleConfig['description'] }}</strong></span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            {{-- Tombol Atur Jadwal Shift (Range) --}}
                            <button type="button" class="btn btn-sm btn-primary px-3 py-2 font-weight-bold shadow-xs mr-2" data-toggle="modal" data-target="#modalTetapkanShift">
                                <i class="fas fa-calendar-plus mr-1"></i> Atur Jadwal Shift (Range)
                            </button>

                            {{-- Tombol Lihat Riwayat Jadwal Kustom --}}
                            <button type="button" class="btn btn-sm btn-outline-info px-3 py-2 font-weight-bold shadow-xs mr-2" data-toggle="modal" data-target="#modalDaftarShift">
                                <i class="fas fa-list-alt mr-1"></i> Riwayat Jadwal FM
                                @if(!empty($customSchedules) && count($customSchedules) > 0)
                                    <span class="badge badge-info ml-1">{{ count($customSchedules) }}</span>
                                @endif
                            </button>

                            {{-- Live Shift Badge --}}
                            @if($currentShiftInfo['is_production_off'])
                                <span class="badge badge-warning px-3 py-2 text-dark font-weight-bold shadow-xs mr-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-pause-circle mr-1"></i> {{ $currentShiftInfo['label'] }}
                                </span>
                            @else
                                <span class="badge badge-info px-3 py-2 font-weight-bold shadow-xs mr-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-broadcast-tower text-light mr-1"></i> Shift Aktif: <strong>{{ $currentShiftInfo['shift'] }}</strong> ({{ $currentShiftInfo['time_range'] }})
                                </span>
                            @endif

                            {{-- Auto Reset Badge --}}
                            <span class="badge badge-success px-3 py-2 font-weight-bold shadow-xs" style="font-size: 0.85rem;" title="Wewenang otomatis kembali Normal (ON) untuk shift berikutnya">
                                <i class="fas fa-sync-alt fa-spin mr-1"></i> Auto Reset ON
                            </span>
                        </div>
                    </div>

                    {{-- Panduan Pendelegasian Singkat --}}
                    <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center flex-wrap">
                        <p class="mb-0 text-muted" style="font-size: 0.88rem;">
                            <i class="fas fa-info-circle text-info mr-1"></i>
                            <strong>Kashift OFF:</strong> Approval topping, force finish, & cancel barcode dialihkan ke <strong>Karu</strong>.
                            &nbsp;|&nbsp;
                            <strong>Karu OFF:</strong> Request topping dialihkan ke <strong>Kashift</strong>.
                            &nbsp;|&nbsp;
                            <span class="text-success font-weight-bold"><i class="fas fa-magic mr-1"></i>Auto Reset:</span> Selesai satu shift, wewenang shift berikutnya otomatis kembali <strong>Hadir/Normal (ON)</strong>.
                        </p>

                        {{-- Date Switcher Form --}}
                        <form method="GET" action="{{ route('absen.index') }}" class="form-inline mt-2 mt-md-0">
                            <label class="mr-2 text-muted small font-weight-bold">Pilih Tanggal:</label>
                            <input type="date" name="active_date" value="{{ $activeDate }}" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                            @if($activeDate !== $currentShiftInfo['production_date'])
                                <a href="{{ route('absen.index') }}" class="btn btn-sm btn-outline-primary" title="Kembali ke Hari Ini">
                                    <i class="fas fa-undo mr-1"></i>Hari Ini
                                </a>
                            @endif
                        </form>
                    </div>

                    {{-- Banner Khusus Hari Minggu --}}
                    @if(\Carbon\Carbon::parse($activeDate)->dayOfWeek === \Carbon\Carbon::SUNDAY)
                        <div class="alert alert-light border mt-2 mb-0 py-2 px-3 small font-weight-bold text-muted" style="background-color: #f8f9fa;">
                            <i class="fas fa-bed text-primary mr-1"></i> <strong>Hari Minggu:</strong> Libur Produksi (OFF). Penugasan atau jadwal shift operasional tidak berjalan pada hari Minggu.
                        </div>
                    @endif
                </div>
            </div>

            <!-- KONTROL DUA SHIFT HARI INI: KASHIFT & KARU -->
            <div class="row">

                <!-- ================= KARTU KEPALA SHIFT (KASHIFT) ================= -->
                <div class="col-lg-6 mb-4">
                    <div class="card card-outline card-primary h-100 shadow-sm">
                        <div class="card-header bg-light py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="card-title font-weight-bold text-dark mb-0" style="font-size: 1.1rem;">
                                    <i class="fas fa-user-tie text-primary mr-2"></i>Status: Kepala Shift (Kashift)
                                </h4>
                                <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.8rem;">
                                    Role Target: kepala_shift
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-3">
                            <div class="alert alert-light border py-2 px-3 mb-3 text-muted small">
                                <i class="fas fa-shield-alt text-primary mr-1"></i>
                                <strong>Ketentuan Delegasi:</strong> Bila Kashift OFF pada shift berjalan, hak approval topping LA/AUX, force finish, dan cancel barcode otomatis dibuka untuk <strong>Kepala Ruangan (Karu)</strong>.
                            </div>

                            @php
                                $shiftsList = ['Shift 1', 'Shift 2'];
                                $kashiftRows = [
                                    'Shift 1' => $kashiftShift1,
                                    'Shift 2' => $kashiftShift2,
                                ];
                                $karuRows = [
                                    'Shift 1' => $karuShift1,
                                    'Shift 2' => $karuShift2,
                                ];
                            @endphp

                            @foreach($shiftsList as $sName)
                                @php
                                    $row = $kashiftRows[$sName];
                                    $karuRow = $karuRows[$sName] ?? null;
                                    $isKaruOff = ($karuRow && !$karuRow->is_active);
                                    $sInfo = $scheduleConfig['shifts'][$sName] ?? [
                                        'range' => ($sName === 'Shift 1' ? '06:00 - 18:00' : '18:00 - 06:00'),
                                        'duration' => 'Shift Kerja'
                                    ];
                                    $isShiftLive = (!$currentShiftInfo['is_production_off'] && $currentShiftInfo['production_date'] === $activeDate && $currentShiftInfo['shift'] === $sName);
                                @endphp

                                <div class="border rounded p-3 mb-3 {{ $row->is_active ? 'bg-white' : 'bg-light border-danger' }}" style="{{ $isShiftLive ? 'border: 2px solid #007bff !important;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                                        <div>
                                            <span class="font-weight-bold text-dark mr-2" style="font-size: 1rem;">
                                                <i class="far fa-clock text-secondary mr-1"></i>{{ $sName }} ({{ $sInfo['range'] }})
                                            </span>
                                            @if($isShiftLive)
                                                <span class="badge badge-primary px-2 py-1 font-weight-bold">
                                                    <i class="fas fa-bolt mr-1"></i>SEDANG BERJALAN (LIVE)
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1">
                                                    {{ $sInfo['duration'] ?? 'Shift' }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Badge Status ON / OFF --}}
                                        <div>
                                            @if($row->is_active)
                                                <span class="badge badge-success px-3 py-1 font-weight-bold" style="font-size: 0.88rem;">
                                                    <i class="fas fa-check-circle mr-1"></i> ON (Hadir / Normal)
                                                </span>
                                            @else
                                                <span class="badge badge-danger px-3 py-1 font-weight-bold" style="font-size: 0.88rem;">
                                                    <i class="fas fa-user-slash mr-1"></i> OFF (Absen / Izin)
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Banner Pendelegasian Khusus Jika OFF --}}
                                    @if(!$row->is_active)
                                        <div class="alert alert-warning py-1 px-2 mb-2 small font-weight-bold">
                                            <i class="fas fa-exchange-alt mr-1"></i>
                                            Wewenang {{ $sName }} dialihkan ke Karu: Approval topping & force finish dapat diproses oleh Kepala Ruangan.
                                        </div>
                                    @endif

                                    {{-- Banner Peringatan Jika Karu Sedang OFF (Kashift Terkunci Tidak Boleh Izin) --}}
                                    @if($row->is_active && $isKaruOff)
                                        <div class="alert alert-warning py-1 px-2 mb-2 small font-weight-bold text-dark" style="background-color: #fff3cd; border-color: #ffeeba;">
                                            <i class="fas fa-shield-alt text-warning mr-1"></i>
                                            <strong>Karu sedang OFF:</strong> Kashift tidak dapat mengajukan izin pada {{ $sName }} karena salah satu harus tetap hadir untuk wewenang operasional.
                                        </div>
                                    @endif

                                    {{-- Metadata Shift --}}
                                    <div class="row text-muted small mb-2">
                                        <div class="col-md-6">
                                            <i class="fas fa-comment-dots mr-1"></i>Keterangan: <strong>{{ $row->keterangan ?: '-' }}</strong>
                                        </div>
                                        <div class="col-md-6 text-md-right mt-1 mt-md-0">
                                            <i class="fas fa-user-edit mr-1"></i>Diubah: <strong>{{ $row->updater->nama ?? 'Sistem' }}</strong> 
                                            ({{ $row->updated_at ? $row->updated_at->format('d/m/Y H:i') : '-' }})
                                        </div>
                                    </div>

                                    {{-- Tombol Interaksi FM --}}
                                    <div class="d-flex justify-content-end pt-2 border-top">
                                        @if($row->is_active)
                                            @if($isKaruOff)
                                                <button type="button" class="btn btn-sm btn-secondary font-weight-bold" disabled
                                                    title="Tidak dapat izin: Kepala Ruangan (Karu) pada {{ $sName }} sudah berstatus OFF. Salah satu harus tetap hadir.">
                                                    <i class="fas fa-lock mr-1"></i> Terkunci (Karu Sudah Izin)
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold"
                                                    onclick="openToggleModal('kepala_shift', 'Kepala Shift (Kashift)', '{{ $sName }}', '{{ $sInfo['range'] }}', 'OFF')">
                                                    <i class="fas fa-user-slash mr-1"></i> Set {{ $sName }} ke OFF (Izin / Sakit)
                                                </button>
                                            @endif
                                        @else
                                            <button type="button" class="btn btn-sm btn-success font-weight-bold"
                                                onclick="openToggleModal('kepala_shift', 'Kepala Shift (Kashift)', '{{ $sName }}', '{{ $sInfo['range'] }}', 'ON')">
                                                <i class="fas fa-user-check mr-1"></i> Kembalikan {{ $sName }} ke ON (Hadir)
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- ================= KARTU KEPALA RUANGAN (KARU) ================= -->
                <div class="col-lg-6 mb-4">
                    <div class="card card-outline card-info h-100 shadow-sm">
                        <div class="card-header bg-light py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="card-title font-weight-bold text-dark mb-0" style="font-size: 1.1rem;">
                                    <i class="fas fa-user-cog text-info mr-2"></i>Status: Kepala Ruangan (Karu)
                                </h4>
                                <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.8rem;">
                                    Role Target: kepala_ruangan
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-3">
                            <div class="alert alert-light border py-2 px-3 mb-3 text-muted small">
                                <i class="fas fa-tools text-info mr-1"></i>
                                <strong>Ketentuan Delegasi:</strong> Bila Karu OFF pada shift berjalan, hak pengajuan Request Topping LA/AUX dan scan barcode dibuka untuk <strong>Kepala Shift</strong>.
                            </div>

                            @php
                                $karuRows = [
                                    'Shift 1' => $karuShift1,
                                    'Shift 2' => $karuShift2,
                                ];
                            @endphp

                            @foreach($shiftsList as $sName)
                                @php
                                    $row = $karuRows[$sName];
                                    $kashiftRow = $kashiftRows[$sName] ?? null;
                                    $isKashiftOff = ($kashiftRow && !$kashiftRow->is_active);
                                    $sInfo = $scheduleConfig['shifts'][$sName] ?? [
                                        'range' => ($sName === 'Shift 1' ? '06:00 - 18:00' : '18:00 - 06:00'),
                                        'duration' => 'Shift Kerja'
                                    ];
                                    $isShiftLive = (!$currentShiftInfo['is_production_off'] && $currentShiftInfo['production_date'] === $activeDate && $currentShiftInfo['shift'] === $sName);
                                @endphp

                                <div class="border rounded p-3 mb-3 {{ $row->is_active ? 'bg-white' : 'bg-light border-danger' }}" style="{{ $isShiftLive ? 'border: 2px solid #17a2b8 !important;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                                        <div>
                                            <span class="font-weight-bold text-dark mr-2" style="font-size: 1rem;">
                                                <i class="far fa-clock text-secondary mr-1"></i>{{ $sName }} ({{ $sInfo['range'] }})
                                            </span>
                                            @if($isShiftLive)
                                                <span class="badge badge-info px-2 py-1 font-weight-bold">
                                                    <i class="fas fa-bolt mr-1"></i>SEDANG BERJALAN (LIVE)
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1">
                                                    {{ $sInfo['duration'] ?? 'Shift' }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Badge Status ON / OFF --}}
                                        <div>
                                            @if($row->is_active)
                                                <span class="badge badge-success px-3 py-1 font-weight-bold" style="font-size: 0.88rem;">
                                                    <i class="fas fa-check-circle mr-1"></i> ON (Hadir / Normal)
                                                </span>
                                            @else
                                                <span class="badge badge-danger px-3 py-1 font-weight-bold" style="font-size: 0.88rem;">
                                                    <i class="fas fa-user-slash mr-1"></i> OFF (Absen / Izin)
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Banner Pendelegasian Khusus Jika OFF --}}
                                    @if(!$row->is_active)
                                        <div class="alert alert-warning py-1 px-2 mb-2 small font-weight-bold">
                                            <i class="fas fa-exchange-alt mr-1"></i>
                                            Wewenang {{ $sName }} dialihkan ke Kashift: Pengajuan topping LA/AUX & scan barcode dapat dilakukan oleh Kepala Shift.
                                        </div>
                                    @endif

                                    {{-- Banner Peringatan Jika Kashift Sedang OFF (Karu Terkunci Tidak Boleh Izin) --}}
                                    @if($row->is_active && $isKashiftOff)
                                        <div class="alert alert-warning py-1 px-2 mb-2 small font-weight-bold text-dark" style="background-color: #fff3cd; border-color: #ffeeba;">
                                            <i class="fas fa-shield-alt text-warning mr-1"></i>
                                            <strong>Kashift sedang OFF:</strong> Karu tidak dapat mengajukan izin pada {{ $sName }} karena wewenang operasional sedang dialihkan ke Karu.
                                        </div>
                                    @endif

                                    {{-- Metadata Shift --}}
                                    <div class="row text-muted small mb-2">
                                        <div class="col-md-6">
                                            <i class="fas fa-comment-dots mr-1"></i>Keterangan: <strong>{{ $row->keterangan ?: '-' }}</strong>
                                        </div>
                                        <div class="col-md-6 text-md-right mt-1 mt-md-0">
                                            <i class="fas fa-user-edit mr-1"></i>Diubah: <strong>{{ $row->updater->nama ?? 'Sistem' }}</strong> 
                                            ({{ $row->updated_at ? $row->updated_at->format('d/m/Y H:i') : '-' }})
                                        </div>
                                    </div>

                                    {{-- Tombol Interaksi FM --}}
                                    <div class="d-flex justify-content-end pt-2 border-top">
                                        @if($row->is_active)
                                            @if($isKashiftOff)
                                                <button type="button" class="btn btn-sm btn-secondary font-weight-bold" disabled
                                                    title="Tidak dapat izin: Kepala Shift (Kashift) pada {{ $sName }} sudah berstatus OFF. Salah satu harus tetap hadir.">
                                                    <i class="fas fa-lock mr-1"></i> Terkunci (Kashift Sudah Izin)
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold"
                                                    onclick="openToggleModal('kepala_ruangan', 'Kepala Ruangan (Karu)', '{{ $sName }}', '{{ $sInfo['range'] }}', 'OFF')">
                                                    <i class="fas fa-user-slash mr-1"></i> Set {{ $sName }} ke OFF (Izin / Sakit)
                                                </button>
                                            @endif
                                        @else
                                            <button type="button" class="btn btn-sm btn-success font-weight-bold"
                                                onclick="openToggleModal('kepala_ruangan', 'Kepala Ruangan (Karu)', '{{ $sName }}', '{{ $sInfo['range'] }}', 'ON')">
                                                <i class="fas fa-user-check mr-1"></i> Kembalikan {{ $sName }} ke ON (Hadir)
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>

            <!-- ================= TABEL RIWAYAT ABSEN & PERUBAHAN STATUS ================= -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light py-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h3 class="card-title font-weight-bold text-dark mb-0" style="font-size: 1.05rem;">
                            <i class="fas fa-history text-primary mr-2"></i>Riwayat Absen & Perubahan Status
                        </h3>
                        <span class="text-muted small">
                            <i class="fas fa-shield-alt text-success mr-1"></i>Audit log permanen termasuk pencatatan otomatis oleh sistem Auto-Reset.
                        </span>
                    </div>
                </div>

                <div class="card-body p-3">
                    <!-- Filter Toolbar -->
                    <form method="GET" action="{{ route('absen.index') }}" class="mb-3">
                        <input type="hidden" name="active_date" value="{{ $activeDate }}">
                        <div class="row align-items-end">
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="text-muted mb-1 small font-weight-bold">Filter Tanggal Riwayat:</label>
                                <input type="date" name="tanggal" value="{{ request('tanggal') }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="text-muted mb-1 small font-weight-bold">Filter Shift:</label>
                                <select name="shift" class="form-control form-control-sm">
                                    <option value="">-- Semua Shift --</option>
                                    <option value="Shift 1" {{ request('shift') === 'Shift 1' ? 'selected' : '' }}>Shift 1</option>
                                    <option value="Shift 2" {{ request('shift') === 'Shift 2' ? 'selected' : '' }}>Shift 2</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="text-muted mb-1 small font-weight-bold">Filter Role:</label>
                                <select name="role_target" class="form-control form-control-sm">
                                    <option value="">-- Semua Role --</option>
                                    <option value="kepala_shift" {{ request('role_target') === 'kepala_shift' ? 'selected' : '' }}>Kepala Shift</option>
                                    <option value="kepala_ruangan" {{ request('role_target') === 'kepala_ruangan' ? 'selected' : '' }}>Kepala Ruangan</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <button type="submit" class="btn btn-sm btn-primary mr-1">
                                    <i class="fas fa-filter mr-1"></i>Filter
                                </button>
                                <a href="{{ route('absen.index', ['active_date' => $activeDate]) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-redo mr-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Table List -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover mb-0" style="font-size: 0.9rem;">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Shift</th>
                                    <th>Role Target</th>
                                    <th>Status Baru</th>
                                    <th>Keterangan / Catatan</th>
                                    <th>Diubah Oleh</th>
                                    <th>Waktu Input</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($histories as $index => $history)
                                    <tr>
                                        <td class="text-center">{{ $histories->firstItem() + $index }}</td>
                                        <td>
                                            <i class="far fa-calendar-alt text-muted mr-1"></i>
                                            {{ $history->tanggal ? $history->tanggal->format('d/m/Y') : '-' }}
                                        </td>
                                        <td>
                                            <span class="badge badge-info px-2 py-1">{{ $history->shift }}</span>
                                        </td>
                                        <td>
                                            @if($history->role_target === 'kepala_shift')
                                                <span class="badge badge-primary px-2 py-1"><i class="fas fa-user-tie mr-1"></i> Kepala Shift</span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1"><i class="fas fa-user-cog mr-1"></i> Kepala Ruangan</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($history->status === 'ON')
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> ON (Hadir)</span>
                                            @else
                                                <span class="badge badge-danger px-2 py-1"><i class="fas fa-power-off mr-1"></i> OFF (Absen)</span>
                                            @endif
                                        </td>
                                        <td>{{ $history->keterangan ?: '-' }}</td>
                                        <td>
                                            @if($history->user)
                                                <strong>{{ $history->user->nama }}</strong>
                                                <span class="badge badge-light border text-muted ml-1">{{ $history->user->role }}</span>
                                            @else
                                                <span class="badge badge-dark"><i class="fas fa-robot mr-1"></i>Sistem (Auto Reset)</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="far fa-clock mr-1"></i>{{ $history->created_at->format('d/m/Y H:i:s') }}
                                            </small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle mr-1"></i> Belum ada data riwayat perubahan absen/delegasi yang tercatat.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3 d-flex justify-content-end">
                        {{ $histories->links() }}
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- ================= MODAL INTERAKTIF TOGGLE ABSEN (SHIFT 1 & SHIFT 2) ================= -->
<div class="modal fade" id="modalToggleShift" tabindex="-1" role="dialog" aria-labelledby="modalToggleShiftLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('absen.toggle') }}" method="POST">
                @csrf
                <input type="hidden" name="role_target" id="modal_role_target">
                <input type="hidden" name="shift" id="modal_shift">
                <input type="hidden" name="status" id="modal_status_input">
                <input type="hidden" name="tanggal" value="{{ $activeDate }}">

                <div class="modal-header" id="modal_header_bg">
                    <h5 class="modal-title font-weight-bold text-white" id="modalToggleShiftLabel">
                        <i class="fas fa-user-shield mr-2"></i><span id="modal_title_text">Konfirmasi Status Absen</span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border p-2 mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Personil:</span>
                            <strong id="modal_role_label" class="text-dark">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Shift Kerja:</span>
                            <strong id="modal_shift_label" class="text-dark">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Tanggal:</span>
                            <strong class="text-primary">{{ \Carbon\Carbon::parse($activeDate)->format('d/m/Y') }}</strong>
                        </div>
                    </div>

                    <p class="text-muted small" id="modal_explanation_text">
                        Pendelegasian hanya berlaku untuk shift yang dipilih. Setelah shift selesai, sistem Auto-Reset akan secara otomatis mengembalikan wewenang ke normal untuk shift berikutnya.
                    </p>

                    <div class="form-group">
                        <label class="font-weight-bold small" id="modal_keterangan_label">Alasan Izin / Sakit:</label>
                        <input type="text" name="keterangan" id="modal_keterangan_input" class="form-control" 
                            placeholder="Tuliskan alasan izin / sakit..." required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" id="modal_submit_btn" class="btn btn-sm btn-danger font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL TETAPKAN JADWAL SHIFT KUSTOM (RANGE TANGGAL) ================= -->
<div class="modal fade" id="modalTetapkanShift" tabindex="-1" role="dialog" aria-labelledby="modalTetapkanShiftLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('absen.shift-schedule.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title font-weight-bold" id="modalTetapkanShiftLabel">
                        <i class="fas fa-calendar-plus mr-2"></i>Tetapkan Jadwal Shift 1 & Shift 2 (Range Tanggal)
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    {{-- Alert Ketentuan Operasional --}}
                    <div class="alert alert-info border py-2 px-3 mb-3 small" style="background-color: #e8f4fd; border-color: #bee5eb; color: #0c5460;">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Ketentuan Penetapan Jadwal Shift FM:</strong>
                        <ul class="mb-0 pl-3 mt-1">
                            <li>Jadwal kustom berlaku untuk hari <strong>Senin hingga Sabtu</strong> pada rentang tanggal yang dipilih.</li>
                            <li>Hari <strong>Minggu selalu otomatis Libur Produksi (OFF)</strong>.</li>
                            <li>Jika jadwal kustom ini nantinya <strong>dibatalkan</strong>, jam operasional otomatis kembali ke <strong>jadwal default pabrik</strong> (Jumat: 06:00-19:00 & 19:00-08:00, Senin: 14:00-22:00, dll).</li>
                        </ul>
                    </div>

                    {{-- Nama Jadwal & Preset Tanggal --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold small">Nama / Label Jadwal (Opsional):</label>
                        <input type="text" name="nama_jadwal" id="modal_nama_jadwal" class="form-control form-control-sm"
                            placeholder="Contoh: Jadwal Shift Ramadhan / Shift Khusus Maintenance">
                        <small class="form-text text-muted">Akan otomatis dinamai berdasarkan rentang tanggal jika dikosongkan.</small>
                    </div>

                    <div class="form-group mb-2">
                        <label class="font-weight-bold small d-block">Pilihan Cepat Rentang Tanggal (Senin s/d Sabtu):</label>
                        <div class="btn-group btn-group-sm mb-2" role="group">
                            <button type="button" class="btn btn-outline-secondary" onclick="setShiftPresetRange('this_week')">
                                <i class="far fa-calendar-alt mr-1"></i>Minggu Ini (Senin - Sabtu)
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="setShiftPresetRange('next_week')">
                                <i class="fas fa-forward mr-1"></i>Minggu Depan (Senin - Sabtu)
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="setShiftPresetRange('two_weeks')">
                                <i class="far fa-calendar-check mr-1"></i>2 Minggu Ke Depan
                            </button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small text-primary"><i class="far fa-calendar-alt mr-1"></i>Tanggal Mulai (Senin):</label>
                                <input type="date" name="tanggal_mulai" id="modal_shift_tgl_mulai" class="form-control" required value="{{ $activeDate }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small text-primary"><i class="far fa-calendar-alt mr-1"></i>Tanggal Selesai (Sabtu):</label>
                                <input type="date" name="tanggal_selesai" id="modal_shift_tgl_selesai" class="form-control" required value="{{ \Carbon\Carbon::parse($activeDate)->addDays(5)->toDateString() }}">
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    {{-- Pemilihan Mode Jadwal --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold small d-block text-dark">Pilih Model Jadwal Shift:</label>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100 shadow-xs" style="cursor: pointer;" onclick="selectModeJadwal('per_hari')">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="mode_per_hari" name="mode_jadwal" value="per_hari" class="custom-control-input" checked onchange="toggleModeJadwal(this.value)">
                                        <label class="custom-control-label font-weight-bold text-success mb-0" for="mode_per_hari" style="cursor: pointer;">
                                            <i class="fas fa-layer-group mr-1"></i> Jadwal Khusus Per Kelompok Hari
                                        </label>
                                    </div>
                                    <div class="small text-muted pl-4 mt-1">
                                        Beda jam untuk <strong>Senin</strong>, <strong>Sel-Kam</strong>, <strong>Jumat</strong>, &amp; <strong>Sabtu</strong>.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100 shadow-xs" style="cursor: pointer;" onclick="selectModeJadwal('seragam')">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="mode_seragam" name="mode_jadwal" value="seragam" class="custom-control-input" onchange="toggleModeJadwal(this.value)">
                                        <label class="custom-control-label font-weight-bold text-primary mb-0" for="mode_seragam" style="cursor: pointer;">
                                            <i class="fas fa-equals mr-1"></i> Mode Seragam (Senin s/d Sabtu Sama)
                                        </label>
                                    </div>
                                    <div class="small text-muted pl-4 mt-1">
                                        Semua hari kerja menggunakan jam Shift 1 &amp; Shift 2 yang sama.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================= PANEL 1: JADWAL KHUSUS PER KELOMPOK HARI ================= --}}
                    <div id="panelModePerHari">
                        {{-- 1. HARI SENIN --}}
                        <div class="card card-outline card-primary mb-3 shadow-xs">
                            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="font-weight-bold text-primary mb-0">
                                    <i class="far fa-calendar mr-1"></i> 1. Hari Senin (Awal Pekan)
                                </h6>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="senin_use_default" name="senin_use_default" value="1" checked onchange="toggleSeninDefault(this.checked)">
                                    <label class="custom-control-label font-weight-bold small text-muted" for="senin_use_default" style="cursor: pointer;">
                                        Gunakan Default Senin (14:00 - 22:00 &amp; 22:00 - 06:00)
                                    </label>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <div id="senin_default_msg" class="alert alert-light border py-2 px-3 mb-0 small text-muted">
                                    <i class="fas fa-check-circle text-success mr-1"></i>
                                    <strong>Standar Senin:</strong> Shift 1 <strong>14:00 - 22:00</strong> | Shift 2 <strong>22:00 - 06:00</strong> (Pagi 00:00 - 14:00 Libur Awal Pekan).
                                </div>
                                <div id="senin_custom_inputs" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="small font-weight-bold text-primary"><i class="far fa-sun text-warning mr-1"></i> Shift 1 Senin:</label>
                                            <div class="input-group input-group-sm">
                                                <input type="time" name="senin_shift1_start" class="form-control" value="14:00">
                                                <div class="input-group-append input-group-prepend"><span class="input-group-text">s/d</span></div>
                                                <input type="time" name="senin_shift1_end" class="form-control" value="22:00">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="small font-weight-bold text-info"><i class="far fa-moon text-primary mr-1"></i> Shift 2 Senin:</label>
                                            <div class="input-group input-group-sm">
                                                <input type="time" name="senin_shift2_start" class="form-control" value="22:00">
                                                <div class="input-group-append input-group-prepend"><span class="input-group-text">s/d</span></div>
                                                <input type="time" name="senin_shift2_end" class="form-control" value="06:00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 2. HARI SELASA S/D KAMIS --}}
                        <div class="card card-outline card-info mb-3 shadow-xs">
                            <div class="card-header py-2 bg-light">
                                <h6 class="font-weight-bold text-info mb-0">
                                    <i class="far fa-calendar-check mr-1"></i> 2. Hari Selasa s/d Kamis (Hari Kerja Penuh)
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="small font-weight-bold text-primary"><i class="far fa-sun text-warning mr-1"></i> Shift 1 (Siang):</label>
                                        <div class="input-group input-group-sm">
                                            <input type="time" name="selasa_shift1_start" class="form-control" value="06:00">
                                            <div class="input-group-append input-group-prepend"><span class="input-group-text">s/d</span></div>
                                            <input type="time" name="selasa_shift1_end" class="form-control" value="18:00">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small font-weight-bold text-info"><i class="far fa-moon text-primary mr-1"></i> Shift 2 (Malam):</label>
                                        <div class="input-group input-group-sm">
                                            <input type="time" name="selasa_shift2_start" class="form-control" value="18:00">
                                            <div class="input-group-append input-group-prepend"><span class="input-group-text">s/d</span></div>
                                            <input type="time" name="selasa_shift2_end" class="form-control" value="06:00">
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>Berlaku serentak untuk hari Selasa, Rabu, dan Kamis.</small>
                            </div>
                        </div>

                        {{-- 3. HARI JUMAT --}}
                        <div class="card card-outline card-warning mb-3 shadow-xs">
                            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="font-weight-bold text-warning text-dark mb-0">
                                    <i class="far fa-clock mr-1"></i> 3. Hari Jumat (Aturan Resmi Pabrik)
                                </h6>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="jumat_use_default" name="jumat_use_default" value="1" checked onchange="toggleJumatDefault(this.checked)">
                                    <label class="custom-control-label font-weight-bold small text-muted" for="jumat_use_default" style="cursor: pointer;">
                                        Gunakan Standar Resmi Jumat (06:00 - 19:00 &amp; 19:00 - 08:00)
                                    </label>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <div id="jumat_default_msg" class="alert alert-light border py-2 px-3 mb-0 small text-muted">
                                    <i class="fas fa-check-circle text-success mr-1"></i>
                                    <strong>Standar Resmi Jumat:</strong> Shift 1 <strong>06:00 - 19:00</strong> | Shift 2 <strong>19:00 - 08:00</strong> (Shift 2 berlanjut menyeberang hingga Sabtu pagi jam 08:00).
                                </div>
                                <div id="jumat_custom_inputs" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="small font-weight-bold text-primary"><i class="far fa-sun text-warning mr-1"></i> Shift 1 Jumat:</label>
                                            <div class="input-group input-group-sm">
                                                <input type="time" name="jumat_shift1_start" class="form-control" value="06:00">
                                                <div class="input-group-append input-group-prepend"><span class="input-group-text">s/d</span></div>
                                                <input type="time" name="jumat_shift1_end" class="form-control" value="19:00">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="small font-weight-bold text-info"><i class="far fa-moon text-primary mr-1"></i> Shift 2 Jumat:</label>
                                            <div class="input-group input-group-sm">
                                                <input type="time" name="jumat_shift2_start" class="form-control" value="19:00">
                                                <div class="input-group-append input-group-prepend"><span class="input-group-text">s/d</span></div>
                                                <input type="time" name="jumat_shift2_end" class="form-control" value="08:00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 4. HARI SABTU --}}
                        <div class="card card-outline card-secondary mb-3 shadow-xs">
                            <div class="card-header py-2 bg-light">
                                <h6 class="font-weight-bold text-secondary mb-0">
                                    <i class="fas fa-bed mr-1"></i> 4. Hari Sabtu (Akhir Pekan)
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="custom-control custom-radio mb-2">
                                    <input type="radio" id="sabtu_opt_off" name="sabtu_option" value="off" class="custom-control-input" checked onchange="toggleSabtuOption(this.value)">
                                    <label class="custom-control-label font-weight-bold small" for="sabtu_opt_off" style="cursor: pointer;">
                                        <span class="text-danger font-weight-bold"><i class="fas fa-ban mr-1"></i>Libur Produksi</span> (Lanjutan Shift 2 Jumat s/d 08:00 pagi, setelahnya OFF/Libur)
                                    </label>
                                </div>
                                <div class="custom-control custom-radio mb-2">
                                    <input type="radio" id="sabtu_opt_custom" name="sabtu_option" value="custom" class="custom-control-input" onchange="toggleSabtuOption(this.value)">
                                    <label class="custom-control-label font-weight-bold small text-primary" for="sabtu_opt_custom" style="cursor: pointer;">
                                        <i class="fas fa-cogs mr-1"></i>Jadwal Kerja Khusus Sabtu (Pabrik tetap operasional di hari Sabtu)
                                    </label>
                                </div>

                                <div id="sabtu_custom_inputs" style="display: none;" class="mt-3 pt-2 border-top">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="small font-weight-bold text-primary"><i class="far fa-sun text-warning mr-1"></i> Shift 1 Sabtu:</label>
                                            <div class="input-group input-group-sm">
                                                <input type="time" name="sabtu_shift1_start" class="form-control" value="06:00">
                                                <div class="input-group-append input-group-prepend"><span class="input-group-text">s/d</span></div>
                                                <input type="time" name="sabtu_shift1_end" class="form-control" value="18:00">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="small font-weight-bold text-info"><i class="far fa-moon text-primary mr-1"></i> Shift 2 Sabtu:</label>
                                            <div class="input-group input-group-sm">
                                                <input type="time" name="sabtu_shift2_start" class="form-control" value="18:00">
                                                <div class="input-group-append input-group-prepend"><span class="input-group-text">s/d</span></div>
                                                <input type="time" name="sabtu_shift2_end" class="form-control" value="06:00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 5. HARI MINGGU INFO --}}
                        <div class="alert alert-light border py-2 px-3 mb-2 small text-muted">
                            <i class="fas fa-lock text-danger mr-1"></i> <strong>Hari Minggu:</strong> Otomatis Libur Produksi (OFF) permanen sesuai kebijakan pabrik.
                        </div>
                    </div>

                    {{-- ================= PANEL 2: MODE SERAGAM ================= --}}
                    <div id="panelModeSeragam" style="display: none;">
                        <div class="row">
                            <!-- Kartu Shift 1 -->
                            <div class="col-md-6 mb-3">
                                <div class="card card-outline card-primary h-100 shadow-xs mb-0">
                                    <div class="card-header py-2 bg-light">
                                        <h6 class="font-weight-bold text-primary mb-0">
                                            <i class="far fa-sun text-warning mr-1"></i> Konfigurasi Shift 1 (Siang)
                                        </h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold">Jam Mulai Shift 1:</label>
                                            <input type="time" name="shift1_start" id="modal_shift1_start" class="form-control" value="06:00">
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="small font-weight-bold">Jam Selesai Shift 1:</label>
                                            <input type="time" name="shift1_end" id="modal_shift1_end" class="form-control" value="18:00">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kartu Shift 2 -->
                            <div class="col-md-6 mb-3">
                                <div class="card card-outline card-info h-100 shadow-xs mb-0">
                                    <div class="card-header py-2 bg-light">
                                        <h6 class="font-weight-bold text-info mb-0">
                                            <i class="far fa-moon text-primary mr-1"></i> Konfigurasi Shift 2 (Malam)
                                        </h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold">Jam Mulai Shift 2:</label>
                                            <input type="time" name="shift2_start" id="modal_shift2_start" class="form-control" value="18:00">
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="small font-weight-bold">Jam Selesai Shift 2:</label>
                                            <input type="time" name="shift2_end" id="modal_shift2_end" class="form-control" value="06:00">
                                        </div>
                                        <small class="form-text text-muted mt-2">
                                            <i class="fas fa-info-circle mr-1"></i>Bila jam selesai lebih kecil dari jam mulai (misal 18:00 - 06:00), sistem otomatis mengenalinya sebagai shift malam yang berlanjut ke pagi esoknya.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Keterangan / Alasan --}}
                    <div class="form-group mb-1">
                        <label class="font-weight-bold small">Keterangan / Alasan Perubahan:</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm"
                            placeholder="Contoh: Penyesuaian shift operasional mingguan oleh Factory Manager">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan & Terapkan Jadwal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL RIWAYAT / DAFTAR JADWAL SHIFT KUSTOM FM ================= -->
<div class="modal fade" id="modalDaftarShift" tabindex="-1" role="dialog" aria-labelledby="modalDaftarShiftLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title font-weight-bold" id="modalDaftarShiftLabel">
                    <i class="fas fa-calendar-alt mr-2"></i>Daftar Penetapan Jadwal Shift Kustom FM
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0" style="font-size: 0.88rem;">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" style="width: 35px;">No</th>
                                <th>Nama Jadwal</th>
                                <th style="width: 130px;">Model Jadwal</th>
                                <th>Rentang Tanggal (Senin - Sabtu)</th>
                                <th>Rincian Shift Operasional</th>
                                <th class="text-center" style="width: 90px;">Status</th>
                                <th>Ditetapkan Oleh</th>
                                <th>Keterangan</th>
                                <th class="text-center" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customSchedules as $idx => $sched)
                                <tr class="{{ $sched->is_active ? '' : 'table-secondary text-muted' }}">
                                    <td class="text-center">{{ $idx + 1 }}</td>
                                    <td>
                                        <strong>{{ $sched->nama_jadwal }}</strong>
                                    </td>
                                    <td>
                                        @if($sched->mode_jadwal === 'per_hari')
                                            <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-layer-group mr-1"></i>Khusus Per Hari</span>
                                        @else
                                            <span class="badge badge-info px-2 py-1"><i class="fas fa-equals mr-1"></i>Seragam</span>
                                        @endif
                                    </td>
                                    <td>
                                        <i class="far fa-calendar-alt mr-1 text-primary"></i>
                                        <strong>{{ $sched->tanggal_mulai->format('d/m/Y') }}</strong>
                                        <span class="text-muted mx-1">s/d</span>
                                        <strong>{{ $sched->tanggal_selesai->format('d/m/Y') }}</strong>
                                    </td>
                                    <td>
                                        @if($sched->mode_jadwal === 'per_hari' && !empty($sched->daily_config))
                                            <ul class="list-unstyled mb-0 font-weight-normal" style="font-size: 0.82rem; line-height: 1.4;">
                                                <li>
                                                    <strong>Sen:</strong>
                                                    @if(!empty($sched->daily_config['senin']['use_default']))
                                                        <span class="text-muted">Default (14-22 / 22-06)</span>
                                                    @else
                                                        <span class="badge badge-light border">{{ $sched->daily_config['senin']['shift1_start'] ?? '14:00' }}-{{ $sched->daily_config['senin']['shift1_end'] ?? '22:00' }}</span> | <span class="badge badge-light border">{{ $sched->daily_config['senin']['shift2_start'] ?? '22:00' }}-{{ $sched->daily_config['senin']['shift2_end'] ?? '06:00' }}</span>
                                                    @endif
                                                </li>
                                                <li>
                                                    <strong>Sel-Kam:</strong>
                                                    <span class="badge badge-light border">{{ $sched->daily_config['selasa_kamis']['shift1_start'] ?? '06:00' }}-{{ $sched->daily_config['selasa_kamis']['shift1_end'] ?? '18:00' }}</span> | <span class="badge badge-light border">{{ $sched->daily_config['selasa_kamis']['shift2_start'] ?? '18:00' }}-{{ $sched->daily_config['selasa_kamis']['shift2_end'] ?? '06:00' }}</span>
                                                </li>
                                                <li>
                                                    <strong>Jum:</strong>
                                                    @if(!empty($sched->daily_config['jumat']['use_default']))
                                                        <span class="text-muted">Resmi (06-19 / 19-08)</span>
                                                    @else
                                                        <span class="badge badge-light border">{{ $sched->daily_config['jumat']['shift1_start'] ?? '06:00' }}-{{ $sched->daily_config['jumat']['shift1_end'] ?? '19:00' }}</span> | <span class="badge badge-light border">{{ $sched->daily_config['jumat']['shift2_start'] ?? '19:00' }}-{{ $sched->daily_config['jumat']['shift2_end'] ?? '08:00' }}</span>
                                                    @endif
                                                </li>
                                                <li>
                                                    <strong>Sab:</strong>
                                                    @if(!empty($sched->daily_config['sabtu']['is_off']))
                                                        <span class="badge badge-danger">Libur (OFF &gt; 08:00)</span>
                                                    @else
                                                        <span class="badge badge-light border">{{ $sched->daily_config['sabtu']['shift1_start'] ?? '06:00' }}-{{ $sched->daily_config['sabtu']['shift1_end'] ?? '18:00' }}</span>
                                                    @endif
                                                </li>
                                            </ul>
                                        @else
                                            <span class="badge badge-light border font-weight-bold">
                                                S1: {{ substr($sched->shift1_start, 0, 5) }}-{{ substr($sched->shift1_end, 0, 5) }}
                                            </span>
                                            <span class="badge badge-light border font-weight-bold ml-1">
                                                S2: {{ substr($sched->shift2_start, 0, 5) }}-{{ substr($sched->shift2_end, 0, 5) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($sched->is_active)
                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Aktif</span>
                                        @else
                                            <span class="badge badge-secondary px-2 py-1"><i class="fas fa-ban mr-1"></i>Dibatalkan</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>
                                            <strong>{{ $sched->creator->nama ?? 'FM' }}</strong><br>
                                            <span class="text-muted">{{ $sched->created_at->format('d/m/Y H:i') }}</span>
                                        </small>
                                    </td>
                                    <td>
                                        <small>{{ $sched->keterangan ?: '-' }}</small>
                                        @if(!$sched->is_active && $sched->canceller)
                                            <div class="text-danger small mt-1">
                                                <i class="fas fa-times-circle mr-1"></i>Dibatalkan oleh: {{ $sched->canceller->nama }} ({{ $sched->cancelled_at ? $sched->cancelled_at->format('d/m/Y H:i') : '-' }})
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($sched->is_active)
                                            <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold"
                                                onclick="confirmCancelSchedule({{ $sched->id }}, '{{ addslashes($sched->nama_jadwal) }}')">
                                                <i class="fas fa-undo mr-1"></i> Batalkan
                                            </button>
                                        @else
                                            <span class="text-muted small"><i class="fas fa-check text-muted mr-1"></i>Sudah Default</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle mr-1"></i> Belum ada penetapan jadwal kustom FM. Sistem saat ini berjalan penuh menggunakan jadwal default pabrik.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Form tersembunyi untuk pembatalan jadwal kustom -->
<form id="formCancelSchedule" method="POST" style="display: none;">
    @csrf
</form>

<script>
    function openToggleModal(roleTarget, roleLabel, shiftName, shiftRange, targetStatus) {
        document.getElementById('modal_role_target').value = roleTarget;
        document.getElementById('modal_shift').value = shiftName;
        document.getElementById('modal_role_label').innerText = roleLabel;
        document.getElementById('modal_shift_label').innerText = shiftName + ' (' + shiftRange + ')';
        document.getElementById('modal_status_input').value = targetStatus;

        const ketInput = document.getElementById('modal_keterangan_input');
        const ketLabel = document.getElementById('modal_keterangan_label');
        const explanationText = document.getElementById('modal_explanation_text');

        // Selalu kosongkan isian agar tidak ada teks otomatis/lama yang nyangkut
        ketInput.value = '';

        if (targetStatus === 'OFF') {
            ketLabel.innerHTML = 'Alasan Izin / Sakit: <span class="text-danger">*</span>';
            ketInput.placeholder = 'Tuliskan alasan izin / sakit (misal: Sakit demam, Izin keperluan keluarga, dll)';
            ketInput.required = true;
            explanationText.innerText = 'Pendelegasian wewenang hanya berlaku untuk shift ini. Setelah shift selesai, sistem Auto-Reset otomatis mengembalikan wewenang ke Hadir/Normal.';
        } else {
            ketLabel.innerHTML = 'Catatan Kembali Bertugas (Opsional):';
            ketInput.placeholder = 'Contoh: Masuk kerja kembali normal (boleh dikosongkan)';
            ketInput.required = false;
            explanationText.innerText = 'Mengembalikan status personil menjadi Hadir/Normal kembali pada shift ini.';
        }

        handleModalStatusChange(targetStatus);
        $('#modalToggleShift').modal('show');
    }

    function handleModalStatusChange(status) {
        const headerBg = document.getElementById('modal_header_bg');
        const submitBtn = document.getElementById('modal_submit_btn');
        const titleText = document.getElementById('modal_title_text');

        if (status === 'OFF') {
            headerBg.className = 'modal-header bg-danger text-white';
            submitBtn.className = 'btn btn-sm btn-danger font-weight-bold';
            submitBtn.innerHTML = '<i class="fas fa-user-slash mr-1"></i> Terapkan OFF (Pendelegasian Aktif)';
            titleText.innerText = 'Nonaktifkan Wewenang (Set ke OFF)';
        } else {
            headerBg.className = 'modal-header bg-success text-white';
            submitBtn.className = 'btn btn-sm btn-success font-weight-bold';
            submitBtn.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Aktifkan Kembali ke ON';
            titleText.innerText = 'Aktifkan Kembali Wewenang (Set ke ON)';
        }
    }

    function confirmCancelSchedule(id, scheduleName) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Batalkan Jadwal Kustom?',
                html: `Apakah Anda yakin ingin membatalkan jadwal kustom <strong>"${scheduleName}"</strong>?<br><br><small class="text-muted">Jam kerja operasional untuk rentang tanggal tersebut akan otomatis kembali menggunakan <strong>jadwal default pabrik</strong> (Jumat: 06:00-19:00 & 19:00-08:00, Senin: 14:00-22:00, dll).</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-undo mr-1"></i> Ya, Batalkan Jadwal',
                cancelButtonText: 'Tidak'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('formCancelSchedule');
                    form.action = "{{ url('/absen/shift-schedule') }}/" + id + "/cancel";
                    form.submit();
                }
            });
        } else {
            if (confirm(`Apakah Anda yakin ingin membatalkan jadwal kustom "${scheduleName}"? Jam kerja akan kembali ke default pabrik.`)) {
                const form = document.getElementById('formCancelSchedule');
                form.action = "{{ url('/absen/shift-schedule') }}/" + id + "/cancel";
                form.submit();
            }
        }
    }

    function setShiftPresetRange(presetType) {
        const today = new Date();
        let monday = new Date(today);
        
        // Day of week: 0 = Sunday, 1 = Monday, ..., 6 = Saturday
        const day = today.getDay();
        const diffToMonday = (day === 0 ? -6 : 1) - day;
        monday.setDate(today.getDate() + diffToMonday);

        if (presetType === 'next_week') {
            monday.setDate(monday.getDate() + 7);
        }

        let saturday = new Date(monday);
        saturday.setDate(monday.getDate() + (presetType === 'two_weeks' ? 12 : 5));

        const formatDate = (d) => {
            const month = '' + (d.getMonth() + 1);
            const date = '' + d.getDate();
            const year = d.getFullYear();
            return [year, month.padStart(2, '0'), date.padStart(2, '0')].join('-');
        };

        document.getElementById('modal_shift_tgl_mulai').value = formatDate(monday);
        document.getElementById('modal_shift_tgl_selesai').value = formatDate(saturday);

        const formatLabel = (d) => {
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        };
        document.getElementById('modal_nama_jadwal').value = 'Penetapan Shift ' + formatLabel(monday) + ' - ' + formatLabel(saturday) + ' ' + saturday.getFullYear();
    }

    function selectModeJadwal(mode) {
        if (mode === 'per_hari') {
            document.getElementById('mode_per_hari').checked = true;
        } else {
            document.getElementById('mode_seragam').checked = true;
        }
        toggleModeJadwal(mode);
    }

    function toggleModeJadwal(mode) {
        const panelPerHari = document.getElementById('panelModePerHari');
        const panelSeragam = document.getElementById('panelModeSeragam');
        const s1Start = document.getElementById('modal_shift1_start');
        const s1End = document.getElementById('modal_shift1_end');
        const s2Start = document.getElementById('modal_shift2_start');
        const s2End = document.getElementById('modal_shift2_end');

        if (mode === 'seragam') {
            panelPerHari.style.display = 'none';
            panelSeragam.style.display = 'block';
            if (s1Start) s1Start.required = true;
            if (s1End) s1End.required = true;
            if (s2Start) s2Start.required = true;
            if (s2End) s2End.required = true;
        } else {
            panelPerHari.style.display = 'block';
            panelSeragam.style.display = 'none';
            if (s1Start) s1Start.required = false;
            if (s1End) s1End.required = false;
            if (s2Start) s2Start.required = false;
            if (s2End) s2End.required = false;
        }
    }

    function toggleSeninDefault(useDefault) {
        const defaultMsg = document.getElementById('senin_default_msg');
        const customInputs = document.getElementById('senin_custom_inputs');
        if (useDefault) {
            defaultMsg.style.display = 'block';
            customInputs.style.display = 'none';
        } else {
            defaultMsg.style.display = 'none';
            customInputs.style.display = 'block';
        }
    }

    function toggleJumatDefault(useDefault) {
        const defaultMsg = document.getElementById('jumat_default_msg');
        const customInputs = document.getElementById('jumat_custom_inputs');
        if (useDefault) {
            defaultMsg.style.display = 'block';
            customInputs.style.display = 'none';
        } else {
            defaultMsg.style.display = 'none';
            customInputs.style.display = 'block';
        }
    }

    function toggleSabtuOption(option) {
        const customInputs = document.getElementById('sabtu_custom_inputs');
        if (option === 'custom') {
            customInputs.style.display = 'block';
        } else {
            customInputs.style.display = 'none';
        }
    }
</script>
@endsection
