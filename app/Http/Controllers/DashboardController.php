<?php

namespace App\Http\Controllers;

use App\Models\Proses;
use App\Models\Approval;
use App\Services\MesinCacheService;
use App\Services\ProsesStatusService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
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


        // Ambil parameter mesin dari query string, support multi-mesin (mesin=1,2,3)
        $selectedMesin = $request->query('mesin');
        $selectedMesinArr = [];
        if (count($restrictedMesinIds) > 0) {
            // User mesin hanya boleh melihat mesin yang di-assign
            $selectedMesinArr = $restrictedMesinIds;
        } elseif ($selectedMesin) {
            if (is_array($selectedMesin)) {
                $selectedMesinArr = $selectedMesin;
            } else {
                $selectedMesinArr = explode(',', $selectedMesin);
            }
        }

        // Ambil proses, filter jika mesin dipilih
        $prosesQuery = Proses::with(['mesin', 'approvals.barcodeLas', 'approvals.barcodeAuxs', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
        if (count($restrictedMesinIds) > 0) {
            $prosesQuery->whereIn('mesin_id', $restrictedMesinIds);
        } elseif (count($selectedMesinArr) > 0) {
            $prosesQuery->whereIn('mesin_id', $selectedMesinArr);
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

        return view('dashboard', [
            'mesins' => $mesins,
            'prosesList' => $prosesList,
            'selectedMesinArr' => $selectedMesinArr,
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

            // Ambil parameter mesin dari query string (sama seperti dashboard)
            $selectedMesin = $request->query('mesin');
            $selectedMesinArr = [];
            if (count($restrictedMesinIds) > 0) {
                $selectedMesinArr = $restrictedMesinIds;
            } elseif ($selectedMesin) {
                if (is_array($selectedMesin)) {
                    $selectedMesinArr = $selectedMesin;
                } else {
                    $selectedMesinArr = explode(',', $selectedMesin);
                }
            }

            // Query proses dengan relasi approvals untuk cek pending
            $prosesQuery = Proses::select('id', 'jenis', 'mulai', 'selesai', 'cycle_time', 'cycle_time_actual', 'mesin_id', 'order')
                ->with(['approvals.barcodeLas', 'approvals.barcodeAuxs', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            if (count($restrictedMesinIds) > 0) {
                $prosesQuery->whereIn('mesin_id', $restrictedMesinIds);
            } elseif (count($selectedMesinArr) > 0) {
                $prosesQuery->whereIn('mesin_id', $selectedMesinArr);
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

