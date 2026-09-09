<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarcodeKain;
use App\Models\Proses;
use App\Http\Controllers\ProsesController;
use Illuminate\Http\Request;

echo "=== TEST 1: checkBarcodeActive untuk barcode pending AA00001194 ===\n";
$c = new ProsesController();
$req1 = new Request(['barcode' => 'AA00001194']);
$res1 = $c->checkBarcodeActive($req1);
echo "Response checkBarcodeActive: " . json_encode($res1->getData()) . "\n";

echo "\n=== TEST 2: Mencoba scan/simpan barcode pending AA00001194 ke Proses lain (Proses ID 3, OP 066000000062) ===\n";
$req2 = new Request([
    'barcodes' => ['AA00001194'],
    'detail_proses_id' => 3
]);
$req2->headers->set('Accept', 'application/json');
$req2->headers->set('X-Requested-With', 'XMLHttpRequest');

$res2 = $c->barcodeKain($req2, 3);
echo "HTTP Status Code: " . $res2->getStatusCode() . "\n";
echo "Response Error: " . json_encode($res2->getData()) . "\n";

echo "\n=== TEST 3: Mencoba scan/simpan barcode pending AA00001194 ke OP lain di proses yang sama (jika ada) atau proses baru ===\n";
// Pastikan database barcode_kain di OP 06600000062 tetap bersih
$countInOp3 = BarcodeKain::where('barcode', 'AA00001194')->where('detail_proses_id', 3)->count();
echo "Jumlah barcode AA00001194 yang tersimpan di OP 066000000062: $countInOp3 (Harus 0)\n";
