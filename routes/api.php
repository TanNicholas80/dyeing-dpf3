<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\ApiCheckStatusBarcodeController;

// Endpoint untuk menerima data berat dari timbangan (POST) dan juga bisa GET data terakhir
Route::match(['get', 'post'], '/weight', function(Request $request) {
    if ($request->isMethod('post')) {
        // Simpan berat ke cache
        Cache::put('latest_weight', [
            'weight' => $request->weight,
            'device' => $request->device,
            'time' => $request->time,
        ], 60);
        return response()->json(['success' => true]);
    }
    // GET: ambil berat terakhir
    $data = Cache::get('latest_weight');
    return response()->json([
        'weight' => $data['weight'] ?? null,
        'device' => $data['device'] ?? null,
        'time' => $data['time'] ?? null,
    ]);
});

/**
 * IoT Arduino/ESP endpoints
 * Base path: /api/iot/...
 *
 * Header (opsional tapi direkomendasikan):
 * - X-DEVICE-TOKEN: <IOT_DEVICE_TOKEN>
 */
Route::prefix('iot')->group(function () {
    // Arduino kirim status PLC ON/OFF
    Route::post('/mesin/{mesin}/state', [ApiCheckStatusBarcodeController::class, 'updateMesinState']);

    // Arduino polling status alarm (ON/OFF) berdasarkan kelengkapan barcode
    Route::get('/mesin/{mesin}/alarm', [ApiCheckStatusBarcodeController::class, 'getAlarmStatus']);
});
