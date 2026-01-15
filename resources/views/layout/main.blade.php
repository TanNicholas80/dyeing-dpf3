<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title id="dynamic-title">Dyeing Schedule - Duniatex</title>
    <link rel="icon" type="png" href="{{ asset('images/logo.png') }}">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/fontawesome-free/css/all.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('lte/dist/css/adminlte.min.css') }}">
    <!-- DataTables -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('lte/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('lte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        #datetime {
            white-space: nowrap;
            min-width: 220px;
            text-align: center;
            display: inline-block;
        }

        @media (max-width: 900px) {
            #datetime {
                min-width: 170px;
                font-size: 13px;
            }
        }

        /* Responsive tweaks for mobile */
        @media (max-width: 767.98px) {
            .content-header .row.mb-2 {
                flex-direction: column;
                align-items: flex-start;
            }

            .content-header .col-sm-6 {
                width: 100%;
                text-align: left !important;
                margin-bottom: 10px;
            }

            .breadcrumb.float-sm-right {
                float: none !important;
                margin-top: 10px;
                padding-left: 0;
            }

            .main-footer {
                font-size: 13px;
                padding: 10px 5px;
            }

            .navbar-brand img.brand-image {
                max-width: 120px;
                height: auto;
            }

            .sidebar {
                font-size: 15px;
            }

            .select2-container .select2-selection--single {
                height: 36px !important;
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 34px !important;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 34px !important;
            }
        }

        @media (max-width: 575.98px) {
            .main-header .navbar {
                padding: 0.25rem 0.5rem;
            }

            .main-footer {
                font-size: 11px;
            }

            .sidebar {
                font-size: 13px;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-collapse layout-top-nav">
    <div class="wrapper">

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
            <div class="container">
                <a href="{{ route('dashboard') }}" class="navbar-brand" title="Halaman Dashboard">
                    <img src="{{ asset('images/logopanjang.svg') }}" alt="Duniatex Logo" class="brand-image">
                </a>

                <button class="navbar-toggler order-1" type="button" data-toggle="collapse"
                    data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse order-3" id="navbarCollapse">
                    <!-- Left navbar links -->
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i
                                    class="fas fa-bars"></i></a>
                        </li>
                        @php
                            $role = Auth::user()->role ?? null;
                            $canDashboard = !in_array($role, ['aux']); // Semua role bisa akses dashboard kecuali aux
                            $canApprovalFm = in_array($role, ['super_admin', 'fm']);
                            $canApprovalVp = in_array($role, ['super_admin', 'vp']);
                            $canUser = in_array($role, ['super_admin']);
                            $canMesin = in_array($role, ['super_admin', 'fm', 'vp', 'ppic', 'owner']);
                            $canAux = in_array($role, ['super_admin', 'aux']);
                            $canLog = in_array($role, ['super_admin', 'fm', 'vp']);
                        @endphp

                        @if ($canDashboard)
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}" class="nav-link">Dashboard
                            </a>
                        </li>
                        @endif

                        @if ($canApprovalFm || $canApprovalVp)
                            <li class="nav-item dropdown">
                                <a id="dropdownSubMenu1" href="#" data-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false" class="nav-link dropdown-toggle" title="Approval">Approval</a>
                                <ul aria-labelledby="dropdownSubMenu1" class="dropdown-menu border-0 shadow">
                                    @if ($canApprovalFm)
                                        <li>
                                            <a href="{{ route('approval.fm') }}" class="dropdown-item">
                                                Approval FM
                                                @if($pendingApprovalFM > 0)
                                                <span class="badge badge-warning float-right">{{ $pendingApprovalFM }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canApprovalVp)
                                        <li>
                                            <a href="{{ route('approval.vp') }}" class="dropdown-item">
                                                Approval VP
                                                @if($pendingApprovalVP > 0)
                                                <span class="badge badge-warning float-right">{{ $pendingApprovalVP }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif

                        @if ($canUser)
                            <li class="nav-item">
                                <a href="{{ route('user.index') }}" class="nav-link" title="User">User</a>
                            </li>
                        @endif

                        @if ($canMesin)
                            <li class="nav-item">
                                <a href="{{ route('mesin.index') }}" class="nav-link" title="Mesin">Mesin</a>
                            </li>
                        @endif

                        @if ($canAux)
                            <li class="nav-item">
                                <a href="{{ route('aux.index') }}" class="nav-link" title="Aux">Aux</a>
                            </li>
                        @endif

                        @if ($canLog)
                            <li class="nav-item">
                                <a href="{{ route('activity-log.index') }}" class="nav-link" title="Log">Log</a>
                            </li>
                        @endif
                    </ul>

                </div>

                <!-- Right navbar links -->
                <ul class="order-1 order-md-3 navbar-nav navbar-no-expand ml-auto">
                    <span id="datetime" class="nav-link"></span>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user"></i> {{ Auth::user()->nama ?? 'User' }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="{{ route('user.profile') }}">
                                <i class="fas fa-key"></i> Ganti Password
                            </a>
                            <a class="dropdown-item" href="#"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST"
                                style="display: none;">
                                @csrf
                            </form>
                        </div>
                    </li>
                    <li class="nav-item" style="display:flex; align-items:center;">
                        <a id="global-fullscreen-btn" href="#" title="Fullscreen"
                            style="margin-left:8px; display:flex; align-items:center; justify-content:center; height:40px; width:40px; border-radius:6px; transition:background 0.2s;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                                fill="currentColor" viewBox="0 0 16 16" style="display:block; margin:auto;">
                                <path
                                    d="M1 1v5h2V3h3V1H1zm14 0h-5v2h3v3h2V1zm-2 11v3h-3v2h5v-5h-2zm-9 3v-3H1v5h5v-2H3z" />
                            </svg>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <!-- /.navbar -->

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-light-primary elevation-4">
            <!-- Brand Logo -->
            <a href="{{ route('dashboard') }}" class="brand-link">
                <img src="{{ asset('images/logo.png') }}" alt="Duniatex Logo" class="brand-image"
                    style="opacity: .8">
                <span class="brand-text font-weight-bold">
                    <span style="color: #000000;">DYEING DUNIATEX</span>
                </span>
            </a>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="false">
                        @php
                            $role = Auth::user()->role ?? null;
                            $canDashboard = !in_array($role, ['aux']); // Semua role bisa akses dashboard kecuali aux
                            $canApprovalFm = in_array($role, ['super_admin', 'fm', 'owner']);
                            $canApprovalVp = in_array($role, ['super_admin', 'vp', 'owner']);
                            $canUser = in_array($role, ['super_admin', 'owner']);
                            $canMesin = in_array($role, ['super_admin', 'fm', 'vp', 'ppic', 'owner']);
                            $canAux = in_array($role, ['super_admin', 'aux', 'owner']);
                            $canLog = in_array($role, ['super_admin', 'fm', 'vp']);
                        @endphp

                        @if ($canDashboard)
                        <li class="nav-item">
                            <a href="{{ route('dashboard') }}" class="nav-link">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        @endif

                        @if ($canApprovalFm || $canApprovalVp)
                            <li class="nav-header">APPROVAL</li>
                            @if ($canApprovalFm)
                                <li class="nav-item">
                                    <a href="{{ route('approval.fm') }}" class="nav-link">
                                        <i class="nav-icon fas fa-clipboard-check"></i>
                                        <p>
                                            Approval FM
                                            @if($pendingApprovalFM > 0)
                                            <span class="badge badge-warning right">{{ $pendingApprovalFM }}</span>
                                            @endif
                                        </p>
                                    </a>
                                </li>
                            @endif
                            @if ($canApprovalVp)
                                <li class="nav-item">
                                    <a href="{{ route('approval.vp') }}" class="nav-link">
                                        <i class="nav-icon fas fa-clipboard-check"></i>
                                        <p>
                                            Approval VP
                                            @if($pendingApprovalVP > 0)
                                            <span class="badge badge-warning right">{{ $pendingApprovalVP }}</span>
                                            @endif
                                        </p>
                                    </a>
                                </li>
                            @endif
                        @endif

                        @if ($canUser)
                            <li class="nav-header">USER</li>
                            <li class="nav-item">
                                <a href="{{ route('user.index') }}" class="nav-link">
                                    <i class="nav-icon fas fa-users"></i>
                                    <p>
                                        Data User
                                    </p>
                                </a>
                            </li>
                        @endif

                        @if ($canMesin)
                            <li class="nav-header">MASTER DATA</li>
                            <li class="nav-item">
                                <a href="{{ route('mesin.index') }}" class="nav-link">
                                    <i class="nav-icon fas fa-cogs"></i>
                                    <p>
                                        Mesin
                                    </p>
                                </a>
                            </li>
                        @endif

                        @if ($canAux)
                            <li class="nav-header">KIMIA</li>
                            <li class="nav-item">
                                <a href="{{ route('aux.index') }}" class="nav-link">
                                    <i class="nav-icon fas fa-flask"></i>
                                    <p>
                                        Auxiliary
                                    </p>
                                </a>
                            </li>
                        @endif

                        @if ($canLog)
                            <li class="nav-header">LAINNYA</li>
                            <li class="nav-item">
                                <a href="{{ route('activity-log.index') }}" class="nav-link">
                                    <i class="nav-icon fas fa-clipboard-list"></i>
                                    <p>
                                        Log Activity
                                    </p>
                                </a>
                            </li>
                        @endif


                    </ul>
                </nav>
                <!-- /.sidebar-menu -->
            </div>
            <!-- /.sidebar -->
        </aside>


        <!-- Content Wrapper. Contains page content -->
        @yield('content')

        <!-- Main Footer -->
        <footer class="main-footer text-center">
            <strong>
                Copyright &copy; {{ date('Y') }} <!-- Menggunakan Blade untuk mendapatkan tahun saat ini -->
                <a href="#" target="_blank">
                    <span style="color: #e41d30;">DUNIATEX</span>
                </a>.
            </strong>
            All rights reserved.
        </footer>

    </div>
    <!-- ./wrapper -->


    <!-- jQuery -->
    <script src="{{ asset('lte/plugins/jquery/jquery.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('lte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('lte/dist/js/adminlte.min.js') }}"></script>
    <!-- AdminLTE for demo purposes -->
    <script src="{{ asset('lte/dist/js/demo.js') }}"></script>
    <!-- Select2 -->
    <script src="{{ asset('lte/plugins/select2/js/select2.full.min.js') }}"></script>

    <!-- DataTables  & Plugins -->
    <script src="{{ asset('lte/plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('lte/plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>

    <script src="{{ asset('lte/plugins/sweetalert2/sweetalert2.min.js') }}"></script>

    <script>
        // Setup CSRF Token untuk AJAX (selalu ambil dari meta tag yang fresh)
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Update CSRF token setiap kali halaman di-load atau refresh
        function updateCsrfToken() {
            // Ambil token baru dari meta tag
            const newToken = $('meta[name="csrf-token"]').attr('content');
            if (newToken) {
                // Update jQuery AJAX setup
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': newToken
                    }
                });
            }
        }

        // Update token saat document ready
        $(document).ready(function() {
            updateCsrfToken();
            
            // Initialize Select2 Elements
            $('.select2').select2();
        });

        // Update token setiap kali meta tag berubah (setelah logout/login)
        $(document).ajaxComplete(function(event, xhr, settings) {
            // Jika mendapat 419 error, refresh token dari meta tag
            if (xhr.status === 419) {
                updateCsrfToken();
            }
        });

        function updateDateTime() {
            const dateTimeElement = document.getElementById('datetime');
            if (dateTimeElement) {
                const now = new Date();
                const daysOfWeek = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const dayOfWeek = daysOfWeek[now.getDay()];
                const dayOfMonth = now.getDate().toString().padStart(2, '0');
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                const year = now.getFullYear();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');

                const dateTimeString = `${dayOfWeek}, ${dayOfMonth}-${month}-${year} | ${hours}:${minutes}:${seconds}`;
                dateTimeElement.textContent = dateTimeString;
            }
        }

        updateDateTime();

        setInterval(updateDateTime, 1000);

        $(document).ready(function() {
            function initializeDataTable(tableId) {
                $(tableId).DataTable({
                    "paging": true,
                    "responsive": false,
                    "lengthChange": true,
                    "autoWidth": false,
                    "scrollX": false,
                    "searching": true,
                    "ordering": true,
                    "info": true,
                }).buttons().container().appendTo($(tableId + '_wrapper .col-md-6:eq(0)'));
            }
            initializeDataTable('#user');
            initializeDataTable('#mesin');
            initializeDataTable('#approval_fm');
            initializeDataTable('#approval_vp');
        });

        $(document).ready(function() {
            function initializeDataTable(tableId) {
                $(tableId).DataTable({
                    "paging": true,
                    "responsive": false,
                    "lengthChange": true,
                    "autoWidth": false,
                    "scrollX": true,
                    "searching": true,
                    "ordering": true,
                    "info": true,
                }).buttons().container().appendTo($(tableId + '_wrapper .col-md-6:eq(0)'));
            }
            initializeDataTable('#auxl');
            initializeDataTable('#activity_log');
        });


        // Fullscreen global button logic
        document.addEventListener('DOMContentLoaded', function() {
            const fsBtn = document.getElementById('global-fullscreen-btn');
            if (!fsBtn) return;

            fsBtn.addEventListener('click', function(e) {
                e.preventDefault();

                if (!document.fullscreenElement) {
                    // Masuk fullscreen
                    if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen();
                    } else if (document.documentElement.webkitRequestFullscreen) {
                        document.documentElement.webkitRequestFullscreen();
                    } else if (document.documentElement.msRequestFullscreen) {
                        document.documentElement.msRequestFullscreen();
                    }
                } else {
                    // Keluar fullscreen
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen();
                    }
                }
            });

            // Event listener untuk memberi tahu semua halaman saat fullscreen berubah
            document.addEventListener('fullscreenchange', function() {
                const isFullscreen = !!document.fullscreenElement;
                window.dispatchEvent(new CustomEvent('globalFullscreenToggle', {
                    detail: isFullscreen
                }));
            });
        });
    </script>

    <script>
        @if (session('success'))
            const ToastSuccess = Swal.mixin({
                toast: true,
                position: 'top-end',
                icon: 'success',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            ToastSuccess.fire({
                title: "{{ session('success') }}"
            });
        @endif

        @if (session('error'))
            const ToastError = Swal.mixin({
                toast: true,
                position: 'top-end',
                icon: 'error',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            ToastError.fire({
                title: "{{ session('error') }}"
            });
        @endif

        @if (session('info'))
            const ToastInfo = Swal.mixin({
                toast: true,
                position: 'top-end',
                icon: 'info',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
            ToastInfo.fire({
                title: "{{ session('info') }}"
            });
        @endif

        @if ($errors->any())
            const ToastValidation = Swal.mixin({
                toast: true,
                position: 'top-end',
                icon: 'error',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
            });
            let errorMsg = `{!! implode('<br>', $errors->all()) !!}`;
            ToastValidation.fire({
                html: errorMsg
            });
        @endif
    </script>

    @yield('scripts')
</body>

</html>
