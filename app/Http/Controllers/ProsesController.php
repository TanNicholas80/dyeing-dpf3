<?php

namespace App\Http\Controllers;

use App\Models\Proses;
use App\Models\DetailProses;
use App\Models\Mesin;
use App\Models\BarcodeKain;
use App\Models\BarcodeLa;
use App\Models\BarcodeAux;
use App\Models\Approval;
use App\Models\Auxl;
use App\Events\ProsesCreated;
use App\Events\ProsesStatusUpdated;
use App\Events\BarcodeStatusUpdated;
use App\Events\ApprovalPendingCreated;
use App\Services\ProsesStatusService;
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

    /**
     * Validasi apakah semua DetailProses dalam proses sudah memenuhi jumlah barcode kain sesuai roll.
     * 
     * @param int $prosesId
     * @return array|null Returns null if valid, or array with error message if invalid
     */
    private function validateBarcodeKainCompleteness($prosesId)
    {
        // Ambil semua DetailProses dalam proses ini
        $detailList = DetailProses::where('proses_id', $prosesId)->get();

        if ($detailList->isEmpty()) {
            return ['error' => 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.'];
        }

        // Cek setiap DetailProses
        $incompleteDetails = [];
        foreach ($detailList as $detail) {
            $roll = $detail->roll ?? 0;

            // Hitung jumlah barcode kain yang sudah di-scan (cancel = false) untuk DetailProses ini
            $barcodeKainCount = BarcodeKain::where('detail_proses_id', $detail->id)
                ->where('cancel', false)
                ->count();

            // Jika jumlah barcode kain belum mencapai roll, tambahkan ke daftar
            if ($barcodeKainCount < $roll) {
                $incompleteDetails[] = [
                    'no_partai' => $detail->no_partai ?? 'N/A',
                    'scanned' => $barcodeKainCount,
                    'required' => $roll,
                    'missing' => $roll - $barcodeKainCount
                ];
            }
        }

        // Jika ada DetailProses yang belum lengkap, kembalikan error
        if (!empty($incompleteDetails)) {
            $errorMessages = [];
            foreach ($incompleteDetails as $detail) {
                $errorMessages[] = "No Partai '{$detail['no_partai']}': sudah scan {$detail['scanned']} dari {$detail['required']} roll (kurang {$detail['missing']})";
            }

            return [
                'error' => "Tidak dapat scan Barcode LA/AUX. " .
                    "Masih ada partai yang belum memenuhi jumlah barcode kain sesuai roll:\n" .
                    implode("\n", $errorMessages) .
                    "\n\nHarap scan barcode kain terlebih dahulu hingga memenuhi jumlah roll untuk semua partai."
            ];
        }

        // Semua DetailProses sudah memenuhi roll
        return null;
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

        /**
         * VALIDASI KHUSUS (NO OP, NO PARTAI)
         * - Unik per kombinasi (no_op, no_partai): No Partai sama boleh dipakai jika No OP berbeda
         *   (dalam 1 proses OP Multiple maupun proses lain).
         * - Produksi: (no_op, no_partai) harus belum pernah dipakai.
         * - Reproses: Boleh pakai (no_op, no_partai) yang sama jika semua proses sebelumnya
         *   untuk pasangan tsb sudah selesai.
         * - Di dalam satu form: (no_op, no_partai) tidak boleh duplikat; No Partai sama dengan
         *   No OP berbeda diperbolehkan.
         */
        if (
            ($validated['jenis'] ?? null) !== 'Maintenance' &&
            isset($validated['details']) &&
            is_array($validated['details'])
        ) {
            $jenisProses = $validated['jenis'];

            // 1. Cek duplikasi (no_op, no_partai) di dalam form yang sama
            $pairs = [];
            $duplicatePairs = [];
            foreach ($validated['details'] as $d) {
                $noOp = $d['no_op'] ?? '';
                $noPartai = $d['no_partai'] ?? '';
                if ($noOp === '' || $noPartai === '') {
                    continue;
                }
                $key = $noOp . '|' . $noPartai;
                if (isset($pairs[$key])) {
                    if (!in_array($key, $duplicatePairs)) {
                        $duplicatePairs[] = $key;
                    }
                } else {
                    $pairs[$key] = true;
                }
            }
            if (!empty($duplicatePairs)) {
                $labels = array_map(function ($k) {
                    return str_replace('|', ' / ', $k);
                }, $duplicatePairs);
                return back()
                    ->withInput()
                    ->withErrors([
                        'details' => 'Kombinasi No OP + No Partai tidak boleh duplikat dalam satu proses. Duplikat: ' . implode(', ', $labels),
                    ]);
            }

            // 2. Validasi terhadap data yang sudah ada di database (per pasangan no_op + no_partai)
            foreach ($validated['details'] as $index => $detail) {
                $noOp = $detail['no_op'] ?? null;
                $noPartai = $detail['no_partai'] ?? null;
                if (!$noOp || !$noPartai) {
                    continue;
                }

                $existingDetails = DetailProses::where('no_op', $noOp)
                    ->where('no_partai', $noPartai)
                    ->with('proses')
                    ->get();

                if ($jenisProses === 'Reproses') {
                    foreach ($existingDetails as $existingDetail) {
                        $proses = $existingDetail->proses;
                        if ($proses && $proses->selesai === null) {
                            return back()
                                ->withInput()
                                ->withErrors([
                                    "details.$index.no_partai" =>
                                    "Kombinasi No OP '$noOp' + No Partai '$noPartai' tidak dapat digunakan untuk Reproses karena masih ada proses yang berjalan / belum selesai.",
                                ]);
                        }
                    }
                } else {
                    if ($existingDetails->isNotEmpty()) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                "details.$index.no_partai" =>
                                "Kombinasi No OP '$noOp' + No Partai '$noPartai' sudah pernah digunakan. " .
                                    "Gunakan 'Reproses' jika ingin memproses ulang (dengan syarat proses sebelumnya sudah selesai).",
                            ]);
                    }
                }
            }
        }

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

                // Broadcast agar semua browser langsung menampilkan blok kuning (menunggu approval FM)
                event(new ApprovalPendingCreated([$proses->id]));

                return redirect()->route('dashboard', ['page' => $page])
                    ->with('success', 'Proses Reproses berhasil ditambahkan. Proses akan tampil dengan warna kuning dan menunggu persetujuan FM terlebih dahulu, kemudian VP.');
            }

            // Produksi / Maintenance langsung tampil
            // Broadcast event untuk update real-time
            $proses->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            $statusService = new ProsesStatusService();
            $affectedProsesIds = $statusService->getAffectedProsesIds();
            $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
            event(new ProsesCreated($proses->id, $statusData));

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
                'http://18.139.142.16:8020/sap/bc/zdyes/zterima_op?sap-client=100',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
                        'Content-Type' => 'text/plain',
                        'Accept' => 'application/json',
                    ],
                    'body' => json_encode($no_op),
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

    /**
     * API: Cek apakah (no_op, no_partai) sudah terpakai di proses lain.
     * Dipakai untuk validasi frontend sebelum submit tambah proses.
     *
     * @return \Illuminate\Http\JsonResponse { ok: bool, message?: string }
     */
    public function checkPartaiUsed(Request $request)
    {
        $noOp = $request->input('no_op');
        $noPartai = $request->input('no_partai');
        $jenis = $request->input('jenis', 'Produksi');

        if (!$noOp || !$noPartai) {
            return response()->json(['ok' => true]);
        }

        if ($jenis === 'Maintenance') {
            return response()->json(['ok' => true]);
        }

        $existing = DetailProses::where('no_op', $noOp)
            ->where('no_partai', $noPartai)
            ->with('proses')
            ->get();

        if ($existing->isEmpty()) {
            return response()->json(['ok' => true]);
        }

        if ($jenis === 'Reproses') {
            foreach ($existing as $d) {
                $p = $d->proses;
                if ($p && $p->selesai === null) {
                    return response()->json([
                        'ok' => false,
                        'message' => "No Partai \"{$noPartai}\" untuk No OP \"{$noOp}\" tidak dapat digunakan karena masih ada proses yang berjalan / belum selesai untuk kombinasi tersebut.",
                    ]);
                }
            }
            return response()->json(['ok' => true]);
        }

        return response()->json([
            'ok' => false,
            'message' => "No Partai \"{$noPartai}\" untuk No OP \"{$noOp}\" sudah dipakai di proses lain.",
        ]);
    }

    // Simpan barcode kain
    public function barcodeKain(Request $request, $id)
    {
        $request->validate([
            // 'barcode' => 'required|string|max:255|unique:barcode_kain,barcode',
            'detail_proses_id' => 'nullable|exists:detail_proses,id',
        ]);
        try {
            $proses = Proses::findOrFail($id);
            $barcode = $request->barcode;
            $barcode = substr($barcode, 0, 10);
            Log::info('BarcodeKain: Barcode received and trimmed', ['original' => $request->barcode, 'trimmed' => $barcode, 'proses_id' => $id]);

            $opberjalan = Proses::select('mulai', 'selesai')
            ->where('id', $id)
            ->first();

            if (!empty($opberjalan->mulai) && empty($opberjalan->selesai)) {
                return response()->json(['status' => 'error', 'message' => 'Tambah kain tidak dapat dilakukan saat mesin berjalan'], 400);
            }

            $detailProses = null;
            if ($request->has('detail_proses_id') && $request->detail_proses_id) {
                $detailProses = DetailProses::where('id', $request->detail_proses_id)
                    ->where('proses_id', $id)
                    ->first();
                if (!$detailProses) {
                    $msg = 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard')->with('error', $msg);
                }
            } else {
                $detailProses = DetailProses::where('proses_id', $id)->first();
                if (!$detailProses) {
                    $msg = 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard')->with('error', $msg);
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
                'http://18.139.142.16:8020/sap/bc/zdyes/zterima_data?sap-client=100',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
                        'Content-Type' => 'text/plain',
                        'Accept' => 'application/json',
                    ],
                    'body' => $body,
                ]
            );
            $data = json_decode($response->getBody(), true);
            Log::info('BarcodeKain: API Response', ['response' => $data]);
            if (!is_array($data) || !isset($data[0]['stats'])) {
                $errorMsg = 'Gagal validasi barcode: response tidak valid';
                Log::error('BarcodeKain: Invalid response', ['data' => $data]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $errorMsg], 400);
                }
                return back()->withInput()->with('error', $errorMsg);
            }
            if ($data[0]['stats'] !== 'success') {
                $errorMsg = $data[0]['stats'] ?: 'Barcode tidak dapat digunakan';
                Log::error('BarcodeKain: API stats not success', ['stats' => $data[0]['stats']]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $errorMsg], 400);
                }
                return redirect()->route('dashboard')->with('error', $errorMsg);
            }
            // Sukses, simpan ke tabel barcode_kain
            \App\Models\BarcodeKain::create([
                'detail_proses_id' => $detailProses->id,
                'no_op' => $no_op,
                'no_partai' => $no_partai,
                'barcode' => $barcode,
                'matdok' => $data[0]['mblnr'] ?? null,
                'qty_gi' => isset($data[0]['menge']) ? (float)$data[0]['menge'] : null,
                'mesin_id' => $mesin_id,
                'cancel' => false,
            ]);
            Log::info('BarcodeKain: Successfully saved to database', ['barcode' => $barcode, 'proses_id' => $proses->id]);
            
            // Broadcast event untuk update real-time
            $proses->refresh();
            $proses->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            $statusService = new ProsesStatusService();
            $affectedProsesIds = $statusService->getAffectedProsesIds();
            $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
            event(new BarcodeStatusUpdated($proses->id, $statusData));
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Barcode kain berhasil disimpan!']);
            }
            return redirect()->route('dashboard')->with('success', 'Barcode kain berhasil disimpan!');
        } catch (\Exception $e) {
            Log::error('BarcodeKain: Exception occurred', ['error' => $e->getMessage(), 'proses_id' => $id]);
            $errorMsg = 'Gagal menyimpan barcode kain: ' . $e->getMessage();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $errorMsg], 500);
            }
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

            if (BarcodeLa::where('barcode', $barcode)->where('cancel', false)->exists()) {
                $msg = 'Barcode LA ini sudah pernah digunakan dan tidak dapat dipakai lagi.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])
                    ->with('error', $msg);
            }

            $validationResult = $this->validateBarcodeKainCompleteness($id);
            if ($validationResult !== null) {
                $msg = $validationResult['error'];
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])
                    ->with('error', $msg);
            }

            $detailProses = null;
            if ($request->has('detail_proses_id') && $request->detail_proses_id) {
                $detailProses = DetailProses::where('id', $request->detail_proses_id)
                    ->where('proses_id', $id)
                    ->first();
                if (!$detailProses) {
                    $msg = 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
                }
            } else {
                $detailProses = DetailProses::where('proses_id', $id)->first();
                if (!$detailProses) {
                    $msg = 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
                }
            }

            $no_partai = $detailProses->no_partai;
            $mesin_id = $proses->mesin_id;

            // Ambil semua detail proses untuk mendapatkan semua no_op
            $detailList = DetailProses::where('proses_id', $proses->id)->get();
            if ($detailList->isEmpty()) {
                $msg = 'Detail proses tidak ditemukan.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }


            // Gabungkan semua no_op dengan total qty_gi dari BarcodeKain dengan delimiter |
            // Ambil semua DetailProses (termasuk yang no_op-nya sama) tanpa di-unique
            $allNoOps = $detailList->filter(function ($detail) {
                return !empty($detail->no_op);
            })->map(function ($detail) {
                // Ambil total QTY GI dari BarcodeKain untuk DetailProses ini
                $totalQtyGi = BarcodeKain::where('detail_proses_id', $detail->id)
                    ->where('cancel', false)
                    ->sum('qty_gi') ?? 0;
                return $detail->no_op . '/' . (int)$totalQtyGi;
            })->implode('|');

            $tickets = DB::connection('sqlsrv')
                ->table('TICKET_DETAIL')
                ->where('ID_NO', $barcode)
                ->select('PRODUCT_CODE', 'ACTUAL_WT')
                ->get();
            Log::info('BarcodeLa: Ticket data from SQL Server', ['tickets' => $tickets->toArray()]);
            if ($tickets->isEmpty()) {
                $msg = 'Barcode tidak ditemukan di database TICKET_DETAIL';
                Log::error('BarcodeLa: No ticket data found', ['barcode' => $barcode]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }
            $details = $tickets->map(function ($row) {
                $product = DB::connection('sqlsrv')
                    ->table('PRODUCT')
                    ->where('ProductCode', $row->PRODUCT_CODE)
                    ->first();
                $productName = $product ? $product->ProductCode : $row->PRODUCT_CODE;
                return $productName . '/' . ($row->ACTUAL_WT ?? 0);
            })->implode('|');
            $body = '"' . $allNoOps . ';' . $details . '"';
            Log::info('BarcodeLa: API body prepared', ['body' => $body]);
            $client = new \GuzzleHttp\Client();
            $response = $client->post(
                'http://18.139.142.16:8020/sap/bc/zdyes/zterima_kimia?sap-client=100',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
                        'Content-Type' => 'text/plain',
                        'Accept' => 'application/json',
                    ],
                    'body' => $body,
                ]
            );
            $rawResponse = $response->getBody()->getContents();
            Log::info('BarcodeLa: API Response', ['response' => $rawResponse]);
            $data = json_decode($rawResponse, true);
            if (empty($data)) {
                $msg = 'Barcode atau detail kimia tidak dikenali oleh sistem SAP (API response kosong)';
                Log::error('BarcodeLa: Empty API response');
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return back()->withInput()->with('error', $msg);
            }
            if (!is_array($data) || !isset($data[0]['stats'])) {
                $msg = 'Gagal validasi barcode: response tidak valid';
                Log::error('BarcodeLa: Invalid API response', ['data' => $data]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return back()->withInput()->with('error', $msg);
            }
            if ($data[0]['stats'] !== 'success') {
                $msg = $data[0]['stats'] ?: 'Barcode tidak dapat digunakan';
                Log::error('BarcodeLa: API stats not success', ['stats' => $data[0]['stats']]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }


            // Validasi Jenis Bahan untuk OP
            // $jenis = Auxl::where('barcode', $barcode)
            // ->value('jenis');

            // foreach ($detailList as $detail) {

            //     Log::info('DEBUG validateOpByJenis', [
            //         'barcode' => $barcode,
            //         'jenis' => $jenis,
            //         'no_op'=> $detail->no_op
            //     ]);
            //     $opValidation = $this->validateOpByJenis($jenis, $detail->no_op);

            //     if ($opValidation) {
            //         if ($request->ajax() || $request->wantsJson()) {
            //             return response()->json(['status' => 'error', 'message' => $opValidation], 400);
            //         }
            //         return redirect()->route('dashboard', ['page' => $page])->with('error', $opValidation);
            //     }

            //     Log::info('BarcodeLa: Successfully saved to all details', [
            //         'barcode' => $barcode,
            //         'proses_id' => $proses->id,
            //         'detail_count' => $detailList->count(),
            //     ]);
            // }


            $matdok = $data[0]['mblnr'] ?? null;

            foreach ($detailList as $detail) {
                BarcodeLa::create([
                    'detail_proses_id' => $detail->id,
                    'no_op' => $detail->no_op,
                    'no_partai' => $detail->no_partai,
                    'barcode' => $barcode,
                    'matdok' => $matdok,
                    'mesin_id' => $mesin_id,
                    'cancel' => false,
                ]);
            }

            // Broadcast event untuk update real-time
            $proses->refresh();
            $proses->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            $statusService = new ProsesStatusService();
            $affectedProsesIds = $statusService->getAffectedProsesIds();
            $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
            event(new BarcodeStatusUpdated($proses->id, $statusData));

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Barcode LA berhasil disimpan untuk semua OP pada proses ini!']);
            }
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', 'Barcode LA berhasil disimpan untuk semua OP pada proses ini!');
        } catch (\Exception $e) {
            Log::error('BarcodeLa: Exception occurred', ['error' => $e->getMessage(), 'proses_id' => $id]);
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout')) {
                $errorMsg = 'Timeout saat menghubungi SAP, barcode LA tidak disimpan. Coba lagi nanti.';
            } else {
                $errorMsg = 'Gagal menyimpan barcode LA: ' . $e->getMessage();
            }
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $errorMsg], 500);
            }
            return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
        }
    }

    // Simpan barcode aux
    public function barcodeAux(Request $request, $id)
    {
        Log::info('BarcodeAux: Request received', [
            'proses_id' => $id,
            'barcode' => $request->barcode,
            'detail_proses_id' => $request->detail_proses_id ?? null,
            'user_id' => Auth::id(),
            'input' => $request->all()
        ]);
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
            Log::info('BarcodeAux: Proses found', ['proses_id' => $proses->id, 'barcode' => $barcode]);

            if (BarcodeAux::where('barcode', $barcode)->where('cancel', false)->exists()) {
                $msg = 'Barcode AUX ini sudah pernah digunakan dan tidak dapat dipakai lagi.';
                Log::error('BarcodeAux: Barcode already used', ['barcode' => $barcode]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])
                    ->with('error', $msg);
            }

            $validationResult = $this->validateBarcodeKainCompleteness($id);
            if ($validationResult !== null) {
                $msg = $validationResult['error'];
                Log::error('BarcodeAux: Barcode kain not complete', ['proses_id' => $id, 'error' => $msg]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])
                    ->with('error', $msg);
            }

            $detailProses = null;
            if ($request->has('detail_proses_id') && $request->detail_proses_id) {
                $detailProses = DetailProses::where('id', $request->detail_proses_id)
                    ->where('proses_id', $id)
                    ->first();
                Log::info('BarcodeAux: DetailProses by ID', ['detail_proses_id' => $request->detail_proses_id, 'found' => !!$detailProses]);
                if (!$detailProses) {
                    $msg = 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.';
                    Log::error('BarcodeAux: DetailProses not found', ['detail_proses_id' => $request->detail_proses_id]);
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
                }
            } else {
                $detailProses = DetailProses::where('proses_id', $id)->first();
                Log::info('BarcodeAux: First DetailProses by proses_id', ['proses_id' => $id, 'found' => !!$detailProses]);
                if (!$detailProses) {
                    $msg = 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.';
                    Log::error('BarcodeAux: No DetailProses found for proses', ['proses_id' => $id]);
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
                }
            }

            $auxl = \App\Models\Auxl::where('barcode', $barcode)->first();
            Log::info('BarcodeAux: Auxl lookup', ['barcode' => $barcode, 'found' => !!$auxl]);
            if (!$auxl) {
                $msg = 'Barcode tidak ditemukan di data auxiliary!';
                Log::error('BarcodeAux: Barcode not found in auxl', ['barcode' => $barcode]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }
            $details = $auxl->details;
            Log::info('BarcodeAux: Auxl details', ['count' => $details->count()]);
            if ($details->isEmpty()) {
                $msg = 'Data detail auxiliary tidak ditemukan!';
                Log::error('BarcodeAux: No details in auxl', ['barcode' => $barcode]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }

            // Ambil semua detail proses untuk mendapatkan semua no_op
            $detailList = DetailProses::where('proses_id', $proses->id)->get();
            if ($detailList->isEmpty()) {
                $msg = 'Detail proses tidak ditemukan.';
                Log::error('BarcodeAux: No DetailProses found for proses', ['proses_id' => $proses->id]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }

            // Gabungkan semua no_op dengan total qty_gi dari BarcodeKain dengan delimiter |
            // Ambil semua DetailProses (termasuk yang no_op-nya sama) tanpa di-unique
            $allNoOps = $detailList->filter(function ($detail) {
                return !empty($detail->no_op);
            })->map(function ($detail) {
                // Ambil total QTY GI dari BarcodeKain untuk DetailProses ini
                $totalQtyGi = BarcodeKain::where('detail_proses_id', $detail->id)
                    ->where('cancel', false)
                    ->sum('qty_gi') ?? 0;
                return $detail->no_op . '/' . (int)$totalQtyGi;
            })->implode('|');

            $detailStr = $details->map(function ($d) {
                $aux = $d->auxiliary;
                $kons = (float)$d->konsentrasi * 1000;
                return $aux . '/' . (int)$kons;
            })->implode('|');
            $body = '"' . $allNoOps . ';' . $detailStr . '"';
            Log::info('BarcodeAux: API body prepared', ['body' => $body]);
            $client = new \GuzzleHttp\Client();
            $response = $client->post(
                'http://18.139.142.16:8020/sap/bc/zdyes/zterima_kimia?sap-client=100',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
                        'Content-Type' => 'text/plain',
                        'Accept' => 'application/json',
                    ],
                    'body' => $body,
                ]
            );
            $rawResponse = $response->getBody()->getContents();
            Log::info('BarcodeAux: API Response', ['body' => $body, 'response' => $rawResponse]);
            $data = json_decode($rawResponse, true);
            if (empty($data)) {
                $msg = 'Barcode atau detail auxiliary tidak dikenali oleh sistem SAP (API response kosong)';
                Log::error('BarcodeAux: API response empty', ['body' => $body]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return back()->withInput()->with('error', $msg);
            }
            if (!is_array($data) || !isset($data[0]['stats'])) {
                $msg = 'Gagal validasi barcode: response tidak valid';
                Log::error('BarcodeAux: API response invalid', ['body' => $body, 'data' => $data]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return back()->withInput()->with('error', $msg);
            }
            if ($data[0]['stats'] !== 'success') {
                $msg = $data[0]['stats'] ?: 'Barcode tidak dapat digunakan';
                Log::error('BarcodeAux: API stats not success', ['body' => $body, 'stats' => $data[0]['stats']]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }

            // Validasi Jenis Bahan untuk OP
            $jenis = Auxl::where('barcode', $barcode)
            ->value('jenis');
            foreach ($detailList as $detail) {
                Log::info('DEBUG validateOpByJenis', [
                    'barcode' => $barcode,
                    'jenis' => $jenis,
                    'no_op'=> $detail->no_op
                ]);

                $opValidation = $this->validateOpByJenis($jenis, $detail->no_op);

                if ($opValidation) {
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $opValidation], 400);
                    }
                    return redirect()->route('dashboard', ['page' => $page])->with('error', $opValidation);
                }

                Log::info('BarcodeLa: Successfully saved to all details', [
                    'barcode' => $barcode,
                    'proses_id' => $proses->id,
                    'detail_count' => $detailList->count(),
                ]);
            }

            $matdok = $data[0]['mblnr'] ?? null;

            // $detailList sudah diambil sebelumnya untuk membuat body, gunakan yang sama
            Log::info('BarcodeAux: All DetailProses for proses', ['proses_id' => $proses->id, 'count' => $detailList->count()]);

            foreach ($detailList as $detail) {
                BarcodeAux::create([
                    'detail_proses_id' => $detail->id,
                    'no_op' => $detail->no_op,
                    'no_partai' => $detail->no_partai,
                    'barcode' => $barcode,
                    'matdok' => $matdok,
                    'mesin_id' => $proses->mesin_id,
                    'cancel' => false,
                ]);
            }
            Log::info('BarcodeAux: Successfully saved to all details', [
                'barcode' => $barcode,
                'proses_id' => $proses->id,
                'detail_count' => $detailList->count(),
            ]);

            // Broadcast event untuk update real-time
            $proses->refresh();
            $proses->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            $statusService = new ProsesStatusService();
            $affectedProsesIds = $statusService->getAffectedProsesIds();
            $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
            event(new BarcodeStatusUpdated($proses->id, $statusData));

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Barcode AUX berhasil disimpan untuk semua OP pada proses ini!']);
            }
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', 'Barcode AUX berhasil disimpan untuk semua OP pada proses ini!');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout')) {
                $errorMsg = 'Timeout saat menghubungi SAP, barcode AUX tidak disimpan. Coba lagi nanti.';
            } else {
                $errorMsg = 'Gagal menyimpan barcode AUX: ' . $e->getMessage();
            }
            Log::error('BarcodeAux: Exception occurred', [
                'error' => $e->getMessage(),
                'barcode' => $request->barcode,
                'proses_id' => $id,
                'user_id' => Auth::id()
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $errorMsg], 500);
            }
            return redirect()->route('dashboard', ['page' => $page])->with('error', $errorMsg);
        }
    }
    
    // Endpoint untuk ambil barcode berdasarkan proses_id

    // Validasi Jenis Bahan untuk OP
    private function validateOpByJenis($jenis, $noOp)
    {

                Log::info('DEBUG validateOpByJenis', [
                    'jenis' => $jenis,
                    'no_op'=> $noOp
                ]);

        if ($jenis === 'reproses') {
            if (!str_starts_with($noOp, '010')) {
                return 'OP ini tidak dapat menggunakan jenis bahan reproses';
            }
        }

        if ($jenis === 'perbaikan') {
            if (!str_starts_with($noOp, '066')) {
                return 'OP ini tidak dapat menggunakan jenis bahan perbaikan';
            }
        }

        return null;
    }


    // Optional: filter per detail dengan query param ?detail_proses_id=123
    public function barcodes(Request $request, $id)
    {
        Proses::findOrFail($id); // validasi proses ada

        $detailProsesId = $request->query('detail_proses_id');

        // Ambil DetailProses sesuai filter (jika ada) atau ambil semua untuk DISPLAY barcode
        if ($detailProsesId) {
            $detailProsesList = DetailProses::where('id', $detailProsesId)
                ->where('proses_id', $id)
                ->get();
        } else {
            $detailProsesList = DetailProses::where('proses_id', $id)->get();
        }

        // PENTING: Ambil SEMUA DetailProses untuk validasi can_scan_la_aux
        // Agar scan LA/AUX hanya bisa dilakukan jika SEMUA detail memenuhi syarat barcode kain
        $allDetailProsesList = DetailProses::where('proses_id', $id)->get();

        // Kumpulkan semua barcode dari DetailProses yang dipilih (untuk display)
        $barcodeKain = collect();
        $barcodeLa = collect();
        $barcodeAux = collect();
        $barcodeKainProgress = []; // Progress dari detail yang dipilih (untuk display)

        foreach ($detailProsesList as $detailProses) {
            // Ambil barcode kain dari DetailProses ini
            $kains = $detailProses->barcodeKains()->get()->map(function ($b) {
                $b->matdok = $b->matdok ?? '';
                return $b;
            });
            $barcodeKain = $barcodeKain->merge($kains);

            // Hitung progress barcode kain untuk DetailProses ini
            $roll = $detailProses->roll ?? 0;
            $barcodeKainCount = BarcodeKain::where('detail_proses_id', $detailProses->id)
                ->where('cancel', false)
                ->count();

            $barcodeKainProgress[] = [
                'detail_proses_id' => $detailProses->id,
                'no_partai' => $detailProses->no_partai ?? 'N/A',
                'no_op' => $detailProses->no_op ?? 'N/A',
                'roll' => $roll,
                'scanned' => $barcodeKainCount,
                'is_complete' => $barcodeKainCount >= $roll
            ];

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

        // Hitung progress untuk SEMUA DetailProses (untuk validasi can_scan_la_aux)
        $allBarcodeKainProgress = [];
        $incompleteDetails = []; // Detail yang belum lengkap

        foreach ($allDetailProsesList as $detailProses) {
            $roll = $detailProses->roll ?? 0;
            $barcodeKainCount = BarcodeKain::where('detail_proses_id', $detailProses->id)
                ->where('cancel', false)
                ->count();

            $isComplete = $barcodeKainCount >= $roll;

            $allBarcodeKainProgress[] = [
                'detail_proses_id' => $detailProses->id,
                'no_partai' => $detailProses->no_partai ?? 'N/A',
                'no_op' => $detailProses->no_op ?? 'N/A',
                'roll' => $roll,
                'scanned' => $barcodeKainCount,
                'is_complete' => $isComplete
            ];

            // Kumpulkan detail yang belum lengkap untuk pesan error
            if (!$isComplete) {
                $incompleteDetails[] = [
                    'no_op' => $detailProses->no_op ?? 'N/A',
                    'no_partai' => $detailProses->no_partai ?? 'N/A',
                    'roll' => $roll,
                    'scanned' => $barcodeKainCount,
                    'remaining' => $roll - $barcodeKainCount
                ];
            }
        }

        // Cek apakah SEMUA DetailProses sudah memenuhi roll
        $allComplete = collect($allBarcodeKainProgress)->every(function ($progress) {
            return $progress['is_complete'];
        });

        return response()->json([
            'barcode_kain' => $barcodeKain->values(),
            'barcode_la' => $barcodeLa->values(),
            'barcode_aux' => $barcodeAux->values(),
            'barcode_kain_progress' => $barcodeKainProgress, // Progress dari detail yang dipilih (untuk display)
            'all_barcode_kain_progress' => $allBarcodeKainProgress, // Progress dari SEMUA detail (untuk validasi)
            'incomplete_details' => $incompleteDetails, // Detail yang belum lengkap (untuk pesan error)
            'can_scan_la_aux' => $allComplete, // Flag untuk frontend: apakah bisa scan LA/AUX (berdasarkan SEMUA detail)
        ]);
    }

    // Cancel barcode (relay ke SAP API, update flag cancel)
    public function cancelBarcode(Request $request, $proses, $type, $barcode)
    {
        $matdok = $request->input('matdok');

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

        // Jika matdok kosong/null, ambil dari database barcode
        if (!$matdok) {
            if ($barcodeObj && $barcodeObj->matdok) {
                $matdok = $barcodeObj->matdok;
            }
        }
        if (!$matdok) {
            Log::error('Cancel barcode gagal: matdok tidak tersedia', compact('type', 'barcode'));
            return response()->json(['status' => 'error', 'message' => 'Material document tidak tersedia'], 400);
        }

        if ($type === 'kain') {
            $barcodeObj = \App\Models\BarcodeKain::find($barcode);  
            $prosesId = DetailProses::select('proses_id')
            ->where('id', $barcodeObj->detail_proses_id)
            ->first();
            $opberjalan = Proses::select('mulai', 'selesai')
            ->where('id', $prosesId->proses_id)
            ->first();

            if (!empty($opberjalan->mulai) && empty($opberjalan->selesai)) {
                return response()->json(['status' => 'error', 'message' => 'Cancel barcode tidak dapat dilakukan saat mesin berjalan'], 400);
            }
        }
        if (!empty($opberjalan->mulai) && !empty($opberjalan->selesai)) {
            return response()->json(['status' => 'error', 'message' => 'Cancel barcode tidak dapat dilakukan saat proses telah selesai'], 400);
        }
    
        try {
            $client = new \GuzzleHttp\Client();
            $body = '"' . $matdok . '"';
            Log::info('SAP Cancel Request', [
                'url' => 'http://18.139.142.16:8020/sap/bc/zdyes/zterima_cancel?sap-client=100',
                'headers' => [
                    'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
                    'Content-Type' => 'text/plain',
                    'Accept' => 'application/json',
                ],
                'body' => $body,
            ]);
            $response = $client->post(
                'http://18.139.142.16:8020/sap/bc/zdyes/zterima_cancel?sap-client=100',
                [
                    'headers' => [
                        'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
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
                    // Ambil prosesId sebelum update untuk memastikan relasi masih ada
                    $prosesId = null;
                    $detailProsesId = null;
                    if ($type === 'kain') {
                        $detailProsesId = $barcodeObj->detail_proses_id;
                        $prosesId = $barcodeObj->detailProses->proses_id ?? null;
                    } elseif ($type === 'la') {
                        $detailProsesId = $barcodeObj->detail_proses_id;
                        $prosesId = $barcodeObj->detailProses->proses_id ?? null;
                    } elseif ($type === 'aux') {
                        $detailProsesId = $barcodeObj->detail_proses_id;
                        $prosesId = $barcodeObj->detailProses->proses_id ?? null;
                    }
                    
                    $barcodeObj->cancel = true;
                    $barcodeObj->save();
                    
                    // Refresh model untuk memastikan perubahan tersimpan
                    $barcodeObj->refresh();
                    
                    // Broadcast event untuk update real-time
                    if ($prosesId) {
                        // Refresh proses dengan relasi yang fresh - gunakan fresh() untuk bypass cache
                        $proses = Proses::with(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs'])->find($prosesId);
                        if ($proses) {
                            // Refresh semua relasi untuk memastikan data terbaru (termasuk yang sudah di-cancel)
                            $proses->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
                            
                            // Pastikan setiap detail juga di-refresh
                            foreach ($proses->details as $detail) {
                                $detail->load(['barcodeKains', 'barcodeLas', 'barcodeAuxs']);
                            }
                            
                            $statusService = new ProsesStatusService();
                            $affectedProsesIds = $statusService->getAffectedProsesIds();
                            $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
                            
                            Log::info('Broadcasting BarcodeStatusUpdated after cancel', [
                                'proses_id' => $prosesId,
                                'detail_id' => $detailProsesId,
                                'barcode_type' => $type,
                                'status_data' => $statusData
                            ]);
                            
                            event(new BarcodeStatusUpdated($prosesId, $statusData));
                        }
                    }
                    
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

        // Broadcast agar semua browser langsung menampilkan blok kuning (menunggu approval)
        event(new ApprovalPendingCreated([$proses->id]));

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

        // Broadcast agar semua browser langsung menampilkan blok kuning (menunggu approval)
        event(new ApprovalPendingCreated([$proses->id]));

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

        // Broadcast agar semua browser langsung menampilkan blok kuning untuk proses yang terpengaruh
        event(new ApprovalPendingCreated($affectedProsesIds));

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

        // Broadcast agar semua browser langsung menampilkan blok kuning (menunggu approval)
        event(new ApprovalPendingCreated([$proses->id]));

        return redirect()->route('dashboard', ['page' => $page])
            ->with('success', 'Permintaan penghapusan proses telah dikirim dan menunggu persetujuan FM.');
    }
}
