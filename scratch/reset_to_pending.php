<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarcodeKain;
use App\Models\Proses;
use App\Services\ProsesStatusService;
use App\Events\BarcodeStatusUpdated;

BarcodeKain::where('barcode', 'AA00001194')->update([
    'approval_status' => 'pending',
    'cancel' => 0,
    'matdok' => null,
    'item_document' => null,
    'qty_gi' => 25.5,
    'approval_reject_reason' => null
]);

BarcodeKain::where('barcode', 'AA00001195')->update([
    'approval_status' => 'pending',
    'cancel' => 0,
    'matdok' => null,
    'item_document' => null,
    'qty_gi' => null,
    'approval_reject_reason' => null
]);

// Broadcast update to sync frontend back to pending
$p = Proses::with(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs'])->find(30);
if ($p) {
    $service = new ProsesStatusService();
    $statusData = $service->generateProsesStatus($p, $service->getAffectedProsesIds());
    event(new BarcodeStatusUpdated($p->id, $statusData));
}

echo "Reset completed: AA00001194 and AA00001195 are back to pending.\n";
