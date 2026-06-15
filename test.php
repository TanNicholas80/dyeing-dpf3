<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$controller = app()->make(App\Http\Controllers\DashboardController::class);
$request = Illuminate\Http\Request::create('/dashboard/proses/1/card-html', 'GET');
$proses = \App\Models\Proses::latest()->first();
if (!$proses) { echo 'No proses found'; exit; }
try {
    $res = $controller->getProsesCardHtml($request, $proses->id);
    echo $res->getContent();
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
}
