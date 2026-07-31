<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Ticket - {{ $summary->barcode }}</title>
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
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 15px;
            background: #fff;
        }

        @media print {
            body {
                padding: 0;
            }

            .ticket-container {
                border: none;
                max-width: 100%;
                padding: 0;
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

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            border-bottom: 1.5px solid #000;
        }

        .detail-table th,
        .detail-table td {
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

        .text-right,
        .detail-table td.text-right {
            text-align: right !important;
        }

        .text-center,
        .detail-table td.text-center {
            text-align: center !important;
        }

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
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Cetak / Print Ticket</button>
    </div>

    <div class="ticket-container">
        <div class="barcode-section">
            <canvas id="qr-code"></canvas>
            <div class="barcode-text">{{ $summary->barcode }}</div>
        </div>

        <div class="divider"></div>

        <table class="info-grid">
            <tr>
                <td class="info-label">KODE RESEP</td>
                <td>: <span class="info-val">{{ $summary->recipe_code }}</span></td>
                <td class="info-label">MESIN</td>
                <td>: <span class="info-val">{{ $summary->machine }}</span></td>
            </tr>
            <tr>
                <td class="info-label">PRODUCT LOT</td>
                <td>: <span class="info-val">{{ $summary->product_lot }}</span></td>
                <td class="info-label">TGL / JAM TIMBANG</td>
                <td>: <span class="info-val">{{ $summary->comp_date }} {{ $summary->comp_time }}</span></td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="detail-table">
            <thead>
                <tr>
                    <th style="width: 5%;">STEP</th>
                    <th style="width: 20%;">KODE KIMIA</th>
                    <th style="width: 35%;">NAMA KIMIA (PRODUCT_NAME)</th>
                    <th style="width: 15%;" class="text-right">TARGET WT (g)</th>
                    <th style="width: 15%;" class="text-right">ACTUAL WT (g)</th>
                    <th style="width: 10%;" class="text-center">UNIT</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ticketDetails as $row)
                    <tr>
                        <td class="text-center">{{ $row->step_no }}</td>
                        <td>{{ $row->product_code }}</td>
                        <td><strong>{{ $row->product_name ?? '-' }}</strong></td>
                        <td class="text-right">{{ number_format((float) $row->target_wt, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format((float) $row->actual_wt, 2) }}</strong></td>
                        <td class="text-center">{{ $row->unit ?? 'g' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            function triggerPrint() {
                setTimeout(function () {
                    window.print();
                }, 300);
            }

            try {
                var canvas = document.getElementById('qr-code');
                var size = 130;
                new QRious({
                    element: canvas,
                    value: "{{ $summary->barcode }}",
                    size: size,
                    level: 'H'
                });

                var logo = new Image();
                logo.src = "{{ asset('images/logo.png') }}";
                logo.onload = function () {
                    var ctx = canvas.getContext('2d');
                    var logoSize = Math.floor(size * 0.22);
                    var center = (size - logoSize) / 2;
                    var padding = Math.max(2, Math.floor(logoSize * 0.15));

                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(center - padding / 2, center - padding / 2, logoSize + padding, logoSize + padding);
                    ctx.drawImage(logo, center, center, logoSize, logoSize);
                    triggerPrint();
                };
                logo.onerror = function () {
                    triggerPrint();
                };
            } catch (e) {
                console.error("QRious error:", e);
                triggerPrint();
            }
        });
    </script>
</body>

</html>