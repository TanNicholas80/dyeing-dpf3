@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>Ubah Password</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <form action="{{ route('user.profile.update') }}" method="POST">
                    @csrf
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Form Ubah Password</h3>
                        </div>
                        <div class="card-body">
                            @if (session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif

                            <div class="form-group">
                                <label>Nama</label>
                                <input type="text" value="{{ $user->nama }}" class="form-control" readonly>
                            </div>

                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="{{ $user->username }}" class="form-control" readonly>
                            </div>

                            <div class="form-group">
                                <label>Password Lama</label>
                                <input type="password" name="current_password" class="form-control"
                                    placeholder="Masukkan password lama" required>
                                @error('current_password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Password Baru</label>
                                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter"
                                    required>
                                @error('password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Konfirmasi Password Baru</label>
                                <input type="password" name="password_confirmation" class="form-control" required>
                            </div>
                        </div>

                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-primary">Ubah Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        document.title = "Ubah Password";
    </script>
@endsection
