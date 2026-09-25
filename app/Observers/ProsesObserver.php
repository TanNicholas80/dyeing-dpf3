<?php

namespace App\Observers;

use App\Models\Proses;
use App\Events\ProsesStatusUpdated;
use App\Services\ProsesStatusService;
use Illuminate\Support\Facades\Log;

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
            try {
                // Load relasi yang diperlukan
                $proses->load(['mesin', 'approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs']);
                
                // Generate status data
                $statusService = new ProsesStatusService();
                $affectedProsesIds = $statusService->getAffectedProsesIds();
                $statusData = $statusService->generateProsesStatus($proses, $affectedProsesIds);
                
                // Broadcast event
                event(new ProsesStatusUpdated($proses->id, $statusData));
            } catch (\Throwable $e) {
                Log::warning('Gagal broadcast ProsesStatusUpdated dari ProsesObserver [Proses ID: ' . $proses->id . ']: ' . $e->getMessage());
            }
        }
    }
}
