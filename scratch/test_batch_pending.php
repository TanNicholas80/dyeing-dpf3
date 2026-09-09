<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarcodeKain;
use App\Http\Controllers\ProsesController;
use Illuminate\Http\Request;

echo "=== TEST 4: Batch scan: 1 barcode baru + 1 barcode pending ===\n";
$c = new ProsesController();
$req = new Request([
    'barcodes' => ['AA99999999', 'AA00001194'],
    'detail_proses_id' => 3
]);
$req->headers->set('Accept', 'application/json');
$req->headers->set('X-Requested-With', 'XMLHttpRequest');

$res = $c->barcodeKain($req, 3);
echo "HTTP Status Code: " . $res->getStatusCode() . "\n";
echo "Response Error: " . json_encode($res->getData()) . "\n";

// Pastikan barcode AA99999999 juga tidak ikut tersimpan karena transaksi di-reject
$exists99 = BarcodeKain::where('barcode', 'AA99999999')->exists();
echo "Apakah AA99999999 sempat tersimpan? " . ($exists99 ? 'YA (SALAH)' : 'TIDAK (BENAR, aman)') . "\n";
