@extends('layout.main')
@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Approval FM</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active"><a>Approval FM</a></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        @php
        // Helper function untuk konversi detik ke format waktu
        if (!function_exists('detikKeWaktu')) {
            function detikKeWaktu($detik) {
                if ($detik === null || $detik === '') return '-';
                $detik = (int) $detik;
                $jam = floor($detik / 3600);
                $menit = floor(($detik % 3600) / 60);
                $detik = $detik % 60;
                return sprintf('%02d:%02d:%02d', $jam, $menit, $detik);
            }
        }
        // Permission check untuk owner
        $userRole = Auth::user()->role ?? null;
        $canManageApproval = strtolower($userRole) !== 'owner';
        @endphp
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Daftar Approval FM</h3>
                        </div>
                        <div class="card-body">
                            <table id="approval_fm" class="table table-head-fixed text-nowrap table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No OP</th>
                                        <th>Status</th>
                                        <th>Action Type</th>
                                        <th>History Data</th>
                                        <th>Dilakukan Oleh</th>
                                        <th>Approve Oleh</th>
                                        <th>Tanggal Request</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($approvals as $approval)
                                    <tr>
                                        <td>
                                            @if($approval->action === 'create_aux_reprocess' && $approval->auxl)
                                            <strong>{{ $approval->auxl->barcode ?? 'AUXL' }}</strong>
                                            @else
                                            @php
                                            // Ambil no_op dari DetailProses jika ada, atau dari history_data, atau fallback ke MAINTENANCE
                                            $noOpDisplay = 'MAINTENANCE';
                                            if ($approval->proses) {
                                                $firstDetail = $approval->proses->details->first();
                                                if ($firstDetail) {
                                                    $noOpDisplay = $firstDetail->no_op ?? 'MAINTENANCE';
                                                    // Jika ada multiple OP, tambahkan indikator
                                                    if ($approval->proses->details->count() > 1) {
                                                        $noOpDisplay .= ' (+' . ($approval->proses->details->count() - 1) . ' OP)';
                                                    }
                                                } elseif ($approval->history_data && isset($approval->history_data['detail_proses_snapshot']) && !empty($approval->history_data['detail_proses_snapshot'])) {
                                                    $firstDetailSnapshot = $approval->history_data['detail_proses_snapshot'][0];
                                                    $noOpDisplay = $firstDetailSnapshot['no_op'] ?? 'MAINTENANCE';
                                                    if (count($approval->history_data['detail_proses_snapshot']) > 1) {
                                                        $noOpDisplay .= ' (+' . (count($approval->history_data['detail_proses_snapshot']) - 1) . ' OP)';
                                                    }
                                                }
                                            }
                                            @endphp
                                            <strong>{{ $noOpDisplay }}</strong>
                                            @endif
                                        </td>
                                        <td>
                                            @if($approval->status === 'pending')
                                            <span class="badge bg-warning">Pending</span>
                                            @elseif($approval->status === 'approved')
                                            <span class="badge bg-success">Approved</span>
                                            @else
                                            <span class="badge bg-danger">Rejected</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                            $actionLabels = [
                                                'move_machine' => 'Pindah Mesin',
                                                'edit_cycle_time' => 'Edit Cycle Time',
                                                'delete_proses' => 'Hapus Proses',
                                                'create_reprocess' => 'Buat Reproses',
                                                'create_aux_reprocess' => 'Buat Reproses Auxl',
                                                'swap_position' => 'Tukar Posisi'
                                            ];
                                            $actionLabel = $actionLabels[$approval->action] ?? ucfirst(str_replace('_', ' ', $approval->action));
                                            @endphp
                                            <span class="badge bg-secondary">{{ $actionLabel }}</span>
                                        </td>
                                        <td>
                                            @if($approval->history_data)
                                            <button type="button" class="btn btn-sm btn-info" data-toggle="modal" 
                                                data-target="#modalHistory{{ $approval->id }}">
                                                <i class="fas fa-eye"></i> Lihat History
                                            </button>
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $approval->requester->nama ?? '-' }}<br>
                                            <small class="text-muted">{{ $approval->requester->username ?? '' }}</small>
                                        </td>
                                        <td>
                                            @if($approval->approver)
                                            {{ $approval->approver->nama }}<br>
                                            <small class="text-muted">{{ $approval->approver->username }}</small>
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $approval->created_at->format('d-m-Y H:i:s') }}
                                        </td>
                                        <td>
                                            @if($approval->status === 'pending')
                                                @if($canManageApproval)
                                                <button type="button" class="btn btn-sm btn-success mb-1" 
                                                    data-toggle="modal" data-target="#modalApprove{{ $approval->id }}">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger mb-1" 
                                                    data-toggle="modal" data-target="#modalReject{{ $approval->id }}">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                                @else
                                                <span class="badge bg-secondary">Hanya View</span>
                                                @endif
                                            @else
                                            <span class="text-muted">Sudah diproses</span>
                                            @if($approval->note)
                                            <br>
                                            <button type="button" class="btn btn-sm btn-link text-primary p-0 mt-1" 
                                                data-toggle="modal" data-target="#modalNote{{ $approval->id }}"
                                                style="font-size: 0.875rem;">
                                                <i class="fas fa-comment"></i> Ada catatan
                                            </button>
                                            @endif
                                            @endif
                                        </td>
                                    </tr>

                                    <!-- Modal History Data -->
                                    @if($approval->history_data)
                                    @php
                                    $history = $approval->history_data;
                                    @endphp
                                    <div class="modal fade" id="modalHistory{{ $approval->id }}" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header bg-info text-white">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-history mr-2"></i>
                                                        History Data - 
                                                        @if($approval->action === 'create_aux_reprocess' && $approval->auxl)
                                                        {{ $approval->auxl->barcode ?? 'AUXL' }}
                                                        @else
                                                        @php
                                                        // Ambil no_op dari DetailProses jika ada, atau dari history_data, atau fallback ke MAINTENANCE
                                                        $noOpModal = 'MAINTENANCE';
                                                        if ($approval->proses) {
                                                            $firstDetail = $approval->proses->details->first();
                                                            if ($firstDetail) {
                                                                $noOpModal = $firstDetail->no_op ?? 'MAINTENANCE';
                                                            } elseif ($approval->history_data && isset($approval->history_data['detail_proses_snapshot']) && !empty($approval->history_data['detail_proses_snapshot'])) {
                                                                $firstDetailSnapshot = $approval->history_data['detail_proses_snapshot'][0];
                                                                $noOpModal = $firstDetailSnapshot['no_op'] ?? 'MAINTENANCE';
                                                            }
                                                        }
                                                        @endphp
                                                        {{ $noOpModal }}
                                                        @endif
                                                    </h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    @if($approval->action === 'edit_cycle_time')
                                                    <!-- Tampilan khusus untuk Edit Cycle Time -->
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <h6 class="mb-3"><i class="fas fa-clock mr-2"></i>Perubahan Cycle Time</h6>
                                                            
                                                            <div class="card mb-3" style="border-left: 4px solid #dc3545;">
                                                                <div class="card-body">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <div>
                                                                            <small class="text-muted d-block mb-1">Cycle Time Sebelumnya</small>
                                                                            <h4 class="mb-0 text-danger">
                                                                                <strong>{{ detikKeWaktu($history['old_cycle_time'] ?? null) }}</strong>
                                                                            </h4>
                                                                            @if(isset($history['old_cycle_time']))
                                                                            <small class="text-muted">({{ number_format($history['old_cycle_time']) }} detik)</small>
                                                                            @endif
                                                                        </div>
                                                                        <div class="text-center">
                                                                            <i class="fas fa-arrow-right fa-2x text-muted"></i>
                                                                        </div>
                                                                        <div>
                                                                            <small class="text-muted d-block mb-1">Cycle Time Baru</small>
                                                                            <h4 class="mb-0 text-success">
                                                                                <strong>{{ detikKeWaktu($history['new_cycle_time'] ?? null) }}</strong>
                                                                            </h4>
                                                                            @if(isset($history['new_cycle_time']))
                                                                            <small class="text-muted">({{ number_format($history['new_cycle_time']) }} detik)</small>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            @if(isset($history['input_format']))
                                                            <div class="alert alert-info mb-0">
                                                                <i class="fas fa-info-circle mr-2"></i>
                                                                <strong>Format Input:</strong> {{ $history['input_format'] }}
                                                            </div>
                                                            @endif

                                                            @if(isset($history['old_cycle_time']) && isset($history['new_cycle_time']))
                                                            @php
                                                            $selisih = $history['new_cycle_time'] - $history['old_cycle_time'];
                                                            $selisihAbs = abs($selisih);
                                                            @endphp
                                                            <div class="mt-3 p-3 bg-light rounded">
                                                                <div class="row text-center">
                                                                    <div class="col-6">
                                                                        <small class="text-muted d-block">Selisih Waktu</small>
                                                                        <strong class="{{ $selisih >= 0 ? 'text-danger' : 'text-success' }}">
                                                                            {{ $selisih >= 0 ? '+' : '' }}{{ detikKeWaktu($selisihAbs) }}
                                                                        </strong>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <small class="text-muted d-block">Persentase Perubahan</small>
                                                                        <strong class="{{ $selisih >= 0 ? 'text-danger' : 'text-success' }}">
                                                                            {{ $history['old_cycle_time'] > 0 ? number_format(($selisih / $history['old_cycle_time']) * 100, 2) : '0' }}%
                                                                        </strong>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @elseif($approval->action === 'delete_proses')
                                                    <!-- Tampilan khusus untuk Delete Proses -->
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="alert alert-danger">
                                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                                <strong>Peringatan:</strong> Proses berikut akan dihapus secara permanen jika disetujui.
                                                            </div>
                                                            @php
                                                            $prosesSnapshot = $history['proses_snapshot'] ?? [];
                                                            $detailProsesSnapshots = $history['detail_proses_snapshot'] ?? [];
                                                            $oldMesin = isset($prosesSnapshot['mesin_id']) ? \App\Models\Mesin::find($prosesSnapshot['mesin_id']) : null;
                                                            @endphp
                                                            <div class="card border-danger mb-3">
                                                                <div class="card-header bg-danger text-white">
                                                                    <h6 class="mb-0"><i class="fas fa-trash mr-2"></i>Detail Proses yang Akan Dihapus</h6>
                                                                </div>
                                                                <div class="card-body">
                                                                    <!-- Informasi Proses Utama -->
                                                                    <div class="row mb-3">
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Jenis</strong>
                                                                            <span class="badge badge-secondary">{{ $prosesSnapshot['jenis'] ?? '-' }}</span>
                                                                        </div>
                                                                        @if(!empty($prosesSnapshot['cycle_time']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Cycle Time</strong>
                                                                            <span class="badge badge-info">{{ detikKeWaktu($prosesSnapshot['cycle_time']) }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['mesin_id']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Mesin</strong>
                                                                            @if($oldMesin)
                                                                            <span class="badge badge-info">{{ $oldMesin->jenis_mesin }}</span>
                                                                            @else
                                                                            <span class="text-muted">ID: {{ $prosesSnapshot['mesin_id'] }}</span>
                                                                            @endif
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                    
                                                                    <!-- Detail Proses (OP) -->
                                                                    @if(!empty($detailProsesSnapshots))
                                                                    <hr class="my-3">
                                                                    <h6 class="mb-3">
                                                                        <i class="fas fa-list mr-2"></i>
                                                                        <strong>Detail OP</strong>
                                                                        <span class="badge badge-secondary ml-2">{{ count($detailProsesSnapshots) }} OP</span>
                                                                    </h6>
                                                                    @foreach($detailProsesSnapshots as $index => $detailSnapshot)
                                                                    <div class="card border-left border-primary mb-3">
                                                                        <div class="card-body">
                                                                            <h6 class="mb-3">
                                                                                <span class="badge badge-primary mr-2">OP #{{ $index + 1 }}</span>
                                                                                <strong>{{ $detailSnapshot['no_op'] ?? '-' }}</strong>
                                                                            </h6>
                                                                            <div class="row">
                                                                                @if(!empty($detailSnapshot['no_partai']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">No Partai</strong>
                                                                                    <span>{{ $detailSnapshot['no_partai'] }}</span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['item_op']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Item OP</strong>
                                                                                    <span>{{ $detailSnapshot['item_op'] }}</span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['konstruksi']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Konstruksi</strong>
                                                                                    <span>{{ $detailSnapshot['konstruksi'] }}</span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['kode_material']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Kode Material</strong>
                                                                                    <span class="small">{{ $detailSnapshot['kode_material'] }}</span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['warna']) || !empty($detailSnapshot['kode_warna']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Warna</strong>
                                                                                    <span>
                                                                                        {{ $detailSnapshot['warna'] ?? '-' }}
                                                                                        @if(!empty($detailSnapshot['kode_warna']))
                                                                                        <small class="text-muted">({{ $detailSnapshot['kode_warna'] }})</small>
                                                                                        @endif
                                                                                    </span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['qty']) || !empty($detailSnapshot['roll']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Qty / Roll</strong>
                                                                                    <span>
                                                                                        {{ !empty($detailSnapshot['qty']) ? number_format($detailSnapshot['qty'], 2) : '-' }}
                                                                                        @if(!empty($detailSnapshot['roll']))
                                                                                        / {{ $detailSnapshot['roll'] }} roll
                                                                                        @endif
                                                                                    </span>
                                                                                </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    @endforeach
                                                                    @else
                                                                    <!-- Fallback ke proses_snapshot lama (untuk backward compatibility) -->
                                                                    <hr class="my-3">
                                                                    <div class="row">
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">No OP</strong>
                                                                            <h5 class="mb-0">{{ $prosesSnapshot['no_op'] ?? '-' }}</h5>
                                                                        </div>
                                                                        @if(!empty($prosesSnapshot['no_partai']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">No Partai</strong>
                                                                            <span>{{ $prosesSnapshot['no_partai'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['item_op']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Item OP</strong>
                                                                            <span>{{ $prosesSnapshot['item_op'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['konstruksi']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Konstruksi</strong>
                                                                            <span>{{ $prosesSnapshot['konstruksi'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['kode_material']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Kode Material</strong>
                                                                            <span class="small">{{ $prosesSnapshot['kode_material'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['warna']) || !empty($prosesSnapshot['kode_warna']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Warna</strong>
                                                                            <span>
                                                                                {{ $prosesSnapshot['warna'] ?? '-' }}
                                                                                @if(!empty($prosesSnapshot['kode_warna']))
                                                                                <small class="text-muted">({{ $prosesSnapshot['kode_warna'] }})</small>
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['qty']) || !empty($prosesSnapshot['roll']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Qty / Roll</strong>
                                                                            <span>
                                                                                {{ !empty($prosesSnapshot['qty']) ? number_format($prosesSnapshot['qty'], 2) : '-' }}
                                                                                @if(!empty($prosesSnapshot['roll']))
                                                                                / {{ $prosesSnapshot['roll'] }} roll
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @elseif($approval->action === 'move_machine')
                                                    <!-- Tampilan khusus untuk Move Machine -->
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <h6 class="mb-3"><i class="fas fa-exchange-alt mr-2"></i>Pindah Mesin</h6>
                                                            @php
                                                            $oldMesinId = $history['old_mesin_id'] ?? $approval->proses->mesin_id ?? null;
                                                            $newMesinId = $history['new_mesin_id'] ?? null;
                                                            $oldMesin = $oldMesinId ? \App\Models\Mesin::find($oldMesinId) : null;
                                                            $newMesin = $newMesinId ? \App\Models\Mesin::find($newMesinId) : null;
                                                            @endphp
                                                            <div class="card mb-3" style="border-left: 4px solid #ffc107;">
                                                                <div class="card-body">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <div class="text-center flex-fill">
                                                                            <small class="text-muted d-block mb-2">Mesin Lama</small>
                                                                            @if($oldMesin)
                                                                            <h5 class="mb-0 text-danger">
                                                                                <strong>{{ $oldMesin->jenis_mesin }}</strong>
                                                                            </h5>
                                                                            <small class="text-muted">ID: {{ $oldMesin->id }}</small>
                                                                            @else
                                                                            <h5 class="mb-0 text-muted">
                                                                                <strong>-</strong>
                                                                            </h5>
                                                                            @endif
                                                                        </div>
                                                                        <div class="text-center px-4">
                                                                            <i class="fas fa-arrow-right fa-3x text-primary"></i>
                                                                        </div>
                                                                        <div class="text-center flex-fill">
                                                                            <small class="text-muted d-block mb-2">Mesin Baru</small>
                                                                            @if($newMesin)
                                                                            <h5 class="mb-0 text-success">
                                                                                <strong>{{ $newMesin->jenis_mesin }}</strong>
                                                                            </h5>
                                                                            <small class="text-muted">ID: {{ $newMesin->id }}</small>
                                                                            @else
                                                                            <h5 class="mb-0 text-danger">
                                                                                <strong>Data Mesin Baru Tidak Ditemukan</strong>
                                                                            </h5>
                                                                            @if($newMesinId)
                                                                            <small class="text-muted">ID: {{ $newMesinId }}</small>
                                                                            @endif
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="alert alert-info mb-0">
                                                                <i class="fas fa-info-circle mr-2"></i>
                                                                <strong>Informasi:</strong> Proses akan dipindahkan dari mesin lama ke mesin baru jika disetujui.
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @elseif($approval->action === 'create_reprocess')
                                                    <!-- Tampilan khusus untuk Create Reproses (Tahap 1: FM Approval) -->
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                                <strong>Informasi:</strong> Proses reproses berikut akan menunggu persetujuan VP setelah disetujui oleh FM.
                                                            </div>
                                                            @php
                                                            $prosesSnapshot = $history['proses_snapshot'] ?? [];
                                                            $detailProsesSnapshots = $history['detail_proses_snapshot'] ?? [];
                                                            $oldMesin = isset($prosesSnapshot['mesin_id']) ? \App\Models\Mesin::find($prosesSnapshot['mesin_id']) : null;
                                                            @endphp
                                                            <div class="card border-warning mb-3">
                                                                <div class="card-header bg-warning text-dark">
                                                                    <h6 class="mb-0"><i class="fas fa-redo mr-2"></i>Detail Proses Reproses (Tahap 1: Approval FM)</h6>
                                                                </div>
                                                                <div class="card-body">
                                                                    <!-- Informasi Proses Utama -->
                                                                    <div class="row mb-3">
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Jenis</strong>
                                                                            <span class="badge badge-warning">{{ $prosesSnapshot['jenis'] ?? 'Reproses' }}</span>
                                                                        </div>
                                                                        @if(!empty($prosesSnapshot['cycle_time']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Cycle Time</strong>
                                                                            <span class="badge badge-info">{{ detikKeWaktu($prosesSnapshot['cycle_time']) }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['mesin_id']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Mesin</strong>
                                                                            @if($oldMesin)
                                                                            <span class="badge badge-info">{{ $oldMesin->jenis_mesin }}</span>
                                                                            @else
                                                                            <span class="text-muted">ID: {{ $prosesSnapshot['mesin_id'] }}</span>
                                                                            @endif
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                    
                                                                    <!-- Detail Proses (OP) -->
                                                                    @if(!empty($detailProsesSnapshots))
                                                                    <hr class="my-3">
                                                                    <h6 class="mb-3">
                                                                        <i class="fas fa-list mr-2"></i>
                                                                        <strong>Detail OP</strong>
                                                                        <span class="badge badge-secondary ml-2">{{ count($detailProsesSnapshots) }} OP</span>
                                                                    </h6>
                                                                    @foreach($detailProsesSnapshots as $index => $detailSnapshot)
                                                                    <div class="card border-left border-warning mb-3">
                                                                        <div class="card-body">
                                                                            <h6 class="mb-3">
                                                                                <span class="badge badge-warning mr-2">OP #{{ $index + 1 }}</span>
                                                                                <strong>{{ $detailSnapshot['no_op'] ?? '-' }}</strong>
                                                                            </h6>
                                                                            <div class="row">
                                                                                @if(!empty($detailSnapshot['no_partai']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">No Partai</strong>
                                                                                    <span>{{ $detailSnapshot['no_partai'] }}</span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['item_op']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Item OP</strong>
                                                                                    <span>{{ $detailSnapshot['item_op'] }}</span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['konstruksi']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Konstruksi</strong>
                                                                                    <span>{{ $detailSnapshot['konstruksi'] }}</span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['kode_material']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Kode Material</strong>
                                                                                    <span class="small">{{ $detailSnapshot['kode_material'] }}</span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['warna']) || !empty($detailSnapshot['kode_warna']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Warna</strong>
                                                                                    <span>
                                                                                        {{ $detailSnapshot['warna'] ?? '-' }}
                                                                                        @if(!empty($detailSnapshot['kode_warna']))
                                                                                        <small class="text-muted">({{ $detailSnapshot['kode_warna'] }})</small>
                                                                                        @endif
                                                                                    </span>
                                                                                </div>
                                                                                @endif
                                                                                @if(!empty($detailSnapshot['qty']) || !empty($detailSnapshot['roll']))
                                                                                <div class="col-md-6 mb-2">
                                                                                    <strong class="text-muted d-block">Qty / Roll</strong>
                                                                                    <span>
                                                                                        {{ !empty($detailSnapshot['qty']) ? number_format($detailSnapshot['qty'], 2) : '-' }}
                                                                                        @if(!empty($detailSnapshot['roll']))
                                                                                        / {{ $detailSnapshot['roll'] }} roll
                                                                                        @endif
                                                                                    </span>
                                                                                </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    @endforeach
                                                                    @else
                                                                    <!-- Fallback ke proses_snapshot lama (untuk backward compatibility) -->
                                                                    <hr class="my-3">
                                                                    <div class="row">
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">No OP</strong>
                                                                            <h5 class="mb-0">{{ $prosesSnapshot['no_op'] ?? '-' }}</h5>
                                                                        </div>
                                                                        @if(!empty($prosesSnapshot['no_partai']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">No Partai</strong>
                                                                            <span>{{ $prosesSnapshot['no_partai'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['item_op']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Item OP</strong>
                                                                            <span>{{ $prosesSnapshot['item_op'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['konstruksi']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Konstruksi</strong>
                                                                            <span>{{ $prosesSnapshot['konstruksi'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['kode_material']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Kode Material</strong>
                                                                            <span class="small">{{ $prosesSnapshot['kode_material'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['warna']) || !empty($prosesSnapshot['kode_warna']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Warna</strong>
                                                                            <span>
                                                                                {{ $prosesSnapshot['warna'] ?? '-' }}
                                                                                @if(!empty($prosesSnapshot['kode_warna']))
                                                                                <small class="text-muted">({{ $prosesSnapshot['kode_warna'] }})</small>
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($prosesSnapshot['qty']) || !empty($prosesSnapshot['roll']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Qty / Roll</strong>
                                                                            <span>
                                                                                {{ !empty($prosesSnapshot['qty']) ? number_format($prosesSnapshot['qty'], 2) : '-' }}
                                                                                @if(!empty($prosesSnapshot['roll']))
                                                                                / {{ $prosesSnapshot['roll'] }} roll
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="alert alert-info mb-0">
                                                                <i class="fas fa-info-circle mr-2"></i>
                                                                <strong>Catatan:</strong> Setelah disetujui oleh FM, proses reproses akan menunggu persetujuan VP (tahap 2) sebelum dapat digunakan.
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @elseif($approval->action === 'create_aux_reprocess')
                                                    <!-- Tampilan khusus untuk Create Aux Reproses (Tahap 1: FM Approval) -->
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                                                <strong>Informasi:</strong> Proses reproses auxl berikut akan menunggu persetujuan VP setelah disetujui oleh FM.
                                                            </div>
                                                            @php
                                                            $auxlSnapshot = $history['auxl_snapshot'] ?? [];
                                                            $oldMesin = isset($auxlSnapshot['mesin_id']) ? \App\Models\Mesin::find($auxlSnapshot['mesin_id']) : null;
                                                            // Details bisa ada di history['details'] (terpisah) atau di auxlSnapshot['details']
                                                            $auxlDetails = [];
                                                            if (isset($history['details']) && is_array($history['details'])) {
                                                                $auxlDetails = $history['details'];
                                                            } elseif (isset($auxlSnapshot['details']) && is_array($auxlSnapshot['details'])) {
                                                                $auxlDetails = $auxlSnapshot['details'];
                                                            } elseif ($approval->auxl && $approval->auxl->details) {
                                                                // Fallback: ambil dari auxl yang masih ada
                                                                $auxlDetails = $approval->auxl->details->toArray();
                                                            }
                                                            @endphp
                                                            <div class="card border-warning mb-3">
                                                                <div class="card-header bg-warning text-dark">
                                                                    <h6 class="mb-0"><i class="fas fa-redo mr-2"></i>Detail Proses Reproses Auxl (Tahap 1: Approval FM)</h6>
                                                                </div>
                                                                <div class="card-body">
                                                                    <div class="row">
                                                                        @if(!empty($auxlSnapshot['barcode']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Barcode</strong>
                                                                            <h5 class="mb-0"><span class="badge badge-info">{{ $auxlSnapshot['barcode'] }}</span></h5>
                                                                        </div>
                                                                        @endif
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Jenis</strong>
                                                                            <span class="badge badge-warning">{{ ucfirst($auxlSnapshot['jenis'] ?? 'Reproses') }}</span>
                                                                        </div>
                                                                        @if(!empty($auxlSnapshot['code']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Code</strong>
                                                                            <span>{{ $auxlSnapshot['code'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($auxlSnapshot['no_op']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">No OP</strong>
                                                                            <span>{{ $auxlSnapshot['no_op'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($auxlSnapshot['no_partai']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">No Partai</strong>
                                                                            <span>{{ $auxlSnapshot['no_partai'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($auxlSnapshot['konstruksi']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Konstruksi</strong>
                                                                            <span>{{ $auxlSnapshot['konstruksi'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($auxlSnapshot['customer']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Customer</strong>
                                                                            <span>{{ $auxlSnapshot['customer'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($auxlSnapshot['marketing']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Marketing</strong>
                                                                            <span>{{ $auxlSnapshot['marketing'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($auxlSnapshot['color']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Warna</strong>
                                                                            <span>{{ $auxlSnapshot['color'] }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($auxlSnapshot['date']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Tanggal</strong>
                                                                            <span>{{ is_string($auxlSnapshot['date']) ? $auxlSnapshot['date'] : (isset($auxlSnapshot['date']) ? date('d-m-Y', strtotime($auxlSnapshot['date'])) : '-') }}</span>
                                                                        </div>
                                                                        @endif
                                                                        @if(!empty($auxlSnapshot['mesin_id']))
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Mesin</strong>
                                                                            @if($oldMesin)
                                                                            <span class="badge badge-info">{{ $oldMesin->jenis_mesin }}</span>
                                                                            @else
                                                                            <span class="text-muted">ID: {{ $auxlSnapshot['mesin_id'] }}</span>
                                                                            @endif
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                    
                                                                    <!-- Detail Auxiliary (List Style) -->
                                                                    @if(!empty($auxlDetails) && count($auxlDetails) > 0)
                                                                    <hr class="my-3">
                                                                    <h6 class="mb-3">
                                                                        <i class="fas fa-flask mr-2"></i>
                                                                        <strong>Detail Auxiliary</strong>
                                                                        <span class="badge badge-secondary ml-2">{{ count($auxlDetails) }} item</span>
                                                                    </h6>
                                                                    <div class="list-group">
                                                                        @foreach($auxlDetails as $index => $detail)
                                                                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3">
                                                                            <div>
                                                                                <span class="badge badge-secondary mr-2">{{ $index + 1 }}</span>
                                                                                <strong>{{ $detail['auxiliary'] ?? '-' }}</strong>
                                                                            </div>
                                                                            <span class="badge badge-info badge-pill">{{ isset($detail['konsentrasi']) ? number_format($detail['konsentrasi'], 2) : '-' }} KG</span>
                                                                        </div>
                                                                        @endforeach
                                                                    </div>
                                                                    @else
                                                                    <hr class="my-3">
                                                                    <div class="alert alert-secondary mb-0">
                                                                        <i class="fas fa-info-circle mr-2"></i>
                                                                        Tidak ada detail auxiliary yang tersedia.
                                                                    </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="alert alert-info mb-0">
                                                                <i class="fas fa-info-circle mr-2"></i>
                                                                <strong>Catatan:</strong> Setelah disetujui oleh FM, proses reproses auxl akan menunggu persetujuan VP (tahap 2) sebelum dapat digunakan.
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @elseif($approval->action === 'swap_position')
                                                    <!-- Tampilan khusus untuk Swap Position (Reorder) - Versi Ringkas) -->
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            @php
                                                            $proses1Id = $history['proses1_id'] ?? null;
                                                            $proses2Id = $history['proses2_id'] ?? null;
                                                            $oldOrder1 = $history['old_order1'] ?? null;
                                                            $oldOrder2 = $history['old_order2'] ?? null;
                                                            $affectedProsesIds = $history['affected_proses_ids'] ?? [];
                                                            
                                                            $proses1 = $proses1Id ? \App\Models\Proses::find($proses1Id) : null;
                                                            $proses2 = $proses2Id ? \App\Models\Proses::find($proses2Id) : null;
                                                            
                                                            // Ambil semua proses yang terpengaruh untuk ditampilkan
                                                            $affectedProses = collect();
                                                            if (is_array($affectedProsesIds) && count($affectedProsesIds) > 0) {
                                                                $affectedProses = \App\Models\Proses::whereIn('id', $affectedProsesIds)
                                                                    ->orderBy('order')
                                                                    ->orderBy('id')
                                                                    ->get();
                                                            }
                                                            
                                                            $newOrder1 = $oldOrder2;
                                                            $newOrder2 = $oldOrder1;
                                                            $affectedCount = is_array($affectedProsesIds) ? count($affectedProsesIds) : 0;
                                                            @endphp
                                                            
                                                            <!-- Ringkasan Perubahan -->
                                                            <div class="card mb-3" style="border-left: 4px solid #ffc107;">
                                                                <div class="card-body">
                                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                                        <div>
                                                                            <h6 class="mb-1"><i class="fas fa-exchange-alt mr-2"></i>Tukar Posisi Proses</h6>
                                                                            <small class="text-muted">{{ $affectedCount }} proses akan terpengaruh</small>
                                                                        </div>
                                                                        <div class="text-right">
                                                                            <span class="badge badge-warning">Order {{ $oldOrder1 ?? '-' }} → {{ $newOrder1 ?? '-' }}</span>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <!-- Proses yang Dipindahkan -->
                                                                    <div class="row mb-3">
                                                                        <div class="col-md-6">
                                                                            <div class="p-3 bg-light rounded border-left border-warning border-3">
                                                                                <small class="text-muted d-block mb-1">Proses yang Dipindahkan</small>
                                                                                <h6 class="mb-1">
                                                                                    @php
                                                                                    $proses1NoOp = 'MAINTENANCE';
                                                                                    $proses1Details = $proses1 ? $proses1->details : collect();
                                                                                    if ($proses1Details->isNotEmpty()) {
                                                                                        $proses1NoOp = $proses1Details->first()->no_op ?? 'MAINTENANCE';
                                                                                        if ($proses1Details->count() > 1) {
                                                                                            $proses1NoOp .= ' (+' . ($proses1Details->count() - 1) . ' OP)';
                                                                                        }
                                                                                    }
                                                                                    @endphp
                                                                                    <strong>{{ $proses1NoOp }}</strong>
                                                                                    @if($proses1Details->isNotEmpty() && $proses1Details->first()->warna)
                                                                                    <br><small class="text-muted">{{ $proses1Details->first()->warna }}</small>
                                                                                    @endif
                                                                                </h6>
                                                                                <div class="mt-2">
                                                                                    <span class="badge badge-danger">Posisi {{ $oldOrder1 ?? '-' }}</span>
                                                                                    <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                                                    <span class="badge badge-success">Posisi {{ $newOrder1 ?? '-' }}</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <!-- Proses Tujuan -->
                                                                        <div class="col-md-6">
                                                                            <div class="p-3 bg-light rounded border-left border-info border-3">
                                                                                <small class="text-muted d-block mb-1">Proses Tujuan</small>
                                                                                <h6 class="mb-1">
                                                                                    @php
                                                                                    $proses2NoOp = 'MAINTENANCE';
                                                                                    $proses2Details = $proses2 ? $proses2->details : collect();
                                                                                    if ($proses2Details->isNotEmpty()) {
                                                                                        $proses2NoOp = $proses2Details->first()->no_op ?? 'MAINTENANCE';
                                                                                        if ($proses2Details->count() > 1) {
                                                                                            $proses2NoOp .= ' (+' . ($proses2Details->count() - 1) . ' OP)';
                                                                                        }
                                                                                    }
                                                                                    @endphp
                                                                                    <strong>{{ $proses2NoOp }}</strong>
                                                                                    @if($proses2Details->isNotEmpty() && $proses2Details->first()->warna)
                                                                                    <br><small class="text-muted">{{ $proses2Details->first()->warna }}</small>
                                                                                    @endif
                                                                                </h6>
                                                                                <div class="mt-2">
                                                                                    <span class="badge badge-danger">Posisi {{ $oldOrder2 ?? '-' }}</span>
                                                                                    <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                                                    <span class="badge badge-success">Posisi {{ $newOrder2 ?? '-' }}</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <!-- Daftar Proses Terpengaruh (Ringkas) -->
                                                                    @if($affectedCount > 2)
                                                                    <div class="alert alert-warning mb-0 py-2">
                                                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                                                        <strong>Catatan:</strong> {{ $affectedCount - 2 }} proses lain akan ikut bergeser posisinya.
                                                                    </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Perubahan Urutan (Visual) -->
                                                            @if($oldOrder1 && $oldOrder2 && $oldOrder1 != $oldOrder2)
                                                            <div class="card mb-0" style="border-left: 4px solid #6c757d;">
                                                                <div class="card-header bg-secondary text-white py-2">
                                                                    <h6 class="mb-0"><i class="fas fa-sort mr-2"></i>Perubahan Urutan</h6>
                                                                </div>
                                                                <div class="card-body">
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <small class="text-muted d-block mb-2"><i class="fas fa-arrow-left mr-1"></i>Sebelum:</small>
                                                                            <div class="p-2 bg-light rounded">
                                                                                @foreach($affectedProses->sortBy('order') as $p)
                                                                                @php
                                                                                $pDetailsBefore = $p ? $p->details : collect();
                                                                                $pNoOpBefore = 'MAINTENANCE';
                                                                                if ($pDetailsBefore->isNotEmpty()) {
                                                                                    $pNoOpBefore = $pDetailsBefore->first()->no_op ?? 'MAINTENANCE';
                                                                                    if ($pDetailsBefore->count() > 1) {
                                                                                        $pNoOpBefore .= ' (+' . ($pDetailsBefore->count() - 1) . ')';
                                                                                    }
                                                                                }
                                                                                @endphp
                                                                                <div class="mb-1">
                                                                                    <span class="badge badge-secondary">#{{ $p->order ?? '-' }}</span> 
                                                                                    <strong class="{{ $p->id == $proses1Id ? 'text-warning' : ($p->id == $proses2Id ? 'text-info' : '') }}">
                                                                                        {{ $pNoOpBefore }}
                                                                                    </strong>
                                                                                    @if($p->id == $proses1Id)
                                                                                    <small class="badge badge-warning">Akan dipindah</small>
                                                                                    @endif
                                                                                </div>
                                                                                @endforeach
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <small class="text-muted d-block mb-2"><i class="fas fa-arrow-right mr-1"></i>Sesudah:</small>
                                                                            <div class="p-2 bg-light rounded">
                                                                                @php
                                                                                // Simulasi urutan setelah reorder
                                                                                $sortedAfter = collect();
                                                                                foreach ($affectedProses->sortBy('order') as $p) {
                                                                                    if ($p->id == $proses1Id) {
                                                                                        $sortedAfter->push((object)['order' => $newOrder1, 'proses' => $p, 'isMoved' => true]);
                                                                                    } elseif ($p->id == $proses2Id) {
                                                                                        $sortedAfter->push((object)['order' => $newOrder2, 'proses' => $p, 'isMoved' => false]);
                                                                                    } elseif ($oldOrder1 < $oldOrder2 && $p->order > $oldOrder1 && $p->order < $oldOrder2) {
                                                                                        $sortedAfter->push((object)['order' => $p->order + 1, 'proses' => $p, 'isMoved' => false]);
                                                                                    } elseif ($oldOrder1 > $oldOrder2 && $p->order > $oldOrder2 && $p->order < $oldOrder1) {
                                                                                        $sortedAfter->push((object)['order' => $p->order - 1, 'proses' => $p, 'isMoved' => false]);
                                                                                    } else {
                                                                                        $sortedAfter->push((object)['order' => $p->order, 'proses' => $p, 'isMoved' => false]);
                                                                                    }
                                                                                }
                                                                                $sortedAfter = $sortedAfter->sortBy('order');
                                                                                @endphp
                                                                                @foreach($sortedAfter as $item)
                                                                                @php 
                                                                                $p = $item->proses;
                                                                                $pDetails = $p ? $p->details : collect();
                                                                                $pNoOp = 'MAINTENANCE';
                                                                                if ($pDetails->isNotEmpty()) {
                                                                                    $pNoOp = $pDetails->first()->no_op ?? 'MAINTENANCE';
                                                                                    if ($pDetails->count() > 1) {
                                                                                        $pNoOp .= ' (+' . ($pDetails->count() - 1) . ')';
                                                                                    }
                                                                                }
                                                                                @endphp
                                                                                <div class="mb-1">
                                                                                    <span class="badge {{ $item->isMoved ? 'badge-success' : 'badge-secondary' }}">#{{ $item->order }}</span> 
                                                                                    <strong class="{{ $p->id == $proses1Id ? 'text-success' : ($p->id == $proses2Id ? 'text-info' : '') }}">
                                                                                        {{ $pNoOp }}
                                                                                    </strong>
                                                                                    @if($p->id == $proses1Id)
                                                                                    <small class="badge badge-success">Dipindah</small>
                                                                                    @elseif($p->order != $item->order)
                                                                                    <small class="badge badge-secondary">Bergeser</small>
                                                                                    @endif
                                                                                </div>
                                                                                @endforeach
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @else
                                                    <!-- Tampilan default untuk action lainnya -->
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th style="width: 200px;">Field</th>
                                                                    <th>Value</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($history as $key => $value)
                                                                <tr>
                                                                    <td><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}</strong></td>
                                                                    <td>
                                                                        @if(is_array($value))
                                                                        <pre class="mb-0" style="background: #f8f9fa; padding: 8px; border-radius: 4px; font-size: 12px;">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                        @elseif(is_numeric($value) && ($key === 'old_cycle_time' || $key === 'new_cycle_time'))
                                                                        <span class="badge badge-info">{{ detikKeWaktu($value) }}</span>
                                                                        <small class="text-muted ml-2">({{ number_format($value) }} detik)</small>
                                                                        @else
                                                                        {{ $value }}
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                        <i class="fas fa-times mr-1"></i>Tutup
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Modal Catatan -->
                                    @if($approval->note)
                                    <div class="modal fade" id="modalNote{{ $approval->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header {{ $approval->status === 'approved' ? 'bg-success' : 'bg-danger' }} text-white">
                                                    <h5 class="modal-title">
                                                        <i class="fas {{ $approval->status === 'approved' ? 'fa-check-circle' : 'fa-times-circle' }} mr-2"></i>
                                                        Catatan {{ $approval->status === 'approved' ? 'Approve' : 'Reject' }}
                                                    </h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong>Status:</strong>
                                                        @if($approval->status === 'approved')
                                                        <span class="badge bg-success">Approved</span>
                                                        @else
                                                        <span class="badge bg-danger">Rejected</span>
                                                        @endif
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>{{ $approval->action === 'create_aux_reprocess' ? 'Barcode' : 'No OP' }}:</strong>
                                                        <span>
                                                            @if($approval->action === 'create_aux_reprocess' && $approval->auxl)
                                                            {{ $approval->auxl->barcode ?? 'AUXL' }}
                                                            @else
                                                            @php
                                                            $noOpModalNoteFM = 'MAINTENANCE';
                                                            if ($approval->proses) {
                                                                $firstDetail = $approval->proses->details->first();
                                                                if ($firstDetail) {
                                                                    $noOpModalNoteFM = $firstDetail->no_op ?? 'MAINTENANCE';
                                                                } elseif ($approval->history_data && isset($approval->history_data['detail_proses_snapshot']) && !empty($approval->history_data['detail_proses_snapshot'])) {
                                                                    $firstDetailSnapshot = $approval->history_data['detail_proses_snapshot'][0];
                                                                    $noOpModalNoteFM = $firstDetailSnapshot['no_op'] ?? 'MAINTENANCE';
                                                                }
                                                            }
                                                            @endphp
                                                            {{ $noOpModalNoteFM }}
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Action Type:</strong>
                                                        <span class="badge bg-secondary">{{ $actionLabel }}</span>
                                                    </div>
                                                    @if($approval->approver)
                                                    <div class="mb-3">
                                                        <strong>Diproses Oleh:</strong>
                                                        <div>
                                                            {{ $approval->approver->nama }}<br>
                                                            <small class="text-muted">{{ $approval->approver->username }}</small>
                                                        </div>
                                                    </div>
                                                    @endif
                                                    @if($approval->updated_at)
                                                    <div class="mb-3">
                                                        <strong>Tanggal Proses:</strong>
                                                        <span>{{ $approval->updated_at->format('d-m-Y H:i:s') }}</span>
                                                    </div>
                                                    @endif
                                                    <div class="form-group">
                                                        <strong>Catatan:</strong>
                                                        <div class="mt-2 p-3 bg-light rounded">
                                                            <p class="mb-0" style="white-space: pre-wrap;">{{ $approval->note }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                        <i class="fas fa-times mr-1"></i>Tutup
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    @if($canManageApproval)
                                    <!-- Modal Approve -->
                                    <div class="modal fade" id="modalApprove{{ $approval->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('approval.status') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="approval_id" value="{{ $approval->id }}">
                                                    <input type="hidden" name="status" value="approved">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title">Approve Request</h5>
                                                        <button type="button" class="close text-white" data-dismiss="modal">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Apakah Anda yakin ingin <strong>meng-approve</strong> request ini?</p>
                                                        <p><strong>{{ $approval->action === 'create_aux_reprocess' ? 'Barcode' : 'No OP' }}:</strong> 
                                                            @if($approval->action === 'create_aux_reprocess' && $approval->auxl)
                                                            {{ $approval->auxl->barcode ?? 'AUXL' }}
                                                            @else
                                                            @php
                                                            $noOpModalNoteFM = 'MAINTENANCE';
                                                            if ($approval->proses) {
                                                                $firstDetail = $approval->proses->details->first();
                                                                if ($firstDetail) {
                                                                    $noOpModalNoteFM = $firstDetail->no_op ?? 'MAINTENANCE';
                                                                } elseif ($approval->history_data && isset($approval->history_data['detail_proses_snapshot']) && !empty($approval->history_data['detail_proses_snapshot'])) {
                                                                    $firstDetailSnapshot = $approval->history_data['detail_proses_snapshot'][0];
                                                                    $noOpModalNoteFM = $firstDetailSnapshot['no_op'] ?? 'MAINTENANCE';
                                                                }
                                                            }
                                                            @endphp
                                                            {{ $noOpModalNoteFM }}
                                                            @endif
                                                        </p>
                                                        <p><strong>Action:</strong> {{ $actionLabel }}</p>
                                                        <div class="form-group">
                                                            <label for="note_approve{{ $approval->id }}">Catatan (Opsional):</label>
                                                            <textarea name="note" id="note_approve{{ $approval->id }}" 
                                                                class="form-control" rows="3" 
                                                                placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    @if($canManageApproval)
                                    <!-- Modal Reject -->
                                    <div class="modal fade" id="modalReject{{ $approval->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('approval.status') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="approval_id" value="{{ $approval->id }}">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Reject Request</h5>
                                                        <button type="button" class="close text-white" data-dismiss="modal">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Apakah Anda yakin ingin <strong>menolak</strong> request ini?</p>
                                                        <p><strong>{{ $approval->action === 'create_aux_reprocess' ? 'Barcode' : 'No OP' }}:</strong> 
                                                            @if($approval->action === 'create_aux_reprocess' && $approval->auxl)
                                                            {{ $approval->auxl->barcode ?? 'AUXL' }}
                                                            @else
                                                            @php
                                                            $noOpModalNoteFM = 'MAINTENANCE';
                                                            if ($approval->proses) {
                                                                $firstDetail = $approval->proses->details->first();
                                                                if ($firstDetail) {
                                                                    $noOpModalNoteFM = $firstDetail->no_op ?? 'MAINTENANCE';
                                                                } elseif ($approval->history_data && isset($approval->history_data['detail_proses_snapshot']) && !empty($approval->history_data['detail_proses_snapshot'])) {
                                                                    $firstDetailSnapshot = $approval->history_data['detail_proses_snapshot'][0];
                                                                    $noOpModalNoteFM = $firstDetailSnapshot['no_op'] ?? 'MAINTENANCE';
                                                                }
                                                            }
                                                            @endphp
                                                            {{ $noOpModalNoteFM }}
                                                            @endif
                                                        </p>
                                                        <p><strong>Action:</strong> {{ $actionLabel }}</p>
                                                        <div class="form-group">
                                                            <label for="note_reject{{ $approval->id }}">Alasan Penolakan <span class="text-danger">*</span>:</label>
                                                            <textarea name="note" id="note_reject{{ $approval->id }}" 
                                                                class="form-control" rows="3" required
                                                                placeholder="Masukkan alasan penolakan..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-danger">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    @empty
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <p class="text-muted py-3">Tidak ada data approval FM.</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.title = "Approval FM";
</script>
@endsection