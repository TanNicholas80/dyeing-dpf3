<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = new App\Http\Controllers\ProsesController();
$req = new Illuminate\Http\Request();
$res = $c->barcodes($req, 30);
$data = $res->getData();

echo "Barcode Kains in response:\n";
foreach ($data->barcode_kain as $bk) {
    echo "ID: {$bk->id} | Barcode: {$bk->barcode} | Status: {$bk->approval_status} | Matdok: {$bk->matdok} | Cancel: {$bk->cancel}\n";
}

echo "\nAll Barcode Kain Progress:\n";
print_r($data->all_barcode_kain_progress);
