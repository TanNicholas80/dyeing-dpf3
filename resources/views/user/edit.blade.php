@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Edit User</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('user.index') }}">User</a></li>
                            <li class="breadcrumb-item active">Edit User</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('user.update', ['id' => $user->id]) }}" method="POST">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Form Edit User</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Nama</label>
                                <input type="text" name="nama" value="{{ old('nama', $user->nama) }}"
                                    class="form-control" required>
                                @error('nama')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" value="{{ old('username', $user->username) }}"
                                    class="form-control" required>
                                @error('username')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Password <small class="text-muted">(kosongkan jika tidak diganti)</small></label>
                                <input type="password" name="password" class="form-control" placeholder="Password">
                                @error('password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Role</label>
                                <select name="role" class="form-control" required>
                                    @php
                                        $roles = [
                                            'super_admin' => 'Super Admin',
                                            'owner' => 'Owner',
                                            'manager' => 'Manager',
                                            'ppic' => 'PPIC',
                                            'mesin' => 'Mesin',
                                            'ds' => 'DS',
                                            'fm' => 'FM',
                                            'vp' => 'VP',
                                        ];
                                    @endphp
                                    @foreach ($roles as $value => $label)
                                        <option value="{{ $value }}" {{ $user->role == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Mesin <small class="text-muted">(kosongkan jika akses semua mesin)</small></label>
                                <select name="mesin" class="form-control" id="mesin-select">
                                    <option value="">-- Pilih Mesin --</option>
                                    @foreach($mesins as $mesin)
                                        <option value="{{ $mesin->jenis_mesin }}" {{ old('mesin', $user->mesin) == $mesin->jenis_mesin ? 'selected' : '' }}>{{ $mesin->jenis_mesin }}</option>
                                    @endforeach
                                </select>
                                @error('mesin')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        const roleSelect = document.querySelector('select[name="role"]');
        const mesinSelect = document.getElementById('mesin-select');

        function toggleMesinRequired() {
            if (roleSelect.value === 'mesin') {
                mesinSelect.required = true;
            } else {
                mesinSelect.required = false;
            }
        }

        roleSelect.addEventListener('change', toggleMesinRequired);
        toggleMesinRequired();

        document.title = "Edit User";
    </script>
@endsection
