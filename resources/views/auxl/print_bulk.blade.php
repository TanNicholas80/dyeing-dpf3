<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Bulk Tickets - Auxiliary</title>
    <style>
        @page {
            size: auto;
            margin: 5mm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 10px;
            background-color: #fff;
        }

        .ticket-container {
            max-width: 800px;
            margin: 0 auto 30px auto;
            border: 1px solid #ccc;
            padding: 15px;
            background: #fff;
            page-break-after: always;
        }

        .ticket-container:last-child {
            page-break-after: auto;
            margin-bottom: 0;
        }

        @media print {
            body {
                padding: 0;
            }
            .ticket-container {
                border: none;
                max-width: 100%;
                padding: 0;
                margin-bottom: 0;
            }
            .no-print {
                display: none !important;
            }
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: normal;
            margin-top: 6px;
            margin-bottom: 6px;
        }

        .barcode-section {
            text-align: center;
            margin-bottom: 4px;
        }

        .barcode-section canvas {
            max-height: 110px;
        }

        .barcode-text {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .divider {
            border-bottom: 1.5px solid #000;
            margin: 6px 0;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .info-grid td {
            padding: 2px 4px;
            vertical-align: top;
            font-size: 12px;
        }

        .info-label {
            font-weight: normal;
            width: 110px;
        }

        .info-val {
            font-weight: bold;
        }

        .step-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: bold;
            margin: 6px 0 4px 0;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            border-bottom: 1.5px solid #000;
        }

        .detail-table th, .detail-table td {
            border-top: 1px dashed #444;
            border-bottom: 1px dashed #444;
            padding: 4px 2px;
            text-align: left;
            font-size: 11px;
        }

        .detail-table th {
            font-weight: bold;
            background-color: transparent;
            text-align: center !important;
        }

        .text-right, .detail-table td.text-right { text-align: right !important; }
        .text-center, .detail-table td.text-center { text-align: center !important; }

        .btn-print {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="no-print" style="max-width: 800px; margin: 0 auto 15px auto; text-align: right;">
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Cetak Semua Ticket</button>
    </div>

    @foreach($auxls as $auxl)
        @php
            $firstDetail = optional(optional($auxl->proses)->details)->first();
            $noOp = $auxl->no_op ?? ($firstDetail->no_op ?? '-');
            $noPartai = $auxl->no_partai ?? ($firstDetail->no_partai ?? '-');
            $customer = $auxl->customer ?? ($firstDetail->customer ?? '-');
            $material = $auxl->konstruksi ?? ($firstDetail->konstruksi ?? '-');
            $color = $auxl->color ?? ($firstDetail->warna ?? $firstDetail->color ?? '-');
            $mesin = optional(optional($auxl->proses)->mesin)->jenis_mesin ?? '-';
            $tipeLabel = ($auxl->tipe ?? 'normal') === 'addition' ? 'Type Addition' : 'Type Normal';
        @endphp

        <div class="ticket-container">
            <!-- QR Code (Centered Top) -->
            <div class="barcode-section">
                <canvas class="qr-canvas" data-barcode="{{ $auxl->barcode }}"></canvas>
                <div class="barcode-text">{{ $auxl->barcode }}</div>
            </div>

            <!-- Top Bar -->
            <div class="top-bar">
                <span>Printout {{ date('Y-m-d H:i:s') }}</span>
                <span style="font-weight: bold;">{{ $tipeLabel }}</span>
            </div>

            <div class="divider"></div>

            <!-- 2 Column Information Grid -->
            <table class="info-grid">
                <tr>
                    <td class="info-label">Batch:</td>
                    <td class="info-val" style="width: 38%;">{{ $noOp }}</td>
                    <td class="info-label">Color Name:</td>
                    <td class="info-val">{{ $color }}</td>
                </tr>
                <tr>
                    <td class="info-label">Fabric Name:</td>
                    <td class="info-val">{{ $material }}</td>
                    <td class="info-label">Order No:</td>
                    <td class="info-val">{{ $noPartai }}</td>
                </tr>
                <tr>
                    <td class="info-label">Customer Name:</td>
                    <td class="info-val">{{ $customer }}</td>
                    <td class="info-label">M/C:</td>
                    <td class="info-val">{{ $mesin }}</td>
                </tr>
                <tr>
                    <td class="info-label">Total Wt.(Kg):</td>
                    <td class="info-val">{{ number_format($auxl->total_wt, 1) }}</td>
                    <td class="info-label">Volume(Litres):</td>
                    <td class="info-val">{{ number_format($auxl->volume_litres, 1) }}</td>
                </tr>
                <tr>
                    <td class="info-label">Liquor Ratio:</td>
                    <td class="info-val" colspan="3">1 : {{ round($auxl->liquor_ratio ?? 10) }}</td>
                </tr>
            </table>

            <div class="divider"></div>

            <!-- Step Header -->
            <div class="step-header">
                <span>Step {{ $auxl->step_proses ?? 1 }}</span>
            </div>

            <!-- Detail Table Auxiliary List (Without Conc & Remark) -->
            <table class="detail-table">
                <thead>
                    <tr>
                        <th style="width: 70%;">Name</th>
                        <th style="width: 30%;">Weight</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($auxl->details as $detail)
                        <tr>
                            <td>{{ $detail->auxiliary }}</td>
                            <td class="text-right" style="text-align: right;">{{ number_format($detail->konsentrasi, 2) }} kg</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center" style="text-align: center;">Tidak ada detail auxiliary.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <!-- CDN QRious untuk render QR Code -->
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            function triggerPrint() {
                setTimeout(function() {
                    window.print();
                }, 300);
            }

            try {
                const canvases = document.querySelectorAll('.qr-canvas');
                const size = 110;
                canvases.forEach(function(canvas) {
                    let barcodeVal = canvas.getAttribute('data-barcode');
                    new QRious({
                        element: canvas,
                        value: barcodeVal,
                        size: size,
                        level: 'H'
                    });
                });

                const logo = new Image();
                logo.src = "{{ asset('images/logo.png') }}";
                logo.onload = function() {
                    const logoSize = Math.floor(size * 0.22);
                    const center = (size - logoSize) / 2;
                    const padding = Math.max(2, Math.floor(logoSize * 0.15));

                    canvases.forEach(function(canvas) {
                        const ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(center - padding / 2, center - padding / 2, logoSize + padding, logoSize + padding);
                        ctx.drawImage(logo, center, center, logoSize, logoSize);
                    });
                    triggerPrint();
                };
                logo.onerror = function() {
                    triggerPrint();
                };
            } catch(e) {
                console.error("QRious error:", e);
                triggerPrint();
            }
        });
    </script>
</body>
</html>
