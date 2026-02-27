@extends('layout.main')
@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Approval Kepala Shift</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Approval Kepala Shift</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        @php
        $userRole = Auth::user()->role ?? null;
        $canManageApproval = in_array($userRole, ['super_admin', 'kepala_shift']);
        $actionLabels = [
            'topping_la' => 'Topping LA',
            'topping_aux' => 'Topping AUX',
        ];
        @endphp
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Daftar Request Topping LA / AUX</h3>
                        </div>
                        <div class="card-body">
                            <table id="approval_kepala_shift" class="table table-head-fixed text-nowrap table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>No OP</th>
                                        <th>Jenis</th>
                                        <th>Dilakukan Oleh</th>
                                        <th>Status</th>
                                        <th>Tanggal Request</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($approvals as $approval)
                                    @php
                                    $noOpDisplay = '-';
                                    if ($approval->proses) {
                                        $firstDetail = $approval->proses->details->first();
                                        if ($firstDetail) {
                                            $noOpDisplay = $firstDetail->no_op ?? '-';
                                            if ($approval->proses->details->count() > 1) {
                                                $noOpDisplay .= ' (+' . ($approval->proses->details->count() - 1) . ' OP)';
                                            }
                                        }
                                    }
                                    $actionLabel = $actionLabels[$approval->action] ?? ucfirst(str_replace('_', ' ', $approval->action));
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $noOpDisplay }}</strong></td>
                                        <td><span class="badge bg-info">{{ $actionLabel }}</span></td>
                                        <td>
                                            {{ $approval->requester->nama ?? '-' }}<br>
                                            <small class="text-muted">{{ $approval->requester->username ?? '' }}</small>
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
                                        <td>{{ $approval->created_at->format('d-m-Y H:i:s') }}</td>
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
                                                        <p>Apakah Anda yakin ingin <strong>meng-approve</strong> request {{ $actionLabel }} ini?</p>
                                                        <p><strong>No OP:</strong> {{ $noOpDisplay }}</p>
                                                        @if($approval->proses && $approval->proses->details->count() > 1)
                                                        <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Multiple OP: 1 kali approval berlaku untuk semua OP. Barcode topping cukup di-scan sekali dan akan ditambahkan ke setiap OP.</p>
                                                        @endif
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
                                                        <p>Apakah Anda yakin ingin <strong>menolak</strong> request {{ $actionLabel }} ini?</p>
                                                        <p><strong>No OP:</strong> {{ $noOpDisplay }}</p>
                                                        @if($approval->proses && $approval->proses->details->count() > 1)
                                                        <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Multiple OP: 1 kali approval berlaku untuk semua OP.</p>
                                                        @endif
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

                                    @if($approval->note)
                                    <!-- Modal Note -->
                                    <div class="modal fade" id="modalNote{{ $approval->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-secondary text-white">
                                                    <h5 class="modal-title">Catatan</h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>{{ $approval->note }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <p class="text-muted py-3">Tidak ada data approval topping LA/AUX.</p>
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
    document.title = "Approval Kepala Shift";
</script>
@endsection
