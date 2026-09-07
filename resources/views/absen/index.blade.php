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
                            <h5 class="font-weight-bold text-info mb-1">
                                <i class="fas fa-calendar-day mr-1"></i> Jadwal Operasional & Pendelegasian Wewenang
                            </h5>
                            <div class="text-muted" style="font-size: 0.9rem;">
                                <span><i class="far fa-calendar-alt text-secondary mr-1"></i> Tanggal Ditampilkan: <strong>{{ \Carbon\Carbon::parse($activeDate)->locale('id')->isoFormat('dddd, DD MMMM YYYY') }}</strong></span>
                                <span class="mx-2">|</span>
                                <span><i class="fas fa-clock text-secondary mr-1"></i> Jadwal: <strong>{{ $scheduleConfig['description'] }}</strong></span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            {{-- Live Shift Badge --}}
                            @if($currentShiftInfo['is_production_off'])
                                <span class="badge badge-warning px-3 py-2 text-dark font-weight-bold shadow-xs mr-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-pause-circle mr-1"></i> {{ $currentShiftInfo['label'] }}
                                </span>
                            @else
                                <span class="badge badge-primary px-3 py-2 font-weight-bold shadow-xs mr-2" style="font-size: 0.9rem;">
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
                            @endphp

                            @foreach($shiftsList as $sName)
                                @php
                                    $row = $kashiftRows[$sName];
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
                                            <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold"
                                                onclick="openToggleModal('kepala_shift', 'Kepala Shift (Kashift)', '{{ $sName }}', '{{ $sInfo['range'] }}', 'OFF', '{{ addslashes($row->keterangan ?? '') }}')">
                                                <i class="fas fa-user-slash mr-1"></i> Set {{ $sName }} ke OFF (Izin / Sakit)
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-success font-weight-bold"
                                                onclick="openToggleModal('kepala_shift', 'Kepala Shift (Kashift)', '{{ $sName }}', '{{ $sInfo['range'] }}', 'ON', 'Hadir kembali / Normal')">
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
                                            <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold"
                                                onclick="openToggleModal('kepala_ruangan', 'Kepala Ruangan (Karu)', '{{ $sName }}', '{{ $sInfo['range'] }}', 'OFF', '{{ addslashes($row->keterangan ?? '') }}')">
                                                <i class="fas fa-user-slash mr-1"></i> Set {{ $sName }} ke OFF (Izin / Sakit)
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-success font-weight-bold"
                                                onclick="openToggleModal('kepala_ruangan', 'Kepala Ruangan (Karu)', '{{ $sName }}', '{{ $sInfo['range'] }}', 'ON', 'Hadir kembali / Normal')">
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
                        <label class="font-weight-bold small">Status Wewenang Baru:</label>
                        <select name="status" id="modal_status_select" class="form-control" required onchange="handleModalStatusChange(this.value)">
                            <option value="OFF">OFF (Absen / Sakit / Izin - Delegasikan Wewenang)</option>
                            <option value="ON">ON (Hadir / Normal Kembali)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Keterangan / Alasan Izin:</label>
                        <input type="text" name="keterangan" id="modal_keterangan_input" class="form-control" 
                            placeholder="Contoh: Izin Sakit, Keperluan Keluarga, Kembali Bertugas..." required>
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

<script>
    function openToggleModal(roleTarget, roleLabel, shiftName, shiftRange, targetStatus, currentNote) {
        document.getElementById('modal_role_target').value = roleTarget;
        document.getElementById('modal_shift').value = shiftName;
        document.getElementById('modal_role_label').innerText = roleLabel;
        document.getElementById('modal_shift_label').innerText = shiftName + ' (' + shiftRange + ')';
        document.getElementById('modal_status_select').value = targetStatus;

        const ketInput = document.getElementById('modal_keterangan_input');
        if (targetStatus === 'ON') {
            ketInput.value = 'Hadir kembali / Normal';
        } else {
            ketInput.value = (currentNote && currentNote !== 'Default sistem: Aktif / Hadir' && currentNote !== 'Hadir kembali / Normal') ? currentNote : '';
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
</script>
@endsection
