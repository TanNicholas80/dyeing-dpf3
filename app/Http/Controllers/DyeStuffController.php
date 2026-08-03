<?php

namespace App\Http\Controllers;

use App\Models\TicketDetail;
use App\Models\BarcodeLa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DyeStuffController extends Controller
{
    /**
     * Display grouped Ticket Detail (Dye Stuff / LA) records with high performance DB grouping & pagination.
     * Fully compatible with PostgreSQL & MySQL databases.
     */
    public function index(Request $request)
    {
        $query = TicketDetail::select(
                'id_no',
                DB::raw('MAX(recipe_code) as recipe_code'),
                DB::raw('MAX(machine) as machine'),
                DB::raw('MAX(product_lot) as product_lot'),
                DB::raw('MAX(comp_date) as comp_date'),
                DB::raw('MAX(comp_time) as comp_time'),
                DB::raw('COUNT(*) as items_count'),
                DB::raw('SUM(target_wt) as total_target_wt'),
                DB::raw('SUM(actual_wt) as total_actual_wt'),
                DB::raw("SUM(CASE WHEN actual_wt > 0 AND comp_date IS NOT NULL AND TRIM(comp_date) != '' THEN 1 ELSE 0 END) as weighed_items_count"),
                DB::raw('MAX(id) as max_id')
            )
            ->whereNotNull('id_no')
            ->where('id_no', '!=', '')
            ->groupBy('id_no');

        // Filter Pencarian (Case-Insensitive Search: Support PostgreSQL ILIKE & MySQL LIKE)
        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(id_no) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(recipe_code) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(machine) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(product_lot) LIKE ?', ["%{$search}%"]);
            });
        }

        // Filter Status Penimbangan (sudah, parsial, belum)
        if ($request->filled('status_timbang')) {
            if ($request->status_timbang === 'sudah') {
                $query->havingRaw("SUM(CASE WHEN actual_wt > 0 AND comp_date IS NOT NULL AND TRIM(comp_date) != '' THEN 1 ELSE 0 END) = COUNT(*)");
            } elseif ($request->status_timbang === 'parsial') {
                $query->havingRaw("SUM(CASE WHEN actual_wt > 0 AND comp_date IS NOT NULL AND TRIM(comp_date) != '' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN actual_wt > 0 AND comp_date IS NOT NULL AND TRIM(comp_date) != '' THEN 1 ELSE 0 END) < COUNT(*)");
            } elseif ($request->status_timbang === 'belum') {
                $query->havingRaw("SUM(CASE WHEN actual_wt > 0 AND comp_date IS NOT NULL AND TRIM(comp_date) != '' THEN 1 ELSE 0 END) = 0");
            }
        }

        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 25;
        }

        // Execute Paginated DB Query (Compatible with PostgreSQL & MySQL)
        $dyeStuffs = $query->orderByDesc(DB::raw('MAX(id)'))
            ->paginate($perPage)
            ->withQueryString();

        $pageBarcodes = $dyeStuffs->pluck('id_no')->filter()->toArray();

        // Pengecekan BarcodeLa HANYA untuk barcode yang ada di halaman aktif (Max 25-100 data)
        $usageCountsByBarcode = [];
        if (!empty($pageBarcodes)) {
            $usageCountsByBarcode = BarcodeLa::whereIn('barcode', $pageBarcodes)
                ->where('cancel', false)
                ->selectRaw('barcode, COUNT(*) as total')
                ->groupBy('barcode')
                ->pluck('total', 'barcode')
                ->toArray();
        }

        // Transform koleksi halaman aktif dengan properti pendukung
        $dyeStuffs->getCollection()->transform(function ($item) use ($usageCountsByBarcode) {
            $usedCount = (int) ($usageCountsByBarcode[$item->id_no] ?? 0);
            $item->barcode = $item->id_no;
            $item->isUsedByProses = $usedCount > 0;
            $item->usedCount = $usedCount;

            $itemsCount = (int) $item->items_count;
            $weighedCount = (int) $item->weighed_items_count;

            if ($weighedCount === $itemsCount && $itemsCount > 0) {
                $item->status_timbang = 'sudah';
            } elseif ($weighedCount > 0 && $weighedCount < $itemsCount) {
                $item->status_timbang = 'parsial';
            } else {
                $item->status_timbang = 'belum';
            }
            $item->is_weighed = $item->status_timbang === 'sudah';
            $item->weighed_count = $weighedCount;
            return $item;
        });

        return view('dye_stuff.index', compact('dyeStuffs'));
    }

    /**
     * Display detailed chemical items for a specific barcode (id_no).
     */
    public function show($id)
    {
        $ticketDetails = TicketDetail::where('id_no', $id)->get();

        if ($ticketDetails->isEmpty()) {
            return redirect()->route('dye-stuff.index')->with('error', 'Data Barcode Dye Stuff / Ticket Detail tidak ditemukan.');
        }

        $first = $ticketDetails->first();
        $usedCount = BarcodeLa::where('barcode', $id)->where('cancel', false)->count();

        $itemsCount = $ticketDetails->count();
        $weighedCount = $ticketDetails->filter(function ($item) {
            return !empty($item->actual_wt) && ((float) $item->actual_wt > 0) && !empty($item->comp_date) && trim($item->comp_date) !== '';
        })->count();

        if ($weighedCount === $itemsCount && $itemsCount > 0) {
            $statusTimbang = 'sudah';
        } elseif ($weighedCount > 0 && $weighedCount < $itemsCount) {
            $statusTimbang = 'parsial';
        } else {
            $statusTimbang = 'belum';
        }

        $summary = (object) [
            'id_no'           => $id,
            'barcode'         => $id,
            'recipe_code'     => $first->recipe_code ?? '-',
            'machine'         => $first->machine ?? '-',
            'product_lot'     => $first->product_lot ?? '-',
            'comp_date'       => $first->comp_date ?? '-',
            'comp_time'       => $first->comp_time ?? '-',
            'total_target_wt' => $ticketDetails->sum('target_wt'),
            'total_actual_wt' => $ticketDetails->sum('actual_wt'),
            'items_count'     => $itemsCount,
            'weighed_count'   => $weighedCount,
            'status_timbang'  => $statusTimbang,
            'isUsedByProses'  => $usedCount > 0,
            'usedCount'       => $usedCount,
            // Field Tambahan Sesuai Layout Tiket fisik
            'batch_no'        => $first->batch_no ?? '-',
            'no_jo'           => $first->res_string1 ?? '-',
            'fabric_name'     => $first->product_lot ?? '-',
            'customer_name'   => $first->res_string2 ?? '-',
            'total_wt_kg'     => $first->fabric_weight ?? 0,
            'color_name'      => $first->res_string3 ?? '-',
            'order_no'        => $first->res_string4 ?? '-',
            'volume'          => $first->volume ?? 0,
            'lr'              => $first->lr ?? '6.0',
            'type_name'       => $first->recipe_type ?: ($first->id_type ?: 'Normal'),
            'print_time'      => now()->format('Y-m-d H:i:s'),
        ];

        return view('dye_stuff.show', compact('summary', 'ticketDetails'));
    }

    /**
     * Print barcode label for a specific id_no.
     */
    public function print($id)
    {
        $ticketDetails = TicketDetail::where('id_no', $id)->get();

        if ($ticketDetails->isEmpty()) {
            return redirect()->route('dye-stuff.index')->with('error', 'Data Barcode Dye Stuff tidak ditemukan.');
        }

        $first = $ticketDetails->first();
        $summary = (object) [
            'id_no'           => $id,
            'barcode'         => $id,
            'recipe_code'     => $first->recipe_code ?? '-',
            'machine'         => $first->machine ?? '-',
            'product_lot'     => $first->product_lot ?? '-',
            'comp_date'       => $first->comp_date ?? '-',
            'comp_time'       => $first->comp_time ?? '-',
            'total_target_wt' => $ticketDetails->sum('target_wt'),
            'total_actual_wt' => $ticketDetails->sum('actual_wt'),
            'items_count'     => $ticketDetails->count(),
            // Field Tambahan Sesuai Layout Tiket fisik
            'batch_no'        => $first->batch_no ?? '-',
            'no_jo'           => $first->res_string1 ?? '-',
            'fabric_name'     => $first->product_lot ?? '-',
            'customer_name'   => $first->res_string2 ?? '-',
            'total_wt_kg'     => $first->fabric_weight ?? 0,
            'color_name'      => $first->res_string3 ?? '-',
            'order_no'        => $first->res_string4 ?? '-',
            'volume'          => $first->volume ?? 0,
            'lr'              => $first->lr ?? '6.0',
            'type_name'       => $first->recipe_type ?: ($first->id_type ?: 'Normal'),
            'print_time'      => now()->format('Y-m-d H:i:s'),
        ];

        return view('dye_stuff.print', compact('summary', 'ticketDetails'));
    }

    /**
     * Print multiple barcode labels in bulk.
     */
    public function printBulk(Request $request)
    {
        $rawIds = $request->query('ids', '');
        $barcodes = array_filter(explode(',', $rawIds));

        $groupedDetails = TicketDetail::whereIn('id_no', $barcodes)
            ->get()
            ->groupBy('id_no');

        if ($groupedDetails->isEmpty()) {
            return redirect()->route('dye-stuff.index')->with('error', 'Tidak ada data Dye Stuff yang dipilih.');
        }

        $dyeStuffs = $groupedDetails->map(function ($items, $barcode) {
            $first = $items->first();
            return (object) [
                'id_no'           => $barcode,
                'barcode'         => $barcode,
                'recipe_code'     => $first->recipe_code ?? '-',
                'machine'         => $first->machine ?? '-',
                'product_lot'     => $first->product_lot ?? '-',
                'comp_date'       => $first->comp_date ?? '-',
                'comp_time'       => $first->comp_time ?? '-',
                'total_target_wt' => $items->sum('target_wt'),
                'total_actual_wt' => $items->sum('actual_wt'),
                'items_count'     => $items->count(),
                'items'           => $items,
                // Field Tambahan Sesuai Layout Tiket fisik
                'batch_no'        => $first->batch_no ?? '-',
                'no_jo'           => $first->res_string1 ?? '-',
                'fabric_name'     => $first->product_lot ?? '-',
                'customer_name'   => $first->res_string2 ?? '-',
                'total_wt_kg'     => $first->fabric_weight ?? 0,
                'color_name'      => $first->res_string3 ?? '-',
                'order_no'        => $first->res_string4 ?? '-',
                'volume'          => $first->volume ?? 0,
                'lr'              => $first->lr ?? '6.0',
                'type_name'       => $first->recipe_type ?: ($first->id_type ?: 'Normal'),
                'print_time'      => now()->format('Y-m-d H:i:s'),
            ];
        })->values();

        return view('dye_stuff.print_bulk', compact('dyeStuffs'));
    }
}
