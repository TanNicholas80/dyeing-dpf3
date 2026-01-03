@extends('layout.main')
@section('content')
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Detail Auxiliary</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('aux.index') }}">Auxiliary</a></li>
                            <li class="breadcrumb-item active">Detail Auxiliary</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <div class="d-flex justify-content-end mb-3">
                    <a href="{{ route('aux.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                </div>
                <div class="card mb-4 shadow">
                    <div class="card-header bg-primary text-white">
                        <b>Informasi Utama</b>
                    </div>
                    <div class="card-body pb-2 pt-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6 col-lg-4 mb-3"><span class="label">Barcode</span><span class="colon">:</span><span class="value badge badge-info">{{ $auxl->barcode }}</span></div>
                            <div class="col-md-6 col-lg-4 mb-3"><span class="label">Jenis</span><span class="colon">:</span><span class="value">{{ ucfirst($auxl->jenis) }}</span></div>
                            <div class="col-md-6 col-lg-4 mb-3"><span class="label">Code</span><span class="colon">:</span><span class="value">{{ $auxl->code }}</span></div>
                            <div class="col-md-6 col-lg-4 mb-3"><span class="label">Konstruksi</span><span class="colon">:</span><span class="value">{{ $auxl->konstruksi }}</span></div>
                            <div class="col-md-6 col-lg-4 mb-3"><span class="label">Customer</span><span class="colon">:</span><span class="value">{{ $auxl->customer }}</span></div>
                            <div class="col-md-6 col-lg-4 mb-3"><span class="label">Marketing</span><span class="colon">:</span><span class="value">{{ $auxl->marketing }}</span></div>
                            <div class="col-md-6 col-lg-4 mb-3"><span class="label">Date</span><span class="colon">:</span><span class="value">{{ $auxl->date }}</span></div>
                            <div class="col-md-6 col-lg-4 mb-3"><span class="label">Color</span><span class="colon">:</span><span class="value">{{ $auxl->color }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="card shadow mb-4">
                    <div class="card-header bg-secondary text-white">
                        <b>Data Detail Auxiliary</b>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:60%">Nama Auxiliary</th>
                                        <th style="width:40%">Konsentrasi (kg)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($auxl->details as $detail)
                                        <tr>
                                            <td class="align-middle">{{ $detail->auxiliary }}</td>
                                            <td class="align-middle">{{ $detail->konsentrasi }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">Tidak ada detail auxiliary.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <style>
        .card,
        .card-header,
        .card-body,
        .table,
        .row,
        .col-md-6,
        .col-lg-4 {
            margin-bottom: 0 !important;
        }

        .row.g-3> [class^='col-'] {
            margin-bottom: 1rem !important;
        }

        .card.mb-4,
        .card.shadow.mb-4 {
            margin-bottom: 1.5rem !important;
        }

        .table th,
        .table td {
            vertical-align: middle !important;
        }

        .card-body .label {
            min-width: 90px;
            display: inline-block;
            text-align: left;
            margin-right: 0.25rem;
        }

        .card-body .colon {
            display: inline-block;
            min-width: 10px;
            margin-right: 0.5rem;
            margin-left: 0.1rem;
        }

        .card-body .value {
            display: inline-block;
            min-width: 0;
        }
    </style>
    <script>
        document.title = "Detail Auxiliary";
    </script>
@endsection
