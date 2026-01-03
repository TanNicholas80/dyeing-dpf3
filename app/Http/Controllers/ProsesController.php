<?php

namespace App\Http\Controllers;

use App\Models\Proses;
use App\Models\Mesin;
use App\Models\BarcodeKain;
use App\Models\BarcodeLa;
use App\Models\BarcodeAux;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class ProsesController extends Controller
{
    /**
     * Cek apakah sudah ada pending approval untuk proses tertentu.
     * 
     * @param int $prosesId
     * @return bool
     */
    private function hasPendingApproval($prosesId)
    {
        return Approval::where('proses_id', $prosesId)
            ->where('status', 'pending')
            ->where('type', 'FM')
            ->whereIn('action', ['edit_cycle_time', 'delete_proses', 'move_machine'])
            ->exists();
    }

    /**
     * Ambil informasi pending approval untuk ditampilkan di pesan error.
     * 
     * @param int $prosesId
     * @return Approval|null
     */
    private function getPendingApproval($prosesId)
    {
        return Approval::where('proses_id', $prosesId)
            ->where('status', 'pending')
            ->where('type', 'FM')
            ->whereIn('action', ['edit_cycle_time', 'delete_proses', 'move_machine'])
            ->first();
    }

    public function store(Request $request)
    {
        $rules = [
            'jenis' => 'required|in:Produksi,Maintenance,Reproses',
            'mesin_id' => 'required|exists:mesins,id',
            'cycle_time' => 'required',
        ];

        if ($request->jenis !== 'Maintenance') {
            $rules += [
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
                'roll' => 'required|integer',
            ];
        } else {
            $rules += [
                'no_op' => 'nullable|string|max:12',
                'no_partai' => 'nullable|string',
                'item_op' => 'nullable|string',
                'kode_material' => 'nullable|string',
                'konstruksi' => 'nullable|string',
                'gramasi' => 'nullable|string',
                'lebar' => 'nullable|string',
                'hfeel' => 'nullable|string',
                'warna' => 'nullable|string',
                'kode_warna' => 'nullable|string',
                'kategori_warna' => 'nullable|string',
                'qty' => 'nullable|numeric',
                'roll' => 'nullable|integer',
            ];
        }

        $validated = $request->validate($rules);

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

        // Ambil halaman asal dari referer jika ada (untuk redirect konsisten)
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
            // Buat Proses seperti biasa
            $proses = Proses::create($validated);

            // Jika jenis = Reproses, buat approval ke VP (proses langsung tampil dengan background kuning)
            if ($proses->jenis === 'Reproses') {
                Approval::create([
                    'proses_id'    => $proses->id,
                    'status'       => 'pending',
                    'type'         => 'VP',
                    'action'       => 'create_reprocess',
                    'history_data' => [
                        'proses_snapshot' => $proses->toArray(),
                    ],
                    'note'         => null,
                    'requested_by' => Auth::id(),
                    'approved_by'  => null, // Akan diisi saat VP approve/reject
                ]);

                return redirect()->route('dashboard', ['page' => $page])
                    ->with('success', 'Proses Reproses berhasil ditambahkan. Proses akan tampil dengan warna kuning dan menunggu persetujuan VP untuk dapat diubah/dihapus/dipindah.');
            }

            // Produksi / Maintenance langsung tampil
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
        try {
            $proses = Proses::findOrFail($id);
            $barcode = $request->barcode;
            $no_op = $proses->no_op;
            $no_partai = $proses->no_partai;
            $mesin_id = $proses->mesin_id;
            $payload = $barcode . ';' . $no_op;
            $client = new \GuzzleHttp\Client();
            $body = json_encode($payload);
            $response = $client->post(
                'http://18.140.227.2:8000/sap/bc/zdyes/zterima_data?sap-client=310',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfRFZEOkFxdWluYWxkbzc=',
                        'Content-Type' => 'text/plain',
                        'Accept' => 'application/json',
                    ],
                    'body' => $body,
                    'timeout' => 10,
                ]
            );
            $data = json_decode($response->getBody(), true);
            if (!is_array($data) || !isset($data[0]['stats'])) {
                $errorMsg = 'Gagal validasi barcode: response tidak valid';
                return back()->withInput()->with('error', $errorMsg);
            }
            if ($data[0]['stats'] !== 'success') {
                $errorMsg = $data[0]['stats'] ?: 'Barcode tidak dapat digunakan';
                return back()->withInput()->with('error', $errorMsg);
            }
            // Sukses, simpan ke tabel barcode_kain
            \App\Models\BarcodeKain::create([
                'proses_id' => $proses->id,
                'no_op' => $no_op,
                'no_partai' => $no_partai,
                'barcode' => $barcode,
                'matdok' => $data[0]['mblnr'] ?? null,
                'mesin_id' => $mesin_id,
                'cancel' => false,
            ]);
            return redirect()->route('dashboard')->with('success', 'Barcode kain berhasil disimpan!');
        } catch (\Exception $e) {
            $errorMsg = 'Gagal menyimpan barcode kain: ' . $e->getMessage();
            return back()->withInput()->with('error', $errorMsg);
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
            $barcode = $request->barcode;
            $no_op = $proses->no_op;
            $no_partai = $proses->no_partai;
            $mesin_id = $proses->mesin_id;
            // Ambil data dari SQL Server
            $tickets = DB::connection('sqlsrv')
                ->table('TICKET_DETAIL')
                ->where('ID_NO', $barcode)
                ->select('PRODUCT_CODE', 'ACTUAL_WT')
                ->get();
            if ($tickets->isEmpty()) {
                $errorMsg = 'Barcode tidak ditemukan di database TICKET_DETAIL';
                return back()->withInput()->with('error', $errorMsg);
            }
            // Format body untuk API
            $details = $tickets->map(function($row) {
                return $row->PRODUCT_CODE . '/' . ($row->ACTUAL_WT ?? 0);
            })->implode('|');
            $body = '"' . $no_op . ';' . $details . '"';
            // Kirim ke API
            $client = new \GuzzleHttp\Client();
            $response = $client->post(
                'http://18.140.227.2:8000/sap/bc/zdyes/zterima_kimia?sap-client=310',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfRFZEOkFxdWluYWxkbzc=',
                        'Content-Type' => 'text/plain',
                        'Accept' => 'application/json',
                    ],
                    'body' => $body,
                    'timeout' => 10,
                ]
            );
            $rawResponse = $response->getBody()->getContents();
            Log::info('API Response for barcode LA:', ['body' => $body, 'response' => $rawResponse]);
            $data = json_decode($rawResponse, true);
            if (empty($data)) {
                $errorMsg = 'Barcode atau detail kimia tidak dikenali oleh sistem SAP (API response kosong)';
                return back()->withInput()->with('error', $errorMsg);
            }
            if (!is_array($data) || !isset($data[0]['stats'])) {
                $errorMsg = 'Gagal validasi barcode: response tidak valid';
                return back()->withInput()->with('error', $errorMsg);
            }
            if ($data[0]['stats'] !== 'success') {
                $errorMsg = $data[0]['stats'] ?: 'Barcode tidak dapat digunakan';
                return back()->withInput()->with('error', $errorMsg);
            }
            // Sukses, simpan ke tabel barcode_la
            \App\Models\BarcodeLa::create([
                'proses_id' => $proses->id,
                'no_op' => $no_op,
                'no_partai' => $no_partai,
                'barcode' => $barcode,
                'matdok' => $data[0]['mblnr'] ?? null,
                'mesin_id' => $mesin_id,
                'cancel' => false,
            ]);
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', 'Barcode LA berhasil disimpan!');
        } catch (\Exception $e) {
            $errorMsg = 'Gagal menyimpan barcode LA: ' . $e->getMessage();
            return back()->withInput()->with('error', $errorMsg);
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

    // Endpoint untuk ambil semua barcode berdasarkan proses_id
    public function barcodes($id)
    {
        $proses = Proses::findOrFail($id);
        // Pastikan field matdok selalu ada di response
        $barcodeKain = $proses->barcodeKains()->get()->map(function($b) {
            $b->matdok = $b->matdok ?? '';
            return $b;
        });
        $barcodeLa = $proses->barcodeLas()->get()->map(function($b) {
            $b->matdok = $b->matdok ?? '';
            return $b;
        });
        $barcodeAux = $proses->barcodeAuxs()->get()->map(function($b) {
            $b->matdok = $b->matdok ?? '';
            return $b;
        });
        return response()->json([
            'barcode_kain' => $barcodeKain,
            'barcode_la' => $barcodeLa,
            'barcode_aux' => $barcodeAux,
        ]);
    }

    // Cancel barcode (relay ke SAP API, update flag cancel)
    public function cancelBarcode(Request $request, $proses, $type, $barcode)
    {
        $matdok = $request->input('matdok');
        // Jika matdok kosong/null, ambil dari database barcode
        if (!$matdok) {
            if ($type === 'kain') {
                $barcodeObj = \App\Models\BarcodeKain::find($barcode);
            } elseif ($type === 'la') {
                $barcodeObj = \App\Models\BarcodeLa::find($barcode);
            } elseif ($type === 'aux') {
                $barcodeObj = \App\Models\BarcodeAux::find($barcode);
            } else {
                Log::error('Cancel barcode gagal: tipe barcode tidak valid', compact('type', 'barcode'));
                return response()->json(['status' => 'error', 'message' => 'Tipe barcode tidak valid'], 400);
            }
            if ($barcodeObj && $barcodeObj->matdok) {
                $matdok = $barcodeObj->matdok;
            }
        }
        if (!$matdok) {
            Log::error('Cancel barcode gagal: matdok tidak tersedia', compact('type', 'barcode'));
            return response()->json(['status' => 'error', 'message' => 'Material document tidak tersedia'], 400);
        }
        try {
            $client = new \GuzzleHttp\Client();
            $body = '"' . $matdok . '"';
            Log::info('SAP Cancel Request', [
                'url' => 'http://18.140.227.2:8000/sap/bc/zdyes/zterima_cancel?sap-client=310',
                'headers' => [
                    'Authorization' => 'Basic RFRfRFZEOkFxdWluYWxkbzc=',
                    'Content-Type' => 'text/plain',
                    'Accept' => 'application/json',
                ],
                'body' => $body,
            ]);
            $response = $client->post(
                'http://18.140.227.2:8000/sap/bc/zdyes/zterima_cancel?sap-client=310',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfRFZEOkFxdWluYWxkbzc=',
                        'Content-Type' => 'text/plain',
                        'Accept' => 'application/json',
                    ],
                    'body' => $body,
                    'timeout' => 10,
                ]
            );
            $rawResponse = $response->getBody()->getContents();
            Log::info('SAP Cancel Raw Response', ['response' => $rawResponse]);
            $data = json_decode($rawResponse, true);
            $stats = isset($data[0]['stats']) ? $data[0]['stats'] : null;
            if (empty($data)) {
                Log::error('Cancel barcode gagal: SAP API response kosong', ['matdok' => $matdok, 'response' => $data]);
                return response()->json(['status' => 'error', 'message' => 'Material document tidak ditemukan di SAP atau sudah dicancel.'], 400);
            }
            if ($stats === 'Success') {
                if (!isset($barcodeObj)) {
                    if ($type === 'kain') {
                        $barcodeObj = \App\Models\BarcodeKain::find($barcode);
                    } elseif ($type === 'la') {
                        $barcodeObj = \App\Models\BarcodeLa::find($barcode);
                    } elseif ($type === 'aux') {
                        $barcodeObj = \App\Models\BarcodeAux::find($barcode);
                    }
                }
                if ($barcodeObj) {
                    $barcodeObj->cancel = true;
                    $barcodeObj->save();
                    return response()->json(['status' => 'success']);
                } else {
                    Log::error('Cancel barcode gagal: barcode tidak ditemukan', compact('type', 'barcode'));
                    return response()->json(['status' => 'error', 'message' => 'Barcode tidak ditemukan'], 404);
                }
            } else {
                Log::error('Cancel barcode gagal: SAP API response', ['matdok' => $matdok, 'response' => $data]);
                return response()->json(['status' => 'error', 'message' => $stats ?: 'Cancel gagal'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Cancel barcode gagal: Exception', ['matdok' => $matdok, 'error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Gagal request ke SAP API: ' . $e->getMessage()], 500);
        }
    }

    // Edit Proses
    public function update(Request $request, $id)
    {
        // Hanya izinkan perubahan cycle_time, simpan sebagai request approval FM
        $request->validate([
            'cycle_time' => 'required|string',
        ]);

        $proses = Proses::findOrFail($id);

        // Validasi: hanya bisa edit jika proses belum mulai (mulai masih null)
        if ($proses->mulai !== null) {
            $errorMessage = 'Tidak dapat mengubah cycle time. Proses sudah dimulai.';
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 400);
            }
            
            return back()->with('error', $errorMessage);
        }

        // Konversi cycle_time input (HH:MM:SS) ke detik, sama seperti di store()
        $inputCycleTime = $request->input('cycle_time');
        $parts = explode(':', $inputCycleTime);
        if (count($parts) === 3) {
            $newCycleTime = ((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60 + ((int) $parts[2]);
        } elseif (count($parts) === 2) {
            $newCycleTime = ((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60;
        } else {
            $newCycleTime = (int) $inputCycleTime;
        }

        // Jika tidak ada perubahan nilai, langsung kembali
        if ($proses->cycle_time == $newCycleTime) {
            return back()->with('info', 'Cycle time tidak berubah, tidak ada permintaan approval yang dibuat.');
        }

        // Cek apakah sudah ada pending approval untuk proses ini
        if ($this->hasPendingApproval($proses->id)) {
            $pendingApproval = $this->getPendingApproval($proses->id);
            $actionLabels = [
                'edit_cycle_time' => 'perubahan cycle time',
                'delete_proses' => 'penghapusan proses',
                'move_machine' => 'pemindahan mesin',
            ];
            $actionLabel = $actionLabels[$pendingApproval->action] ?? 'perubahan';
            
            return back()
                ->with('error', "Tidak dapat mengajukan permintaan baru. Masih ada permintaan {$actionLabel} yang menunggu persetujuan FM.");
        }

        // Ambil halaman asal dari referer jika ada (supaya UX konsisten)
        $referer = $request->headers->get('referer');
        $page = 1;
        if ($referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryArr);
                if (isset($queryArr['page']) && is_numeric($queryArr['page'])) {
                    $page = (int) $queryArr['page'];
                }
            }
        }

        // Buat record approval untuk FM (edit cycle time)
        Approval::create([
            'proses_id'    => $proses->id,
            'status'       => 'pending',
            'type'         => 'FM',
            'action'       => 'edit_cycle_time',
            'history_data' => [
                'old_cycle_time' => $proses->cycle_time,
                'new_cycle_time' => $newCycleTime,
                'input_format'   => $inputCycleTime,
            ],
            'note'         => null,
            'requested_by' => Auth::id(),
            'approved_by'  => null, // Akan diisi saat FM approve/reject
        ]);

        return redirect()->route('dashboard', ['page' => $page])
            ->with('success', 'Permintaan perubahan cycle time telah dikirim dan menunggu persetujuan FM.');
    }
    
    // Pindah proses ke mesin lain
    public function move(Request $request, $id)
    {
        $proses = Proses::findOrFail($id);
        
        // Validasi: hanya bisa pindah jika proses belum mulai (mulai masih null)
        if ($proses->mulai !== null) {
            $errorMessage = 'Tidak dapat memindahkan proses. Proses sudah dimulai.';
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 400);
            }
            
            return back()->with('error', $errorMessage);
        }
        
        $currentMesinId = $proses->mesin_id;

        // Validasi mesin_id baru dengan custom rule untuk memastikan berbeda dari mesin saat ini
        $request->validate([
            'mesin_id' => [
                'required',
                'exists:mesins,id',
                function ($attribute, $value, $fail) use ($currentMesinId) {
                    $newMesinId = (int) $value;
                    if ($newMesinId == $currentMesinId) {
                        $fail('Mesin tujuan harus berbeda dari mesin saat ini.');
                    }
                },
            ],
        ]);

        $newMesinId = (int) $request->input('mesin_id');

        // Cek apakah sudah ada pending approval untuk proses ini
        if ($this->hasPendingApproval($proses->id)) {
            $pendingApproval = $this->getPendingApproval($proses->id);
            $actionLabels = [
                'edit_cycle_time' => 'perubahan cycle time',
                'delete_proses' => 'penghapusan proses',
                'move_machine' => 'pemindahan mesin',
            ];
            $actionLabel = $actionLabels[$pendingApproval->action] ?? 'perubahan';
            
            $errorMessage = "Tidak dapat mengajukan permintaan baru. Masih ada permintaan {$actionLabel} yang menunggu persetujuan FM.";
            
            // Jika request adalah AJAX, kembalikan JSON response
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 400);
            }
            
            return back()->with('error', $errorMessage);
        }

        // Ambil halaman asal dari referer jika ada (supaya UX konsisten)
        $referer = $request->headers->get('referer');
        $page = 1;
        if ($referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryArr);
                if (isset($queryArr['page']) && is_numeric($queryArr['page'])) {
                    $page = (int) $queryArr['page'];
                }
            }
        }

        // Buat record approval untuk FM (move machine)
        Approval::create([
            'proses_id'    => $proses->id,
            'status'       => 'pending',
            'type'         => 'FM',
            'action'       => 'move_machine',
            'history_data' => [
                'old_mesin_id' => $proses->mesin_id,
                'new_mesin_id' => $newMesinId,
            ],
            'note'         => null,
            'requested_by' => Auth::id(),
            'approved_by'  => null, // Akan diisi saat FM approve/reject
        ]);

        $successMessage = 'Permintaan pemindahan mesin telah dikirim dan menunggu persetujuan FM.';
        
        // Jika request adalah AJAX, kembalikan JSON response
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMessage,
                'redirect' => route('dashboard', ['page' => $page])
            ]);
        }

        return redirect()->route('dashboard', ['page' => $page])
            ->with('success', $successMessage);
    }
    
    // Hapus Proses
    public function destroy(Request $request, $id)
    {
        $proses = Proses::findOrFail($id);

        // Validasi: hanya bisa hapus jika proses belum mulai (mulai masih null)
        if ($proses->mulai !== null) {
            return back()
                ->with('error', 'Tidak dapat menghapus proses. Proses sudah dimulai.');
        }

        // Cek apakah sudah ada pending approval untuk proses ini
        if ($this->hasPendingApproval($proses->id)) {
            $pendingApproval = $this->getPendingApproval($proses->id);
            $actionLabels = [
                'edit_cycle_time' => 'perubahan cycle time',
                'delete_proses' => 'penghapusan proses',
                'move_machine' => 'pemindahan mesin',
            ];
            $actionLabel = $actionLabels[$pendingApproval->action] ?? 'perubahan';
            
            return back()
                ->with('error', "Tidak dapat mengajukan permintaan baru. Masih ada permintaan {$actionLabel} yang menunggu persetujuan FM.");
        }

        // Ambil halaman asal dari referer jika ada
        $referer = $request->headers->get('referer');
        $page = 1;
        if ($referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryArr);
                if (isset($queryArr['page']) && is_numeric($queryArr['page'])) {
                    $page = (int) $queryArr['page'];
                }
            }
        }

        // Buat record approval untuk FM (delete proses), belum menghapus data
        Approval::create([
            'proses_id'    => $proses->id,
            'status'       => 'pending',
            'type'         => 'FM',
            'action'       => 'delete_proses',
            'history_data' => [
                'proses_snapshot' => $proses->toArray(),
            ],
            'note'         => null,
            'requested_by' => Auth::id(),
            'approved_by'  => null, // Akan diisi saat FM approve/reject
        ]);

        return redirect()->route('dashboard', ['page' => $page])
            ->with('success', 'Permintaan penghapusan proses telah dikirim dan menunggu persetujuan FM.');
    }
}
