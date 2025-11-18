<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
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
            ->paginate(5);

        // Ambil nomor halaman dari query string jika ada (untuk redirect setelah tambah proses)
        $currentPage = $request->query('page', 1);

        return view('dashboard', compact('mesins', 'currentPage'));
    }
}
