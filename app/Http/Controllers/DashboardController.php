<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use App\Models\Proses;
use App\Models\Approval;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $mesins = Mesin::select('id', 'jenis_mesin')
            ->orderBy('id', 'asc')
            ->get();

        $currentPage = $request->query('page', 1);

        // Ambil parameter mesin dari query string, support multi-mesin (mesin=1,2,3)
        $selectedMesin = $request->query('mesin');
        $selectedMesinArr = [];
        if ($selectedMesin) {
            if (is_array($selectedMesin)) {
                $selectedMesinArr = $selectedMesin;
            } else {
                $selectedMesinArr = explode(',', $selectedMesin);
            }
        }

        // Ambil proses, filter jika mesin dipilih
        $prosesQuery = Proses::with(['barcodeKains', 'barcodeLas', 'barcodeAuxs', 'mesin', 'approvals']);
        if (count($selectedMesinArr) > 0) {
            $prosesQuery->whereIn('mesin_id', $selectedMesinArr);
        }

        // Urutkan berdasarkan order untuk proses pending (belum mulai), kemudian created_at dan id
        // Proses yang sudah mulai/selesai tetap diurutkan berdasarkan created_at dan id
        $prosesList = $prosesQuery->get()->sort(function($a, $b) {
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

        return view('dashboard', [
            'mesins' => $mesins,
            'currentPage' => $currentPage,
            'prosesList' => $prosesList,
            'selectedMesinArr' => $selectedMesinArr,
        ]);
    }

    /**
     * Get status semua proses untuk real-time update
     * Return JSON dengan informasi mulai, selesai untuk update warna card
     */
    public function prosesStatuses(Request $request)
    {
        try {
            // Ambil parameter mesin dari query string (sama seperti dashboard)
            $selectedMesin = $request->query('mesin');
            $selectedMesinArr = [];
            if ($selectedMesin) {
                if (is_array($selectedMesin)) {
                    $selectedMesinArr = $selectedMesin;
                } else {
                    $selectedMesinArr = explode(',', $selectedMesin);
                }
            }

            // Query proses dengan relasi approvals untuk cek pending
            $prosesQuery = Proses::with(['approvals', 'barcodeKains', 'barcodeLas', 'barcodeAuxs']);
            if (count($selectedMesinArr) > 0) {
                $prosesQuery->whereIn('mesin_id', $selectedMesinArr);
            }

            $prosesList = $prosesQuery->select('id', 'jenis', 'mulai', 'selesai', 'cycle_time', 'cycle_time_actual', 'mesin_id', 'order')
                ->get()
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
                $hasBarcodeKain = $proses->barcodeKains ? $proses->barcodeKains->where('cancel', false)->count() > 0 : false;
                $hasBarcodeLa = $proses->barcodeLas ? $proses->barcodeLas->where('cancel', false)->count() > 0 : false;
                $hasBarcodeAux = $proses->barcodeAuxs ? $proses->barcodeAuxs->where('cancel', false)->count() > 0 : false;

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
                    if ($cycle_time_actual > $cycle_time + 3600) {
                        $bg = '#e53935'; // merah (overtime)
                    } else {
                        $bg = '#00c853'; // hijau (selesai normal)
                    }
                } else {
                    // Proses sedang berjalan (mulai ada, selesai belum)
                    if ($proses->jenis !== 'Maintenance' && (!$hasBarcodeKain || !$hasBarcodeLa || !$hasBarcodeAux)) {
                        $bg = '#e53935'; // merah (barcode belum lengkap)
                    } else {
                        $bg = '#002b80'; // biru (berjalan dengan barcode lengkap)
                    }
                }

                $result[$proses->id] = [
                    'mulai' => $proses->mulai,
                    'selesai' => $proses->selesai,
                    'bg_color' => $bg,
                    'jenis' => $proses->jenis,
                    'order' => (int)($proses->order ?? 0),
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error getting proses statuses: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
