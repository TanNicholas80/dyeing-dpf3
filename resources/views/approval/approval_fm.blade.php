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
                                            <strong>{{ $approval->proses->no_op ?? 'MAINTENANCE' }}</strong>
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
                                                'create_reprocess' => 'Buat Reproses'
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
                                            <button type="button" class="btn btn-sm btn-success mb-1" 
                                                data-toggle="modal" data-target="#modalApprove{{ $approval->id }}">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger mb-1" 
                                                data-toggle="modal" data-target="#modalReject{{ $approval->id }}">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            @else
                                            <span class="text-muted">Sudah diproses</span>
                                            @if($approval->note)
                                            <br><small class="text-muted" title="{{ $approval->note }}">
                                                <i class="fas fa-comment"></i> Ada catatan
                                            </small>
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
                                                        History Data - {{ $approval->proses->no_op ?? 'MAINTENANCE' }}
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
                                                            $oldMesin = isset($prosesSnapshot['mesin_id']) ? \App\Models\Mesin::find($prosesSnapshot['mesin_id']) : null;
                                                            @endphp
                                                            <div class="card border-danger mb-3">
                                                                <div class="card-header bg-danger text-white">
                                                                    <h6 class="mb-0"><i class="fas fa-trash mr-2"></i>Detail Proses yang Akan Dihapus</h6>
                                                                </div>
                                                                <div class="card-body">
                                                                    <div class="row">
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">No OP</strong>
                                                                            <h5 class="mb-0">{{ $prosesSnapshot['no_op'] ?? '-' }}</h5>
                                                                        </div>
                                                                        <div class="col-md-6 mb-3">
                                                                            <strong class="text-muted d-block">Jenis</strong>
                                                                            <span class="badge badge-secondary">{{ $prosesSnapshot['jenis'] ?? '-' }}</span>
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
                                                        <p><strong>No OP:</strong> {{ $approval->proses->no_op ?? 'MAINTENANCE' }}</p>
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
                                                        <p><strong>No OP:</strong> {{ $approval->proses->no_op ?? 'MAINTENANCE' }}</p>
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