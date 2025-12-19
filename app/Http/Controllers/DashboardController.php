<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use App\Models\Proses;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
        $prosesQuery = Proses::with(['barcodeKains', 'barcodeLas', 'barcodeAuxs', 'mesin']);
        if (count($selectedMesinArr) > 0) {
            $prosesQuery->whereIn('mesin_id', $selectedMesinArr);
        }
        $prosesList = $prosesQuery->orderBy('id', 'asc')->get();

        return view('dashboard', [
            'mesins' => $mesins,
            'currentPage' => $currentPage,
            'prosesList' => $prosesList,
            'selectedMesinArr' => $selectedMesinArr,
        ]);
    }
}
