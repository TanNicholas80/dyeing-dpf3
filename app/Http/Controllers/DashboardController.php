<?php

namespace App\Http\Controllers;

use App\Models\Proses;
use App\Models\Approval;
use App\Services\MesinCacheService;
use App\Services\ProsesStatusService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Helper untuk mem-parsing input filter (array atau comma-separated string)
     */
    private function parseFilterParam($param): array
    {
        if (empty($param)) {
            return [];
        }
        if (is_array($param)) {
            return array_values(array_unique(array_filter($param, fn($v) => $v !== null && $v !== '')));
        }
        return array_values(array_unique(array_filter(explode(',', (string) $param), fn($v) => $v !== '')));
    }

    /**
     * Mengambil opsi distinct untuk kriteria filter detail_proses yang dipisah berdasarkan mode:
     * - 'produksi': dari proses yang belum selesai (proses.selesai IS NULL)
     * - 'history': dari proses yang sudah selesai (proses.selesai IS NOT NULL)
     */
    private function getFilterOptions(): array
    {
        return Cache::remember('dashboard_filter_options_by_mode', 60, function () {
            $result = [];
            foreach (['produksi', 'history'] as $mode) {
                $isProd = ($mode === 'produksi');
                $query = DB::table('detail_proses')
                    ->join('proses', 'detail_proses.proses_id', '=', 'proses.id');

                if ($isProd) {
                    $query->whereNull('proses.selesai');
                } else {
                    $query->whereNotNull('proses.selesai');
                }

                $result[$mode] = [
                    'mesin_ids' => (clone $query)->whereNotNull('proses.mesin_id')->distinct()->pluck('proses.mesin_id')->values()->all(),
                    'customers' => (clone $query)->whereNotNull('customer')->where('customer', '!=', '')->distinct()->orderBy('customer')->pluck('customer')->values()->all(),
                    'marketings' => (clone $query)->whereNotNull('marketing')->where('marketing', '!=', '')->distinct()->orderBy('marketing')->pluck('marketing')->values()->all(),
                    'warnas' => (clone $query)->whereNotNull('warna')->where('warna', '!=', '')->distinct()->orderBy('warna')->pluck('warna')->values()->all(),
                    'kategori_warnas' => (clone $query)->whereNotNull('kategori_warna')->where('kategori_warna', '!=', '')->distinct()->orderBy('kategori_warna')->pluck('kategori_warna')->values()->all(),
                    'kode_warnas' => (clone $query)->whereNotNull('kode_warna')->where('kode_warna', '!=', '')->distinct()->orderBy('kode_warna')->pluck('kode_warna')->values()->all(),
                    'hfeels' => (clone $query)->whereNotNull('hfeel')->where('hfeel', '!=', '')->distinct()->orderBy('hfeel')->pluck('hfeel')->values()->all(),
                    'gramasis' => (clone $query)->whereNotNull('gramasi')->where('gramasi', '!=', '')->distinct()->orderBy('gramasi')->pluck('gramasi')->values()->all(),
                    'no_ops' => (clone $query)->whereNotNull('no_op')->where('no_op', '!=', '')->distinct()->orderBy('no_op')->pluck('no_op')->values()->all(),
                    'no_partais' => (clone $query)->whereNotNull('no_partai')->where('no_partai', '!=', '')->distinct()->orderBy('no_partai')->pluck('no_partai')->values()->all(),
                    'konstruksis' => (clone $query)->whereNotNull('konstruksi')->where('konstruksi', '!=', '')->distinct()->orderBy('konstruksi')->pluck('konstruksi')->values()->all(),
                    'kode_materials' => (clone $query)->whereNotNull('kode_material')->where('kode_material', '!=', '')->distinct()->orderBy('kode_material')->pluck('kode_material')->values()->all(),
                ];
            }
            return $result;
        });
    }

    public function dashboard(Request $request, MesinCacheService $mesinCache)
    {
        \App\Http\Controllers\ApprovalController::autoRejectExpiredPauseApprovals();

        $user = $request->user();

        // Jika user role mesin dan memiliki mesin spesifik, batasi daftar mesin
        $restrictedMesinIds = [];
        if ($user && $user->role === 'mesin' && $user->mesin) {
            $restrictedMesinId = $mesinCache->getIdByJenis($user->mesin);
            if ($restrictedMesinId !== null) {
                $restrictedMesinIds = [$restrictedMesinId];
            }
        }

        $mesins = count($restrictedMesinIds) > 0
            ? $mesinCache->getSelectionListForJenis($user->mesin)
            : $mesinCache->getSelectionList();

        // Ambil semua parameter filter
        $selectedMesinArr = [];
        if (count($restrictedMesinIds) > 0) {
            $selectedMesinArr = $restrictedMesinIds;
        } else {
            $selectedMesinArr = $this->parseFilterParam($request->query('mesin'));
        }

        $selectedCustomerArr = $this->parseFilterParam($request->query('customer'));
        $selectedMarketingArr = $this->parseFilterParam($request->query('marketing'));
        $selectedWarnaArr = $this->parseFilterParam($request->query('warna'));
        $selectedKategoriWarnaArr = $this->parseFilterParam($request->query('kategori_warna'));
        $selectedKodeWarnaArr = $this->parseFilterParam($request->query('kode_warna'));
        $selectedHfeelArr = $this->parseFilterParam($request->query('hfeel') ?? $request->query('handfeel'));
        $selectedGramasiArr = $this->parseFilterParam($request->query('gramasi'));
        $selectedNoOpArr = $this->parseFilterParam($request->query('no_op'));
        $selectedNoPartaiArr = $this->parseFilterParam($request->query('no_partai'));
        $selectedKonstruksiArr = $this->parseFilterParam($request->query('konstruksi'));
        $selectedKodeMaterialArr = $this->parseFilterParam($request->query('kode_material'));

        // Hitung total kriteria filter yang sedang aktif
        $activeFilterCount = (count($restrictedMesinIds) === 0 && count($selectedMesinArr) > 0 ? 1 : 0)
            + (count($selectedCustomerArr) > 0 ? 1 : 0)
            + (count($selectedMarketingArr) > 0 ? 1 : 0)
            + (count($selectedWarnaArr) > 0 ? 1 : 0)
            + (count($selectedKategoriWarnaArr) > 0 ? 1 : 0)
            + (count($selectedKodeWarnaArr) > 0 ? 1 : 0)
            + (count($selectedHfeelArr) > 0 ? 1 : 0)
            + (count($selectedGramasiArr) > 0 ? 1 : 0)
            + (count($selectedNoOpArr) > 0 ? 1 : 0)
            + (count($selectedNoPartaiArr) > 0 ? 1 : 0)
            + (count($selectedKonstruksiArr) > 0 ? 1 : 0)
            + (count($selectedKodeMaterialArr) > 0 ? 1 : 0);

        // Ambil proses, filter jika mesin atau atribut detail dipilih
        $prosesQuery = Proses::with(['mesin', 'approvals.barcodeLas', 'approvals.barcodeAuxs', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
        if (count($restrictedMesinIds) > 0) {
            $prosesQuery->whereIn('mesin_id', $restrictedMesinIds);
        } elseif (count($selectedMesinArr) > 0) {
            $prosesQuery->whereIn('mesin_id', $selectedMesinArr);
        }

        $hasDetailFilters = !empty($selectedCustomerArr) || !empty($selectedMarketingArr) || !empty($selectedWarnaArr)
            || !empty($selectedKategoriWarnaArr) || !empty($selectedKodeWarnaArr) || !empty($selectedHfeelArr)
            || !empty($selectedGramasiArr) || !empty($selectedNoOpArr) || !empty($selectedNoPartaiArr)
            || !empty($selectedKonstruksiArr) || !empty($selectedKodeMaterialArr);

        if ($hasDetailFilters) {
            $prosesQuery->whereHas('details', function ($q) use (
                $selectedCustomerArr, $selectedMarketingArr, $selectedWarnaArr,
                $selectedKategoriWarnaArr, $selectedKodeWarnaArr, $selectedHfeelArr,
                $selectedGramasiArr, $selectedNoOpArr, $selectedNoPartaiArr,
                $selectedKonstruksiArr, $selectedKodeMaterialArr
            ) {
                if (!empty($selectedCustomerArr)) $q->whereIn('customer', $selectedCustomerArr);
                if (!empty($selectedMarketingArr)) $q->whereIn('marketing', $selectedMarketingArr);
                if (!empty($selectedWarnaArr)) $q->whereIn('warna', $selectedWarnaArr);
                if (!empty($selectedKategoriWarnaArr)) $q->whereIn('kategori_warna', $selectedKategoriWarnaArr);
                if (!empty($selectedKodeWarnaArr)) $q->whereIn('kode_warna', $selectedKodeWarnaArr);
                if (!empty($selectedHfeelArr)) $q->whereIn('hfeel', $selectedHfeelArr);
                if (!empty($selectedGramasiArr)) $q->whereIn('gramasi', $selectedGramasiArr);
                if (!empty($selectedNoOpArr)) $q->whereIn('no_op', $selectedNoOpArr);
                if (!empty($selectedNoPartaiArr)) $q->whereIn('no_partai', $selectedNoPartaiArr);
                if (!empty($selectedKonstruksiArr)) $q->whereIn('konstruksi', $selectedKonstruksiArr);
                if (!empty($selectedKodeMaterialArr)) $q->whereIn('kode_material', $selectedKodeMaterialArr);
            });
        }

        // Urutkan berdasarkan order untuk proses pending (belum mulai), kemudian created_at dan id
        // Proses yang sudah mulai/selesai tetap diurutkan berdasarkan created_at dan id
        $prosesList = $prosesQuery->get()->map(function ($proses) {
            $proses->barcode_kain_optional = $proses->isBarcodeKainOptionalForLaAux();
            return $proses;
        })->sort(function ($a, $b) {
            // Proses pending (belum mulai) diurutkan berdasarkan order
            if (!$a->mulai && !$b->mulai) {
                $orderA = (int) ($a->order ?? 0);
                $orderB = (int) ($b->order ?? 0);
                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }
            }
            // Fallback ke created_at dan id
            if ($a->created_at != $b->created_at) {
                return $a->created_at <=> $b->created_at;
            }
            return $a->id <=> $b->id;
        })->values();

        // Ambil role user dan permission untuk optimasi (hindari checking di view)
        $userRole = $user ? $user->role : null;
        $canCancelBarcode = !in_array($userRole, ['ds', 'mesin', 'vp', 'fm', 'owner', 'scm']);

        $cantModifyStructure = in_array($userRole, ['fm', 'vp', 'ds', 'mesin', 'owner', 'scm']);

        $cantScan = in_array($userRole, ['fm', 'vp', 'ds', 'owner', 'scm']);
        $canAddProses = !$cantModifyStructure;
        $canEditProses = !$cantModifyStructure;
        $canDeleteProses = !$cantModifyStructure;
        $canMoveProses = !$cantModifyStructure;
        $canSwapProses = !$cantModifyStructure;

        $canScanBarcode = !$cantScan;

        // Opsi filter
        $filterOptions = $this->getFilterOptions();

        return view('dashboard', [
            'mesins' => $mesins,
            'prosesList' => $prosesList,
            'selectedMesinArr' => $selectedMesinArr,
            'selectedCustomerArr' => $selectedCustomerArr,
            'selectedMarketingArr' => $selectedMarketingArr,
            'selectedWarnaArr' => $selectedWarnaArr,
            'selectedKategoriWarnaArr' => $selectedKategoriWarnaArr,
            'selectedKodeWarnaArr' => $selectedKodeWarnaArr,
            'selectedHfeelArr' => $selectedHfeelArr,
            'selectedGramasiArr' => $selectedGramasiArr,
            'selectedNoOpArr' => $selectedNoOpArr,
            'selectedNoPartaiArr' => $selectedNoPartaiArr,
            'selectedKonstruksiArr' => $selectedKonstruksiArr,
            'selectedKodeMaterialArr' => $selectedKodeMaterialArr,
            'filterOptions' => $filterOptions,
            'activeFilterCount' => $activeFilterCount,
            'userRole' => $userRole,
            'canCancelBarcode' => $canCancelBarcode,
            'canAddProses' => $canAddProses,
            'canEditProses' => $canEditProses,
            'canDeleteProses' => $canDeleteProses,
            'canMoveProses' => $canMoveProses,
            'canSwapProses' => $canSwapProses,
            'canScanBarcode' => $canScanBarcode,
        ]);
    }

    /**
     * Get status semua proses untuk real-time update
     * Return JSON dengan informasi mulai, selesai untuk update warna card
     */
    public function prosesStatuses(Request $request, MesinCacheService $mesinCache)
    {
        \App\Http\Controllers\ApprovalController::autoRejectExpiredPauseApprovals();

        try {
            $user = $request->user();

            // Batasi mesin jika user role mesin
            $restrictedMesinIds = [];
            if ($user && $user->role === 'mesin' && $user->mesin) {
                $restrictedMesinId = $mesinCache->getIdByJenis($user->mesin);
                if ($restrictedMesinId !== null) {
                    $restrictedMesinIds = [$restrictedMesinId];
                }
            }

            // Ambil parameter filter dari query string
            $selectedMesinArr = [];
            if (count($restrictedMesinIds) > 0) {
                $selectedMesinArr = $restrictedMesinIds;
            } else {
                $selectedMesinArr = $this->parseFilterParam($request->query('mesin'));
            }

            $selectedCustomerArr = $this->parseFilterParam($request->query('customer'));
            $selectedMarketingArr = $this->parseFilterParam($request->query('marketing'));
            $selectedWarnaArr = $this->parseFilterParam($request->query('warna'));
            $selectedKategoriWarnaArr = $this->parseFilterParam($request->query('kategori_warna'));
            $selectedKodeWarnaArr = $this->parseFilterParam($request->query('kode_warna'));
            $selectedHfeelArr = $this->parseFilterParam($request->query('hfeel') ?? $request->query('handfeel'));
            $selectedGramasiArr = $this->parseFilterParam($request->query('gramasi'));
            $selectedNoOpArr = $this->parseFilterParam($request->query('no_op'));
            $selectedNoPartaiArr = $this->parseFilterParam($request->query('no_partai'));
            $selectedKonstruksiArr = $this->parseFilterParam($request->query('konstruksi'));
            $selectedKodeMaterialArr = $this->parseFilterParam($request->query('kode_material'));

            // Query proses dengan relasi approvals untuk cek pending
            $prosesQuery = Proses::select('id', 'jenis', 'mulai', 'selesai', 'cycle_time', 'cycle_time_actual', 'mesin_id', 'order')
                ->with(['approvals.barcodeLas', 'approvals.barcodeAuxs', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            if (count($restrictedMesinIds) > 0) {
                $prosesQuery->whereIn('mesin_id', $restrictedMesinIds);
            } elseif (count($selectedMesinArr) > 0) {
                $prosesQuery->whereIn('mesin_id', $selectedMesinArr);
            }

            $hasDetailFilters = !empty($selectedCustomerArr) || !empty($selectedMarketingArr) || !empty($selectedWarnaArr)
                || !empty($selectedKategoriWarnaArr) || !empty($selectedKodeWarnaArr) || !empty($selectedHfeelArr)
                || !empty($selectedGramasiArr) || !empty($selectedNoOpArr) || !empty($selectedNoPartaiArr)
                || !empty($selectedKonstruksiArr) || !empty($selectedKodeMaterialArr);

            if ($hasDetailFilters) {
                $prosesQuery->whereHas('details', function ($q) use (
                    $selectedCustomerArr, $selectedMarketingArr, $selectedWarnaArr,
                    $selectedKategoriWarnaArr, $selectedKodeWarnaArr, $selectedHfeelArr,
                    $selectedGramasiArr, $selectedNoOpArr, $selectedNoPartaiArr,
                    $selectedKonstruksiArr, $selectedKodeMaterialArr
                ) {
                    if (!empty($selectedCustomerArr)) $q->whereIn('customer', $selectedCustomerArr);
                    if (!empty($selectedMarketingArr)) $q->whereIn('marketing', $selectedMarketingArr);
                    if (!empty($selectedWarnaArr)) $q->whereIn('warna', $selectedWarnaArr);
                    if (!empty($selectedKategoriWarnaArr)) $q->whereIn('kategori_warna', $selectedKategoriWarnaArr);
                    if (!empty($selectedKodeWarnaArr)) $q->whereIn('kode_warna', $selectedKodeWarnaArr);
                    if (!empty($selectedHfeelArr)) $q->whereIn('hfeel', $selectedHfeelArr);
                    if (!empty($selectedGramasiArr)) $q->whereIn('gramasi', $selectedGramasiArr);
                    if (!empty($selectedNoOpArr)) $q->whereIn('no_op', $selectedNoOpArr);
                    if (!empty($selectedNoPartaiArr)) $q->whereIn('no_partai', $selectedNoPartaiArr);
                    if (!empty($selectedKonstruksiArr)) $q->whereIn('konstruksi', $selectedKonstruksiArr);
                    if (!empty($selectedKodeMaterialArr)) $q->whereIn('kode_material', $selectedKodeMaterialArr);
                });
            }

            $prosesList = $prosesQuery->get()
                ->sort(function ($a, $b) {
                    // Proses pending (belum mulai) diurutkan berdasarkan order
                    if (!$a->mulai && !$b->mulai) {
                        $orderA = (int) ($a->order ?? 0);
                        $orderB = (int) ($b->order ?? 0);
                        if ($orderA !== $orderB) {
                            return $orderA <=> $orderB;
                        }
                    }
                    // Fallback ke created_at dan id
                    if ($a->created_at != $b->created_at) {
                        return $a->created_at <=> $b->created_at;
                    }
                    return $a->id <=> $b->id;
                })
                ->values();

            // Ambil semua proses ID yang terlibat dalam swap position approval
            // Termasuk swapped_proses_id dan affected_proses_ids (semua proses yang terpengaruh)
            // Query sekali di awal untuk efisiensi
            $affectedProsesIds = [];
            try {
                $swapApprovals = Approval::where('status', 'pending')
                    ->where('type', 'FM')
                    ->where('action', 'swap_position')
                    ->get();

                foreach ($swapApprovals as $appr) {
                    $historyData = $appr->history_data;
                    if (is_string($historyData)) {
                        $historyData = json_decode($historyData, true);
                    }
                    if (is_array($historyData)) {
                        // Tambahkan swapped_proses_id (untuk backward compatibility)
                        if (isset($historyData['swapped_proses_id'])) {
                            $affectedProsesIds[] = (int) $historyData['swapped_proses_id'];
                        }
                        // Tambahkan semua affected_proses_ids (proses yang akan bergeser)
                        if (isset($historyData['affected_proses_ids']) && is_array($historyData['affected_proses_ids'])) {
                            foreach ($historyData['affected_proses_ids'] as $id) {
                                $affectedProsesIds[] = (int) $id;
                            }
                        }
                    }
                }
                $affectedProsesIds = array_unique($affectedProsesIds);
            } catch (\Exception $e) {
                $affectedProsesIds = [];
            }

            $statusService = new ProsesStatusService();
            $result = [];
            foreach ($prosesList as $proses) {
                $result[$proses->id] = $statusService->generateProsesStatus($proses, $affectedProsesIds);
                
                // Adjust Carbon date formats if they are objects, making sure they match response expectations
                if (isset($result[$proses->id]['mulai']) && $result[$proses->id]['mulai'] instanceof \Carbon\Carbon) {
                    $result[$proses->id]['mulai'] = $result[$proses->id]['mulai']->format('Y-m-d H:i:s');
                }
                if (isset($result[$proses->id]['selesai']) && $result[$proses->id]['selesai'] instanceof \Carbon\Carbon) {
                    $result[$proses->id]['selesai'] = $result[$proses->id]['selesai']->format('Y-m-d H:i:s');
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error getting proses statuses: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getProsesCardHtml(Request $request, $id)
    {
        $proses = \App\Models\Proses::with([
            'mesin', 
            'details.barcodeKains', 
            'details.barcodeLas', 
            'details.barcodeAuxs', 
            'approvals'
        ])->findOrFail($id);

        $statusService = new \App\Services\ProsesStatusService();
        $affectedProsesIds = $statusService->getAffectedProsesIds();

        $user = $request->user();
        $userRole = $user ? $user->role : null;
        $cantModifyStructure = in_array($userRole, ['operator', 'mesin', 'scm']);
        $cantScan = in_array($userRole, ['dashboard', 'scm']);

        $canCancelBarcode = in_array($userRole, ['super_admin', 'kepala_shift']);
        $canAddProses = !$cantModifyStructure;
        $canEditProses = !$cantModifyStructure;
        $canDeleteProses = !$cantModifyStructure;
        $canMoveProses = !$cantModifyStructure;
        $canSwapProses = !$cantModifyStructure;
        $canScanBarcode = !$cantScan;

        $html = view('partials.dashboard.status_card', [
            'proses' => $proses,
            'affectedProsesIds' => $affectedProsesIds,
            'userRole' => $userRole,
            'canCancelBarcode' => $canCancelBarcode,
            'canAddProses' => $canAddProses,
            'canEditProses' => $canEditProses,
            'canDeleteProses' => $canDeleteProses,
            'canMoveProses' => $canMoveProses,
            'canSwapProses' => $canSwapProses,
            'canScanBarcode' => $canScanBarcode,
        ])->render();

        return response()->json(['html' => $html]);
    }
}

