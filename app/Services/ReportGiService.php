<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportGiService
{
    /**
     * Mengambil data gabungan GI (Kain, Dye Stuff, Aux) dengan optimasi query
     * dan 100% kompatibel dengan PostgreSQL & MySQL (menggunakan ANSI SQL).
     */
    public function getData(Request $request, int $perPage = 25)
    {
        $startDate = $request->input('start_date', now()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $jenis = $request->input('jenis', 'all');
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'active'); // 'active', 'cancel', 'all'

        $queries = [];

        // 1. Barcode Kain (Greige & Finish)
        if (empty($jenis) || $jenis === 'all' || in_array($jenis, ['Greige', 'Finish'], true)) {
            $kainQuery = DB::table('barcode_kain')
                ->join('detail_proses', 'barcode_kain.detail_proses_id', '=', 'detail_proses.id')
                ->join('proses', 'detail_proses.proses_id', '=', 'proses.id')
                ->select([
                    'barcode_kain.id as raw_id',
                    DB::raw("'kain' as source_type"),
                    'barcode_kain.barcode',
                    'barcode_kain.no_op',
                    'barcode_kain.no_partai',
                    DB::raw("COALESCE(
                        NULLIF(detail_proses.konstruksi, ''),
                        (SELECT dp_sub.konstruksi FROM detail_proses dp_sub WHERE dp_sub.no_op = barcode_kain.no_op AND dp_sub.konstruksi IS NOT NULL AND dp_sub.konstruksi != '' LIMIT 1)
                    ) as konstruksi"),
                    DB::raw("CAST(COALESCE(barcode_kain.qty_gi, 0) AS DECIMAL(15,4)) as qty"),
                    DB::raw("'KG' as uom"),
                    DB::raw("CASE WHEN proses.mode = 'finish' THEN 'Finish' ELSE 'Greige' END as jenis"),
                    'barcode_kain.created_at as tanggal_gi',
                    'proses.id as proses_id',
                    'detail_proses.id as detail_proses_id',
                    'barcode_kain.cancel as is_cancel',
                ]);

            if ($jenis === 'Greige') {
                $kainQuery->where('proses.mode', 'greige');
            } elseif ($jenis === 'Finish') {
                $kainQuery->where('proses.mode', 'finish');
            }

            if (!empty($startDate) && !empty($endDate)) {
                $kainQuery->whereBetween('barcode_kain.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
            if ($status === 'active') {
                $kainQuery->where('barcode_kain.cancel', false);
            } elseif ($status === 'cancel') {
                $kainQuery->where('barcode_kain.cancel', true);
            }
            if ($search !== '') {
                $searchLower = strtolower($search);
                $kainQuery->where(function ($q) use ($searchLower) {
                    $q->whereRaw("LOWER(barcode_kain.no_op) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(barcode_kain.no_partai) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(barcode_kain.barcode) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(COALESCE(detail_proses.konstruksi, '')) LIKE ?", ["%{$searchLower}%"]);
                });
            }

            $queries[] = $kainQuery;
        }

        // 2. Barcode LA (Dye Stuff & Topping Dye Stuff)
        if (empty($jenis) || $jenis === 'all' || in_array($jenis, ['Dye Stuff', 'Topping Dye Stuff'], true)) {
            $laQuery = DB::table('barcode_la')
                ->join('detail_proses', 'barcode_la.detail_proses_id', '=', 'detail_proses.id')
                ->join('proses', 'detail_proses.proses_id', '=', 'proses.id')
                ->select([
                    'barcode_la.id as raw_id',
                    DB::raw("'la' as source_type"),
                    'barcode_la.barcode',
                    'barcode_la.no_op',
                    'barcode_la.no_partai',
                    DB::raw("COALESCE(
                        NULLIF(detail_proses.konstruksi, ''),
                        (SELECT td_sub.fabric_name FROM ticket_details td_sub WHERE td_sub.id_no = barcode_la.barcode AND td_sub.fabric_name IS NOT NULL AND td_sub.fabric_name != '' LIMIT 1),
                        (SELECT dp_sub.konstruksi FROM detail_proses dp_sub WHERE dp_sub.no_op = barcode_la.no_op AND dp_sub.konstruksi IS NOT NULL AND dp_sub.konstruksi != '' LIMIT 1)
                    ) as konstruksi"),
                    DB::raw("CAST((SELECT COALESCE(SUM(actual_wt), 0) FROM ticket_details WHERE ticket_details.id_no = barcode_la.barcode) AS DECIMAL(15,4)) as qty"),
                    DB::raw("'Gram' as uom"),
                    DB::raw("CASE WHEN barcode_la.approval_id IS NOT NULL THEN 'Topping Dye Stuff' ELSE 'Dye Stuff' END as jenis"),
                    'barcode_la.created_at as tanggal_gi',
                    'proses.id as proses_id',
                    'detail_proses.id as detail_proses_id',
                    'barcode_la.cancel as is_cancel',
                ]);

            if ($jenis === 'Dye Stuff') {
                $laQuery->whereNull('barcode_la.approval_id');
            } elseif ($jenis === 'Topping Dye Stuff') {
                $laQuery->whereNotNull('barcode_la.approval_id');
            }

            if (!empty($startDate) && !empty($endDate)) {
                $laQuery->whereBetween('barcode_la.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
            if ($status === 'active') {
                $laQuery->where('barcode_la.cancel', false);
            } elseif ($status === 'cancel') {
                $laQuery->where('barcode_la.cancel', true);
            }
            if ($search !== '') {
                $searchLower = strtolower($search);
                $laQuery->where(function ($q) use ($searchLower) {
                    $q->whereRaw("LOWER(barcode_la.no_op) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(barcode_la.no_partai) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(barcode_la.barcode) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(COALESCE(detail_proses.konstruksi, '')) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereExists(function ($sub) use ($searchLower) {
                          $sub->select(DB::raw(1))
                              ->from('ticket_details')
                              ->whereColumn('ticket_details.id_no', 'barcode_la.barcode')
                              ->whereRaw("LOWER(ticket_details.fabric_name) LIKE ?", ["%{$searchLower}%"]);
                      });
                });
            }

            $queries[] = $laQuery;
        }

        // 3. Barcode AUX (Aux & Topping Aux)
        if (empty($jenis) || $jenis === 'all' || in_array($jenis, ['Aux', 'Topping Aux'], true)) {
            $auxQuery = DB::table('barcode_aux')
                ->join('detail_proses', 'barcode_aux.detail_proses_id', '=', 'detail_proses.id')
                ->join('proses', 'detail_proses.proses_id', '=', 'proses.id')
                ->leftJoin('auxls', 'barcode_aux.barcode', '=', 'auxls.barcode')
                ->select([
                    'barcode_aux.id as raw_id',
                    DB::raw("'aux' as source_type"),
                    'barcode_aux.barcode',
                    'barcode_aux.no_op',
                    'barcode_aux.no_partai',
                    DB::raw("COALESCE(
                        NULLIF(detail_proses.konstruksi, ''),
                        NULLIF(auxls.konstruksi, ''),
                        (SELECT dp_sub.konstruksi FROM detail_proses dp_sub WHERE dp_sub.no_op = barcode_aux.no_op AND dp_sub.konstruksi IS NOT NULL AND dp_sub.konstruksi != '' LIMIT 1)
                    ) as konstruksi"),
                    DB::raw("CAST(COALESCE(auxls.total_wt, 0) AS DECIMAL(15,4)) as qty"),
                    DB::raw("'KG' as uom"),
                    DB::raw("CASE WHEN barcode_aux.approval_id IS NOT NULL THEN 'Topping Aux' ELSE 'Aux' END as jenis"),
                    'barcode_aux.created_at as tanggal_gi',
                    'proses.id as proses_id',
                    'detail_proses.id as detail_proses_id',
                    'barcode_aux.cancel as is_cancel',
                ]);

            if ($jenis === 'Aux') {
                $auxQuery->whereNull('barcode_aux.approval_id');
            } elseif ($jenis === 'Topping Aux') {
                $auxQuery->whereNotNull('barcode_aux.approval_id');
            }

            if (!empty($startDate) && !empty($endDate)) {
                $auxQuery->whereBetween('barcode_aux.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
            if ($status === 'active') {
                $auxQuery->where('barcode_aux.cancel', false);
            } elseif ($status === 'cancel') {
                $auxQuery->where('barcode_aux.cancel', true);
            }
            if ($search !== '') {
                $searchLower = strtolower($search);
                $auxQuery->where(function ($q) use ($searchLower) {
                    $q->whereRaw("LOWER(barcode_aux.no_op) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(barcode_aux.no_partai) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(barcode_aux.barcode) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(COALESCE(detail_proses.konstruksi, '')) LIKE ?", ["%{$searchLower}%"])
                      ->orWhereRaw("LOWER(COALESCE(auxls.konstruksi, '')) LIKE ?", ["%{$searchLower}%"]);
                });
            }

            $queries[] = $auxQuery;
        }

        // Jika tidak ada query yang cocok
        if (empty($queries)) {
            return [
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage),
                'summary' => (object)[
                    'total_transaksi' => 0,
                    'total_kain_kg' => 0,
                    'total_dyes_gr' => 0,
                    'total_aux_kg' => 0,
                ],
                'filters' => compact('startDate', 'endDate', 'jenis', 'search', 'status'),
            ];
        }

        // Satukan query jika ada lebih dari 1 dengan UNION ALL
        $firstQuery = array_shift($queries);
        foreach ($queries as $sub) {
            $firstQuery->unionAll($sub);
        }

        $mainQuery = DB::query()->fromSub($firstQuery, 'report_gi');

        // Hitung Summary KPI dalam 1 query ringkas dan optimal
        $summary = (clone $mainQuery)->selectRaw("
            COUNT(*) as total_transaksi,
            COALESCE(SUM(CASE WHEN source_type = 'kain' THEN qty ELSE 0 END), 0) as total_kain_kg,
            COALESCE(SUM(CASE WHEN source_type = 'la' THEN qty ELSE 0 END), 0) as total_dyes_gr,
            COALESCE(SUM(CASE WHEN source_type = 'aux' THEN qty ELSE 0 END), 0) as total_aux_kg
        ")->first();

        // Paginate hasil urut berdasarkan tanggal GI terbaru
        $records = $mainQuery->orderByDesc('tanggal_gi')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'records' => $records,
            'summary' => $summary,
            'filters' => compact('startDate', 'endDate', 'jenis', 'search', 'status'),
        ];
    }
}
