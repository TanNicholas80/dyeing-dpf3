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
use App\Services\MesinCacheService;
use App\Services\ProsesStatusService;
use App\Support\SapApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
     * Cek apakah proses lama boleh diabaikan saat validasi reuse OP/Partai untuk Produksi.
     * Boleh diabaikan jika:
     * - Proses sudah soft delete
     * - Belum memiliki end cycle time (selesai = null)
     * - Penghapusan berasal dari approval flow:
     *   a) create_reprocess ditolak FM/VP, atau
     *   b) delete_proses disetujui.
     */
    private function isReusableDeletedByApprovalFlow(?Proses $proses): bool
    {
        if (!$proses) {
            return false;
        }

        if (!method_exists($proses, 'trashed') || !$proses->trashed()) {
            return false;
        }

        if ($proses->selesai !== null) {
            return false;
        }

        $deletedByRejectedCreateReprocess = Approval::where('proses_id', $proses->id)
            ->where('action', 'create_reprocess')
            ->whereIn('type', ['FM', 'VP'])
            ->where('status', 'rejected')
            ->exists();

        if ($deletedByRejectedCreateReprocess) {
            return true;
        }

        $deletedByApprovedDelete = Approval::where('proses_id', $proses->id)
            ->where('action', 'delete_proses')
            ->where('status', 'approved')
            ->exists();

        return $deletedByApprovedDelete;
    }

    /**
     * Ambil DetailProses yang masih memblokir reuse OP/Partai.
     */
    private function getBlockingDetailsForProduksiReuse($existingDetails)
    {
        return $existingDetails->filter(function ($detail) {
            $proses = Proses::withTrashed()->find($detail->proses_id);

            // Jika proses tidak ditemukan, perlakukan sebagai blocking untuk aman.
            if (!$proses) {
                return true;
            }

            // Jika memenuhi rule reuse, detail ini tidak dianggap blocking.
            if ($this->isReusableDeletedByApprovalFlow($proses)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Jika mesin dalam status ON (1) dan tidak ada proses yang sedang berjalan,
     * mulai proses berikutnya sesuai antrian (order). Mirip logic trigger mesin_after_update_status.
     *
     * @param int $mesinId
     * @return Proses|null Proses yang baru dimulai, atau null jika tidak ada yang dimulai
     */
    private function startNextProsesIfMesinOn(int $mesinId): ?Proses
    {
        $mesin = Mesin::find($mesinId);
        if (!$mesin || (int) $mesin->status !== 1) {
            return null;
        }

        // Sudah ada proses yang sedang berjalan (mulai ada, selesai belum) → jangan mulai yang lain
        $adaProsesAktif = Proses::where('mesin_id', $mesinId)
            ->whereNotNull('mulai')
            ->whereNull('selesai')
            ->exists();
        if ($adaProsesAktif) {
            return null;
        }

        // Cari antrian proses: belum mulai, belum selesai, order > 0, urut order lalu id
        $antrian = Proses::where('mesin_id', $mesinId)
            ->whereNull('mulai')
            ->whereNull('selesai')
            ->where('order', '>', 0)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        if ($antrian->isEmpty()) {
            return null;
        }

        $prosesSelanjutnya = null;
        foreach ($antrian as $candidate) {
            // Khusus create_reprocess: selama masih pending FM/VP, proses di-skip dulu.
            $hasPendingCreateReprocess = Approval::where('proses_id', $candidate->id)
                ->where('status', 'pending')
                ->where('action', 'create_reprocess')
                ->whereIn('type', ['FM', 'VP'])
                ->exists();
            if ($hasPendingCreateReprocess) {
                continue;
            }

            // Untuk aksi perubahan umum: jika masih pending dan proses ini akan jalan, auto reject oleh sistem.
            $blockingPendingApprovals = Approval::where('proses_id', $candidate->id)
                ->where('status', 'pending')
                ->where('type', 'FM')
                ->whereIn('action', ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position'])
                ->get();
            foreach ($blockingPendingApprovals as $approval) {
                $note = (string) ($approval->note ?? '');
                $systemNote = 'Auto rejected by system: proses otomatis berjalan saat mesin ON.';
                $approval->status = 'rejected';
                $approval->note = $note !== '' ? ($note . ' | ' . $systemNote) : $systemNote;
                $approval->approved_by = null;
                $approval->save();
            }

            $prosesSelanjutnya = $candidate;
            break;
        }

        if (!$prosesSelanjutnya) {
            return null;
        }

        $prosesSelanjutnya->update([
            'mulai' => now(),
            'order' => 0,
        ]);

        // Renumber proses pending yang tersisa (1, 2, 3, ...)
        $pending = Proses::where('mesin_id', $mesinId)
            ->whereNull('mulai')
            ->whereNull('selesai')
            ->where('id', '!=', $prosesSelanjutnya->id)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $order = 1;
        foreach ($pending as $p) {
            $p->update(['order' => $order++]);
        }

        return $prosesSelanjutnya->fresh();
    }

    /**
     * Validasi apakah semua DetailProses dalam proses sudah memenuhi jumlah barcode kain sesuai roll.
     * Untuk Greige Reproses: barcode kain (G) tidak wajib, hanya D & A → skip validasi.
     * Untuk Finish Reproses ke-2+: barcode kain (F) tidak wajib → skip validasi.
     *
     * @param int $prosesId
     * @return array|null Returns null if valid, or array with error message if invalid
     */
    private function validateBarcodeKainCompleteness($prosesId)
    {
        $proses = Proses::find($prosesId);
        if (!$proses) {
            return ['error' => 'Proses tidak ditemukan.'];
        }

        if ($proses->isBarcodeKainOptionalForLaAux()) {
            return null; // Boleh scan LA/AUX tanpa wajib barcode kain lengkap
        }

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
        $mode = $request->input('mode', 'greige');
        $rules = [
            'mode' => 'nullable|in:greige,finish',
            'jenis' => 'required|in:Produksi,Maintenance,Reproses',
            'mesin_id' => 'required|exists:mesins,id',
            'cycle_time' => 'required',
        ];

        // Finish mode: hanya Reproses
        if ($mode === 'finish') {
            $rules['jenis'] = 'required|in:Reproses';
        }

        if ($request->jenis !== 'Maintenance') {
            $rules += [
                'jenis_op' => 'required|in:Single,Multiple',
                // Default: minimal 1 detail OP
                // Akan dioverride menjadi min:2 jika jenis_op = Multiple
                'details' => 'required|array|min:1',
                'details.*.no_op' => 'required|string|max:12',
                'details.*.no_partai' => 'required|string',
                'details.*.customer' => 'nullable|string',
                'details.*.marketing' => 'nullable|string',
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
         * - Format No OP menurut mode: Greige = kode awal 066, Finish = kode awal 010.
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
            $mode = $validated['mode'] ?? $request->input('mode', 'greige');

            // 0. Validasi format No OP menurut mode (Greige = 066, Finish = 010)
            $prefixGreige = '066';
            $prefixFinish = '010';
            foreach ($validated['details'] as $index => $detail) {
                $noOp = isset($detail['no_op']) ? trim((string) $detail['no_op']) : '';
                if ($noOp === '') {
                    continue;
                }
                if ($mode === 'finish') {
                    if (!str_starts_with($noOp, $prefixFinish)) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                "details.$index.no_op" => "No OP untuk mode Finish harus memiliki kode awal {$prefixFinish}. Contoh: {$prefixFinish}xxxxx. No OP yang dimasukkan: \"{$noOp}\".",
                            ]);
                    }
                } else {
                    // greige
                    if (!str_starts_with($noOp, $prefixGreige)) {
                        return back()
                            ->withInput()
                            ->withErrors([
                                "details.$index.no_op" => "No OP untuk mode Greige harus memiliki kode awal {$prefixGreige}. Contoh: {$prefixGreige}xxxxx. No OP yang dimasukkan: \"{$noOp}\".",
                            ]);
                    }
                }
            }

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
                    // Mode Greige - Reproses: No OP & No Partai harus pernah dipakai pada jenis proses Produksi
                    if ($mode === 'greige') {
                        $pernahProduksi = $existingDetails->contains(function ($d) {
                            return $d->proses && $d->proses->jenis === 'Produksi';
                        });
                        if (!$pernahProduksi) {
                            return back()
                                ->withInput()
                                ->withErrors([
                                    "details.$index.no_partai" =>
                                    "Kombinasi No OP \"{$noOp}\" + No Partai \"{$noPartai}\" belum pernah dipakai pada jenis proses Produksi. " .
                                    "Reproses hanya dapat dibuat untuk Detail OP yang pernah dipakai di Produksi.",
                                ]);
                        }
                    }
                } else {
                    $blockingDetails = $this->getBlockingDetailsForProduksiReuse($existingDetails);

                    if ($blockingDetails->isNotEmpty()) {
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
                'mode' => $validated['mode'] ?? 'greige',
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
                        'customer' => $detail['customer'] ?? null,
                        'marketing' => $detail['marketing'] ?? null,
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

            // Logic sama dengan trigger: jika mesin ON dan tidak ada proses berjalan, mulai proses berikutnya (urut order)
            $started = $this->startNextProsesIfMesinOn((int) $validated['mesin_id']);
            if ($started && $started->id !== $proses->id) {
                // Proses yang dimulai bukan proses baru kita → broadcast agar card proses itu update (mulai berjalan)
                $started->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
                $statusService = new ProsesStatusService();
                $affectedProsesIds = $statusService->getAffectedProsesIds();
                $statusData = $statusService->generateProsesStatus($started, $affectedProsesIds);
                event(new ProsesStatusUpdated($started->id, $statusData));
            }
            if ($started && $started->id === $proses->id) {
                $proses = $proses->fresh(); // Proses baru kita yang dimulai → pakai data terbaru (mulai terisi)
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

    public function create(MesinCacheService $mesinCache)
    {
        $mesins = $mesinCache->getSelectionList();
        return response()->json([
            'mesins' => $mesins
        ]);
    }

    public function proxyOpSearch(Request $request)
    {
        $no_op = trim((string) $request->input('no_op', ''));
        if ($no_op === '') {
            return response()->json(['results' => []]);
        }

        $cacheKey = 'proxy_sap:op:' . md5($no_op);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post(
                SapApi::url('zterima_op'),
                SapApi::guzzleOptions(['body' => json_encode($no_op)])
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
            $payload = [
                'results' => $unique_ops,
                'raw' => $data,
            ];
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
            return response()->json($payload);
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

        $blocking = $this->getBlockingDetailsForProduksiReuse($existing);

        if ($blocking->isEmpty()) {
            return response()->json(['ok' => true]);
        }

        return response()->json([
            'ok' => false,
            'message' => "No Partai \"{$noPartai}\" untuk No OP \"{$noOp}\" sudah dipakai di proses lain.",
        ]);
    }

    public function checkBarcodeActive(Request $request)
    {
        $barcode = $request->input('barcode');
        if (!$barcode) {
            return response()->json(['active' => false]);
        }

        // Trim ke 10 karakter seperti standar sistem
        $trimmed = substr(trim($barcode), 0, 10);

        $exists = BarcodeKain::where('barcode', $trimmed)
            ->where('cancel', false)
            ->exists();

        return response()->json([
            'active' => $exists,
            'barcode' => $trimmed
        ]);
    }

    // Simpan barcode kain (mendukung multi-barcode dalam 1 request)
    public function barcodeKain(Request $request, $id)
    {
        // Terima 'barcodes' (array) atau 'barcode' (single string) untuk backward-compat
        $rawBarcodes = $request->input('barcodes');
        if (!is_array($rawBarcodes) || empty($rawBarcodes)) {
            if ($request->filled('barcode')) {
                $rawBarcodes = [$request->input('barcode')];
            } else {
                $rawBarcodes = [];
            }
        }
        $request->merge(['barcodes' => $rawBarcodes]);

        $request->validate([
            'barcodes' => 'required|array|min:1',
            'barcodes.*' => 'required|string|max:255',
            'detail_proses_id' => 'nullable|exists:detail_proses,id',
        ]);

        try {
            $proses = Proses::findOrFail($id);

            // Trim tiap barcode ke 10 karakter dan dedupe di dalam batch
            $trimmedBarcodes = collect($rawBarcodes)
                ->map(fn($b) => substr(trim((string)$b), 0, 10))
                ->filter(fn($b) => $b !== '')
                ->unique()
                ->values()
                ->all();

            if (empty($trimmedBarcodes)) {
                $msg = 'Tidak ada barcode valid untuk disimpan.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard')->with('error', $msg);
            }

            Log::info('BarcodeKain: Barcodes received', [
                'original' => $rawBarcodes,
                'trimmed' => $trimmedBarcodes,
                'count' => count($trimmedBarcodes),
                'proses_id' => $id,
            ]);

            // Resolve detail proses
            $detailProses = null;
            if ($request->filled('detail_proses_id')) {
                $detailProses = DetailProses::where('id', $request->detail_proses_id)
                    ->where('proses_id', $id)
                    ->first();
            } else {
                $detailProses = DetailProses::where('proses_id', $id)->first();
            }
            if (!$detailProses) {
                $msg = 'Detail proses tidak ditemukan. Pastikan proses memiliki detail OP terlebih dahulu.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard')->with('error', $msg);
            }

            // Cek barcode di DB (termasuk yang di-cancel)
            $existingBarcodes = BarcodeKain::whereIn('barcode', $trimmedBarcodes)->get();
            $realDuplicates = $existingBarcodes->where('cancel', false)->pluck('barcode')->all();

            if (!empty($realDuplicates)) {
                $msg = 'Barcode sudah pernah digunakan dan aktif: ' . implode(', ', $realDuplicates);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard')->with('error', $msg);
            }

            // Identifikasi mana yang bisa diaktifkan kembali dan mana yang baru (perlu ke SAP)
            $reactivatable = $existingBarcodes->where('cancel', true);
            $reactivatedBarcodes = $reactivatable->pluck('barcode')->all();
            $barcodesToSap = array_diff($trimmedBarcodes, $existingBarcodes->pluck('barcode')->all());

            // Cek kapasitas roll
            $roll = (int) ($detailProses->roll ?? 0);
            $existingCount = BarcodeKain::where('detail_proses_id', $detailProses->id)
                ->where('cancel', false)
                ->count();
            $newCount = count($trimmedBarcodes);
            if ($roll > 0 && ($existingCount + $newCount) > $roll) {
                $msg = "Jumlah barcode melebihi kebutuhan roll untuk OP {$detailProses->no_op} "
                    . "({$existingCount} sudah tersimpan + {$newCount} baru > {$roll} roll).";
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard')->with('error', $msg);
            }

            $no_op = $detailProses->no_op;
            $no_partai = $detailProses->no_partai;
            $mesin_id = $proses->mesin_id;
            $item_op = $detailProses->item_op;

            $results = [];
            
            // 1. Proses Barcode Baru via SAP
            if (!empty($barcodesToSap)) {
                // Bangun payload SAP: {no_op}-{item_op}|{barcode};{barcode};{barcode}
                $payload = $no_op . '-' . $item_op . '|' . implode(';', $barcodesToSap);
                Log::info('BarcodeKain: Payload prepared (multi) for SAP', ['payload' => $payload]);

                $client = new \GuzzleHttp\Client();
                $body = json_encode($payload);
                $response = $client->post(
                    SapApi::url('zterima_data'),
                    SapApi::guzzleOptions(['body' => $body])
                );
                $data = json_decode($response->getBody(), true);
                Log::info('BarcodeKain: SAP API Response', ['response' => $data]);

                if (!is_array($data) || empty($data)) {
                    $errorMsg = 'Gagal validasi barcode baru: response SAP tidak valid';
                    Log::error('BarcodeKain: Invalid response', ['data' => $data]);
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $errorMsg], 400);
                    }
                    return back()->withInput()->with('error', $errorMsg);
                }

                // Petakan response SAP
                if (count($data) === count($barcodesToSap)) {
                    $barcodesToSapArray = array_values($barcodesToSap);
                    foreach ($barcodesToSapArray as $idx => $bc) {
                        $results[$bc] = $data[$idx] ?? [];
                    }
                } else {
                    $agg = $data[0];
                    foreach ($barcodesToSap as $bc) {
                        $results[$bc] = $agg;
                    }
                }

                // Periksa status tiap barcode SAP
                $sapErrors = [];
                foreach ($results as $bc => $r) {
                    $stats = is_array($r) ? ($r['stats'] ?? null) : null;
                    if ($stats !== 'success') {
                        $sapErrors[] = $bc . ': ' . ($stats ?: 'tidak dikenali');
                    }
                }
                if (!empty($sapErrors)) {
                    $errorMsg = 'Beberapa barcode baru ditolak SAP: ' . implode('; ', $sapErrors);
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $errorMsg], 400);
                    }
                    return redirect()->route('dashboard')->with('error', $errorMsg);
                }
            }

            // Semua sukses (baik reaktivasi maupun SAP): simpan ke DB dalam transaksi
            DB::beginTransaction();
            try {
                // A. Reaktivasi Barcode Lama
                foreach ($reactivatable as $bcObj) {
                    $bcObj->update([
                        'detail_proses_id' => $detailProses->id,
                        'no_op' => $no_op,
                        'no_partai' => $no_partai,
                        'mesin_id' => $mesin_id,
                        'cancel' => false,
                    ]);
                }

                // B. Simpan Barcode Baru dari SAP
                foreach ($barcodesToSap as $bc) {
                    $r = $results[$bc];
                    BarcodeKain::create([
                        'detail_proses_id' => $detailProses->id,
                        'no_op' => $no_op,
                        'no_partai' => $no_partai,
                        'barcode' => $bc,
                        'matdok' => $r['mblnr'] ?? null,
                        'item_document' => $r['zeile'] ?? null,
                        'qty_gi' => isset($r['menge']) ? (float)$r['menge'] : null,
                        'mesin_id' => $mesin_id,
                        'cancel' => false,
                    ]);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            Log::info('BarcodeKain: Successfully saved (multi)', [
                'count' => $newCount,
                'proses_id' => $proses->id,
            ]);

            // Broadcast event untuk update real-time
            $proses->refresh();
            $proses->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            $statusService = new ProsesStatusService();
            $affectedProsesIds = $statusService->getAffectedProsesIds();
            $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
            event(new BarcodeStatusUpdated($proses->id, $statusData));
            Cache::forget("iot:mesin:{$proses->mesin_id}:alarm_result");

            $msg = $newCount > 1
                ? "{$newCount} barcode kain berhasil disimpan!"
                : 'Barcode kain berhasil disimpan!';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $msg,
                    'saved_count' => $newCount,
                ]);
            }
            return redirect()->route('dashboard')->with('success', $msg);
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
            'approval_id' => 'nullable|exists:approvals,id',
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
            $approvalId = $request->approval_id ? (int) $request->approval_id : null;
            $userRole = Auth::user()->role ?? null;

            // Kepala Ruangan: hanya boleh input topping (harus ada approval_id yang valid)
            if (in_array($userRole, ['kepala_ruangan'])) {
                if (!$approvalId) {
                    $msg = 'Anda hanya dapat input barcode LA untuk topping yang sudah di-approve Kepala Shift.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 403);
                    }
                    return redirect()->route('dashboard')->with('error', $msg);
                }
                $approval = Approval::where('id', $approvalId)
                    ->where('proses_id', $id)
                    ->where('type', 'KEPALA_SHIFT')
                    ->where('action', 'topping_la')
                    ->where('status', 'approved')
                    ->first();
                if (!$approval) {
                    $msg = 'Approval topping LA tidak ditemukan atau belum di-approve.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard')->with('error', $msg);
                }
                if (BarcodeLa::where('approval_id', $approvalId)->where('cancel', false)->exists()) {
                    $msg = 'Barcode untuk topping LA ini sudah di-input.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard')->with('error', $msg);
                }
            }

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

            // Validasi: tolak scan LA jika kebutuhan awal + topping sudah terpenuhi
            $laInitialScanned = BarcodeLa::whereHas('detailProses', fn ($q) => $q->where('proses_id', $id))
                ->whereNull('approval_id')->where('cancel', false)->exists() ? 1 : 0;
            $laToppingReq = Approval::where('proses_id', $id)->where('type', 'KEPALA_SHIFT')
                ->where('action', 'topping_la')->where('status', 'approved')->count();
            $laToppingScn = Approval::where('proses_id', $id)->where('type', 'KEPALA_SHIFT')
                ->where('action', 'topping_la')->where('status', 'approved')
                ->whereHas('barcodeLas', fn ($q) => $q->where('cancel', false))->count();
            $laIsComplete = ($laInitialScanned + $laToppingScn) >= (1 + $laToppingReq);
            if ($laIsComplete) {
                $msg = 'Kebutuhan barcode LA (awal + topping) sudah terpenuhi. Tidak dapat menambah scan.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }
            if ($approvalId) {
                $alreadyHasBarcode = BarcodeLa::where('approval_id', $approvalId)->where('cancel', false)->exists();
                if ($alreadyHasBarcode) {
                    $msg = 'Barcode untuk topping LA ini sudah di-input.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
                }
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
                SapApi::url('zterima_kimia'),
                SapApi::guzzleOptions(['body' => $body])
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

            $matdok = $data[0]['mblnr'] ?? null;

            $barcodeLaData = [
                'detail_proses_id' => null,
                'no_op' => null,
                'no_partai' => null,
                'barcode' => $barcode,
                'matdok' => $matdok,
                'mesin_id' => $mesin_id,
                'cancel' => false,
                'approval_id' => $approvalId,
            ];
            // Untuk Multiple OP: barcode topping ditambahkan ke setiap OP (1 approval = 1 scan = tambah ke semua detail)
            foreach ($detailList as $detail) {
                $barcodeLaData['detail_proses_id'] = $detail->id;
                $barcodeLaData['no_op'] = $detail->no_op;
                $barcodeLaData['no_partai'] = $detail->no_partai;
                BarcodeLa::create($barcodeLaData);
            }

            // Broadcast event untuk update real-time
            $proses->refresh();
            $proses->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            $statusService = new ProsesStatusService();
            $affectedProsesIds = $statusService->getAffectedProsesIds();
            $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
            event(new BarcodeStatusUpdated($proses->id, $statusData));
            Cache::forget("iot:mesin:{$proses->mesin_id}:alarm_result");

            $msg = $approvalId ? 'Barcode LA topping berhasil disimpan!' : 'Barcode LA berhasil disimpan untuk semua OP pada proses ini!';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'success', 'message' => $msg]);
            }
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', $msg);
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
            'approval_id' => 'nullable|exists:approvals,id',
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
            $approvalId = $request->approval_id ? (int) $request->approval_id : null;
            $userRole = Auth::user()->role ?? null;

            // Kepala Ruangan: hanya boleh input topping (harus ada approval_id yang valid)
            if (in_array($userRole, ['kepala_ruangan'])) {
                if (!$approvalId) {
                    $msg = 'Anda hanya dapat input barcode AUX untuk topping yang sudah di-approve Kepala Shift.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 403);
                    }
                    return redirect()->route('dashboard')->with('error', $msg);
                }
                $approval = Approval::where('id', $approvalId)
                    ->where('proses_id', $id)
                    ->where('type', 'KEPALA_SHIFT')
                    ->where('action', 'topping_aux')
                    ->where('status', 'approved')
                    ->first();
                if (!$approval) {
                    $msg = 'Approval topping AUX tidak ditemukan atau belum di-approve.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard')->with('error', $msg);
                }
                if (BarcodeAux::where('approval_id', $approvalId)->where('cancel', false)->exists()) {
                    $msg = 'Barcode untuk topping AUX ini sudah di-input.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard')->with('error', $msg);
                }
            }

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

            // Validasi: tolak scan AUX jika kebutuhan awal + topping sudah terpenuhi
            $auxInitialScanned = BarcodeAux::whereHas('detailProses', fn ($q) => $q->where('proses_id', $id))
                ->whereNull('approval_id')->where('cancel', false)->exists() ? 1 : 0;
            $auxToppingReq = Approval::where('proses_id', $id)->where('type', 'KEPALA_SHIFT')
                ->where('action', 'topping_aux')->where('status', 'approved')->count();
            $auxToppingScn = Approval::where('proses_id', $id)->where('type', 'KEPALA_SHIFT')
                ->where('action', 'topping_aux')->where('status', 'approved')
                ->whereHas('barcodeAuxs', fn ($q) => $q->where('cancel', false))->count();
            $auxIsComplete = ($auxInitialScanned + $auxToppingScn) >= (1 + $auxToppingReq);
            if ($auxIsComplete) {
                $msg = 'Kebutuhan barcode AUX (awal + topping) sudah terpenuhi. Tidak dapat menambah scan.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 400);
                }
                return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
            }
            if ($approvalId) {
                $alreadyHasBarcode = BarcodeAux::where('approval_id', $approvalId)->where('cancel', false)->exists();
                if ($alreadyHasBarcode) {
                    $msg = 'Barcode untuk topping AUX ini sudah di-input.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 400);
                    }
                    return redirect()->route('dashboard', ['page' => $page])->with('error', $msg);
                }
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
                SapApi::url('zterima_kimia'),
                SapApi::guzzleOptions(['body' => $body])
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

            // Validasi Jenis Bahan untuk OP (bedakan kebutuhan awal vs topping AUX)
            $auxJenis = Auxl::where('barcode', $barcode)->value('jenis');
            $prosesJenis = $proses->jenis ?? null;
            $isToppingAux = !is_null($approvalId);
            foreach ($detailList as $detail) {
                Log::info('DEBUG validateOpByJenis', [
                    'barcode' => $barcode,
                    'aux_jenis' => $auxJenis,
                    'no_op' => $detail->no_op,
                    'proses_jenis' => $prosesJenis,
                    'is_topping_aux' => $isToppingAux,
                ]);

                $opValidation = $this->validateOpByJenis($auxJenis, $detail->no_op, $prosesJenis, $isToppingAux);

                if ($opValidation) {
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['status' => 'error', 'message' => $opValidation], 400);
                    }
                    return redirect()->route('dashboard', ['page' => $page])->with('error', $opValidation);
                }

                Log::info('BarcodeAux: Validasi OP/Jenis passed for detail', [
                    'barcode' => $barcode,
                    'proses_id' => $proses->id,
                    'no_op' => $detail->no_op,
                ]);
            }

            $matdok = $data[0]['mblnr'] ?? null;

            // Untuk Multiple OP: barcode topping ditambahkan ke setiap OP (1 approval = 1 scan = tambah ke semua detail)
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
                    'approval_id' => $approvalId,
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
            Cache::forget("iot:mesin:{$proses->mesin_id}:alarm_result");

            $msg = $approvalId ? 'Barcode AUX topping berhasil disimpan!' : 'Barcode AUX berhasil disimpan untuk semua OP pada proses ini!';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'success', 'message' => $msg]);
            }
            return redirect()->route('dashboard', ['page' => $page])
                ->with('success', $msg);
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

    /**
     * Validasi jenis AUX terhadap no_op dan jenis proses.
     * - AUX perbaikan: no_op 066; untuk Reproses boleh awal/topping, untuk Produksi hanya topping AUX
     * - AUX normal: hanya untuk kebutuhan awal pada no_op 066 dengan Proses jenis Produksi
     * - AUX reproses: hanya bisa dipakai pada no_op 010
     *
     * @param string $auxJenis Jenis AUX dari Auxl: 'normal', 'perbaikan', 'reproses'
     * @param string $noOp No OP dari DetailProses
     * @param string|null $prosesJenis Jenis Proses: 'Produksi', 'Reproses', 'Maintenance'
     * @param bool $isToppingAux True jika scan AUX untuk topping (approval_id terisi)
     */
    /**
     * Request topping LA - Kepala Ruangan menambah permintaan topping LA.
     * Constraint: barcode LA & AUX pertama sudah ada, tidak ada topping LA pending.
     * Untuk topping berikutnya: semua topping LA sebelumnya sudah approved dan di-scan.
     */
    public function requestToppingLa(Request $request, $id)
    {
        return $this->requestTopping($id, 'topping_la');
    }

    /**
     * Request topping AUX - Kepala Ruangan menambah permintaan topping AUX.
     */
    public function requestToppingAux(Request $request, $id)
    {
        return $this->requestTopping($id, 'topping_aux');
    }

    /**
     * Helper: buat approval topping (LA atau AUX).
     */
    private function requestTopping($prosesId, string $action)
    {
        $user = Auth::user();
        $role = $user->role ?? null;
        if (!in_array($role, ['super_admin', 'kepala_ruangan'])) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak memiliki akses untuk request topping.'], 403);
        }

        $proses = Proses::find($prosesId);
        if (!$proses) {
            return response()->json(['status' => 'error', 'message' => 'Proses tidak ditemukan.'], 404);
        }
        if ($proses->jenis === 'Maintenance') {
            return response()->json(['status' => 'error', 'message' => 'Proses Maintenance tidak mendukung topping LA/AUX.'], 400);
        }

        $detailList = DetailProses::where('proses_id', $prosesId)->get();
        if ($detailList->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Detail proses tidak ditemukan.'], 400);
        }

        $jenisOp = $proses->jenis_op ?? 'Single';
        $isMultipleOp = $jenisOp === 'Multiple' && $detailList->count() > 1;

        // Constraint: minimal 1 barcode LA dan 1 barcode AUX (cancel=false) di proses
        // Untuk Multiple OP: 1 barcode LA/AUX dipakai bersama untuk beberapa OP, cukup cek ada di minimal 1 detail
        $hasLa = false;
        $hasAux = false;
        foreach ($detailList as $detail) {
            if (BarcodeLa::where('detail_proses_id', $detail->id)->where('cancel', false)->exists()) {
                $hasLa = true;
            }
            if (BarcodeAux::where('detail_proses_id', $detail->id)->where('cancel', false)->exists()) {
                $hasAux = true;
            }
        }
        if (!$hasLa || !$hasAux) {
            return response()->json([
                'status' => 'error',
                'message' => 'Topping hanya boleh di-request setelah barcode LA dan AUX pertama sudah di-input.'
            ], 400);
        }

        // Tidak ada approval topping dengan status pending
        $pending = Approval::where('proses_id', $prosesId)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', $action)
            ->where('status', 'pending')
            ->exists();
        if ($pending) {
            $label = $action === 'topping_la' ? 'LA' : 'AUX';
            return response()->json([
                'status' => 'error',
                'message' => "Masih ada request topping {$label} yang menunggu approval Kepala Shift."
            ], 400);
        }

        // Untuk topping berikutnya: semua approval topping sebelumnya harus approved dan sudah ada barcode
        $previousApprovals = Approval::where('proses_id', $prosesId)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', $action)
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();
        foreach ($previousApprovals as $prev) {
            $hasBarcode = $action === 'topping_la'
                ? BarcodeLa::where('approval_id', $prev->id)->where('cancel', false)->exists()
                : BarcodeAux::where('approval_id', $prev->id)->where('cancel', false)->exists();
            if (!$hasBarcode) {
                $label = $action === 'topping_la' ? 'LA' : 'AUX';
                return response()->json([
                    'status' => 'error',
                    'message' => "Silakan scan barcode topping {$label} yang sudah di-approve terlebih dahulu sebelum request topping baru."
                ], 400);
            }
        }

        // Satu approval per proses: Kepala Shift hanya perlu approve sekali.
        // Untuk Multiple OP: satu approval berlaku untuk semua OP; saat scan, barcode akan ditambahkan ke setiap OP.
        $historyData = $isMultipleOp ? [
            'jenis_op' => 'Multiple',
            'detail_count' => $detailList->count(),
            'note' => 'Untuk Multiple OP: 1 approval berlaku untuk semua OP. Barcode topping akan ditambahkan ke setiap OP saat di-scan.',
        ] : null;

        Approval::create([
            'proses_id' => $prosesId,
            'status' => 'pending',
            'type' => 'KEPALA_SHIFT',
            'action' => $action,
            'history_data' => $historyData,
            'note' => null,
            'requested_by' => Auth::id(),
            'approved_by' => null,
        ]);

        event(new ApprovalPendingCreated([$prosesId]));

        $label = $action === 'topping_la' ? 'LA' : 'AUX';
        $msg = $isMultipleOp
            ? "Request topping {$label} berhasil dibuat untuk semua OP. Kepala Shift hanya perlu approve sekali."
            : "Request topping {$label} berhasil dibuat. Menunggu approval Kepala Shift.";
        return response()->json([
            'status' => 'success',
            'message' => $msg,
            'is_multiple_op' => $isMultipleOp,
        ]);
    }

    private function validateOpByJenis($auxJenis, $noOp, $prosesJenis = null, bool $isToppingAux = false)
    {
        Log::info('DEBUG validateOpByJenis', [
            'aux_jenis' => $auxJenis,
            'no_op' => $noOp,
            'proses_jenis' => $prosesJenis,
            'is_topping_aux' => $isToppingAux,
        ]);

        if ($auxJenis === 'reproses') {
            if (!str_starts_with((string)$noOp, '010')) {
                return 'AUX jenis Reproses hanya dapat dipakai pada No OP 010.';
            }
        }

        if ($auxJenis === 'perbaikan') {
            if (!str_starts_with((string)$noOp, '066')) {
                return 'AUX jenis Perbaikan hanya dapat dipakai pada No OP 066.';
            }
            // Aturan baru:
            // - Reproses: boleh untuk kebutuhan awal maupun topping
            // - Produksi: hanya boleh untuk topping AUX (bukan kebutuhan awal)
            if ($prosesJenis === 'Produksi') {
                if (!$isToppingAux) {
                    return 'Untuk proses Produksi (No OP 066), kebutuhan awal AUX harus jenis Normal. Jenis Perbaikan hanya boleh untuk topping AUX.';
                }
            } elseif ($prosesJenis !== 'Reproses') {
                return 'AUX jenis Perbaikan hanya dapat dipakai pada proses Reproses, atau pada proses Produksi khusus topping AUX (No OP 066).';
            }
        }

        if ($auxJenis === 'normal') {
            if (!str_starts_with((string)$noOp, '066')) {
                return 'AUX jenis Normal hanya dapat dipakai pada No OP 066 dengan jenis proses Produksi.';
            }
            if ($prosesJenis !== 'Produksi') {
                return 'AUX jenis Normal hanya dapat dipakai pada proses dengan jenis Produksi (No OP 066).';
            }
            if ($isToppingAux) {
                return 'AUX jenis Normal hanya untuk kebutuhan awal. Untuk topping AUX pada proses Produksi (No OP 066), gunakan AUX jenis Perbaikan.';
            }
        }

        return null;
    }


    // Optional: filter per detail dengan query param ?detail_proses_id=123
    public function barcodes(Request $request, $id)
    {
        $proses = Proses::findOrFail($id); // validasi proses ada

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

        // Cek apakah SEMUA DetailProses sudah memenuhi roll (atau barcode kain tidak wajib: Greige Reproses / Finish Reproses ke-2+)
        $barcodeKainOptional = $proses->isBarcodeKainOptionalForLaAux();
        $allComplete = $barcodeKainOptional
            ? true
            : collect($allBarcodeKainProgress)->every(function ($progress) {
                return $progress['is_complete'];
            });

        // Topping LA/AUX: data untuk indikator TD/TA, tombol request, dan scan
        $detailListForTopping = DetailProses::where('proses_id', $id)->get();
        $hasLa = false;
        $hasAux = false;
        foreach ($detailListForTopping as $d) {
            if (BarcodeLa::where('detail_proses_id', $d->id)->where('cancel', false)->exists()) {
                $hasLa = true;
            }
            if (BarcodeAux::where('detail_proses_id', $d->id)->where('cancel', false)->exists()) {
                $hasAux = true;
            }
        }

        $pendingToppingLa = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'pending')
            ->exists();
        $pendingToppingAux = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'pending')
            ->exists();
        $hasToppingLa = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
        $hasToppingAux = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        $approvedToppingLa = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->whereDoesntHave('barcodeLas', fn ($q) => $q->where('cancel', false))
            ->first();
        $approvedToppingAux = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->whereDoesntHave('barcodeAuxs', fn ($q) => $q->where('cancel', false))
            ->first();
        $approvedToppingLaNotScanned = !is_null($approvedToppingLa);
        $approvedToppingAuxNotScanned = !is_null($approvedToppingAux);
        $tdColor = $hasToppingLa ? ($pendingToppingLa ? 'yellow' : ($approvedToppingLaNotScanned ? 'red' : 'green')) : null;
        $taColor = $hasToppingAux ? ($pendingToppingAux ? 'yellow' : ($approvedToppingAuxNotScanned ? 'red' : 'green')) : null;

        $canRequestToppingLa = $hasLa && $hasAux && !$pendingToppingLa;
        if ($canRequestToppingLa) {
            $prevApprovedLa = Approval::where('proses_id', $id)
                ->where('type', 'KEPALA_SHIFT')
                ->where('action', 'topping_la')
                ->where('status', 'approved')
                ->orderBy('id')
                ->get();
            foreach ($prevApprovedLa as $p) {
                if (!BarcodeLa::where('approval_id', $p->id)->where('cancel', false)->exists()) {
                    $canRequestToppingLa = false;
                    break;
                }
            }
        }

        $canRequestToppingAux = $hasLa && $hasAux && !$pendingToppingAux;
        if ($canRequestToppingAux) {
            $prevApprovedAux = Approval::where('proses_id', $id)
                ->where('type', 'KEPALA_SHIFT')
                ->where('action', 'topping_aux')
                ->where('status', 'approved')
                ->orderBy('id')
                ->get();
            foreach ($prevApprovedAux as $p) {
                if (!BarcodeAux::where('approval_id', $p->id)->where('cancel', false)->exists()) {
                    $canRequestToppingAux = false;
                    break;
                }
            }
        }

        // Hitung progress LA: 1 awal + topping yang di-approve
        $laInitialScanned = BarcodeLa::whereHas('detailProses', fn ($q) => $q->where('proses_id', $id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->exists() ? 1 : 0;
        $laToppingRequired = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->count();
        $laToppingScanned = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_la')
            ->where('status', 'approved')
            ->whereHas('barcodeLas', fn ($q) => $q->where('cancel', false))
            ->count();
        $laRequired = 1 + $laToppingRequired;
        $laScanned = $laInitialScanned + $laToppingScanned;
        $laIsComplete = $laScanned >= $laRequired;
        $laProgress = [
            'required' => $laRequired,
            'scanned' => $laScanned,
            'initial_required' => 1,
            'topping_required' => $laToppingRequired,
            'initial_scanned' => $laInitialScanned,
            'topping_scanned' => $laToppingScanned,
            'is_complete' => $laIsComplete,
        ];

        // Hitung progress AUX: 1 awal + topping yang di-approve
        $auxInitialScanned = BarcodeAux::whereHas('detailProses', fn ($q) => $q->where('proses_id', $id))
            ->whereNull('approval_id')
            ->where('cancel', false)
            ->exists() ? 1 : 0;
        $auxToppingRequired = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->count();
        $auxToppingScanned = Approval::where('proses_id', $id)
            ->where('type', 'KEPALA_SHIFT')
            ->where('action', 'topping_aux')
            ->where('status', 'approved')
            ->whereHas('barcodeAuxs', fn ($q) => $q->where('cancel', false))
            ->count();
        $auxRequired = 1 + $auxToppingRequired;
        $auxScanned = $auxInitialScanned + $auxToppingScanned;
        $auxIsComplete = $auxScanned >= $auxRequired;
        $auxProgress = [
            'required' => $auxRequired,
            'scanned' => $auxScanned,
            'initial_required' => 1,
            'topping_required' => $auxToppingRequired,
            'initial_scanned' => $auxInitialScanned,
            'topping_scanned' => $auxToppingScanned,
            'is_complete' => $auxIsComplete,
        ];

        $userRole = Auth::user()->role ?? null;
        $canScanLa = (in_array($userRole, ['super_admin', 'ppic'])
            || ($userRole === 'kepala_ruangan' && $approvedToppingLa && $allComplete))
            && $allComplete && !$laIsComplete;
        $canScanAux = (in_array($userRole, ['super_admin', 'ppic'])
            || ($userRole === 'kepala_ruangan' && $approvedToppingAux && $allComplete))
            && $allComplete && !$auxIsComplete;

        $jenisOp = $proses->jenis_op ?? 'Single';
        $isMultipleOp = $jenisOp === 'Multiple' && $detailListForTopping->count() > 1;

        return response()->json([
            'barcode_kain' => $barcodeKain->values(),
            'barcode_la' => $barcodeLa->values(),
            'barcode_aux' => $barcodeAux->values(),
            'barcode_kain_progress' => $barcodeKainProgress,
            'all_barcode_kain_progress' => $allBarcodeKainProgress,
            'incomplete_details' => $incompleteDetails,
            'can_scan_la_aux' => $allComplete,
            'barcode_kain_optional' => $barcodeKainOptional,
            'pending_topping_la' => $pendingToppingLa,
            'pending_topping_aux' => $pendingToppingAux,
            'has_topping_la' => $hasToppingLa,
            'has_topping_aux' => $hasToppingAux,
            'td_color' => $tdColor,
            'ta_color' => $taColor,
            'approved_topping_la' => $approvedToppingLa ? ['id' => $approvedToppingLa->id] : null,
            'approved_topping_aux' => $approvedToppingAux ? ['id' => $approvedToppingAux->id] : null,
            'can_request_topping_la' => $canRequestToppingLa,
            'can_request_topping_aux' => $canRequestToppingAux,
            'can_scan_la' => $canScanLa,
            'can_scan_aux' => $canScanAux,
            'la_progress' => $laProgress,
            'aux_progress' => $auxProgress,
            'is_multiple_op' => $isMultipleOp,
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
            $bodyValue = $matdok;
            if ($type === 'kain' && $barcodeObj instanceof \App\Models\BarcodeKain && $barcodeObj->item_document) {
                $bodyValue = $matdok . ';' . $barcodeObj->item_document;
            }
            $body = '"' . $bodyValue . '"';

            $cancelUrl = SapApi::url('zterima_cancel');
            Log::info('SAP Cancel Request', [
                'url' => $cancelUrl,
                'headers' => SapApi::defaultHeaders(),
                'body' => $body,
                'type' => $type,
            ]);
            $response = $client->post(
                $cancelUrl,
                SapApi::guzzleOptions(['body' => $body])
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
                            Cache::forget("iot:mesin:{$proses->mesin_id}:alarm_result");
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
