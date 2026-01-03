<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

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
