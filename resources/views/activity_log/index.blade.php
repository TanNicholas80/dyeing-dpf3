@extends('layout.main')
@section('content')
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Activity Log</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active"><a>Activity Log</a></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Data Activity Log</h3>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="activity_log" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>User</th>
                                            <th>Log Name</th>
                                            <th>Description</th>
                                            <th>Event</th>
                                            <th>Properties</th>
                                            <th>Created At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($activities as $index => $activity)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                @if($activity->causer)
                                                <code>{{ $activity->causer->username ?? $activity->causer->name ?? 'N/A' }}</code>
                                                @else
                                                <span class="text-muted">System</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($activity->log_name)
                                                <span class="badge bg-info">{{ $activity->log_name }}</span>
                                                @else
                                                <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $activity->description ?? 'N/A' }}</td>
                                            <td>
                                                @php
                                                $event = $activity->event ?? 'unknown';
                                                $badgeClass = match($event) {
                                                'created' => 'badge bg-success',
                                                'updated' => 'badge bg-warning text-dark',
                                                'deleted' => 'badge bg-danger',
                                                default => 'badge bg-secondary'
                                                };
                                                @endphp
                                                <span class="{{ $badgeClass }}">{{ ucfirst($event) }}</span>
                                            </td>
                                            <td>
                                                @php
                                                $properties = $activity->properties ?? [];
                                                $hasBefore = isset($properties['before_update']) && !empty($properties['before_update']);
                                                $hasAfter = isset($properties['after_update']) && !empty($properties['after_update'])
                                                || isset($properties['created_data']) && !empty($properties['created_data'])
                                                || isset($properties['deleted_data']) && !empty($properties['deleted_data']);
                                                @endphp

                                                <div class="d-flex">
                                                    @if($hasBefore)
                                                    <button type="button" class="btn btn-sm btn-outline-info mr-2"
                                                        data-content='@json($properties['before_update'])'
                                                        data-title="Data Sebelum Update"
                                                        onclick="showDataModal(this)">
                                                        <i class="fas fa-eye"></i> Before
                                                    </button>
                                                    @endif

                                                    @if($hasAfter)
                                                    @php
                                                    $afterData = $properties['after_update'] ?? $properties['created_data'] ?? $properties['deleted_data'] ?? null;
                                                    $afterTitle = isset($properties['after_update']) ? 'Data Setelah Update'
                                                    : (isset($properties['created_data']) ? 'Data yang Dibuat'
                                                    : 'Data yang Dihapus');
                                                    @endphp
                                                    <button type="button" class="btn btn-sm btn-outline-success mr-2"
                                                        data-content='@json($afterData)'
                                                        data-title="{{ $afterTitle }}"
                                                        onclick="showDataModal(this)">
                                                        <i class="fas fa-eye"></i> After
                                                    </button>
                                                    @endif

                                                    @if(!$hasBefore && !$hasAfter && !empty($properties))
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-content='@json($properties)'
                                                        data-title="Properties"
                                                        onclick="showDataModal(this)">
                                                        <i class="fas fa-info-circle"></i> View
                                                    </button>
                                                    @endif

                                                    @if(!$hasBefore && !$hasAfter && empty($properties))
                                                    <span class="text-muted">-</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                {{ $activity->created_at->format('d-m-Y H:i:s') }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal untuk melihat detail data -->
<div class="modal fade" id="dataModal" tabindex="-1" role="dialog" aria-labelledby="dataModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataModalLabel">Detail Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="dataContent" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.title = "Activity Log";

    function formatKey(key) {
        return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function formatValueByKey(key, value, indent = '') {
        if (value === null || value === undefined) {
            return '-';
        }

        // Khusus untuk status mesin: 0 = Mati, 1 = Nyala
        if (key === 'status' && (value === 0 || value === 1 || value === '0' || value === '1')) {
            const statusValue = parseInt(value);
            return statusValue === 1 ? 'Nyala' : 'Mati';
        }

        if (Array.isArray(value)) {
            if (!value.length) {
                return '[]';
            }
            return value.map(item => {
                if (typeof item === 'object' && item !== null) {
                    return `\n${indent}- ${formatObject(item, indent + '  ')}`;
                }
                return `\n${indent}- ${formatValueByKey(key, item, indent + '  ')}`;
            }).join('');
        }

        if (typeof value === 'object') {
            return `\n${formatObject(value, indent + '  ')}`;
        }

        if (typeof value === 'boolean') {
            return value ? 'Ya' : 'Tidak';
        }

        if (typeof value === 'number') {
            if (Math.abs(value) >= 1000) {
                return new Intl.NumberFormat('id-ID').format(value);
            }
            return value;
        }

        return value;
    }

    function formatObject(obj, indent = '') {
        return Object.keys(obj).map(childKey => {
            const formattedValue = formatValueByKey(childKey, obj[childKey], indent);
            return `${indent}${formatKey(childKey)}: ${formattedValue}`;
        }).join('\n');
    }

    // Fungsi untuk menampilkan modal dengan data yang sudah diformat rapi
    function showDataModal(button) {
        const content = button.getAttribute('data-content');
        const title = button.getAttribute('data-title');

        try {
            const data = JSON.parse(content);
            document.getElementById('dataModalLabel').textContent = title;

            let formattedContent = '';
            if (typeof data === 'object' && data !== null) {
                formattedContent = formatObject(data);
            } else {
                formattedContent = data?.toString?.() ?? '';
            }

            document.getElementById('dataContent').textContent = formattedContent;
            // Bootstrap 4 modal (AdminLTE)
            $('#dataModal').modal('show');
        } catch (e) {
            console.error('Error parsing JSON:', e);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data'
                });
            } else {
                alert('Gagal memuat data');
            }
        }
    }
</script>
@endsection

