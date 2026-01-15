<?php

namespace App\Http\Controllers;

use App\Models\Proses;
use App\Models\DetailProses;
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
            ->whereIn('action', ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position'])
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
            ->whereIn('action', ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position'])
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
                'jenis_op' => 'required|in:Single,Multiple',
                // Default: minimal 1 detail OP
                // Akan dioverride menjadi min:2 jika jenis_op = Multiple
                'details' => 'required|array|min:1',
                'details.*.no_op' => 'required|string|max:12',
                'details.*.no_partai' => 'required|string',
                'details.*.item_op' => 'required|string',
                'details.*.kode_material' => 'required|string',
                'details.*.konstruksi' => 'required|string',
                'details.*.gramasi' => 'required|string',
                'details.*.lebar' => 'required|string',
                'details.*.hfeel' => 'required|string',
                'details.*.warna' => 'required|string',
                'details.*.kode_warna' => 'required|string',
                'details.*.kategori_warna' => 'required|string',
                'details.*.qty' => 'required|numeric',
                'details.*.roll' => 'required|integer',
            ];

            // Jika jenis_op = Multiple, wajib minimal 2 OP
            if ($request->input('jenis_op') === 'Multiple') {
                $rules['details'] = 'required|array|min:2';
            }
        } else {
            $rules += [
                'jenis_op' => 'nullable|in:Single,Multiple',
                'details' => 'nullable|array',
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
            // Set order untuk proses baru (order = max order + 1 untuk proses pending di mesin yang sama)
            $maxOrder = Proses::where('mesin_id', $validated['mesin_id'])
                ->whereNull('mulai')
                ->whereNull('selesai')
                ->max('order') ?? 0;

            $validated['order'] = $maxOrder + 1;

            // Buat Proses (hanya field yang ada di model Proses)
            $prosesData = [
                'jenis' => $validated['jenis'],
                'jenis_op' => $validated['jenis_op'] ?? null,
                'cycle_time' => $validated['cycle_time'],
                'mesin_id' => $validated['mesin_id'],
                'order' => $validated['order'],
            ];
            $proses = Proses::create($prosesData);

            // Buat DetailProses dengan data OP (jika bukan Maintenance)
            if ($validated['jenis'] !== 'Maintenance' && isset($validated['details']) && is_array($validated['details'])) {
                foreach ($validated['details'] as $detail) {
                    $detailData = [
                        'proses_id' => $proses->id,
                        'no_op' => $detail['no_op'] ?? null,
                        'item_op' => $detail['item_op'] ?? null,
                        'kode_material' => $detail['kode_material'] ?? null,
                        'konstruksi' => $detail['konstruksi'] ?? null,
                        'no_partai' => $detail['no_partai'] ?? null,
                        'gramasi' => $detail['gramasi'] ?? null,
                        'lebar' => $detail['lebar'] ?? null,
                        'hfeel' => $detail['hfeel'] ?? null,
                        'warna' => $detail['warna'] ?? null,
                        'kode_warna' => $detail['kode_warna'] ?? null,
                        'kategori_warna' => $detail['kategori_warna'] ?? null,
                        'qty' => $detail['qty'] ?? null,
                        'roll' => $detail['roll'] ?? null,
                    ];
                    DetailProses::create($detailData);
                }
            }

            // Jika jenis = Reproses, buat approval ke FM terlebih dahulu (2 tahap: FM dulu, baru VP)
            if ($proses->jenis === 'Reproses') {
                // Ambil semua DetailProses untuk snapshot
                $detailProsesList = DetailProses::where('proses_id', $proses->id)->get();
                $detailProsesSnapshots = $detailProsesList->map(function ($detail) {
                    return $detail->toArray();
                })->toArray();
                
                Approval::create([
                    'proses_id'    => $proses->id,
                    'status'       => 'pending',
                    'type'         => 'FM',
                    'action'       => 'create_reprocess',
                    'history_data' => [
                        'proses_snapshot' => $proses->toArray(),
                        'detail_proses_snapshot' => $detailProsesSnapshots, // Snapshot DetailProses untuk history lengkap
                    ],
                    'note'         => null,
                    'requested_by' => Auth::id(),
                    'approved_by'  => null, // Akan diisi saat FM approve/reject
                ]);

                return redirect()->route('dashboard', ['page' => $page])
                    ->with('success', 'Proses Reproses berhasil ditambahkan. Proses akan tampil dengan warna kuning dan menunggu persetujuan FM terlebih dahulu, kemudian VP.');
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
            'barcode' => 'required|string|max:255|unique:barcode_kain,barcode',
            'detail_proses_id' => 'nullable|exists:detail_proses,id',
        ]);
        try {
            $proses = Proses::findOrFail($id);
            $barcode = $request->barcode;
            // Potong barcode agar maksimal 10 digit
            $barcode = substr($barcode, 0, 10);
            Log::info('BarcodeKain: Barcode received and trimmed', ['original' => $request->barcode, 'trimmed' => $barcode, 'proses_id' => $id]);
            
            // Cari DetailProses yang sesuai
            $detailProses = null;
            if ($request->has('detail_proses_id') && $request->detail_proses_id) {
                $detailProses = DetailProses::where('id', $request->detail_proses_id)
                    ->where('proses_id', $id)
                    ->firstOrFail();
            } else {
                // Jika tidak ada detail_proses_id, ambil DetailProses pertama dari proses ini
                $detailProses = DetailProses::where('proses_id', $id)->first();
                if (!$detailProses) {
                    return redirect()->route('dashboard')->with('error', 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.');
                }
            }
            
            $no_op = $detailProses->no_op;
            $no_partai = $detailProses->no_partai;
            $mesin_id = $proses->mesin_id;
            $item_op = $detailProses->item_op;
            $payload = $barcode . '-' . $item_op . ';' . $no_op;
            Log::info('BarcodeKain: Payload prepared', ['payload' => $payload]);
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
            Log::info('BarcodeKain: API Response', ['response' => $data]);
            if (!is_array($data) || !isset($data[0]['stats'])) {
                $errorMsg = 'Gagal validasi barcode: response tidak valid';
                Log::error('BarcodeKain: Invalid response', ['data' => $data]);
                return back()->withInput()->with('error', $errorMsg);
            }
            if ($data[0]['stats'] !== 'success') {
                $errorMsg = $data[0]['stats'] ?: 'Barcode tidak dapat digunakan';
                Log::error('BarcodeKain: API stats not success', ['stats' => $data[0]['stats']]);
                return redirect()->route('dashboard')->with('error', $errorMsg);
            }
            // Sukses, simpan ke tabel barcode_kain
            \App\Models\BarcodeKain::create([
                'detail_proses_id' => $detailProses->id,
                'no_op' => $no_op,
                'no_partai' => $no_partai,
                'barcode' => $barcode,
                'matdok' => $data[0]['mblnr'] ?? null,
                'mesin_id' => $mesin_id,
                'cancel' => false,
            ]);
            Log::info('BarcodeKain: Successfully saved to database', ['barcode' => $barcode, 'proses_id' => $proses->id]);
            return redirect()->route('dashboard')->with('success', 'Barcode kain berhasil disimpan!');
        } catch (\Exception $e) {
            Log::error('BarcodeKain: Exception occurred', ['error' => $e->getMessage(), 'proses_id' => $id]);
            $errorMsg = 'Gagal menyimpan barcode kain: ' . $e->getMessage();
            return redirect()->route('dashboard')->with('error', $errorMsg);
        }
    }
    // Simpan barcode la
    public function barcodeLa(Request $request, $id)
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
            'detail_proses_id' => 'nullable|exists:detail_proses,id',
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
            Log::info('BarcodeLa: Barcode received', ['barcode' => $barcode, 'proses_id' => $id]);

            // Cek global: jika barcode sudah pernah dipakai di mana pun (proses lain
            // atau proses yang sama), jangan izinkan dipakai lagi.
            if (BarcodeLa::where('barcode', $barcode)->where('cancel', false)->exists()) {
                return redirect()->route('dashboard', ['page' => $page])
                    ->with('error', 'Barcode LA ini sudah pernah digunakan dan tidak dapat dipakai lagi.');
            }
            
            // Cari DetailProses yang sesuai
            $detailProses = null;
            if ($request->has('detail_proses_id') && $request->detail_proses_id) {
                $detailProses = DetailProses::where('id', $request->detail_proses_id)
                    ->where('proses_id', $id)
                    ->firstOrFail();
            } else {
                // Jika tidak ada detail_proses_id, ambil DetailProses pertama dari proses ini
                $detailProses = DetailProses::where('proses_id', $id)->first();
                if (!$detailProses) {
                    return redirect()->route('dashboard', ['page' => $page])->with('error', 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.');
                }
            }
            
            $no_op = $detailProses->no_op;
            $no_partai = $detailProses->no_partai;
            $mesin_id = $proses->mesin_id;
            // Ambil data dari SQL Server
            $tickets = DB::connection('sqlsrv')
                ->table('TICKET_DETAIL')
                ->where('ID_NO', $barcode)
                ->select('PRODUCT_CODE', 'ACTUAL_WT')
                ->get();
            Log::info('BarcodeLa: Ticket data from SQL Server', ['tickets' => $tickets->toArray()]);
            if ($tickets->isEmpty()) {
                $errorMsg = 'Barcode tidak ditemukan di database TICKET_DETAIL';
                Log::error('BarcodeLa: No ticket data found', ['barcode' => $barcode]);
                return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
            }
            // Format body untuk API
            $details = $tickets->map(function ($row) {
                // Cari ProductName berdasarkan PRODUCT_CODE di tabel PRODUCT
                $product = DB::connection('sqlsrv')
                    ->table('PRODUCT')
                    ->where('ProductCode', $row->PRODUCT_CODE)
                    ->first();
                $productName = $product ? $product->ProductName : $row->PRODUCT_CODE; // Fallback ke PRODUCT_CODE jika tidak ditemukan
                return $productName . '/' . ($row->ACTUAL_WT ?? 0);
            })->implode('|');
            $body = '"' . $no_op . ';' . $details . '"';
            Log::info('BarcodeLa: API body prepared', ['body' => $body]);
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
            Log::info('BarcodeLa: API Response', ['response' => $rawResponse]);
            $data = json_decode($rawResponse, true);
            if (empty($data)) {
                $errorMsg = 'Barcode atau detail kimia tidak dikenali oleh sistem SAP (API response kosong)';
                Log::error('BarcodeLa: Empty API response');
                return back()->withInput()->with('error', $errorMsg);
            }
            if (!is_array($data) || !isset($data[0]['stats'])) {
                $errorMsg = 'Gagal validasi barcode: response tidak valid';
                Log::error('BarcodeLa: Invalid API response', ['data' => $data]);
                return back()->withInput()->with('error', $errorMsg);
            }
            if ($data[0]['stats'] !== 'success') {
                $errorMsg = $data[0]['stats'] ?: 'Barcode tidak dapat digunakan';
                Log::error('BarcodeLa: API stats not success', ['stats' => $data[0]['stats']]);
                return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
            }
            $matdok = $data[0]['mblnr'] ?? null;

            // Sukses, simpan ke tabel barcode_la untuk setiap DetailProses
            // dalam proses ini (multiple OP: semua OP mendapat barcode yang sama).
            $detailList = DetailProses::where('proses_id', $proses->id)->get();
            if ($detailList->isEmpty()) {
                return redirect()->route('dashboard', ['page' => $page])
                    ->with('error', 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.');
            }

            foreach ($detailList as $detail) {
                BarcodeLa::create([
                    'detail_proses_id' => $detail->id,
                    'no_op'            => $detail->no_op,
                    'no_partai'        => $detail->no_partai,
                    'barcode'          => $barcode,
                    'matdok'           => $matdok,
                    'mesin_id'         => $mesin_id,
                    'cancel'           => false,
                ]);
            }

            Log::info('BarcodeLa: Successfully saved to all details', [
                'barcode' => $barcode,
                'proses_id' => $proses->id,
                'detail_count' => $detailList->count(),
            ]);

            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', 'Barcode LA berhasil disimpan untuk semua OP pada proses ini!');
        } catch (\Exception $e) {
            Log::error('BarcodeLa: Exception occurred', ['error' => $e->getMessage(), 'proses_id' => $id]);
            // Jika timeout, jangan simpan karena tidak ada matdok dari SAP
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout')) {
                $errorMsg = 'Timeout saat menghubungi SAP, barcode LA tidak disimpan. Coba lagi nanti.';
                Log::info('BarcodeLa: Not saved due to timeout', ['barcode' => $barcode, 'proses_id' => $proses->id]);
                return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
            } else {
                $errorMsg = 'Gagal menyimpan barcode LA: ' . $e->getMessage();
                return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
            }
        }
    }

    // Simpan barcode aux
    public function barcodeAux(Request $request, $id)
    {
        $request->validate([
            // Sama seperti LA: uniqueness dijaga di level kode
            'barcode' => 'required|string|max:255',
            'detail_proses_id' => 'nullable|exists:detail_proses,id',
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
            
            // Cek global: jika barcode sudah pernah dipakai di mana pun, tolak
            if (BarcodeAux::where('barcode', $barcode)->where('cancel', false)->exists()) {
                return redirect()->route('dashboard', ['page' => $page])
                    ->with('error', 'Barcode AUX ini sudah pernah digunakan dan tidak dapat dipakai lagi.');
            }
            
            // Cari DetailProses yang sesuai
            $detailProses = null;
            if ($request->has('detail_proses_id') && $request->detail_proses_id) {
                $detailProses = DetailProses::where('id', $request->detail_proses_id)
                    ->where('proses_id', $id)
                    ->firstOrFail();
            } else {
                // Jika tidak ada detail_proses_id, ambil DetailProses pertama dari proses ini
                $detailProses = DetailProses::where('proses_id', $id)->first();
                if (!$detailProses) {
                    return redirect()->route('dashboard', ['page' => $page])->with('error', 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.');
                }
            }
            
            // Cari data auxl berdasarkan barcode (hanya untuk validasi & relasi detail)
            $auxl = \App\Models\Auxl::where('barcode', $barcode)->first();
            if (!$auxl) {
                return redirect()->route('dashboard', ['page' => $page])->with('error', 'Barcode tidak ditemukan di data auxiliary!');
            }
            // Ambil detail auxiliary
            $details = $auxl->details;
            if ($details->isEmpty()) {
                return redirect()->route('dashboard', ['page' => $page])->with('error', 'Data detail auxiliary tidak ditemukan!');
            }
            // Format: auxiliary/konsentrasi (konsentrasi dalam gram)
            $detailStr = $details->map(function ($d) {
                $aux = $d->auxiliary;
                $kons = (float)$d->konsentrasi * 1000; // kg ke gram
                return $aux . '/' . (int)$kons;
            })->implode('|');
            $body = '"' . $detailProses->no_op . ';' . $detailStr . '"';
            // Kirim ke API SAP
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
            Log::info('API Response for barcode AUX:', ['body' => $body, 'response' => $rawResponse]);
            $data = json_decode($rawResponse, true);
            if (empty($data)) {
                return back()->withInput()->with('error', 'Barcode atau detail auxiliary tidak dikenali oleh sistem SAP (API response kosong)');
            }
            if (!is_array($data) || !isset($data[0]['stats'])) {
                return back()->withInput()->with('error', 'Gagal validasi barcode: response tidak valid');
            }
            if ($data[0]['stats'] !== 'success') {
                $errorMsg = $data[0]['stats'] ?: 'Barcode tidak dapat digunakan';
                return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
            }
            $matdok = $data[0]['mblnr'] ?? null;

            // Sukses, simpan ke tabel barcode_aux untuk setiap DetailProses
            $detailList = DetailProses::where('proses_id', $proses->id)->get();
            if ($detailList->isEmpty()) {
                return redirect()->route('dashboard', ['page' => $page])
                    ->with('error', 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.');
            }

            foreach ($detailList as $detail) {
                BarcodeAux::create([
                    'detail_proses_id' => $detail->id,
                    'no_op'            => $detail->no_op,
                    'no_partai'        => $detail->no_partai,
                    'barcode'          => $barcode,
                    'matdok'           => $matdok,
                    'mesin_id'         => $proses->mesin_id,
                    'cancel'           => false,
                ]);
            }

            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', 'Barcode AUX berhasil disimpan untuk semua OP pada proses ini!');
        } catch (\Exception $e) {
            // Jika timeout, jangan simpan karena tidak ada matdok dari SAP
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout')) {
                $errorMsg = 'Timeout saat menghubungi SAP, barcode AUX tidak disimpan. Coba lagi nanti.';
                Log::info('BarcodeAux: Not saved due to timeout', ['barcode' => $barcode, 'proses_id' => $proses->id]);
                return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
            } else {
                $errorMsg = 'Gagal menyimpan barcode AUX: ' . $e->getMessage();
                return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
            }
        }
    }


    // Endpoint untuk ambil barcode berdasarkan proses_id
    // Optional: filter per detail dengan query param ?detail_proses_id=123
    public function barcodes(Request $request, $id)
    {
        Proses::findOrFail($id); // validasi proses ada

        $detailProsesId = $request->query('detail_proses_id');

        // Ambil DetailProses sesuai filter (jika ada) atau ambil semua
        if ($detailProsesId) {
            $detailProsesList = DetailProses::where('id', $detailProsesId)
                ->where('proses_id', $id)
                ->get();
        } else {
            $detailProsesList = DetailProses::where('proses_id', $id)->get();
        }
        
        // Kumpulkan semua barcode dari semua DetailProses
        $barcodeKain = collect();
        $barcodeLa = collect();
        $barcodeAux = collect();
        
        foreach ($detailProsesList as $detailProses) {
            // Ambil barcode kain dari DetailProses ini
            $kains = $detailProses->barcodeKains()->get()->map(function ($b) {
                $b->matdok = $b->matdok ?? '';
                return $b;
            });
            $barcodeKain = $barcodeKain->merge($kains);
            
            // Ambil barcode la dari DetailProses ini
            $las = $detailProses->barcodeLas()->get()->map(function ($b) {
                $b->matdok = $b->matdok ?? '';
                return $b;
            });
            $barcodeLa = $barcodeLa->merge($las);
            
            // Ambil barcode aux dari DetailProses ini
            $auxs = $detailProses->barcodeAuxs()->get()->map(function ($b) {
                $b->matdok = $b->matdok ?? '';
                return $b;
            });
            $barcodeAux = $barcodeAux->merge($auxs);
        }
        
        return response()->json([
            'barcode_kain' => $barcodeKain->values(),
            'barcode_la' => $barcodeLa->values(),
            'barcode_aux' => $barcodeAux->values(),
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
            // Anggap sukses baik untuk response 'Success' maupun pesan bahwa semua item sudah dicancel/reversed
            $alreadyCanceled = is_string($stats)
                && stripos($stats, 'already') !== false
                && (stripos($stats, 'cancel') !== false || stripos($stats, 'reverse') !== false || stripos($stats, 'reversed') !== false);

            if ($stats === 'Success' || $alreadyCanceled) {
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
                    return response()->json([
                        'status' => 'success',
                        'message' => $alreadyCanceled
                            ? 'Barcode sudah pernah dicancel / reversed di SAP. Status lokal diperbarui menjadi cancel.'
                            : 'Barcode berhasil dicancel di SAP.'
                    ]);
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
                'swap_position' => 'penukaran posisi',
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
                'swap_position' => 'penukaran posisi',
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

    // Swap posisi dua proses di mesin yang sama
    public function swap(Request $request, $id)
    {
        $proses1 = Proses::findOrFail($id);

        // Validasi: hanya bisa swap jika proses belum mulai (mulai masih null)
        if ($proses1->mulai !== null) {
            $errorMessage = 'Tidak dapat menukar posisi proses. Proses sudah dimulai.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 400);
            }

            return back()->with('error', $errorMessage);
        }

        // Validasi proses_id kedua proses
        $request->validate([
            'swapped_proses_id' => [
                'required',
                'integer',
                'exists:proses,id',
                function ($attribute, $value, $fail) use ($id) {
                    if ((int) $value == (int) $id) {
                        $fail('Proses kedua harus berbeda dari proses pertama.');
                    }
                },
            ],
        ]);

        $proses2Id = (int) $request->input('swapped_proses_id');
        $proses2 = Proses::findOrFail($proses2Id);

        // Validasi: proses kedua juga belum mulai
        if ($proses2->mulai !== null) {
            $errorMessage = 'Tidak dapat menukar posisi proses. Proses kedua sudah dimulai.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 400);
            }

            return back()->with('error', $errorMessage);
        }

        // Validasi: kedua proses harus di mesin yang sama
        if ($proses1->mesin_id !== $proses2->mesin_id) {
            $errorMessage = 'Tidak dapat menukar posisi proses. Kedua proses harus berada di mesin yang sama.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 400);
            }

            return back()->with('error', $errorMessage);
        }

        // Cek apakah sudah ada pending approval untuk proses pertama
        if ($this->hasPendingApproval($proses1->id)) {
            $pendingApproval = $this->getPendingApproval($proses1->id);
            $actionLabels = [
                'edit_cycle_time' => 'perubahan cycle time',
                'delete_proses' => 'penghapusan proses',
                'move_machine' => 'pemindahan mesin',
                'swap_position' => 'penukaran posisi',
            ];
            $actionLabel = $actionLabels[$pendingApproval->action] ?? 'perubahan';

            $errorMessage = "Tidak dapat mengajukan permintaan baru. Masih ada permintaan {$actionLabel} yang menunggu persetujuan FM untuk proses pertama.";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 400);
            }

            return back()->with('error', $errorMessage);
        }

        // Cek apakah sudah ada pending approval untuk proses kedua
        if ($this->hasPendingApproval($proses2->id)) {
            $pendingApproval = $this->getPendingApproval($proses2->id);
            $actionLabels = [
                'edit_cycle_time' => 'perubahan cycle time',
                'delete_proses' => 'penghapusan proses',
                'move_machine' => 'pemindahan mesin',
                'swap_position' => 'penukaran posisi',
            ];
            $actionLabel = $actionLabels[$pendingApproval->action] ?? 'perubahan';

            $errorMessage = "Tidak dapat mengajukan permintaan baru. Masih ada permintaan {$actionLabel} yang menunggu persetujuan FM untuk proses kedua.";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage
                ], 400);
            }

            return back()->with('error', $errorMessage);
        }

        // Cek apakah sudah ada pending swap_position approval yang melibatkan salah satu proses
        $proses1Id = $proses1->id;
        $existingSwapApproval = Approval::where('status', 'pending')
            ->where('type', 'FM')
            ->where('action', 'swap_position')
            ->where(function ($query) use ($proses1Id, $proses2Id) {
                $query->where('proses_id', $proses1Id)
                    ->orWhere('proses_id', $proses2Id);
            })
            ->first();

        if ($existingSwapApproval) {
            $errorMessage = 'Salah satu proses sudah terlibat dalam permintaan penukaran posisi yang menunggu persetujuan FM.';

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

        // Ambil order saat ini dari kedua proses
        $order1 = $proses1->order ?? 0;
        $order2 = $proses2->order ?? 0;

        // Cari semua proses yang akan terpengaruh oleh reorder ini
        // (proses yang berada di antara oldOrder dan newOrder akan bergeser)
        $affectedProsesIds = [$proses1->id, $proses2Id]; // Minimal kedua proses yang langsung terlibat
        $mesinId = $proses1->mesin_id;
        $affectedProses = collect([$proses1, $proses2]); // Inisialisasi dengan kedua proses yang terlibat

        // Ambil semua proses pending di mesin yang sama untuk normalisasi order jika perlu
        $allPendingProses = Proses::where('mesin_id', $mesinId)
            ->whereNull('mulai')
            ->whereNull('selesai')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        // Jika order belum jelas, normalisasi dulu
        if ($order1 === 0 || $order2 === 0 || $allPendingProses->count() !== $allPendingProses->where('order', '>', 0)->count()) {
            $idx = 1;
            foreach ($allPendingProses as $p) {
                $p->order = $idx++;
                $p->save();
            }

            $proses1->refresh();
            $proses2->refresh();
            $order1 = (int) ($proses1->order ?? 0);
            $order2 = (int) ($proses2->order ?? 0);
        }

        // Tentukan rentang order yang akan terpengaruh
        if ($order1 !== $order2 && $order1 > 0 && $order2 > 0) {
            $minOrder = min($order1, $order2);
            $maxOrder = max($order1, $order2);

            // Ambil semua proses yang berada di rentang tersebut (akan bergeser)
            // Termasuk proses yang berada di batas minOrder dan maxOrder
            $affectedProses = Proses::where('mesin_id', $mesinId)
                ->whereNull('mulai')
                ->whereNull('selesai')
                ->where('order', '>=', $minOrder)
                ->where('order', '<=', $maxOrder)
                ->get();

            $affectedProsesIds = $affectedProses->pluck('id')->toArray();
            $affectedProsesIds = array_unique($affectedProsesIds);

            // Pastikan kedua proses yang langsung terlibat selalu ada di daftar
            if (!in_array($proses1->id, $affectedProsesIds)) {
                $affectedProsesIds[] = $proses1->id;
                // Tambahkan juga ke affectedProses collection
                $affectedProses->push($proses1);
            }
            if (!in_array($proses2Id, $affectedProsesIds)) {
                $affectedProsesIds[] = $proses2Id;
                // Tambahkan juga ke affectedProses collection
                $affectedProses->push($proses2);
            }
            $affectedProsesIds = array_unique($affectedProsesIds);
        }

        // Buat record approval untuk FM (swap position) - hanya 1 approval untuk proses pertama
        // Approval ini akan menukar posisi kedua proses saat di-approve
        Approval::create([
            'proses_id'    => $proses1->id,
            'status'       => 'pending',
            'type'         => 'FM',
            'action'       => 'swap_position',
            'history_data' => [
                'proses1_id' => $proses1->id,
                'proses2_id' => $proses2Id,
                'old_order1' => $order1,
                'old_order2' => $order2,
                'swapped_proses_id' => $proses2Id, // Untuk tracking di dashboard
                'affected_proses_ids' => $affectedProsesIds, // Semua proses yang terpengaruh (akan bergeser)
            ],
            'note'         => null,
            'requested_by' => Auth::id(),
            'approved_by'  => null, // Akan diisi saat FM approve/reject
        ]);

        // Hitung order baru untuk semua proses yang terpengaruh (untuk preview di frontend)
        $affectedOrders = [];
        if ($order1 !== $order2 && $order1 > 0 && $order2 > 0 && isset($affectedProses)) {
            $newOrder1 = $order2; // Proses1 akan pindah ke posisi order2
            $newOrder2 = $order1; // Proses2 akan pindah ke posisi order1

            foreach ($affectedProses as $p) {
                if ($p->id == $proses1->id) {
                    $affectedOrders[$p->id] = $newOrder1;
                } elseif ($p->id == $proses2Id) {
                    $affectedOrders[$p->id] = $newOrder2;
                } elseif ($order1 < $order2) {
                    // Proses1 pindah ke bawah (order naik), proses di antara bergeser ke atas (order +1)
                    if ($p->order > $order1 && $p->order < $order2) {
                        $affectedOrders[$p->id] = $p->order + 1;
                    } else {
                        $affectedOrders[$p->id] = $p->order;
                    }
                } else {
                    // Proses1 pindah ke atas (order turun), proses di antara bergeser ke bawah (order -1)
                    if ($p->order > $order2 && $p->order < $order1) {
                        $affectedOrders[$p->id] = $p->order - 1;
                    } else {
                        $affectedOrders[$p->id] = $p->order;
                    }
                }
            }
        }

        $successMessage = 'Permintaan penukaran posisi proses telah dikirim dan menunggu persetujuan FM.';

        // Jika request adalah AJAX, kembalikan JSON response
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'affected_orders' => $affectedOrders, // Order baru untuk preview di frontend
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
                'swap_position' => 'penukaran posisi',
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

        // Ambil semua DetailProses untuk snapshot (untuk audit trail lengkap)
        $detailProsesList = DetailProses::where('proses_id', $proses->id)->get();
        $detailProsesSnapshots = $detailProsesList->map(function ($detail) {
            return $detail->toArray();
        })->toArray();
        
        // Buat record approval untuk FM (delete proses), belum menghapus data
        Approval::create([
            'proses_id'    => $proses->id,
            'status'       => 'pending',
            'type'         => 'FM',
            'action'       => 'delete_proses',
            'history_data' => [
                'proses_snapshot' => $proses->toArray(),
                'detail_proses_snapshot' => $detailProsesSnapshots, // Snapshot DetailProses untuk audit trail lengkap
            ],
            'note'         => null,
            'requested_by' => Auth::id(),
            'approved_by'  => null, // Akan diisi saat FM approve/reject
        ]);

        return redirect()->route('dashboard', ['page' => $page])
            ->with('success', 'Permintaan penghapusan proses telah dikirim dan menunggu persetujuan FM.');
    }
}
