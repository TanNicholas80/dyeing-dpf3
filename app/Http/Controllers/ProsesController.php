<?php

namespace App\Http\Controllers;

use App\Models\Proses;
use App\Models\Mesin;
use Illuminate\Http\Request;


class ProsesController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'jenis' => 'required|in:Produksi,Maintenance,Reproses',
            'no_op' => 'required|string|max:12',
            'no_partai' => 'required|string',
            'item_op' => 'required|string',
            'kode_material' => 'required|string',
            'konstruksi' => 'required|string',
            'gramasi' => 'required|string',
            'lebar' => 'required|string',
            'hfeel' => 'required|string',
            'warna' => 'required|string',
            'kode_warna' => 'required|string',
            'kategori_warna' => 'required|string',
            'qty' => 'required|numeric',
            'cycle_time' => 'nullable', // validasi string, konversi manual
            'mesin_id' => 'required|exists:mesins,id',
        ]);

        // Konversi cycle_time dari jam:menit:detik ke detik (integer)
        if (!empty($validated['cycle_time'])) {
            $parts = explode(':', $validated['cycle_time']);
            if (count($parts) === 3) {
                $validated['cycle_time'] = ((int)$parts[0]) * 3600 + ((int)$parts[1]) * 60 + ((int)$parts[2]);
            } elseif (count($parts) === 2) {
                $validated['cycle_time'] = ((int)$parts[0]) * 3600 + ((int)$parts[1]) * 60;
            } else {
                $validated['cycle_time'] = (int)$validated['cycle_time'];
            }
        } else {
            $validated['cycle_time'] = null;
        }

        try {
            Proses::create($validated);

            // Ambil halaman asal dari referer jika ada
            $referer = $request->headers->get('referer');
            $page = 1;
            if ($referer) {
                $parsed = parse_url($referer);
                if (isset($parsed['query'])) {
                    parse_str($parsed['query'], $queryArr);
                    if (isset($queryArr['page']) && is_numeric($queryArr['page'])) {
                        $page = (int)$queryArr['page'];
                    }
                }
            }
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', 'Proses berhasil ditambahkan');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Gagal menambahkan proses: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $mesins = Mesin::select('id', 'jenis_mesin')->get();
        return response()->json([
            'mesins' => $mesins
        ]);
    }

    public function proxyOpSearch(Request $request)
    {
        $no_op = $request->input('no_op');
        if (!$no_op) {
            return response()->json(['results' => []]);
        }
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post(
                'http://18.140.227.2:8000/sap/bc/zdyes/zterima_op?sap-client=310',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfRFZEOkFxdWluYWxkbzc=',
                        'Content-Type' => 'text/plain',
                        'Accept' => 'application/json',
                    ],
                    'body' => json_encode($no_op),
                    'timeout' => 10,
                ]
            );
            $data = json_decode($response->getBody(), true);
            if (!is_array($data)) {
                return response()->json(['results' => []]);
            }
            $unique_ops = collect($data)
                ->pluck('no_op')
                ->unique()
                ->map(fn($op) => [
                    'id' => $op,
                    'text' => $op
                ])
                ->values();
            return response()->json([
                'results' => $unique_ops,
                'raw' => $data // ← untuk no partai
            ]);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }

    // Simpan barcode kain
    public function barcodeKain(Request $request, $id)
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
        ]);
        $referer = $request->headers->get('referer');
        $page = 1;
        if ($referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryArr);
                if (isset($queryArr['page']) && is_numeric($queryArr['page'])) {
                    $page = (int)$queryArr['page'];
                }
            }
        }
        try {
            $proses = Proses::findOrFail($id);
            $proses->barcode_kain = $request->barcode;
            $proses->save();
            $successMsg = 'Barcode kain berhasil disimpan!';
            if ($request->ajax()) {
                return response()->json([
                    'redirect' => route('dashboard', ['page' => $page]),
                    'message' => $successMsg,
                    'status' => 'success',
                ]);
            }
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', $successMsg);
        } catch (\Exception $e) {
            $errorMsg = 'Gagal menyimpan barcode kain: ' . $e->getMessage();
            if ($request->ajax()) {
                return response()->json([
                    'message' => $errorMsg,
                    'status' => 'error',
                ], 500);
            }
            return back()
                ->withInput()
                ->with('error', $errorMsg);
        }
    }

    // Simpan barcode la
    public function barcodeLa(Request $request, $id)
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
        ]);
        $referer = $request->headers->get('referer');
        $page = 1;
        if ($referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryArr);
                if (isset($queryArr['page']) && is_numeric($queryArr['page'])) {
                    $page = (int)$queryArr['page'];
                }
            }
        }
        try {
            $proses = Proses::findOrFail($id);
            $proses->barcode_la = $request->barcode;
            $proses->save();
            $successMsg = 'Barcode LA berhasil disimpan!';
            if ($request->ajax()) {
                return response()->json([
                    'redirect' => route('dashboard', ['page' => $page]),
                    'message' => $successMsg,
                    'status' => 'success',
                ]);
            }
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', $successMsg);
        } catch (\Exception $e) {
            $errorMsg = 'Gagal menyimpan barcode LA: ' . $e->getMessage();
            if ($request->ajax()) {
                return response()->json([
                    'message' => $errorMsg,
                    'status' => 'error',
                ], 500);
            }
            return back()
                ->withInput()
                ->with('error', $errorMsg);
        }
    }

    // Simpan barcode aux
    public function barcodeAux(Request $request, $id)
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
        ]);
        $referer = $request->headers->get('referer');
        $page = 1;
        if ($referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryArr);
                if (isset($queryArr['page']) && is_numeric($queryArr['page'])) {
                    $page = (int)$queryArr['page'];
                }
            }
        }
        try {
            $proses = Proses::findOrFail($id);
            $proses->barcode_aux = $request->barcode;
            $proses->save();
            $successMsg = 'Barcode AUX berhasil disimpan!';
            if ($request->ajax()) {
                return response()->json([
                    'redirect' => route('dashboard', ['page' => $page]),
                    'message' => $successMsg,
                    'status' => 'success',
                ]);
            }
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', $successMsg);
        } catch (\Exception $e) {
            $errorMsg = 'Gagal menyimpan barcode AUX: ' . $e->getMessage();
            if ($request->ajax()) {
                return response()->json([
                    'message' => $errorMsg,
                    'status' => 'error',
                ], 500);
            }
            return back()
                ->withInput()
                ->with('error', $errorMsg);
        }
    }
}
