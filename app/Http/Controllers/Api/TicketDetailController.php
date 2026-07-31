<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportTicketDetailCsvJob;
use App\Models\TicketDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketDetailController extends Controller
{
    /**
     * Display a listing of ticket details.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TicketDetail::query();

        if ($request->filled('batch_no')) {
            $query->where('batch_no', 'like', '%' . $request->batch_no . '%');
        }

        if ($request->filled('product_code')) {
            $query->where('product_code', 'like', '%' . $request->product_code . '%');
        }

        if ($request->filled('product_name')) {
            $query->where('product_name', 'like', '%' . $request->product_name . '%');
        }

        if ($request->filled('machine')) {
            $query->where('machine', $request->machine);
        }

        if ($request->filled('id_no')) {
            $query->where('id_no', $request->id_no);
        }

        $perPage = $request->input('per_page', 25);
        $ticketDetails = $query->latest('id')->paginate($perPage);

        return response()->json($ticketDetails);
    }

    /**
     * Export all ticket details as CSV stream.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $fileName = 'ticket_details_export_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'ID_NO', 'STEP_NO', 'PRODUCT_CODE', 'PRODUCT_NAME', 'PRODUCT_TYPE', 'TARGET_WT', 'ACTUAL_WT', 'UNIT',
            'COMP_DATE', 'COMP_TIME', 'TRANSFER_STATE', 'ERROR_CODE', 'MACHINE', 'TANK_NO', 'ID_TYPE',
            'PRODUCT_LOT', 'RECIPE_CODE', 'LR', 'FABRIC_WEIGHT', 'VOLUME', 'RECIPE_TYPE', 'CONC',
            'CONCUNIT', 'REMARK', 'ADJUST', 'PRICE', 'RES_DOUBLE1', 'RES_DOUBLE2', 'RES_DOUBLE3',
            'RES_DOUBLE4', 'RES_STRING1', 'RES_STRING2', 'RES_STRING3', 'RES_STRING4', 'REWEIGHT',
            'DyeWeightTime', 'ReDye', 'UserCode', 'UserAccount', 'Batch_NO', 'RECORD_ORDER',
            'Station', 'Process', 'GRAVITY', 'CurrentStock'
        ];

        $dbColumns = [
            'id_no', 'step_no', 'product_code', 'product_name', 'product_type', 'target_wt', 'actual_wt', 'unit',
            'comp_date', 'comp_time', 'transfer_state', 'error_code', 'machine', 'tank_no', 'id_type',
            'product_lot', 'recipe_code', 'lr', 'fabric_weight', 'volume', 'recipe_type', 'conc',
            'conc_unit', 'remark', 'adjust', 'price', 'res_double1', 'res_double2', 'res_double3',
            'res_double4', 'res_string1', 'res_string2', 'res_string3', 'res_string4', 'reweight',
            'dye_weight_time', 're_dye', 'user_code', 'user_account', 'batch_no', 'record_order',
            'station', 'process', 'gravity', 'current_stock'
        ];

        $callback = function () use ($columns, $dbColumns) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for SSMS / Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            // Write Header
            fputcsv($file, $columns);

            TicketDetail::chunk(500, function ($records) use ($file, $dbColumns) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($dbColumns as $col) {
                        $row[] = $record->{$col};
                    }
                    fputcsv($file, $row);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import CSV via file upload and dispatch background job.
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:51200', // max 50MB
        ]);

        $file = $request->file('csv_file');
        $storedPath = $file->storeAs('csv_imports', 'import_' . time() . '_' . uniqid() . '.csv');

        // Dispatch background job with stored path
        ImportTicketDetailCsvJob::dispatch($storedPath, null, true);

        return response()->json([
            'success' => true,
            'message' => 'File CSV berhasil diupload. Import data sedang diproses di Background Job.',
            'file_name' => $file->getClientOriginalName(),
        ], 202);
    }

    /**
     * Import small Delta CSV (for updated ACTUAL_WT / weighing data) and dispatch background job.
     */
    public function updateDeltaCsv(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:51200', // max 50MB
        ]);

        $file = $request->file('csv_file');
        $storedPath = $file->storeAs('csv_imports', 'delta_import_' . time() . '_' . uniqid() . '.csv');

        // Dispatch background job with stored path
        ImportTicketDetailCsvJob::dispatch($storedPath, null, true);

        return response()->json([
            'success' => true,
            'message' => 'File Delta CSV berhasil diupload. Pembaruan data (ACTUAL_WT) sedang diproses di Background Job.',
            'file_name' => $file->getClientOriginalName(),
        ], 202);
    }

    /**
     * Fetch CSV from external API URL and dispatch background job.
     */
    public function syncFromApi(Request $request): JsonResponse
    {
        $request->validate([
            'api_url' => 'required|url',
        ]);

        $apiUrl = $request->input('api_url');

        // Dispatch background job with API URL
        ImportTicketDetailCsvJob::dispatch(null, $apiUrl, false);

        return response()->json([
            'success' => true,
            'message' => 'Proses sync CSV dari API berhasil didaftarkan ke Background Job.',
            'api_url' => $apiUrl,
        ], 202);
    }
}
