<?php

namespace App\Http\Controllers;

use App\Models\Auxl;
use App\Models\TicketDetail;
use App\Services\ReportGiService;
use Illuminate\Http\Request;

class ReportGiController extends Controller
{
    protected $reportGiService;

    public function __construct(ReportGiService $reportGiService)
    {
        $this->reportGiService = $reportGiService;
    }

    /**
     * Tampilan utama tabel laporan GI
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $data = $this->reportGiService->getData($request, $perPage);

        return view('report_gi.index', [
            'records' => $data['records'],
            'summary' => $data['summary'],
            'filters' => $data['filters'],
            'perPage' => $perPage,
        ]);
    }

    /**
     * Endpoint AJAX untuk memuat rincian bahan kimia (Dye Stuff / Aux) pada modal
     */
    public function getChemicalDetails(Request $request, string $type, string $barcode)
    {
        if ($type === 'la') {
            $items = TicketDetail::where('id_no', $barcode)
                ->select([
                    'id',
                    'step_no',
                    'product_code',
                    'product_name',
                    'target_wt',
                    'actual_wt',
                    'unit',
                    'comp_date',
                    'comp_time',
                    'recipe_code',
                    'machine',
                ])
                ->orderBy('step_no')
                ->orderBy('id')
                ->get();

            $totalTargetWt = (float) $items->sum('target_wt');
            $totalActualWt = (float) $items->sum('actual_wt');

            return response()->json([
                'status' => 'success',
                'type' => 'la',
                'barcode' => $barcode,
                'total_target_wt' => $totalTargetWt,
                'total_actual_wt' => $totalActualWt,
                'uom' => 'Gram',
                'count' => $items->count(),
                'items' => $items,
            ]);
        }

        if ($type === 'aux') {
            $auxl = Auxl::with(['details:id,auxl_id,auxiliary,konsentrasi'])
                ->where('barcode', $barcode)
                ->first();

            return response()->json([
                'status' => 'success',
                'type' => 'aux',
                'barcode' => $barcode,
                'total_wt' => $auxl?->total_wt ? (float) $auxl->total_wt : 0,
                'volume_litres' => $auxl?->volume_litres,
                'liquor_ratio' => $auxl?->liquor_ratio,
                'color' => $auxl?->color,
                'uom' => 'KG',
                'count' => $auxl?->details ? $auxl->details->count() : 0,
                'items' => $auxl?->details ?? [],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Tipe bahan kimia tidak valid.',
        ], 400);
    }
}
