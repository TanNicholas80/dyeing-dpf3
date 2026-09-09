<?php

$payload = [
    'AUFNR' => '066000000098',
    'items' => [
        [
            'barcode' => 'AA00001194',
            'status' => 'APPROVED',
            'mblnr' => '4903635006',
            'zeile' => 1,
            'menge' => 25.5,
            'message' => 'Success'
        ],
        [
            'barcode' => 'AA00001195',
            'status' => 'REJECTED',
            'mblnr' => '0',
            'zeile' => 0,
            'menge' => 0,
            'message' => 'Over limit tidak disetujui'
        ]
    ]
];

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

echo "HTTP Code: $httpCode\n";
echo "Response:\n$response\n";
