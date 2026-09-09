<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BarcodeKain;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Authenticate as Super Admin
$user = User::where('role', 'super_admin')->first() ?: User::first();
Auth::login($user);

$bRejected = BarcodeKain::where('barcode', 'AA00001195')->first();
echo "Testing cancel for rejected barcode ID: {$bRejected->id} (status: {$bRejected->approval_status})\n";

$c = new App\Http\Controllers\ProsesController();
$req = new Illuminate\Http\Request();
$response = $c->cancelBarcode($req, 30, 'kain', $bRejected->id);
$result = $response->getData();

echo "Cancel Response: " . json_encode($result) . "\n";

$bRejected->refresh();
echo "After cancel - cancel field: " . ($bRejected->cancel ? 'TRUE (1)' : 'FALSE (0)') . "\n";
