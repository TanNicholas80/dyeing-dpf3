@extends('layout.main')

@section('content')
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold">
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

            {{-- Guidance & Overview Callout --}}
            <div class="callout callout-info shadow-sm bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h5 class="font-weight-bold text-info mb-1">
                            <i class="fas fa-info-circle mr-1"></i> Sistem Pendelegasian Wewenang Otomatis
                        </h5>
                        <p class="mb-0 text-muted" style="font-size: 0.95rem;">
                            Fitur ini digunakan saat personil <strong>Kepala Shift (Kashift)</strong> atau <strong>Kepala Ruangan (Karu)</strong> berhalangan / izin / sakit di lapangan:
                        </p>
                        <ul class="mb-0 mt-1 pl-3 text-muted" style="font-size: 0.9rem;">
                            <li>Bila <strong>Kashift OFF</strong>: Semua wewenang approval topping LA/AUX, force finish proses, dan cancel barcode otomatis <strong>dibuka untuk Karu</strong>.</li>
                            <li>Bila <strong>Karu OFF</strong>: Wewenang pengajuan request topping dan scan barcode topping otomatis <strong>dibuka untuk Kashift</strong>.</li>
                            <li>Default sistem: <strong>Keduanya ON</strong>. Hanya role <strong>Factory Manager (FM)</strong> dan <strong>Admin (Super Admin)</strong> yang berhak mengubah status.</li>
                        </ul>
                    </div>
                    <div class="text-right mt-2 mt-md-0">
                        <span class="badge badge-light border px-3 py-2 text-dark font-weight-bold">
                            <i class="fas fa-business-time text-primary mr-1"></i> Shift Terdeteksi: <span class="text-primary">{{ $currentShift }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Kontrol Status Kartu (Kashift & Karu) -->
            <div class="row">
                <!-- KARTU KEPALA SHIFT -->
                <div class="col-md-6 mb-4">
                    <div class="card card-outline {{ $delegasiKashift->is_active ? 'card-success' : 'card-danger' }} h-100 shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="card-title font-weight-bold text-dark mb-0">
                                    <i class="fas fa-user-tie text-secondary mr-2"></i>Status: Kepala Shift (Kashift)
                                </h3>
                                @if($delegasiKashift->is_active)
                                    <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.9rem;">
                                        <i class="fas fa-check-circle mr-1"></i> ON (Hadir / Normal)
                                    </span>
                                @else
                                    <span class="badge badge-danger px-3 py-2 font-weight-bold" style="font-size: 0.9rem;">
                                        <i class="fas fa-power-off mr-1"></i> OFF (Absen / Izin)
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            @if(!$delegasiKashift->is_active)
                                <div class="alert alert-warning py-2 mb-3">
                                    <i class="fas fa-exchange-alt mr-1"></i>
                                    <strong>Pendelegasian Aktif:</strong> Hak approval topping & force finish dialihkan ke <strong>Kepala Ruangan (Karu)</strong>.
                                </div>
                            @else
                                <div class="alert alert-light border py-2 mb-3 text-muted">
                                    <i class="fas fa-shield-alt text-success mr-1"></i>
                                    <strong>Normal:</strong> Approval topping dikendalikan penuh oleh Kepala Shift.
                                </div>
                            @endif

                            <table class="table table-sm table-borderless mb-2">
                                <tr>
                                    <td class="text-muted" style="width: 40%;"><i class="fas fa-calendar-alt mr-1"></i> Shift Terakhir</td>
                                    <td class="font-weight-bold">: {{ $delegasiKashift->current_shift ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-comment-alt mr-1"></i> Keterangan</td>
                                    <td class="font-weight-bold">: {{ $delegasiKashift->keterangan ?: 'Tidak ada catatan' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-user-edit mr-1"></i> Terakhir Diubah</td>
                                    <td>: {{ $delegasiKashift->updater->nama ?? 'Sistem' }} ({{ $delegasiKashift->updated_at ? $delegasiKashift->updated_at->format('d/m/Y H:i:s') : '-' }})</td>
                                </tr>
                            </table>
                        </div>
                        <div class="card-footer bg-white border-top">
                            <button type="button" class="btn {{ $delegasiKashift->is_active ? 'btn-outline-danger' : 'btn-success' }} btn-block font-weight-bold shadow-sm"
                                data-toggle="modal" data-target="#modalToggleKashift">
                                @if($delegasiKashift->is_active)
                                    <i class="fas fa-user-slash mr-1"></i> Set Kashift ke OFF (Izin / Absen)
                                @else
                                    <i class="fas fa-user-check mr-1"></i> Aktifkan Kembali Kashift ke ON (Hadir)
                                @endif
                            </button>
                        </div>
                    </div>
                </div>

                <!-- KARTU KEPALA RUANGAN -->
                <div class="col-md-6 mb-4">
                    <div class="card card-outline {{ $delegasiKaru->is_active ? 'card-success' : 'card-danger' }} h-100 shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h3 class="card-title font-weight-bold text-dark mb-0">
                                    <i class="fas fa-user-cog text-secondary mr-2"></i>Status: Kepala Ruangan (Karu)
                                </h3>
                                @if($delegasiKaru->is_active)
                                    <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.9rem;">
                                        <i class="fas fa-check-circle mr-1"></i> ON (Hadir / Normal)
                                    </span>
                                @else
                                    <span class="badge badge-danger px-3 py-2 font-weight-bold" style="font-size: 0.9rem;">
                                        <i class="fas fa-power-off mr-1"></i> OFF (Absen / Izin)
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            @if(!$delegasiKaru->is_active)
                                <div class="alert alert-warning py-2 mb-3">
                                    <i class="fas fa-exchange-alt mr-1"></i>
                                    <strong>Pendelegasian Aktif:</strong> Hak request topping & scan barcode dialihkan ke <strong>Kepala Shift</strong>.
                                </div>
                            @else
                                <div class="alert alert-light border py-2 mb-3 text-muted">
                                    <i class="fas fa-tools text-success mr-1"></i>
                                    <strong>Normal:</strong> Request topping diajukan mandiri oleh Kepala Ruangan.
                                </div>
                            @endif

                            <table class="table table-sm table-borderless mb-2">
                                <tr>
                                    <td class="text-muted" style="width: 40%;"><i class="fas fa-calendar-alt mr-1"></i> Shift Terakhir</td>
                                    <td class="font-weight-bold">: {{ $delegasiKaru->current_shift ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-comment-alt mr-1"></i> Keterangan</td>
                                    <td class="font-weight-bold">: {{ $delegasiKaru->keterangan ?: 'Tidak ada catatan' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-user-edit mr-1"></i> Terakhir Diubah</td>
                                    <td>: {{ $delegasiKaru->updater->nama ?? 'Sistem' }} ({{ $delegasiKaru->updated_at ? $delegasiKaru->updated_at->format('d/m/Y H:i:s') : '-' }})</td>
                                </tr>
                            </table>
                        </div>
                        <div class="card-footer bg-white border-top">
                            <button type="button" class="btn {{ $delegasiKaru->is_active ? 'btn-outline-danger' : 'btn-success' }} btn-block font-weight-bold shadow-sm"
                                data-toggle="modal" data-target="#modalToggleKaru">
                                @if($delegasiKaru->is_active)
                                    <i class="fas fa-user-slash mr-1"></i> Set Karu ke OFF (Izin / Absen)
                                @else
                                    <i class="fas fa-user-check mr-1"></i> Aktifkan Kembali Karu ke ON (Hadir)
                                @endif
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABEL RIWAYAT ABSEN & DELEGASI -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-history text-primary mr-2"></i>Riwayat Absen & Perubahan Status
                        </h3>
                        <span class="text-muted font-italic" style="font-size: 0.85rem;">
                            Mencatat data tanggal, shift, role, FM/Admin pengubah, dan waktu input secara permanen.
                        </span>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filter Toolbar -->
                    <form method="GET" action="{{ route('absen.index') }}" class="mb-3">
                        <div class="row align-items-end">
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="text-muted mb-1" style="font-size: 0.85rem;">Filter Tanggal:</label>
                                <input type="date" name="tanggal" value="{{ request('tanggal') }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="text-muted mb-1" style="font-size: 0.85rem;">Filter Shift:</label>
                                <select name="shift" class="form-control form-control-sm">
                                    <option value="">-- Semua Shift --</option>
                                    <option value="Shift 1" {{ request('shift') === 'Shift 1' ? 'selected' : '' }}>Shift 1</option>
                                    <option value="Shift 2" {{ request('shift') === 'Shift 2' ? 'selected' : '' }}>Shift 2</option>
                                    <option value="Shift 3" {{ request('shift') === 'Shift 3' ? 'selected' : '' }}>Shift 3</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="text-muted mb-1" style="font-size: 0.85rem;">Filter Role:</label>
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
                                <a href="{{ route('absen.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-redo mr-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Table List -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;">No</th>
                                    <th>Tanggal</th>
                                    <th>Shift</th>
                                    <th>Role Target</th>
                                    <th>Status Baru</th>
                                    <th>Keterangan / Alasan</th>
                                    <th>Diubah Oleh (FM / Admin)</th>
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
                                                <span class="text-muted">-</span>
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

<!-- MODAL KONFIRMASI STATUS KEPALA SHIFT -->
<div class="modal fade" id="modalToggleKashift" tabindex="-1" role="dialog" aria-labelledby="modalToggleKashiftLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('absen.toggle') }}" method="POST">
                @csrf
                <input type="hidden" name="role_target" value="kepala_shift">
                <div class="modal-header {{ $delegasiKashift->is_active ? 'bg-danger text-white' : 'bg-success text-white' }}">
                    <h5 class="modal-title font-weight-bold" id="modalToggleKashiftLabel">
                        <i class="fas fa-user-shield mr-2"></i>
                        {{ $delegasiKashift->is_active ? 'Nonaktifkan Kepala Shift (Set ke OFF)' : 'Aktifkan Kembali Kepala Shift (Set ke ON)' }}
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        {{ $delegasiKashift->is_active 
                            ? 'Ketika Kepala Shift di-set ke OFF, seluruh hak akses approval topping, force finish, dan cancel barcode akan dialihkan sementara ke Kepala Ruangan (Karu).'
                            : 'Ketika Kepala Shift di-set ke ON, wewenang approval topping akan kembali normal dan ditutup kembali untuk Kepala Ruangan.' }}
                    </p>

                    <div class="form-group">
                        <label class="font-weight-bold">Status Baru:</label>
                        <select name="status" class="form-control" required>
                            <option value="OFF" {{ $delegasiKashift->is_active ? 'selected' : '' }}>OFF (Absen / Tidak Masuk / Izin)</option>
                            <option value="ON" {{ !$delegasiKashift->is_active ? 'selected' : '' }}>ON (Hadir / Masuk Normal)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Pilih Shift:</label>
                        <select name="shift" class="form-control" required>
                            <option value="Shift 1" {{ $currentShift === 'Shift 1' ? 'selected' : '' }}>Shift 1 (07:00 - 15:00)</option>
                            <option value="Shift 2" {{ $currentShift === 'Shift 2' ? 'selected' : '' }}>Shift 2 (15:00 - 23:00)</option>
                            <option value="Shift 3" {{ $currentShift === 'Shift 3' ? 'selected' : '' }}>Shift 3 (23:00 - 07:00)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Keterangan / Alasan:</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Izin Sakit, Cuti Tahunan, Bertugas Kembali..." required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn {{ $delegasiKashift->is_active ? 'btn-danger' : 'btn-success' }} font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI STATUS KEPALA RUANGAN -->
<div class="modal fade" id="modalToggleKaru" tabindex="-1" role="dialog" aria-labelledby="modalToggleKaruLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('absen.toggle') }}" method="POST">
                @csrf
                <input type="hidden" name="role_target" value="kepala_ruangan">
                <div class="modal-header {{ $delegasiKaru->is_active ? 'bg-danger text-white' : 'bg-success text-white' }}">
                    <h5 class="modal-title font-weight-bold" id="modalToggleKaruLabel">
                        <i class="fas fa-user-cog mr-2"></i>
                        {{ $delegasiKaru->is_active ? 'Nonaktifkan Kepala Ruangan (Set ke OFF)' : 'Aktifkan Kembali Kepala Ruangan (Set ke ON)' }}
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        {{ $delegasiKaru->is_active 
                            ? 'Ketika Kepala Ruangan di-set ke OFF, hak akses pengajuan Request Topping dan scan barcode topping akan dibuka untuk Kepala Shift.'
                            : 'Ketika Kepala Ruangan di-set ke ON, wewenang pengajuan topping kembali menjadi hak khusus Kepala Ruangan.' }}
                    </p>

                    <div class="form-group">
                        <label class="font-weight-bold">Status Baru:</label>
                        <select name="status" class="form-control" required>
                            <option value="OFF" {{ $delegasiKaru->is_active ? 'selected' : '' }}>OFF (Absen / Tidak Masuk / Izin)</option>
                            <option value="ON" {{ !$delegasiKaru->is_active ? 'selected' : '' }}>ON (Hadir / Masuk Normal)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Pilih Shift:</label>
                        <select name="shift" class="form-control" required>
                            <option value="Shift 1" {{ $currentShift === 'Shift 1' ? 'selected' : '' }}>Shift 1 (07:00 - 15:00)</option>
                            <option value="Shift 2" {{ $currentShift === 'Shift 2' ? 'selected' : '' }}>Shift 2 (15:00 - 23:00)</option>
                            <option value="Shift 3" {{ $currentShift === 'Shift 3' ? 'selected' : '' }}>Shift 3 (23:00 - 07:00)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Keterangan / Alasan:</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Izin Cuti, Sakit, Masuk Kembali..." required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn {{ $delegasiKaru->is_active ? 'btn-danger' : 'btn-success' }} font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Simpan Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
