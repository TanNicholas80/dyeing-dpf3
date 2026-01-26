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

        return [
            'mulai' => $proses->mulai ? $proses->mulai->format('Y-m-d H:i:s') : null,
            'selesai' => $proses->selesai ? $proses->selesai->format('Y-m-d H:i:s') : null,
            'bg_color' => $bg,
            'jenis' => $proses->jenis,
            'order' => (int)($proses->order ?? 0),
            'gda_details' => $gdaDetails, // Status GDA per detail proses untuk update real-time
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
