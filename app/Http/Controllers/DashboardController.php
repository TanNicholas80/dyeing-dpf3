<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use App\Models\Proses;
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

        // Sembunyikan proses Reproses yang masih menunggu approval VP (status pending)
        $prosesQuery->whereNot(function ($q) {
            $q->where('jenis', 'Reproses')
              ->whereExists(function ($sub) {
                  $sub->select(DB::raw(1))
                      ->from('approvals')
                      ->whereColumn('approvals.proses_id', 'proses.id')
                      ->where('approvals.type', 'VP')
                      ->where('approvals.action', 'create_reprocess')
                      ->where('approvals.status', 'pending');
              });
        });

        $prosesList = $prosesQuery->orderBy('id', 'asc')->get();

        return view('dashboard', [
            'mesins' => $mesins,
            'currentPage' => $currentPage,
            'prosesList' => $prosesList,
            'selectedMesinArr' => $selectedMesinArr,
        ]);
    }
}
