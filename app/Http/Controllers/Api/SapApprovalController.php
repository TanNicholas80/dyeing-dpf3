<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BarcodeKain;
use App\Models\DetailProses;
use App\Models\Proses;
use App\Events\BarcodeStatusUpdated;
use App\Services\ProsesStatusService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SapApprovalController extends Controller
{
    /**
     * Endpoint callback dari SAP untuk update status approval Barcode Kain (Over Limit GI).
     *
     * Format Request yang diseragamkan:
     * {
     *     "AUFNR": "066000000098",
     *     "items": [
     *         {
     *             "barcode": "AA00001194",
     *             "status": "APPROVED",
     *             "mblnr": "4903635006",
     *             "zeile": 1,
     *             "menge": 25.5,
     *             "message": "Success"
     *         },
     *         {
     *             "barcode": "AA00001195",
     *             "status": "REJECTED",
     *             "mblnr": "0",
     *             "zeile": 0,
     *             "menge": 0,
     *             "message": "Over limit tidak disetujui"
     *         }
     *     ]
     * }
     */
    public function updateApprovalOverGi(Request $request)
    {
        Log::info('SAP API Approval Callback received', ['payload' => $request->all()]);

        $payload = $request->all();
        $aufnr = trim((string) ($payload['AUFNR'] ?? $payload['aufnr'] ?? $payload['no_op'] ?? ''));
        $items = [];

        if (isset($payload['items']) && is_array($payload['items'])) {
            $items = $payload['items'];
        } elseif (isset($payload['barcode']) && is_array($payload['barcode']) && isset($payload['barcode'][0])) {
            $items = $payload['barcode'];
        } elseif (is_array($payload) && isset($payload[0]) && is_array($payload[0])) {
            $items = $payload;
        } elseif (!empty($payload['barcode']) || !empty($payload['BARCODE'])) {
            $items = [$payload];
        }

        if (empty($items)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format payload tidak valid atau daftar items kosong.'
            ], 400);
        }

        $results = [];
        $affectedProsesIds = [];

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $rawBarcode = $item['barcode'] ?? $item['BARCODE'] ?? null;
                if (!$rawBarcode) {
                    continue;
                }
                $barcodeStr = trim((string) $rawBarcode);

                $rawStatus = strtoupper(trim((string) ($item['status'] ?? $item['STATUS'] ?? $item['stats'] ?? '')));
                $isApproved = in_array($rawStatus, ['APPROVED', 'APPROVE', 'SUCCESS', 'OK', '1', 'TRUE']);
                $isRejected = in_array($rawStatus, ['REJECTED', 'REJECT', 'FAILED', 'ERROR', '0', 'FALSE']);

                if (!$isApproved && !$isRejected) {
                    $isApproved = true; // default bila response sukses
                }

                $query = BarcodeKain::where('barcode', $barcodeStr)
                    ->where('cancel', false);

                // Cocokkan dengan nomor OP bila tersedia
                $itemAufnr = trim((string) ($item['AUFNR'] ?? $item['no_op'] ?? $aufnr));
                if (!empty($itemAufnr)) {
                    $query->where('no_op', $itemAufnr);
                }

                $record = $query->orderByDesc('id')->first();

                if (!$record) {
                    $results[] = [
                        'barcode' => $barcodeStr,
                        'status' => 'NOT_FOUND',
                        'message' => 'Barcode tidak ditemukan di database atau sudah dibatalkan.'
                    ];
                    continue;
                }

                if ($isApproved) {
                    $matdok = trim((string) ($item['mblnr'] ?? $item['MBLNR'] ?? $record->matdok));
                    $zeile = trim((string) ($item['zeile'] ?? $item['ZEILE'] ?? $item['item_document'] ?? $record->item_document));
                    $menge = isset($item['menge']) ? (float) $item['menge'] : (isset($item['MENGE']) ? (float) $item['MENGE'] : $record->qty_gi);
                    $message = trim((string) ($item['message'] ?? $item['MESSAGE'] ?? 'Success'));

                    $record->update([
                        'approval_status' => 'approved',
                        'matdok' => (!empty($matdok) && $matdok !== '0') ? $matdok : $record->matdok,
                        'item_document' => (!empty($zeile) && $zeile !== '0') ? $zeile : $record->item_document,
                        'qty_gi' => $menge > 0 ? $menge : $record->qty_gi,
                        'approved_at' => now(),
                        'approval_reject_reason' => null,
                    ]);

                    $results[] = [
                        'barcode' => $barcodeStr,
                        'status' => 'APPROVED',
                        'mblnr' => $record->matdok,
                        'zeile' => $record->item_document,
                        'menge' => $record->qty_gi,
                        'message' => !empty($message) ? $message : 'Success',
                    ];
                } else {
                    $reason = trim((string) ($item['message'] ?? $item['MESSAGE'] ?? $item['reason'] ?? 'Over limit tidak disetujui'));

                    // Status rejected: cancel tetap false agar barcode muncul di modal dengan badge 'Ditolak SAP'
                    // dan operator dapat melihat opsi cancel barcode untuk membatalkannya.
                    $record->update([
                        'approval_status' => 'rejected',
                        'cancel' => false,
                        'approval_reject_reason' => $reason,
                    ]);

                    $results[] = [
                        'barcode' => $barcodeStr,
                        'status' => 'REJECTED',
                        'mblnr' => '0',
                        'zeile' => 0,
                        'menge' => 0,
                        'message' => $reason,
                    ];
                }

                if ($record->detail_proses_id) {
                    $detail = DetailProses::find($record->detail_proses_id);
                    if ($detail && $detail->proses_id) {
                        $affectedProsesIds[] = (int) $detail->proses_id;
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SAP API Approval Callback Error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses data approval: ' . $e->getMessage()
            ], 500);
        }

        // Broadcast real-time WebSocket update untuk semua proses yang terpengaruh
        $affectedProsesIds = array_unique($affectedProsesIds);
        $statusService = new ProsesStatusService();
        $globalAffected = $statusService->getAffectedProsesIds();

        foreach ($affectedProsesIds as $pId) {
            try {
                $p = Proses::with(['approvals', 'details.barcodeKains', 'details.barcodeLas', 'details.barcodeAuxs'])->find($pId);
                if ($p) {
                    $statusData = $statusService->generateProsesStatus($p, $globalAffected);
                    event(new BarcodeStatusUpdated($p->id, $statusData));
                    Cache::forget("iot:mesin:{$p->mesin_id}:alarm_result");
                }
            } catch (\Exception $e) {
                Log::warning('Failed broadcasting BarcodeStatusUpdated in SAP callback', ['proses_id' => $pId, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'status' => 'success',
            'AUFNR' => $aufnr,
            'message' => 'Data approval SAP berhasil diproses.',
            'processed_count' => count($results),
            'items' => $results,
        ], 200);
    }
}
