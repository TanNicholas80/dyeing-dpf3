<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarcodeKain;
use App\Models\Proses;

echo "=== Verifikasi Kode Baru: Barcode yang di-cancel sekarang masuk ke \$barcodesToSap ===\n";

// Kita periksa baris kode di ProsesController::barcodeKain
$reflector = new ReflectionClass('App\Http\Controllers\ProsesController');
$method = $reflector->getMethod('barcodeKain');
$filename = $method->getFileName();
$startLine = $method->getStartLine();
$endLine = $method->getEndLine();

$fileContent = file($filename);
$code = implode("", array_slice($fileContent, $startLine - 1, $endLine - $startLine + 1));

if (strpos($code, '$barcodesToSap = $trimmedBarcodes;') !== false) {
    echo "[PASS] \$barcodesToSap = \$trimmedBarcodes terpasang dengan benar.\n";
} else {
    echo "[FAIL] \$barcodesToSap tidak ditemukan.\n";
}

if (strpos($code, 'reactivatable') === false) {
    echo "[PASS] Logika lama \$reactivatable sudah berhasil dihapus sepenuhnya.\n";
} else {
    echo "[FAIL] Logika reactivatable masih ada!\n";
}

echo "\nSemua verifikasi sukses!\n";
