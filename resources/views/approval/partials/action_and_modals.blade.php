@php
$userRole = Auth::user()->role ?? null;
$canManageApproval = strtolower($userRole) !== 'owner';

$actionLabels = [
    'move_machine' => 'Pindah Mesin',
    'edit_cycle_time' => 'Edit Cycle Time',
    'delete_proses' => 'Hapus Proses',
    'create_reprocess' => 'Buat Reproses',
    'create_aux_reprocess' => 'Buat Reproses Auxl',
    'swap_position' => 'Tukar Posisi',
    'topping_la' => 'Topping LA',
    'topping_aux' => 'Topping Auxl'
];
$actionLabel = $actionLabels[$approval->action] ?? ucfirst(str_replace('_', ' ', $approval->action));

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

<!-- Main Actions Container -->
<div class="d-flex flex-column align-items-start">
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
        <button type="button" class="btn btn-sm btn-link text-primary p-0 mt-1" 
            data-toggle="modal" data-target="#modalNote{{ $approval->id }}"
            style="font-size: 0.875rem;">
            <i class="fas fa-comment"></i> Ada catatan
        </button>
        @endif
    @endif
</div>

<!-- Modal History Data -->
@if($approval->history_data)
@php
$history = $approval->history_data;
@endphp
<div class="modal fade" id="modalHistory{{ $approval->id }}" tabindex="-1" style="z-index: 1055" data-backdrop="false">
    <div class="modal-dialog modal-lg text-left" role="document">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-history mr-2"></i>
                    History Data - 
                    @if($approval->action === 'create_aux_reprocess' && $approval->auxl)
                    {{ $approval->auxl->barcode ?? 'AUXL' }}
                    @else
                    @php
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
                                            @if(!empty($detailSnapshot['warna']) || !empty($detailSnapshot['kode_warna']))
                                            <div class="col-md-6 mb-2">
                                                <strong class="text-muted d-block">Warna</strong>
                                                <span>{{ $detailSnapshot['warna'] ?? '-' }}</span>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @elseif($approval->action === 'move_machine')
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
                                        <h5 class="mb-0 text-danger"><strong>{{ $oldMesin->jenis_mesin }}</strong></h5>
                                        @endif
                                    </div>
                                    <div class="text-center px-4">
                                        <i class="fas fa-arrow-right fa-3x text-primary"></i>
                                    </div>
                                    <div class="text-center flex-fill">
                                        <small class="text-muted d-block mb-2">Mesin Baru</small>
                                        @if($newMesin)
                                        <h5 class="mb-0 text-success"><strong>{{ $newMesin->jenis_mesin }}</strong></h5>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @elseif($approval->action === 'swap_position')
                <div class="row">
                    <div class="col-md-12">
                        @php
                        $proses1Id = $history['proses1_id'] ?? null;
                        $proses2Id = $history['proses2_id'] ?? null;
                        $oldOrder1 = $history['old_order1'] ?? null;
                        $oldOrder2 = $history['old_order2'] ?? null;
                        $newOrder1 = $oldOrder2;
                        $newOrder2 = $oldOrder1;
                        @endphp
                        
                        <div class="card mb-3" style="border-left: 4px solid #ffc107;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div><h6 class="mb-1"><i class="fas fa-exchange-alt mr-2"></i>Tukar Posisi Proses</h6></div>
                                    <div class="text-right">
                                        <span class="badge badge-warning">Order {{ $oldOrder1 ?? '-' }} → {{ $newOrder1 ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @else
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
            <div class="modal-footer d-flex">
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
<div class="modal fade" id="modalNote{{ $approval->id }}" tabindex="-1" style="z-index: 1055" data-backdrop="false">
    <div class="modal-dialog text-left">
        <div class="modal-content shadow-lg">
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
                <div class="form-group">
                    <strong>Catatan:</strong>
                    <div class="mt-2 p-3 bg-light rounded">
                        <p class="mb-0" style="white-space: pre-wrap;">{{ $approval->note }}</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endif

@if($canManageApproval)
<!-- Modal Approve -->
<div class="modal fade" id="modalApprove{{ $approval->id }}" tabindex="-1" style="z-index: 1055" data-backdrop="false">
    <div class="modal-dialog text-left">
        <div class="modal-content shadow-lg">
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
                    <p><strong>Action:</strong> {{ $actionLabel }}</p>
                    <div class="form-group">
                        <label>Catatan (Opsional):</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reject -->
<div class="modal fade" id="modalReject{{ $approval->id }}" tabindex="-1" style="z-index: 1055" data-backdrop="false">
    <div class="modal-dialog text-left">
        <div class="modal-content shadow-lg">
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
                    <p><strong>Action:</strong> {{ $actionLabel }}</p>
                    <div class="form-group">
                        <label>Alasan Penolakan <span class="text-danger">*</span>:</label>
                        <textarea name="note" class="form-control" rows="3" required placeholder="Masukkan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
