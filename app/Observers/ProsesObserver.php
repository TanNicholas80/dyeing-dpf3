<?php

namespace App\Observers;

use App\Models\Proses;
use App\Events\ProsesStatusUpdated;
use App\Services\ProsesStatusService;

class ProsesObserver
{
    /**
     * Handle the Proses "updated" event.
     */
    public function updated(Proses $proses): void
    {
        // Cek apakah field yang relevan berubah (mulai, selesai, order, jenis, cycle_time)
        $relevantFields = ['mulai', 'selesai', 'order', 'jenis', 'cycle_time', 'cycle_time_actual'];
        $changedFields = array_keys($proses->getChanges());
        
        // Jika ada field yang relevan berubah, broadcast event
        if (array_intersect($relevantFields, $changedFields)) {
            // Load relasi yang diperlukan
            $proses->load(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
            
            // Generate status data
            $statusService = new ProsesStatusService();
            $affectedProsesIds = $statusService->getAffectedProsesIds();
            $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
            
            // Broadcast event
            event(new ProsesStatusUpdated($proses->id, $statusData));
        }
    }
}
