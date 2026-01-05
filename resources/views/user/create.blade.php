@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Tambah User</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('user.index') }}">User</a></li>
                            <li class="breadcrumb-item active">Tambah User</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('user.store') }}" method="POST">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Form Tambah User</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Nama</label>
                                <input type="text" name="nama" class="form-control" placeholder="Nama"
                                    value="{{ old('nama') }}" required>
                                @error('nama')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" placeholder="Username"
                                    value="{{ old('username') }}" required>
                                @error('username')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Password" required>
                                @error('password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Role</label>
                                <select name="role" class="form-control" required>
                                    <option value="" disabled {{ old('role') ? '' : 'selected' }}>-- Pilih Role --
                                    </option>
                                    <option value="super_admin" {{ old('role') == 'super_admin' ? 'selected' : '' }}>Super
                                        Admin</option>
                                    <option value="owner" {{ old('role') == 'owner' ? 'selected' : '' }}>Owner</option>
                                    <option value="manager" {{ old('role') == 'manager' ? 'selected' : '' }}>Manager
                                    </option>
                                    <option value="ppic" {{ old('role') == 'ppic' ? 'selected' : '' }}>PPIC</option>
                                    <option value="mesin" {{ old('role') == 'mesin' ? 'selected' : '' }}>Mesin</option>
                                    <option value="ds" {{ old('role') == 'ds' ? 'selected' : '' }}>DS</option>
                                    <option value="fm" {{ old('role') == 'fm' ? 'selected' : '' }}>FM</option>
                                    <option value="vp" {{ old('role') == 'vp' ? 'selected' : '' }}>VP</option>
                                </select>
                                @error('role')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Mesin <small class="text-muted">(kosongkan jika akses semua mesin)</small></label>
                                <select name="mesin" class="form-control" id="mesin-select">
                                    <option value="" {{ old('mesin') ? '' : 'selected' }}>-- Pilih Mesin --</option>
                                    @foreach ($mesins as $mesin)
                                        <option value="{{ $mesin->jenis_mesin }}"
                                            {{ old('mesin') == $mesin->jenis_mesin ? 'selected' : '' }}>
                                            {{ $mesin->jenis_mesin }}</option>
                                    @endforeach
                                </select>
                                @error('mesin')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-primary">Submit</button>
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

        document.title = "Tambah User";
    </script>
@endsection
