<?php

namespace App\Http\Controllers;

use App\Models\Auxl;
use App\Models\AuxlDetail;
use App\Models\BarcodeAux;
use App\Models\Approval;
use App\Models\Proses;
use App\Support\SapApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuxlController extends Controller
{
    public function index()
    {
        $auxls = Auxl::with(['proses.details', 'proses.mesin', 'details'])->orderByDesc('created_at')->get();

        // Tandai auxl yang sudah dipakai proses (ter-scan sebagai Barcode AUX aktif).
        $usageCountsByBarcode = collect();
        if ($auxls->isNotEmpty()) {
            $barcodes = $auxls->pluck('barcode')->filter()->unique()->values();
            if ($barcodes->isNotEmpty()) {
                $usageCountsByBarcode = BarcodeAux::whereIn('barcode', $barcodes)
                    ->where('cancel', false)
                    ->selectRaw('barcode, COUNT(*) as total')
                    ->groupBy('barcode')
                    ->pluck('total', 'barcode');
            }
        }

        // Tandai auxl yang masih menunggu approval (FM atau VP) untuk jenis reproses
        if ($auxls->isNotEmpty()) {
            $pendingApprovals = Approval::whereIn('auxl_id', $auxls->pluck('id'))
                ->where('action', 'create_aux_reprocess')
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('auxl_id');

            $auxls->transform(function ($auxl) use ($pendingApprovals) {
                $auxlCollection = $pendingApprovals->get($auxl->id);
                $auxl->pendingApproval = $auxlCollection ? $auxlCollection->first() : null;
                return $auxl;
            });
        }

        $auxls->transform(function ($auxl) use ($usageCountsByBarcode) {
            $usedCount = (int) ($usageCountsByBarcode[$auxl->barcode] ?? 0);
            $auxl->isUsedByProses = $usedCount > 0;
            $auxl->usedCount = $usedCount;
            return $auxl;
        });

        return view('auxl.index', compact('auxls'));
    }

    public function create()
    {
        if (Auth::user()->role === 'scm') {
            abort(403, 'Unauthorized action.');
        }

        $allProses = Proses::with(['details', 'mesin'])
            ->withCount(['auxls as normal_aux_count' => function ($q) {
                $q->where('tipe', 'normal');
            }])
            ->whereNull('selesai')
            ->orderByDesc('created_at')
            ->get();

        // Pisahkan menjadi Sedang Berjalan dan Belum Berjalan
        $prosesRunning = $allProses->filter(fn($p) => !is_null($p->mulai));
        $prosesPending = $allProses->filter(fn($p) => is_null($p->mulai));

        return view('auxl.create', compact('prosesRunning', 'prosesPending'));
    }

    public function store(Request $request)
    {
        if (Auth::user()->role === 'scm') {
            abort(403, 'Unauthorized action.');
        }
        $data = $request->validate([
            'proses_id'   => 'required|exists:proses,id',
            'jenis'       => 'required|in:normal,reproses,perbaikan',
            'tipe'        => 'required|in:normal,addition',
            'step_proses' => 'nullable|integer|in:1,2,3',
            'liquor_ratio' => 'required|numeric|min:0.1',
            'total_wt'    => 'required|numeric|min:0',
            'volume_litres' => 'required|numeric|min:0',
            'code'        => 'required|string',
            'konstruksi'  => 'nullable|string',
            'customer'    => 'nullable|string',
            'marketing'   => 'nullable|string',
            'date'        => 'nullable|date',
            'color'       => 'nullable|string',
            'details'     => 'required|array|min:1',
            'details.*.auxiliary'   => 'required|string',
            'details.*.konsentrasi' => 'required|numeric|min:0',
        ]);

        $proses = Proses::findOrFail($data['proses_id']);
        $maxQty = (int) ($proses->qty_aux ?? 0);
        if ($data['tipe'] === 'normal' && $maxQty < 1) {
            return back()->withInput()->withErrors([
                'proses_id' => 'Proses ini memiliki QTY AUX = 0 (tidak memerlukan AUX Normal).'
            ]);
        }
        if ($maxQty < 1) {
            $maxQty = 1;
        }

        if ($maxQty > 1 && empty($data['step_proses'])) {
            return back()->withInput()->withErrors([
                'step_proses' => 'Pick List Step wajib dipilih ketika QTY Aux lebih dari 1.'
            ]);
        }

        // Cek kuota dan keunikan step untuk Aux Type Normal
        if ($data['tipe'] === 'normal') {
            $existingNormalCount = Auxl::where('proses_id', $data['proses_id'])
                ->where('tipe', 'normal')
                ->count();

            if ($existingNormalCount >= $maxQty) {
                return back()->withInput()->withErrors([
                    'tipe' => "AUX Type Normal untuk proses ini sudah mencapai batas maksimum ({$maxQty}x)."
                ]);
            }

            $targetStep = $data['step_proses'] ?? 1;
            $stepExists = Auxl::where('proses_id', $data['proses_id'])
                ->where('tipe', 'normal')
                ->where('step_proses', $targetStep)
                ->exists();

            if ($stepExists) {
                return back()->withInput()->withErrors([
                    'step_proses' => "AUX Type Normal untuk Step {$targetStep} sudah pernah dibuat pada proses ini."
                ]);
            }
        } elseif ($data['tipe'] === 'addition') {
            if (empty($data['step_proses'])) {
                return back()->withInput()->withErrors([
                    'step_proses' => 'Pick List Step (1 - Reactive / 2 - Dispers) wajib dipilih untuk Tipe Addition (Topping).'
                ]);
            }
            $targetStep = (int) $data['step_proses'];
            if (!in_array($targetStep, [1, 2])) {
                return back()->withInput()->withErrors([
                    'step_proses' => 'Pick List Step Tipe Addition (Topping) harus berupa 1 (Reactive) atau 2 (Dispers).'
                ]);
            }
            $stepExists = Auxl::where('proses_id', $data['proses_id'])
                ->where('tipe', 'addition')
                ->where('step_proses', $targetStep)
                ->exists();

            if ($stepExists) {
                $stepLabel = $targetStep == 1 ? '1 - Reactive' : '2 - Dispers';
                return back()->withInput()->withErrors([
                    'step_proses' => "AUX Tipe Addition (Topping) untuk Step {$stepLabel} sudah pernah dibuat pada proses ini."
                ]);
            }
        }

        $data['step_proses'] = $data['step_proses'] ?? 1;
        $data['volume_litres'] = round(floatval($data['total_wt']) * floatval($data['liquor_ratio']), 2);

        return DB::transaction(function () use ($data) {
            $barcode = Auxl::generateBarcode();
            $data['barcode'] = $barcode;

            $auxl = Auxl::create($data);
            foreach ($data['details'] as $detail) {
                $auxl->details()->create($detail);
            }

            // Jika jenis reproses / perbaikan, trigger approval 2 step (FM lalu VP)
            if (in_array($auxl->jenis, ['reproses', 'perbaikan'])) {
                $this->createReprocessApproval($auxl);

                return redirect()
                    ->route('aux.index')
                    ->with('success', 'Data Auxl disimpan dengan status menunggu approval FM & VP. Barcode: ' . $barcode);
            }

            return redirect()
                ->route('aux.index')
                ->with('success', 'Data Auxl berhasil disimpan. Barcode: ' . $barcode);
        });
    }

    public function show($id)
    {
        $auxl = Auxl::with(['proses.details', 'proses.mesin', 'details'])->findOrFail($id);
        return view('auxl.show', compact('auxl'));
    }

    public function edit($id)
    {
        if (Auth::user()->role === 'scm') {
            abort(403, 'Unauthorized action.');
        }
        $auxl = Auxl::with(['proses.details', 'details'])->findOrFail($id);

        $allProses = Proses::with(['details', 'mesin'])
            ->withCount(['auxls as normal_aux_count' => function ($q) use ($id) {
                $q->where('tipe', 'normal')->where('id', '!=', $id);
            }])
            ->whereNull('selesai')
            ->orderByDesc('created_at')
            ->get();

        $prosesRunning = $allProses->filter(fn($p) => !is_null($p->mulai));
        $prosesPending = $allProses->filter(fn($p) => is_null($p->mulai));

        return view('auxl.edit', compact('auxl', 'prosesRunning', 'prosesPending'));
    }

    public function update(Request $request, $id)
    {
        if (Auth::user()->role === 'scm') {
            abort(403, 'Unauthorized action.');
        }
        $data = $request->validate([
            'proses_id'   => 'required|exists:proses,id',
            'jenis'       => 'required|in:normal,reproses,perbaikan',
            'tipe'        => 'required|in:normal,addition',
            'step_proses' => 'nullable|integer|in:1,2,3',
            'liquor_ratio' => 'required|numeric|min:0.1',
            'total_wt'    => 'required|numeric|min:0',
            'volume_litres' => 'required|numeric|min:0',
            'code'        => 'required|string',
            'konstruksi'  => 'nullable|string',
            'customer'    => 'nullable|string',
            'marketing'   => 'nullable|string',
            'date'        => 'nullable|date',
            'color'       => 'nullable|string',
            'barcode'     => 'nullable|string',
            'details'     => 'required|array|min:1',
            'details.*.auxiliary'   => 'required|string',
            'details.*.konsentrasi' => 'required|numeric|min:0',
        ]);

        $auxl = Auxl::findOrFail($id);
        $proses = Proses::findOrFail($data['proses_id']);
        $maxQty = (int) ($proses->qty_aux ?? 0);
        if ($data['tipe'] === 'normal' && $maxQty < 1) {
            return back()->withInput()->withErrors([
                'proses_id' => 'Proses ini memiliki QTY AUX = 0 (tidak memerlukan AUX Normal).'
            ]);
        }
        if ($maxQty < 1) {
            $maxQty = 1;
        }

        if ($maxQty > 1 && empty($data['step_proses'])) {
            return back()->withInput()->withErrors([
                'step_proses' => 'Pick List Step wajib dipilih ketika QTY Aux lebih dari 1.'
            ]);
        }

        if ($data['tipe'] === 'normal') {
            $existingNormalCount = Auxl::where('proses_id', $data['proses_id'])
                ->where('tipe', 'normal')
                ->where('id', '!=', $id)
                ->count();

            if ($existingNormalCount >= $maxQty) {
                return back()->withInput()->withErrors([
                    'tipe' => "AUX Type Normal untuk proses ini sudah mencapai batas maksimum ({$maxQty}x)."
                ]);
            }

            $targetStep = $data['step_proses'] ?? 1;
            $stepExists = Auxl::where('proses_id', $data['proses_id'])
                ->where('tipe', 'normal')
                ->where('step_proses', $targetStep)
                ->where('id', '!=', $id)
                ->exists();

            if ($stepExists) {
                return back()->withInput()->withErrors([
                    'step_proses' => "AUX Type Normal untuk Step {$targetStep} sudah pernah dibuat pada proses ini."
                ]);
            }
        } elseif ($data['tipe'] === 'addition') {
            if (empty($data['step_proses'])) {
                return back()->withInput()->withErrors([
                    'step_proses' => 'Pick List Step (1 - Reactive / 2 - Dispers) wajib dipilih untuk Tipe Addition (Topping).'
                ]);
            }
            $targetStep = (int) $data['step_proses'];
            if (!in_array($targetStep, [1, 2])) {
                return back()->withInput()->withErrors([
                    'step_proses' => 'Pick List Step Tipe Addition (Topping) harus berupa 1 (Reactive) atau 2 (Dispers).'
                ]);
            }
            $stepExists = Auxl::where('proses_id', $data['proses_id'])
                ->where('tipe', 'addition')
                ->where('step_proses', $targetStep)
                ->where('id', '!=', $id)
                ->exists();

            if ($stepExists) {
                $stepLabel = $targetStep == 1 ? '1 - Reactive' : '2 - Dispers';
                return back()->withInput()->withErrors([
                    'step_proses' => "AUX Tipe Addition (Topping) untuk Step {$stepLabel} sudah pernah dibuat pada proses ini."
                ]);
            }
        }

        $data['step_proses'] = $data['step_proses'] ?? 1;
        $data['volume_litres'] = round(floatval($data['total_wt']) * floatval($data['liquor_ratio']), 2);

        return DB::transaction(function () use ($auxl, $data) {
            $auxl->update($data);
            // Hapus detail lama, simpan ulang
            $auxl->details()->delete();
            foreach ($data['details'] as $detail) {
                $auxl->details()->create($detail);
            }
            return redirect()->route('aux.index')->with('success', 'Data Auxl berhasil diupdate.');
        });
    }

    public function print($id)
    {
        $auxl = Auxl::with(['proses.details', 'proses.mesin', 'details'])->findOrFail($id);
        return view('auxl.print', compact('auxl'));
    }

    public function printBulk(Request $request)
    {
        $rawIds = $request->query('ids', '');
        $ids = array_filter(explode(',', $rawIds));

        $auxls = Auxl::with(['proses.details', 'proses.mesin', 'details'])
            ->whereIn('id', $ids)
            ->get();

        if ($auxls->isEmpty()) {
            return redirect()->route('aux.index')->with('error', 'Tidak ada data AUX yang dipilih.');
        }

        return view('auxl.print_bulk', compact('auxls'));
    }

    public function destroy($id)
    {
        $auxl = Auxl::findOrFail($id);

        // Cek apakah barcode AUX sudah di-scan di proses (BarcodeAux aktif)
        $isUsed = BarcodeAux::where('barcode', $auxl->barcode)
            ->where('cancel', false)
            ->exists();

        if ($isUsed) {
            return redirect()->route('aux.index')->with('error', 'Data Auxl tidak dapat dihapus karena barcode sudah di-scan pada proses.');
        }

        DB::transaction(function () use ($auxl) {
            $auxl->details()->delete();
            $auxl->delete();
        });

        return redirect()->route('aux.index')->with('success', 'Data Auxl berhasil dihapus.');
    }

    /**
     * Membuat approval awal untuk alur reproses:
     * 1) Pending FM
     * 2) Setelah FM approve, otomatis dibuat approval VP (di ApprovalController)
     */
    private function createReprocessApproval(Auxl $auxl): void
    {
        // Cegah duplikasi approval FM yang masih pending
        $existingPending = Approval::where('auxl_id', $auxl->id)
            ->where('action', 'create_aux_reprocess')
            ->where('type', 'FM')
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return;
        }

        Approval::create([
            'auxl_id'     => $auxl->id,
            'status'      => 'pending',
            'type'        => 'FM',
            'action'      => 'create_aux_reprocess',
            'history_data'=> [
                'auxl_snapshot' => $auxl->toArray(),
                'details'       => $auxl->details()->get()->toArray(),
            ],
            'requested_by'=> Auth::id(),
        ]);
    }

    /**
     * Proxy untuk Select2 auxiliary dari API eksternal.
     * Route: POST /api/proxy-auxiliary
     */
    public function proxyAuxiliary(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $cacheKey = 'proxy_sap:auxiliary:' . md5($q);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request(
                'POST',
                SapApi::url('zterima_zchm'),
                SapApi::proxyGuzzleOptions(['body' => '"' . $q . '"'])
            );
            $data = json_decode($response->getBody(), true);
            $results = collect($data)
                ->filter(fn ($item) => isset($item['matnr']))
                ->map(fn ($item) => [
                    'id' => $item['matnr'],
                    'text' => $item['matnr'],
                ])
                ->values()
                ->all();
            $payload = ['results' => $results];
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }

    /**
     * Proxy untuk Select2 customer dari API SAP.
     * Route: POST /api/proxy-customer
     */
    public function proxyCustomerSearch(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $cacheKey = 'proxy_sap:customer:' . md5($q);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request(
                'POST',
                SapApi::url('zterima_cstmr'),
                SapApi::proxyGuzzleOptions(['body' => json_encode($q)])
            );
            $data = json_decode($response->getBody(), true);
            if (!is_array($data)) {
                return response()->json(['results' => []]);
            }
            $results = collect($data)
                ->filter(fn ($item) => isset($item['customer']))
                ->map(fn ($item) => [
                    'id' => $item['customer'],
                    'text' => $item['customer'],
                ])
                ->unique('id')
                ->values()
                ->all();
            $payload = ['results' => $results];
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }

    public function proxyMarketingSearch(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $cacheKey = 'proxy_sap:marketing:' . md5($q);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request(
                'POST',
                SapApi::url('zterima_mkt'),
                SapApi::proxyGuzzleOptions(['body' => json_encode($q)])
            );
            $data = json_decode($response->getBody(), true);
            if (!is_array($data)) {
                return response()->json(['results' => []]);
            }
            $results = collect($data)
                ->filter(fn ($item) => isset($item['marketing']))
                ->map(fn ($item) => [
                    'id' => $item['marketing'],
                    'text' => $item['marketing'],
                ])
                ->unique('id')
                ->values()
                ->all();
            $payload = ['results' => $results];
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }
}
