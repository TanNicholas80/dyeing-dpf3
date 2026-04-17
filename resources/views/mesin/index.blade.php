@extends('layout.main')

@section('content')
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Mesin</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item active"><a>Mesin</a></li>
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
                                <h3 class="card-title">Data Mesin</h3>

                                @php
                                    $userRole = Auth::user()->role ?? null;
                                    $restrictedRoles = ['fm', 'vp', 'ppic', 'owner'];
                                    $canManageMesin = !in_array(strtolower($userRole), $restrictedRoles);
                                    $isSuperAdmin = strtolower($userRole ?? '') === 'super_admin';
                                @endphp

                                @if ($canManageMesin)
                                    <div class="d-flex justify-content-end">
                                        <a href="{{ route('mesin.create') }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Tambah
                                        </a>
                                    </div>
                                @endif
                            </div>

                            <div class="card-body">
                                <table id="mesin" class="table table-head-fixed text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Jenis Mesin</th>
                                            <th>Status</th>
                                            @if ($isSuperAdmin)
                                            <th>Alarm Paksa OFF</th>
                                            @endif
                                            <th>Terakhir Nyala</th>
                                            <th>Terakhir Mati</th>
                                            @if ($canManageMesin)
                                            <th>Aksi</th>
                                             @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($mesins as $i => $mesin)
                                            <tr>
                                                <td>{{ $mesin->jenis_mesin }}</td>
                                                <td>
                                                    <span class="badge status-badge {{ $mesin->status ? 'badge-success' : 'badge-secondary' }}"
                                                        data-id="{{ $mesin->id }}">
                                                        {{ $mesin->status ? 'Hidup' : 'Mati' }}
                                                    </span>
                                                </td>
                                                @if ($isSuperAdmin)
                                                @php $forceOff = (bool) ($forceAlarmOffMap[$mesin->id] ?? false); @endphp
                                                <td>
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox"
                                                            class="custom-control-input force-alarm-toggle"
                                                            id="forceAlarm{{ $mesin->id }}"
                                                            data-id="{{ $mesin->id }}"
                                                            data-jenis="{{ $mesin->jenis_mesin }}"
                                                            {{ $forceOff ? 'checked' : '' }}>
                                                        <label class="custom-control-label" for="forceAlarm{{ $mesin->id }}">
                                                            {{ $forceOff ? 'ON' : 'OFF' }}
                                                        </label>
                                                    </div>
                                                </td>
                                                @endif
                                                <td>{{ $lastStatusMap[$mesin->id]['nyala'] }}</td>
                                                <td>{{ $lastStatusMap[$mesin->id]['mati'] }}</td>
                                                @if ($canManageMesin)
                                                <td>
                                                    <a href="{{ route('mesin.edit', $mesin->id) }}"
                                                        class="btn btn-warning btn-sm mr-2">
                                                        <i class="fas fa-pen"></i> Edit
                                                    </a>
                                                    <a href="#" data-toggle="modal"
                                                        data-target="#modal-hapus{{ $mesin->id }}"
                                                        class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash-alt"></i> Hapus
                                                    </a>
                                                </td>
                                                @endif
                                            </tr>

                                            @if ($canManageMesin)
                                            <!-- Modal Hapus -->
                                            <div class="modal fade" id="modal-hapus{{ $mesin->id }}">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title">Konfirmasi Hapus Data</h4>
                                                            <button type="button" class="close" data-dismiss="modal"
                                                                aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Apakah anda yakin ingin menghapus mesin
                                                                <b>{{ $mesin->jenis_mesin }}</b>?
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer justify-content-between">
                                                            <form action="{{ route('mesin.destroy', $mesin->id) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="button" class="btn btn-default"
                                                                    data-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-danger">Ya,
                                                                    Hapus</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- /.card -->

                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Modal konfirmasi Alarm Paksa OFF --}}
    <div class="modal fade" id="modalForceAlarmOff" tabindex="-1" role="dialog" aria-labelledby="modalForceAlarmOffLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="modalForceAlarmOffLabel">Konfirmasi Alarm Paksa OFF</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="forceAlarmActionText"></p>
                    <p class="mb-2" id="forceAlarmActionHint"></p>
                    <div class="form-group">
                        <label for="forceAlarmReason">Alasan perubahan status Alarm Paksa OFF <span
                                class="text-danger">*</span></label>
                        <textarea id="forceAlarmReason" class="form-control" rows="3" placeholder="Contoh: Alarm gangguan sensor, pengecekan manual oleh teknisi"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" id="btnConfirmForceAlarmOff" class="btn btn-warning text-dark">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.title = "Data Mesin";

        document.addEventListener('DOMContentLoaded', function() {
            let currentForceAlarmToggle = null;
            let currentForceAlarmLabel = null;
            let currentForceAlarmMesinId = null;
            let currentForceAlarmEnabled = null;

            setInterval(function() {
                fetch('/mesin/statuses')
                    .then(response => response.json())
                    .then(data => {
                        document.querySelectorAll('.status-badge').forEach(function(badge) {
                            var mesinId = badge.getAttribute('data-id');
                            if (data[mesinId]) {
                                badge.textContent = data[mesinId].label;
                                badge.classList.remove('badge-success', 'badge-secondary');
                                badge.classList.add(data[mesinId].status ? 'badge-success' : 'badge-secondary');
                            }
                        });
                        document.querySelectorAll('.force-alarm-toggle').forEach(function(toggle) {
                            var mesinId = toggle.getAttribute('data-id');
                            if (data[mesinId] && data[mesinId].force_alarm_off !== undefined) {
                                var checked = !!data[mesinId].force_alarm_off;
                                if (toggle.checked !== checked) {
                                    toggle.checked = checked;
                                }
                                var label = document.querySelector('label[for="' + toggle.id + '"]');
                                if (label) {
                                    label.textContent = checked ? 'ON' : 'OFF';
                                }
                            }
                        });
                    });
            }, 1000);

            document.querySelectorAll('.force-alarm-toggle').forEach(function(toggle) {
                toggle.addEventListener('change', function() {
                    var mesinId = this.getAttribute('data-id');
                    var enabled = this.checked;
                    var label = document.querySelector('label[for="' + this.id + '"]');
                    // Simpan referensi toggle & label untuk dipakai setelah konfirmasi
                    currentForceAlarmToggle = this;
                    currentForceAlarmLabel = label;
                    currentForceAlarmMesinId = mesinId;
                    currentForceAlarmEnabled = enabled;

                    // Revert dulu sampai konfirmasi selesai
                    this.checked = !enabled;
                    if (label) label.textContent = (!enabled) ? 'ON' : 'OFF';

                    var mesinName = this.getAttribute('data-jenis') || ('ID ' + mesinId);
                    var actionTextEl = document.getElementById('forceAlarmActionText');
                    var actionHintEl = document.getElementById('forceAlarmActionHint');
                    var titleEl = document.getElementById('modalForceAlarmOffLabel');
                    var confirmBtn = document.getElementById('btnConfirmForceAlarmOff');

                    if (enabled) {
                        titleEl.textContent = 'Aktifkan Alarm Paksa OFF';
                        actionTextEl.innerHTML = 'Anda akan <strong>mengaktifkan Alarm Paksa OFF</strong> untuk mesin <strong>' + mesinName + '</strong>.';
                        actionHintEl.innerHTML = 'Mode ini akan <strong>memaksa alarm selalu mati</strong> dan mengabaikan semua rule alarm sampai mode ini dimatikan kembali.';
                        confirmBtn.textContent = 'Ya, Aktifkan Alarm Paksa OFF';
                    } else {
                        titleEl.textContent = 'Nonaktifkan Alarm Paksa OFF';
                        actionTextEl.innerHTML = 'Anda akan <strong>menonaktifkan Alarm Paksa OFF</strong> untuk mesin <strong>' + mesinName + '</strong>.';
                        actionHintEl.innerHTML = 'Setelah dinonaktifkan, alarm akan kembali mengikuti <strong>rule normal sistem</strong>.';
                        confirmBtn.textContent = 'Ya, Nonaktifkan Alarm Paksa OFF';
                    }

                    document.getElementById('forceAlarmReason').value = '';
                    $('#modalForceAlarmOff').modal('show');
                });
            });

            // Konfirmasi dari modal untuk mengubah status Alarm Paksa OFF
            document.getElementById('btnConfirmForceAlarmOff').addEventListener('click', function() {
                if (!currentForceAlarmToggle || !currentForceAlarmMesinId || currentForceAlarmEnabled === null) {
                    $('#modalForceAlarmOff').modal('hide');
                    return;
                }
                var reasonInput = document.getElementById('forceAlarmReason');
                var reason = reasonInput.value.trim();
                if (!reason) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Validasi',
                        text: 'Alasan wajib diisi untuk mengubah status Alarm Paksa OFF.'
                    });
                    reasonInput.focus();
                    return;
                }

                currentForceAlarmToggle.disabled = true;

                fetch('/mesin/' + currentForceAlarmMesinId + '/alarm-force', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ enabled: currentForceAlarmEnabled, reason: reason })
                })
                .then(function(response) {
                    if (!response.ok) {
                        return response.json().then(function(err) {
                            throw new Error(err.message || 'Gagal update alarm paksa');
                        }).catch(function() {
                            throw new Error('Gagal update alarm paksa');
                        });
                    }
                    return response.json();
                })
                .then(function(result) {
                    var isOn = !!result.force_alarm_off;
                    currentForceAlarmToggle.checked = isOn;
                    if (currentForceAlarmLabel) {
                        currentForceAlarmLabel.textContent = isOn ? 'ON' : 'OFF';
                    }
                    $('#modalForceAlarmOff').modal('hide');
                })
                .catch(function(e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: e.message || 'Gagal mengubah alarm paksa. Silakan coba lagi.'
                    });
                    currentForceAlarmToggle.checked = false;
                    if (currentForceAlarmLabel) currentForceAlarmLabel.textContent = 'OFF';
                })
                .finally(function() {
                    currentForceAlarmToggle.disabled = false;
                    currentForceAlarmToggle = null;
                    currentForceAlarmLabel = null;
                    currentForceAlarmMesinId = null;
                    currentForceAlarmEnabled = null;
                });
            });

            $('#modalForceAlarmOff').on('hidden.bs.modal', function() {
                currentForceAlarmToggle = null;
                currentForceAlarmLabel = null;
                currentForceAlarmMesinId = null;
                currentForceAlarmEnabled = null;
                document.getElementById('forceAlarmReason').value = '';
            });
        });
    </script>
@endsection
