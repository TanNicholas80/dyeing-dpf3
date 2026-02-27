<?php

namespace App\Services;

use App\Models\Proses;
use App\Models\Approval;
use App\Models\BarcodeKain;
use App\Models\BarcodeLa;
use App\Models\BarcodeAux;

class ProsesStatusService
{
    /**
     * Generate status data untuk satu proses (mirip dengan logika di DashboardController::prosesStatuses)
     *
     * Multiple OP: 1 barcode LA/AUX dipakai untuk beberapa OP. Topping: 1 approval per proses,
     * Kepala Shift approve sekali, saat scan barcode topping ditambahkan ke setiap OP.
     *
     * @param Proses $proses
     * @param array $affectedProsesIds Array of proses IDs yang terlibat dalam swap position approval
     * @return array
     */
    public function generateProsesStatus(Proses $proses, array $affectedProsesIds = []): array
    {
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
        if (!$hasPendingChange && in_array($proses->id, $affectedProsesIds)) {
            $hasPendingChange = true;
        }

        // Cek barcode (untuk menentukan warna biru atau merah)
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
            // Hitung la/aux complete termasuk topping
            $laComplete = $hasBarcodeLa;
            $auxComplete = $hasBarcodeAux;
            if ($proses->approvals && $proses->approvals->isNotEmpty()) {
                $laInitialScanned = 0;
                foreach ($proses->details ?? [] as $d) {
                    if ($d->barcodeLas && $d->barcodeLas->where('cancel', false)->filter(fn ($b) => $b->approval_id === null)->count() > 0) {
                        $laInitialScanned = 1;
                        break;
                    }
                }
                $auxInitialScanned = 0;
                foreach ($proses->details ?? [] as $d) {
                    if ($d->barcodeAuxs && $d->barcodeAuxs->where('cancel', false)->filter(fn ($b) => $b->approval_id === null)->count() > 0) {
                        $auxInitialScanned = 1;
                        break;
                    }
                }
                $laToppingRequired = collect($proses->approvals)->where('action', 'topping_la')->where('status', 'approved')->count();
                $auxToppingRequired = collect($proses->approvals)->where('action', 'topping_aux')->where('status', 'approved')->count();
                $laToppingScanned = 0;
                $auxToppingScanned = 0;
                foreach ($proses->approvals as $a) {
                    if (($a->action ?? '') === 'topping_la' && ($a->status ?? '') === 'approved') {
                        $bl = $a->barcodeLas;
                        if ($bl && $bl->where('cancel', false)->count() > 0) $laToppingScanned++;
                    }
                    if (($a->action ?? '') === 'topping_aux' && ($a->status ?? '') === 'approved') {
                        $ba = $a->barcodeAuxs;
                        if ($ba && $ba->where('cancel', false)->count() > 0) $auxToppingScanned++;
                    }
                }
                $laComplete = ($laInitialScanned + $laToppingScanned) >= (1 + $laToppingRequired);
                $auxComplete = ($auxInitialScanned + $auxToppingScanned) >= (1 + $auxToppingRequired);
            }
            $barcodeKainOptional = $proses->isBarcodeKainOptionalForLaAux();
            if ($proses->jenis !== 'Maintenance') {
                $incomplete = (!$barcodeKainOptional && !$hasBarcodeKain) || !$laComplete || !$auxComplete;
                $bg = $incomplete ? '#ef9a9a' : '#002b80';
            } else {
                $bg = '#002b80';
            }
        }

