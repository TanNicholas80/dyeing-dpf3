<?php

namespace App\Http\Controllers;

use App\Models\Proses;
use App\Models\Approval;
use App\Services\MesinCacheService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard(Request $request, MesinCacheService $mesinCache)
    {
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

        $currentPage = $request->query('page', 1);

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
        })->sort(function($a, $b) {
            // Proses pending (belum mulai) diurutkan berdasarkan order
            if (!$a->mulai && !$b->mulai) {
                $orderA = (int)($a->order ?? 0);
                $orderB = (int)($b->order ?? 0);
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
        $canCancelBarcode = !in_array($userRole, ['ds', 'mesin', 'vp', 'fm', 'owner']);

        $cantModifyStructure = in_array($userRole, ['fm', 'vp', 'ds', 'mesin', 'owner']);
        
        $cantScan = in_array($userRole, ['fm', 'vp', 'ds', 'owner']);
        $canAddProses    = !$cantModifyStructure;
        $canEditProses   = !$cantModifyStructure;
        $canDeleteProses = !$cantModifyStructure;
        $canMoveProses   = !$cantModifyStructure;
        $canSwapProses   = !$cantModifyStructure;

        $canScanBarcode  = !$cantScan;

        return view('dashboard', [
            'mesins' => $mesins,
            'currentPage' => $currentPage,
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
                ->sort(function($a, $b) {
                    // Proses pending (belum mulai) diurutkan berdasarkan order
                    if (!$a->mulai && !$b->mulai) {
                        $orderA = (int)($a->order ?? 0);
                        $orderB = (int)($b->order ?? 0);
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
                            $affectedProsesIds[] = (int)$historyData['swapped_proses_id'];
                        }
                        // Tambahkan semua affected_proses_ids (proses yang akan bergeser)
                        if (isset($historyData['affected_proses_ids']) && is_array($historyData['affected_proses_ids'])) {
                            foreach ($historyData['affected_proses_ids'] as $id) {
                                $affectedProsesIds[] = (int)$id;
                            }
                        }
                    }
                }
                $affectedProsesIds = array_unique($affectedProsesIds);
            } catch (\Exception $e) {
                $affectedProsesIds = [];
            }

            $result = [];
            foreach ($prosesList as $proses) {
                // Cek pending approval
                $hasPendingChange = false;
                $hasPendingReprocessApproval = false;
                if ($proses->approvals) {
                    $hasPendingChange = $proses->approvals->contains(function ($appr) {
                        return $appr->status === 'pending'
                            && $appr->type === 'FM'
                            && in_array($appr->action, ['edit_cycle_time', 'delete_proses', 'move_machine', 'swap_position']);
                    });
                    if ($proses->jenis === 'Reproses') {
                        $hasPendingReprocessApproval = $proses->approvals->contains(function ($appr) {
                            return $appr->status === 'pending'
                                && $appr->action === 'create_reprocess'
                                && ($appr->type === 'FM' || $appr->type === 'VP');
                        });
                    }
                }
                // Cek apakah proses ini terlibat dalam swap position approval dari proses lain
                // (sebagai swapped_proses_id atau affected_proses_ids di history_data approval swap_position)
                if (!$hasPendingChange && in_array($proses->id, $affectedProsesIds)) {
                    $hasPendingChange = true;
                }

                // Cek barcode (untuk menentukan warna biru atau merah)
                // Ambil barcode melalui DetailProses
                $hasBarcodeKain = false;
                $hasBarcodeLa = false;
                $hasBarcodeAux = false;
                
                // Array untuk menyimpan status GDA per detail proses
                $gdaDetails = [];
                
                if ($proses->details) {
                    foreach ($proses->details as $detail) {
                        // Cek status barcode per detail
                        $detailHasLa = false;
                        $detailHasAux = false;
                        
                        // Untuk indikator G (Kain): cek apakah jumlah barcode kain sudah sesuai dengan roll
                        $roll = $detail->roll ?? 0;
                        $barcodeKainCount = 0;
                        if ($detail->barcodeKains) {
                            $barcodeKainCount = $detail->barcodeKains->where('cancel', false)->count();
                        }
                        // Indikator G hijau hanya jika jumlah barcode kain >= jumlah roll
                        $detailHasKain = ($barcodeKainCount >= $roll && $roll > 0);
                        $hasBarcodeKain = $hasBarcodeKain || $detailHasKain;
                        
                        if ($detail->barcodeLas) {
                            $detailHasLa = $detail->barcodeLas->where('cancel', false)->count() > 0;
                            $hasBarcodeLa = $hasBarcodeLa || $detailHasLa;
                        }
                        if ($detail->barcodeAuxs) {
                            $detailHasAux = $detail->barcodeAuxs->where('cancel', false)->count() > 0;
                            $hasBarcodeAux = $hasBarcodeAux || $detailHasAux;
                        }
                        
                        // Simpan status GDA per detail untuk update real-time
                        $gdaDetails[] = [
                            'detail_id' => $detail->id,
                            'has_kain' => $detailHasKain,
                            'has_la' => $detailHasLa,
                            'has_aux' => $detailHasAux,
                            'roll' => $roll,
                            'barcode_kain_count' => $barcodeKainCount,
                        ];
                    }
                }

                // Hitung la/aux complete termasuk topping (untuk block bg merah jika belum lengkap)
                // Multiple OP: 1 approval per proses, barcode topping ditambahkan ke setiap OP saat scan
                $laInitialScanned = 0;
                $auxInitialScanned = 0;
                foreach ($proses->details ?? [] as $d) {
                    if ($d->barcodeLas && $d->barcodeLas->where('cancel', false)->filter(fn ($b) => $b->approval_id === null)->count() > 0) {
                        $laInitialScanned = 1;
                        break;
                    }
                }
                foreach ($proses->details ?? [] as $d) {
                    if ($d->barcodeAuxs && $d->barcodeAuxs->where('cancel', false)->filter(fn ($b) => $b->approval_id === null)->count() > 0) {
                        $auxInitialScanned = 1;
                        break;
                    }
                }
                $laToppingRequired = collect($proses->approvals ?? [])->where('action', 'topping_la')->where('status', 'approved')->count();
                $auxToppingRequired = collect($proses->approvals ?? [])->where('action', 'topping_aux')->where('status', 'approved')->count();
                $laToppingScanned = 0;
                $auxToppingScanned = 0;
                foreach ($proses->approvals ?? [] as $a) {
                    if (($a->action ?? '') === 'topping_la' && ($a->status ?? '') === 'approved') {
                        if ($a->barcodeLas && $a->barcodeLas->where('cancel', false)->count() > 0) {
                            $laToppingScanned++;
                        }
                    }
                    if (($a->action ?? '') === 'topping_aux' && ($a->status ?? '') === 'approved') {
                        if ($a->barcodeAuxs && $a->barcodeAuxs->where('cancel', false)->count() > 0) {
                            $auxToppingScanned++;
                        }
                    }
                }
                $laRequired = 1 + $laToppingRequired;
                $auxRequired = 1 + $auxToppingRequired;
                $laComplete = ($laInitialScanned + $laToppingScanned) >= $laRequired;
                $auxComplete = ($auxInitialScanned + $auxToppingScanned) >= $auxRequired;
                $laInitialComplete = $laInitialScanned >= 1;
                $auxInitialComplete = $auxInitialScanned >= 1;
                $hasToppingLa = collect($proses->approvals ?? [])->contains(fn ($a) => ($a->action ?? '') === 'topping_la');
                $hasToppingAux = collect($proses->approvals ?? [])->contains(fn ($a) => ($a->action ?? '') === 'topping_aux');
                $pendingToppingLa = collect($proses->approvals ?? [])->contains(fn ($a) => ($a->action ?? '') === 'topping_la' && ($a->status ?? '') === 'pending');
                $pendingToppingAux = collect($proses->approvals ?? [])->contains(fn ($a) => ($a->action ?? '') === 'topping_aux' && ($a->status ?? '') === 'pending');
                $approvedToppingLaNotScanned = collect($proses->approvals ?? [])->contains(function ($a) {
                    if (($a->action ?? '') !== 'topping_la' || ($a->status ?? '') !== 'approved') return false;
                    return !($a->barcodeLas && $a->barcodeLas->where('cancel', false)->count() > 0);
                });
                $approvedToppingAuxNotScanned = collect($proses->approvals ?? [])->contains(function ($a) {
                    if (($a->action ?? '') !== 'topping_aux' || ($a->status ?? '') !== 'approved') return false;
                    return !($a->barcodeAuxs && $a->barcodeAuxs->where('cancel', false)->count() > 0);
                });
                $tdColor = $hasToppingLa ? ($pendingToppingLa ? 'yellow' : ($approvedToppingLaNotScanned ? 'red' : 'green')) : null;
                $taColor = $hasToppingAux ? ($pendingToppingAux ? 'yellow' : ($approvedToppingAuxNotScanned ? 'red' : 'green')) : null;

                // Tentukan warna background (sama seperti logika di blade)
                $bg = '#757575'; // default abu-abu
                if ($hasPendingChange || $hasPendingReprocessApproval) {
                    $bg = '#ffeb3b'; // kuning
                } elseif ($proses->jenis === 'Maintenance') {
                    $bg = '#757575'; // abu-abu
                } elseif (!$proses->mulai) {
                    $bg = '#757575'; // abu-abu
                } elseif ($proses->selesai) {
                    // Hitung cycle_time_actual jika belum ada
                    $cycle_time_actual = $proses->cycle_time_actual;
                    if (!$cycle_time_actual && $proses->mulai && $proses->selesai) {
                        $mulai = \Carbon\Carbon::parse($proses->mulai);
                        $selesai = \Carbon\Carbon::parse($proses->selesai);
                        $cycle_time_actual = max(0, $mulai->diffInSeconds($selesai, false));
                    }
                    $cycle_time = $proses->cycle_time ? (int)$proses->cycle_time : 0;
                    $cycle_time_actual = $cycle_time_actual ? (int)$cycle_time_actual : 0;
                    // Merah: durasi sangat singkat (< 1 jam). Hijau: sudah lebih dari 1 jam berjalan dan berhenti.
                    if ($cycle_time_actual < 3600) {
                        $bg = '#e53935'; // merah (durasi terlalu singkat, belum 1 jam)
                    } elseif ($cycle_time_actual > $cycle_time + 3600) {
                        $bg = '#e53935'; // merah (overtime)
                    } else {
                        $bg = '#00c853'; // hijau (selesai normal, >= 1 jam)
                    }
                } else {
                    // Proses sedang berjalan (mulai ada, selesai belum)
                    $barcodeKainOptional = $proses->isBarcodeKainOptionalForLaAux();
                    if ($proses->jenis !== 'Maintenance') {
                        $incomplete = !$barcodeKainOptional && !$hasBarcodeKain;
                        $incomplete = $incomplete || !$laComplete || !$auxComplete;
                        if ($incomplete) {
                            $bg = '#ef9a9a'; // merah muda / merah (barcode termasuk topping belum lengkap)
                        } else {
                            $bg = '#002b80'; // biru (berjalan dengan barcode lengkap)
                        }
                    } else {
                        $bg = '#002b80';
                    }
                }

                $pendingApprovals = [];
                if ($proses->approvals) {
                    foreach ($proses->approvals as $appr) {
                        if ($appr->status === 'pending') {
                            $pendingApprovals[] = ['type' => $appr->type, 'action' => $appr->action];
                        }
                    }
                }

                $result[$proses->id] = [
                    'mulai' => $proses->mulai,
                    'selesai' => $proses->selesai,
                    'bg_color' => $bg,
                    'jenis' => $proses->jenis,
                    'order' => (int)($proses->order ?? 0),
                    'gda_details' => $gdaDetails,
                    'cycle_time' => $proses->cycle_time !== null ? (int) $proses->cycle_time : null,
                    'cycle_time_actual' => $proses->cycle_time_actual !== null ? (int) $proses->cycle_time_actual : null,
                    'pending_approvals' => $pendingApprovals,
                    'la_complete' => $laComplete,
                    'aux_complete' => $auxComplete,
                    'la_initial_complete' => $laInitialComplete,
                    'aux_initial_complete' => $auxInitialComplete,
                    'has_topping_la' => $hasToppingLa,
                    'has_topping_aux' => $hasToppingAux,
                    'td_color' => $tdColor,
                    'ta_color' => $taColor,
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error getting proses statuses: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
