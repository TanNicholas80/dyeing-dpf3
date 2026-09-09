<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarcodeKain;
use App\Models\DetailProses;
use App\Models\Proses;

function sendPost($payload) {
    $url = 'http://127.0.0.1:8000/api/sap/approval-over-gi';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true), 'raw' => $response];
}

echo "=== TEST 1: Standardized Payload (APPROVED & REJECTED) ===\n";
// Reset barcode records first to pending
BarcodeKain::where('barcode', 'AA00001194')->update([
    'approval_status' => 'pending',
    'matdok' => null,
    'item_document' => null,
    'qty_gi' => 25.5,
    'approval_reject_reason' => null
]);
BarcodeKain::where('barcode', 'AA00001195')->update([
    'approval_status' => 'pending',
    'matdok' => null,
    'item_document' => null,
    'qty_gi' => null,
    'approval_reject_reason' => null
]);

$payload1 = [
    "AUFNR" => "066000000098",
    "items" => [
        [
            "barcode" => "AA00001194",
            "status" => "APPROVED",
            "mblnr" => "4903635006",
            "zeile" => 1,
            "menge" => 25.5,
            "message" => "Success"
        ],
        [
            "barcode" => "AA00001195",
            "status" => "REJECTED",
            "mblnr" => "0",
            "zeile" => 0,
            "menge" => 0,
            "message" => "Over limit tidak disetujui"
        ]
    ]
];

$res1 = sendPost($payload1);
echo "HTTP Code: " . $res1['code'] . "\n";
echo "Response status: " . ($res1['body']['status'] ?? 'none') . "\n";

$b1 = BarcodeKain::where('barcode', 'AA00001194')->first();
$b2 = BarcodeKain::where('barcode', 'AA00001195')->first();
echo "AA00001194 in DB: approval_status={$b1->approval_status}, matdok={$b1->matdok}, item_document={$b1->item_document}\n";
echo "AA00001195 in DB: approval_status={$b2->approval_status}, reject_reason={$b2->approval_reject_reason}\n";

echo "\n=== TEST 2: Barcode Not Found ===\n";
$payload2 = [
    "AUFNR" => "066000000098",
    "items" => [
        [
            "barcode" => "NONEXISTENT999",
            "status" => "APPROVED",
            "mblnr" => "12345"
        ]
    ]
];
$res2 = sendPost($payload2);
echo "HTTP Code: " . $res2['code'] . "\n";
echo "Item status in response: " . ($res2['body']['items'][0]['status'] ?? 'none') . " (" . ($res2['body']['items'][0]['message'] ?? '') . ")\n";

echo "\n=== TEST 3: Invalid / Empty items ===\n";
$payload3 = [
    "AUFNR" => "066000000098",
    "items" => []
];
$res3 = sendPost($payload3);
echo "HTTP Code: " . $res3['code'] . "\n";
echo "Response message: " . ($res3['body']['message'] ?? '') . "\n";

echo "\n=== ALL TESTS FINISHED ===\n";