        $pendingApprovals = [];
        $pendingToppingLa = false;
        $pendingToppingAux = false;
        $hasToppingLa = false;
        $hasToppingAux = false;
        $tdColor = null;
        $taColor = null;
        $laComplete = $hasBarcodeLa ?? false;
        $auxComplete = $hasBarcodeAux ?? false;
        if ($proses->approvals) {
            foreach ($proses->approvals as $appr) {
                if ($appr->status === 'pending') {
                    $pendingApprovals[] = ['type' => $appr->type, 'action' => $appr->action];
                    if ($appr->type === 'KEPALA_SHIFT' && $appr->action === 'topping_la') {
                        $pendingToppingLa = true;
                    }
                    if ($appr->type === 'KEPALA_SHIFT' && $appr->action === 'topping_aux') {
                        $pendingToppingAux = true;
                    }
                }
                if (($appr->action ?? '') === 'topping_la') {
                    $hasToppingLa = true;
                }
                if (($appr->action ?? '') === 'topping_aux') {
                    $hasToppingAux = true;
                }
            }
            $approvedToppingLaNotScanned = collect($proses->approvals)->contains(function ($a) {
                if (($a->action ?? '') !== 'topping_la' || ($a->status ?? '') !== 'approved') return false;
                return !($a->barcodeLas && $a->barcodeLas->where('cancel', false)->count() > 0);
            });
            $approvedToppingAuxNotScanned = collect($proses->approvals)->contains(function ($a) {
                if (($a->action ?? '') !== 'topping_aux' || ($a->status ?? '') !== 'approved') return false;
                return !($a->barcodeAuxs && $a->barcodeAuxs->where('cancel', false)->count() > 0);
            });
            $tdColor = $hasToppingLa ? ($pendingToppingLa ? 'yellow' : ($approvedToppingLaNotScanned ? 'red' : 'green')) : null;
            $taColor = $hasToppingAux ? ($pendingToppingAux ? 'yellow' : ($approvedToppingAuxNotScanned ? 'red' : 'green')) : null;
            $laInitialScanned = 0;
            foreach ($proses->details ?? [] as $d) {
                if ($d->barcodeLas && $d->barcodeLas->where('cancel', false)->filter(fn ($b) => $b->approval_id === null)->count() > 0) {
                    $laInitialScanned = 1;
                    break;
                }
            }
            $auxInitialScanned = 0;
            foreach ($proses->details ?? [] as $d) {
                if ($d->barcodeAuxs && $d->barcodeAuxs->where('cancel', false)->filter(fn ($b) => $b->approval_id === null)->count() > 0) {
                    $auxInitialScanned = 1;
                    break;
                }
            }
            $laToppingRequired = collect($proses->approvals)->where('action', 'topping_la')->where('status', 'approved')->count();
            $auxToppingRequired = collect($proses->approvals)->where('action', 'topping_aux')->where('status', 'approved')->count();
            $laToppingScanned = 0;
            $auxToppingScanned = 0;
            foreach ($proses->approvals as $a) {
                if (($a->action ?? '') === 'topping_la' && ($a->status ?? '') === 'approved') {
                    $bl = $a->barcodeLas;
                    if ($bl && $bl->where('cancel', false)->count() > 0) $laToppingScanned++;
                }
                if (($a->action ?? '') === 'topping_aux' && ($a->status ?? '') === 'approved') {
                    $ba = $a->barcodeAuxs;
                    if ($ba && $ba->where('cancel', false)->count() > 0) $auxToppingScanned++;
                }
            }
            $laComplete = ($laInitialScanned + $laToppingScanned) >= (1 + $laToppingRequired);
            $auxComplete = ($auxInitialScanned + $auxToppingScanned) >= (1 + $auxToppingRequired);
            $laInitialComplete = $laInitialScanned >= 1;
            $auxInitialComplete = $auxInitialScanned >= 1;
        } else {
            $laInitialComplete = $hasBarcodeLa ?? false;
            $auxInitialComplete = $hasBarcodeAux ?? false;
        }

        return [
            'mulai' => $proses->mulai ? $proses->mulai->format('Y-m-d H:i:s') : null,
            'selesai' => $proses->selesai ? $proses->selesai->format('Y-m-d H:i:s') : null,
            'bg_color' => $bg,
            'jenis' => $proses->jenis,
            'mode' => $proses->mode ?? 'greige',
            'order' => (int)($proses->order ?? 0),
            'gda_details' => $gdaDetails,
            'cycle_time' => $proses->cycle_time !== null ? (int) $proses->cycle_time : null,
            'cycle_time_actual' => $proses->cycle_time_actual !== null ? (int) $proses->cycle_time_actual : null,
            'pending_approvals' => $pendingApprovals,
            'pending_topping_la' => $pendingToppingLa,
            'pending_topping_aux' => $pendingToppingAux,
            'has_topping_la' => $hasToppingLa,
            'has_topping_aux' => $hasToppingAux,
            'td_color' => $tdColor,
            'ta_color' => $taColor,
            'la_complete' => $laComplete,
            'aux_complete' => $auxComplete,
            'la_initial_complete' => $laInitialComplete ?? $laComplete,
            'aux_initial_complete' => $auxInitialComplete ?? $auxComplete,
        ];
    }

    /**
     * Ambil semua proses ID yang terlibat dalam swap position approval
     * 
     * @return array
     */
    public function getAffectedProsesIds(): array
    {
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
        
        return $affectedProsesIds;
    }
}
