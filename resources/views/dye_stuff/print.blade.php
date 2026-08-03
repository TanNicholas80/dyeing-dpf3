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

        .barcode-section {
            text-align: center;
            margin-bottom: 2px;
        }

        .barcode-section canvas {
            max-height: 80px;
        }

        .barcode-text {
            font-size: 14px;
            font-weight: normal;
            letter-spacing: 1px;
            margin-top: 1px;
        }

        .header-meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .divider {
            border-bottom: 1.5px solid #000;
            margin: 4px 0 8px 0;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .info-table td {
            padding: 2px 4px;
            vertical-align: top;
            font-size: 12px;
            line-height: 1.35;
        }

        .label-col {
            width: 125px;
            color: #000;
        }

        .val-col {
            font-weight: normal;
        }

        .recipe-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: normal;
            margin-top: 4px;
            margin-bottom: 4px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            border-bottom: 1.5px solid #000;
        }

        .detail-table th,
        .detail-table td {
            border-top: 1px dashed #666;
            border-bottom: 1px dashed #666;
            padding: 5px 4px;
            text-align: left;
            font-size: 11px;
        }

        .detail-table th {
            font-weight: normal;
            background-color: transparent;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
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
        <!-- Barcode Utama -->
        <div class="barcode-section">
            <canvas id="qr-code"></canvas>
            <div class="barcode-text">*{{ $summary->barcode }}*</div>
        </div>

        <!-- Header Metadata -->
        <div class="header-meta">
            <div>Printout {{ $summary->print_time }}</div>
            <div>Type {{ $summary->type_name }}</div>
        </div>

        <div class="divider"></div>

        <!-- Grid Informasi Tiket Fisik -->
        <table class="info-table">
            <tr>
                <td class="label-col">Batch:</td>
                <td class="val-col" style="width: 35%;">
                    {{ $summary->batch_no }}
                    @if($summary->no_jo && $summary->no_jo !== '-')
                        <span style="margin-left: 8px; color: #555;">(No JO: {{ $summary->no_jo }})</span>
                    @endif
                </td>
                <td class="label-col">Color Name:</td>
                <td class="val-col">{{ $summary->color_name }}</td>
            </tr>
            <tr>
                <td class="label-col">Fabric Name:</td>
                <td class="val-col">
                    {{ $summary->fabric_name }}
                </td>
                <td class="label-col">Order No:</td>
                <td class="val-col">
                    {{ $summary->order_no }}
                    @if($summary->order_no !== $summary->product_lot && $summary->product_lot !== '-')
                        <span style="color: #555;">({{ $summary->product_lot }})</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label-col">Customer Name:</td>
                <td class="val-col">{{ $summary->customer_name }}</td>
                <td class="label-col">M/C:</td>
                <td class="val-col">{{ $summary->machine }}</td>
            </tr>
            <tr>
                <td class="label-col">Total Wt.(Kg):</td>
                <td class="val-col">{{ number_format((float) $summary->total_wt_kg, 1) }}</td>
                <td class="label-col">Volume(Litres):</td>
                <td class="val-col">{{ number_format((float) $summary->volume, 1) }}</td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- Sub-header Resep & Liquor Ratio -->
        <div class="recipe-header">
            <div>Step 1 &nbsp; Recipe Code: {{ $summary->recipe_code }}</div>
            <div>Liquor Ratio = 1 : {{ $summary->lr }}</div>
        </div>

        <!-- Tabel Detail Kimia / Dye Stuff -->
        <table class="detail-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Code</th>
                    <th style="width: 40%;">Name</th>
                    <th style="width: 15%;" class="text-right">Conc.</th>
                    <th style="width: 8%;"></th>
                    <th style="width: 12%;" class="text-right">Weight</th>
                    <th style="width: 5%;"></th>
                    <th style="width: 5%;">Remark</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ticketDetails as $row)
                    <tr>
                        <td>{{ $row->product_code }}</td>
                        <td>{{ $row->product_name ?? '-' }}</td>
                        <td class="text-right">{{ number_format((float) ($row->conc ?? 0), 5) }}</td>
                        <td class="text-center">{{ $row->conc_unit ?? '%' }}</td>
                        <td class="text-right">{{ number_format((float) ($row->target_wt ?? $row->actual_wt), 2) }}</td>
                        <td class="text-left">{{ $row->unit ?? 'g' }}</td>
                        <td>{{ $row->remark ?? '' }}</td>
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
                var size = 110;
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