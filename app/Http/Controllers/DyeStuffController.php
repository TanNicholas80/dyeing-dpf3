<?php

namespace App\Http\Controllers;

use App\Models\DyeStuff;
use App\Models\DyeStuffDetail;
use App\Models\BarcodeLa;
use App\Models\Proses;
use App\Models\DetailProses;
use App\Models\BarcodeKain;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DyeStuffController extends Controller
{
    public function index()
    {
        $dyeStuffs = DyeStuff::with(['proses.details', 'proses.mesin', 'details'])->orderByDesc('created_at')->get();

        // Tandai DyeStuff yang sudah dipakai proses (ter-scan sebagai Barcode LA aktif).
        $usageCountsByBarcode = collect();
        if ($dyeStuffs->isNotEmpty()) {
            $barcodes = $dyeStuffs->pluck('barcode')->filter()->unique()->values();
            if ($barcodes->isNotEmpty()) {
                $usageCountsByBarcode = BarcodeLa::whereIn('barcode', $barcodes)
                    ->where('cancel', false)
                    ->selectRaw('barcode, COUNT(*) as total')
                    ->groupBy('barcode')
                    ->pluck('total', 'barcode');
            }
        }

        // Tandai dye stuff yang masih menunggu approval (FM atau VP) untuk jenis reproses / perbaikan
        if ($dyeStuffs->isNotEmpty()) {
            $pendingApprovals = Approval::whereIn('dyestuff_id', $dyeStuffs->pluck('id'))
                ->where('action', 'create_la_reprocess')
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('dyestuff_id');

            $dyeStuffs->transform(function ($item) use ($pendingApprovals) {
                $itemCollection = $pendingApprovals->get($item->id);
                $item->pendingApproval = $itemCollection ? $itemCollection->first() : null;
                return $item;
            });
        }

        $dyeStuffs->transform(function ($item) use ($usageCountsByBarcode) {
            $usedCount = (int) ($usageCountsByBarcode[$item->barcode] ?? 0);
            $item->isUsedByProses = $usedCount > 0;
            $item->usedCount = $usedCount;
            return $item;
        });

        return view('dye_stuff.index', compact('dyeStuffs'));
    }

    public function create()
    {
        if (Auth::user()->role === 'scm') {
            abort(403, 'Unauthorized action.');
        }

        $allProses = Proses::with(['details', 'mesin'])
            ->withCount(['dyeStuffs as normal_dyestuff_count' => function ($q) {
                $q->where('tipe', 'normal')->where('cancel', false);
            }])
            ->whereNull('selesai')
            ->orderByDesc('created_at')
            ->get();

        // Pisahkan menjadi Sedang Berjalan dan Belum Berjalan
        $prosesRunning = $allProses->filter(fn($p) => !is_null($p->mulai));
        $prosesPending = $allProses->filter(fn($p) => is_null($p->mulai));

        return view('dye_stuff.create', compact('prosesRunning', 'prosesPending'));
    }

    public function store(Request $request)
    {
        if (Auth::user()->role === 'scm') {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'proses_id'            => 'required|exists:proses,id',
            'tipe'                 => 'required|in:normal,additional',
            'jenis'                => 'required|in:normal,reproses,perbaikan',
            'liquor_ratio'         => 'required|numeric|min:0.1',
            'total_wt'             => 'required|numeric|min:0',
            'volume_litres'        => 'required|numeric|min:0',
            'step_proses'          => 'nullable|integer|in:1,2,3',
            'details'              => 'required|array|min:1',
            'details.*.chemical_name' => 'required|string',
            'details.*.konsentrasi'   => 'required|numeric|min:0',
            'details.*.weight'        => 'required|numeric|min:0',
            'details.*.unit'          => 'nullable|string',
            'details.*.remark'        => 'nullable|string',
        ]);

        $proses = Proses::findOrFail($data['proses_id']);
        $maxQty = (int) ($proses->qty_dye_stuff ?? 1);
        if ($maxQty < 1) {
            $maxQty = 1;
        }

        if ($maxQty > 1 && empty($data['step_proses'])) {
            return back()->withInput()->withErrors([
                'step_proses' => 'Pick List Step wajib dipilih ketika QTY Dye Stuff lebih dari 1.'
            ]);
        }

        // Cek kuota dan keunikan step untuk Dye Stuff Type Normal
        if ($data['tipe'] === 'normal') {
            $existingNormalCount = DyeStuff::where('proses_id', $data['proses_id'])
                ->where('tipe', 'normal')
                ->where('cancel', false)
                ->count();

            if ($existingNormalCount >= $maxQty) {
                return back()->withInput()->withErrors([
                    'tipe' => "Dye Stuff Type Normal untuk proses ini sudah mencapai batas maksimum ({$maxQty}x)."
                ]);
            }

            $targetStep = $data['step_proses'] ?? 1;
            $stepExists = DyeStuff::where('proses_id', $data['proses_id'])
                ->where('tipe', 'normal')
                ->where('step_proses', $targetStep)
                ->where('cancel', false)
                ->exists();

            if ($stepExists) {
                return back()->withInput()->withErrors([
                    'step_proses' => "Dye Stuff Type Normal untuk Step {$targetStep} sudah pernah dibuat pada proses ini."
                ]);
            }
        } elseif ($data['tipe'] === 'additional') {
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
            $stepExists = DyeStuff::where('proses_id', $data['proses_id'])
                ->where('tipe', 'additional')
                ->where('step_proses', $targetStep)
                ->where('cancel', false)
                ->exists();

            if ($stepExists) {
                $stepLabel = $targetStep == 1 ? '1 - Reactive' : '2 - Dispers';
                return back()->withInput()->withErrors([
                    'step_proses' => "Dye Stuff Tipe Addition (Topping) untuk Step {$stepLabel} sudah pernah dibuat pada proses ini."
                ]);
            }
        }

        return DB::transaction(function () use ($data) {
            $barcode = DyeStuff::generateBarcode();

            $dyeStuff = DyeStuff::create([
                'proses_id'     => $data['proses_id'],
                'barcode'       => $barcode,
                'tipe'          => $data['tipe'],
                'jenis'         => $data['jenis'],
                'liquor_ratio'  => $data['liquor_ratio'],
                'total_wt'      => $data['total_wt'],
                'volume_litres' => $data['volume_litres'],
                'step_proses'   => $data['step_proses'] ?? 1,
            ]);

            foreach ($data['details'] as $detail) {
                $dyeStuff->details()->create([
                    'chemical_name' => $detail['chemical_name'],
                    'konsentrasi'   => $detail['konsentrasi'],
                    'weight'        => $detail['weight'],
                    'unit'          => $detail['unit'] ?? 'g',
                    'remark'        => $detail['remark'] ?? null,
                ]);
            }

            // Jika jenis reproses / perbaikan, trigger approval 2 step (FM lalu VP)
            if (in_array($dyeStuff->jenis, ['reproses', 'perbaikan'])) {
                $this->createReprocessApproval($dyeStuff);

                return redirect()
                    ->route('dye-stuff.index')
                    ->with('success', 'Data Dye Stuff disimpan dengan status menunggu approval FM & VP. Barcode: ' . $barcode);
            }

            return redirect()
                ->route('dye-stuff.index')
                ->with('success', 'Data Dye Stuff berhasil disimpan. Barcode: ' . $barcode);
        });
    }

    public function show($id)
    {
        $dyeStuff = DyeStuff::with(['proses.details', 'proses.mesin', 'details'])->findOrFail($id);
        return view('dye_stuff.show', compact('dyeStuff'));
    }

    public function edit($id)
    {
        if (Auth::user()->role === 'scm') {
            abort(403, 'Unauthorized action.');
        }

        $dyeStuff = DyeStuff::with('details')->findOrFail($id);

        $allProses = Proses::with(['details', 'mesin'])
            ->withCount(['dyeStuffs as normal_dyestuff_count' => function ($q) use ($id) {
                $q->where('tipe', 'normal')->where('cancel', false)->where('id', '!=', $id);
            }])
            ->whereNull('selesai')
            ->orderByDesc('created_at')
            ->get();

        $prosesRunning = $allProses->filter(fn($p) => !is_null($p->mulai));
        $prosesPending = $allProses->filter(fn($p) => is_null($p->mulai));

        return view('dye_stuff.edit', compact('dyeStuff', 'prosesRunning', 'prosesPending'));
    }

    public function update(Request $request, $id)
    {
        if (Auth::user()->role === 'scm') {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'proses_id'            => 'required|exists:proses,id',
            'tipe'                 => 'required|in:normal,additional',
            'jenis'                => 'required|in:normal,reproses,perbaikan',
            'liquor_ratio'         => 'required|numeric|min:0.1',
            'total_wt'             => 'required|numeric|min:0',
            'volume_litres'        => 'required|numeric|min:0',
            'step_proses'          => 'nullable|integer|in:1,2,3',
            'details'              => 'required|array|min:1',
            'details.*.chemical_name' => 'required|string',
            'details.*.konsentrasi'   => 'required|numeric|min:0',
            'details.*.weight'        => 'required|numeric|min:0',
            'details.*.unit'          => 'nullable|string',
            'details.*.remark'        => 'nullable|string',
        ]);

        $dyeStuff = DyeStuff::findOrFail($id);

        $proses = Proses::findOrFail($data['proses_id']);
        $maxQty = (int) ($proses->qty_dye_stuff ?? 1);
        if ($maxQty < 1) {
            $maxQty = 1;
        }

        if ($maxQty > 1 && empty($data['step_proses'])) {
            return back()->withInput()->withErrors([
                'step_proses' => 'Pick List Step wajib dipilih ketika QTY Dye Stuff lebih dari 1.'
            ]);
        }

        // Cek kuota dan keunikan step untuk Dye Stuff Type Normal (abaikan ID yang sedang diedit)
        if ($data['tipe'] === 'normal') {
            $existingNormalCount = DyeStuff::where('proses_id', $data['proses_id'])
                ->where('tipe', 'normal')
                ->where('cancel', false)
                ->where('id', '!=', $id)
                ->count();

            if ($existingNormalCount >= $maxQty) {
                return back()->withInput()->withErrors([
                    'tipe' => "Dye Stuff Type Normal untuk proses ini sudah mencapai batas maksimum ({$maxQty}x)."
                ]);
            }

            $targetStep = $data['step_proses'] ?? 1;
            $stepExists = DyeStuff::where('proses_id', $data['proses_id'])
                ->where('tipe', 'normal')
                ->where('step_proses', $targetStep)
                ->where('cancel', false)
                ->where('id', '!=', $id)
                ->exists();

            if ($stepExists) {
                return back()->withInput()->withErrors([
                    'step_proses' => "Dye Stuff Type Normal untuk Step {$targetStep} sudah pernah dibuat pada proses ini."
                ]);
            }
        } elseif ($data['tipe'] === 'additional') {
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
            $stepExists = DyeStuff::where('proses_id', $data['proses_id'])
                ->where('tipe', 'additional')
                ->where('step_proses', $targetStep)
                ->where('cancel', false)
                ->where('id', '!=', $id)
                ->exists();

            if ($stepExists) {
                $stepLabel = $targetStep == 1 ? '1 - Reactive' : '2 - Dispers';
                return back()->withInput()->withErrors([
                    'step_proses' => "Dye Stuff Tipe Addition (Topping) Untuk Step {$stepLabel} sudah pernah dibuat pada proses ini."
                ]);
            }
        }

        DB::transaction(function () use ($dyeStuff, $data) {
            $dyeStuff->update([
                'proses_id'     => $data['proses_id'],
                'tipe'          => $data['tipe'],
                'jenis'         => $data['jenis'],
                'liquor_ratio'  => $data['liquor_ratio'],
                'total_wt'      => $data['total_wt'],
                'volume_litres' => $data['volume_litres'],
                'step_proses'   => $data['step_proses'] ?? 1,
            ]);

            $dyeStuff->details()->delete();

            foreach ($data['details'] as $detail) {
                $dyeStuff->details()->create([
                    'chemical_name' => $detail['chemical_name'],
                    'konsentrasi'   => $detail['konsentrasi'],
                    'weight'        => $detail['weight'],
                    'unit'          => $detail['unit'] ?? 'g',
                    'remark'        => $detail['remark'] ?? null,
                ]);
            }
        });

        return redirect()->route('dye-stuff.index')->with('success', 'Data Dye Stuff berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $dyeStuff = DyeStuff::findOrFail($id);
        $dyeStuff->delete();
        return redirect()->route('dye-stuff.index')->with('success', 'Data Dye Stuff berhasil dihapus.');
    }

    public function print($id)
    {
        $dyeStuff = DyeStuff::with(['proses.details', 'proses.mesin', 'details'])->findOrFail($id);
        return view('dye_stuff.print', compact('dyeStuff'));
    }

    public function printBulk(Request $request)
    {
        $rawIds = $request->query('ids', '');
        $ids = array_filter(explode(',', $rawIds));

        $dyeStuffs = DyeStuff::with(['proses.details', 'proses.mesin', 'details'])
            ->whereIn('id', $ids)
            ->get();

        if ($dyeStuffs->isEmpty()) {
            return redirect()->route('dye-stuff.index')->with('error', 'Tidak ada data Dye Stuff yang dipilih.');
        }

        return view('dye_stuff.print_bulk', compact('dyeStuffs'));
    }

    public function getProsesInfo(Request $request, $id)
    {
        $proses = Proses::with(['details', 'mesin'])->find($id);
        if (!$proses) {
            return response()->json(['error' => 'Proses tidak ditemukan'], 404);
        }

        $detailList = $proses->details;
        $firstDetail = $detailList->first();

        // 1. Total WT (Kg): Sum QTY per OP dari DetailProses (Multiple OP dijumlahkan, Single OP ambil 1 qty)
        $totalWt = (float) $detailList->sum('qty');

        // Fallback jika QTY di DetailProses 0, ambil dari BarcodeKain
        if ($totalWt <= 0 && $detailList->isNotEmpty()) {
            $detailIds = $detailList->pluck('id');
            $totalWt = (float) BarcodeKain::whereIn('detail_proses_id', $detailIds)
                ->where('cancel', false)
                ->sum('qty_gi');
        }

        // 2. Jenis Dye Stuff Otomatis berdasarkan Planning Proses:
        // - Jika jenis proses === 'produksi' -> 'normal' (Normal)
        // - Jika jenis proses === 'reproses' & mode === 'greige' -> 'perbaikan' (Perbaikan BDP)
        // - Jika jenis proses === 'reproses' & mode === 'finish' -> 'reproses' (Reproses FG)
        $autoJenis = 'normal';
        $pJenis = strtolower($proses->jenis ?? 'produksi');
        $pMode = strtolower($proses->mode ?? '');

        if ($pJenis === 'produksi') {
            $autoJenis = 'normal';
        } elseif ($pJenis === 'reproses') {
            if ($pMode === 'greige') {
                $autoJenis = 'perbaikan';
            } elseif ($pMode === 'finish') {
                $autoJenis = 'reproses';
            } else {
                $autoJenis = 'reproses';
            }
        }

        // 3. QTY Dye Stuff & Existing Normal Dye Stuffs
        $qtyDyeStuff = (int) ($proses->qty_dye_stuff ?? 1);
        if ($qtyDyeStuff < 1) {
            $qtyDyeStuff = 1;
        }

        $excludeId = $request->query('exclude_id');

        $existingNormalQuery = DyeStuff::where('proses_id', $id)
            ->where('tipe', 'normal')
            ->where('cancel', false);

        if ($excludeId) {
            $existingNormalQuery->where('id', '!=', $excludeId);
        }

        $existingNormalCount = $existingNormalQuery->count();
        $usedNormalSteps = $existingNormalQuery->pluck('step_proses')
            ->map(fn($v) => (int)$v)
            ->filter()
            ->values()
            ->all();

        $canCreateNormal = $existingNormalCount < $qtyDyeStuff;

        // 4. QTY Aux & Existing Normal Auxls
        $qtyAux = (int) ($proses->qty_aux ?? 1);
        if ($qtyAux < 1) {
            $qtyAux = 1;
        }

        $excludeAuxId = $request->query('exclude_aux_id');

        $existingNormalAuxQuery = \App\Models\Auxl::where('proses_id', $id)
            ->where('tipe', 'normal');

        if ($excludeAuxId) {
            $existingNormalAuxQuery->where('id', '!=', $excludeAuxId);
        }

        $existingNormalAuxCount = $existingNormalAuxQuery->count();
        $usedNormalAuxSteps = $existingNormalAuxQuery->pluck('step_proses')
            ->map(fn($v) => (int)$v)
            ->filter()
            ->values()
            ->all();

        $canCreateNormalAux = $existingNormalAuxCount < $qtyAux;

        $existingAdditionQuery = DyeStuff::where('proses_id', $id)
            ->where('tipe', 'additional')
            ->where('cancel', false);
        if ($excludeId) {
            $existingAdditionQuery->where('id', '!=', $excludeId);
        }
        $usedAdditionSteps = $existingAdditionQuery->pluck('step_proses')
            ->map(fn($v) => (int)$v)
            ->filter()
            ->values()
            ->all();

        $existingAdditionAuxQuery = \App\Models\Auxl::where('proses_id', $id)
            ->where('tipe', 'addition');
        if ($excludeAuxId) {
            $existingAdditionAuxQuery->where('id', '!=', $excludeAuxId);
        }
        $usedAdditionAuxSteps = $existingAdditionAuxQuery->pluck('step_proses')
            ->map(fn($v) => (int)$v)
            ->filter()
            ->values()
            ->all();

        $statusLabel = !is_null($proses->selesai) ? 'Selesai' : (!is_null($proses->mulai) ? 'Sedang Berjalan' : 'Belum Berjalan');

        return response()->json([
            'id'                        => $proses->id,
            'no_jo'                     => $firstDetail->no_op ?? '-',
            'no_partai'                 => $firstDetail->no_partai ?? '-',
            'customer'                  => $firstDetail->customer ?? '-',
            'marketing'                 => $firstDetail->marketing ?? '-',
            'material'                  => $firstDetail->konstruksi ?? '-',
            'color'                     => $firstDetail->warna ?? $firstDetail->color ?? '-',
            'mesin'                     => $proses->mesin->jenis_mesin ?? '-',
            'status_proses'             => $statusLabel,
            'auto_jenis'                => $autoJenis,
            'qty_dye_stuff'             => $qtyDyeStuff,
            'existing_normal_count'     => $existingNormalCount,
            'used_normal_steps'         => $usedNormalSteps,
            'can_create_normal'         => $canCreateNormal,
            'qty_aux'                   => $qtyAux,
            'existing_normal_aux_count' => $existingNormalAuxCount,
            'used_normal_aux_steps'     => $usedNormalAuxSteps,
            'can_create_normal_aux'     => $canCreateNormalAux,
            'used_addition_steps'       => $usedAdditionSteps,
            'used_addition_aux_steps'   => $usedAdditionAuxSteps,
            'total_wt'                  => $totalWt,
            'step_count'                => $detailList->count(),
            'all_no_op'                 => $detailList->pluck('no_op')->filter()->implode(', '),
        ]);
    }

    /**
     * Membuat approval awal untuk alur reproses Dye Stuff (LA):
     * 1) Pending FM
     * 2) Setelah FM approve, otomatis dibuat approval VP (di ApprovalController)
     */
    private function createReprocessApproval(DyeStuff $dyeStuff): void
    {
        $existingPending = Approval::where('dyestuff_id', $dyeStuff->id)
            ->where('action', 'create_la_reprocess')
            ->where('type', 'FM')
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return;
        }

        Approval::create([
            'dyestuff_id'  => $dyeStuff->id,
            'status'       => 'pending',
            'type'         => 'FM',
            'action'       => 'create_la_reprocess',
            'history_data' => [
                'dyestuff_snapshot' => $dyeStuff->toArray(),
                'details'           => $dyeStuff->details()->get()->toArray(),
            ],
            'requested_by' => Auth::id(),
        ]);
    }
}
