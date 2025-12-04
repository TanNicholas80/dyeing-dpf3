<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dyeing Schedule | Login</title>

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,600,700&display=fallback">
    <link rel="stylesheet" href="{{ asset('lte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/dist/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}">

    <style>
        body {
            background: url('{{ asset('images/a1.jpg') }}') no-repeat center center fixed;
            background-size: cover;
            background-color: #000;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            position: relative;
        }

        .login-logo img {
            width: 230px;
            height: auto;
            margin-bottom: 15px;
            max-width: 100%;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.35);
            background-color: rgba(255, 255, 255, 0.95);
            transition: transform 0.3s ease;
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
        }

        .login-card-body {
            border-radius: 12px;
            padding: 30px;
        }

        .field-error {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.35rem;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-8px);
            }

            40%,
            80% {
                transform: translateX(8px);
            }
        }

        .login-error {
            animation: shake 0.4s ease-in-out;
        }

        /* ✅ Perataan checkbox "Ingat Saya" dan tombol Masuk */
        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .remember-container {
            display: flex;
            align-items: center;
        }

        .remember-container .icheck-primary {
            margin-left: 0;
        }

        .login-btn-container {
            display: flex;
            align-items: center;
        }

        /* ✅ Pesan error umum */
        .general-error {
            color: #dc3545;
            font-weight: 600;
            margin-top: -5px;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        /* Responsive design untuk mobile */
        @media (max-width: 576px) {
            body {
                padding: 15px;
                align-items: flex-start;
                padding-top: 40px;
            }

            .login-card-body {
                padding: 20px;
            }

            .form-footer {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .remember-container {
                justify-content: center;
            }

            .login-btn-container {
                justify-content: center;
            }

            .login-logo img {
                width: 200px;
            }
        }

        @media (max-width: 400px) {
            .login-logo img {
                width: 180px;
            }

            .login-card-body {
                padding: 15px;
            }
        }

        @media (min-width: 1200px) {
            .login-box {
                max-width: 480px;
                margin: 40px auto;
            }

            .login-logo img {
                width: 230px;
            }

            .card {
                max-width: 480px;
            }
        }

        @media (min-width: 900px) and (max-width: 1199px) {
            .login-box {
                max-width: 420px;
                margin: 30px auto;
            }

            .login-logo img {
                width: 200px;
            }

            .card {
                max-width: 420px;
            }
        }

        @media (min-width: 600px) and (max-width: 899px) {
            .login-box {
                max-width: 350px;
                margin: 20px auto;
            }

            .login-logo img {
                width: 180px;
            }

            .card {
                max-width: 350px;
            }
        }

        @media (min-width: 1200px) and (max-width: 2000px) {
            body {
                padding: 40px;
            }

            .login-box {
                margin-top: 60px;
            }
        }
    </style>
</head>

<body class="hold-transition login-page">
    <div class="login-box text-center">
        <div class="login-logo">
            <img src="{{ asset('images/logopanjang.svg') }}" alt="Logo">
        </div>

        <div class="card" id="login-card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Masuk untuk memulai sesi Anda</p>

                {{-- ✅ Pesan error umum --}}
                @if (session('error'))
                    <div class="general-error" id="error-message">
                        {{ session('error') }}
                    </div>
                @endif

                <form action="{{ route('login-proses') }}" method="POST" novalidate>
                    @csrf

                    <!-- Username -->
                    <div class="input-group mb-3">
                        <input type="text" name="username"
                            class="form-control @error('username') is-invalid @enderror" placeholder="Username"
                            value="{{ old('username') }}" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-user"></span></div>
                        </div>
                    </div>
                    @error('username')
                        <div class="field-error">{{ $message }}</div>
                    @enderror

                    <!-- Password -->
                    <div class="input-group mb-3">
                        <input type="password" name="password"
                            class="form-control @error('password') is-invalid @enderror" placeholder="Password"
                            required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-lock"></span></div>
                        </div>
                    </div>
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror

                    <!-- Baris baru untuk checkbox dan tombol -->
                    <div class="form-footer">
                        <div class="remember-container">
                            <div class="icheck-primary">
                                <input type="checkbox" id="remember" name="remember"
                                    {{ old('remember') ? 'checked' : '' }}>
                                <label for="remember">Ingat Saya</label>
                            </div>
                        </div>

                        <div class="login-btn-container">
                            <button type="submit" class="btn btn-primary">Masuk</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="{{ asset('lte/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('lte/dist/js/adminlte.min.js') }}"></script>

    <script>
        $(function() {
            @if (session('error'))
                $('#login-card').addClass('login-error');
                setTimeout(() => $('#login-card').removeClass('login-error'), 600);
            @endif
        });
    </script>
</body>

</html>
