<?php

namespace App\Http\Controllers;

use App\Models\AbsenDelegasi;
use App\Models\AbsenHistory;
use App\Services\AbsenService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbsenController extends Controller
{
    /**
     * Tampilkan halaman utama Menu Absen & Delegasi.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['super_admin', 'fm'], true)) {
            abort(403, 'Hanya Factory Manager (FM) dan Super Admin yang berhak mengakses menu Absen.');
        }

        // Lazy auto-reset: pastikan status shift lampau yang masih OFF langsung di-reset dan dicatat ke log
        AbsenService::checkAndPerformAutoReset();

        // Info shift & tanggal produksi saat ini
        $currentShiftInfo = AbsenService::getCurrentShiftDetails();

        // Tanggal yang sedang ditampilkan (default ke tanggal produksi hari ini)
        $activeDate = $request->input('active_date', $currentShiftInfo['production_date']);

        // Konfigurasi jam shift untuk tanggal aktif
        $scheduleConfig = AbsenService::getScheduleConfigForDate($activeDate);

        // Data status Shift 1 & Shift 2 untuk Kashift dan Karu pada tanggal aktif
        $delegations = AbsenService::getDayShiftDelegations($activeDate);
        $kashiftShift1 = $delegations['kepala_shift']['Shift 1'];
        $kashiftShift2 = $delegations['kepala_shift']['Shift 2'];
        $karuShift1 = $delegations['kepala_ruangan']['Shift 1'];
        $karuShift2 = $delegations['kepala_ruangan']['Shift 2'];

        // Query riwayat dengan filter
        $query = AbsenHistory::with('user')->orderByDesc('created_at');

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        if ($request->filled('shift')) {
            $query->where('shift', $request->shift);
        }

        if ($request->filled('role_target')) {
            $query->where('role_target', $request->role_target);
        }

        $histories = $query->paginate(15)->withQueryString();

        return view('absen.index', compact(
            'currentShiftInfo',
            'activeDate',
            'scheduleConfig',
            'kashiftShift1',
            'kashiftShift2',
            'karuShift1',
            'karuShift2',
            'histories'
        ));
    }

    /**
     * Ubah status absen ON / OFF untuk shift tertentu oleh FM atau Super Admin.
     */
    public function toggle(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['super_admin', 'fm'], true)) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Hanya FM dan Admin yang berwenang.'], 403);
            }
            return back()->with('error', 'Hanya Factory Manager (FM) dan Super Admin yang berhak mengubah status absen.');
        }

        $validated = $request->validate([
            'role_target' => 'required|in:kepala_shift,kepala_ruangan',
            'status' => 'required|in:ON,OFF,on,off',
            'shift' => 'required|string|max:50',
            'tanggal' => 'nullable|date',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $roleTarget = $validated['role_target'];
        $status = strtoupper($validated['status']);
        $shift = $validated['shift'];
        $keterangan = $validated['keterangan'] ?? null;
        $tanggal = $validated['tanggal'] ?? null;

        $delegasi = AbsenService::toggleStatus(
            $roleTarget,
            $status,
            $shift,
            $keterangan,
            $user->id,
            $tanggal
        );

        $roleName = $roleTarget === 'kepala_shift' ? 'Kepala Shift' : 'Kepala Ruangan';
        $statusMsg = $status === 'OFF' 
            ? "berhasil diubah menjadi OFF (Izin/Absen) untuk {$shift}. Wewenang dialihkan ke " . ($roleTarget === 'kepala_shift' ? 'Kepala Ruangan (KARU)' : 'Kepala Shift')
            : "berhasil diubah menjadi ON (Hadir/Normal) untuk {$shift}. Akses kembali normal.";

        if (function_exists('activity')) {
            activity('Manajemen Absen')
                ->performedOn($delegasi)
                ->causedBy($user)
                ->withProperties([
                    'role_target' => $roleTarget,
                    'status' => $status,
                    'shift' => $shift,
                    'tanggal' => $delegasi->tanggal ? $delegasi->tanggal->toDateString() : $tanggal,
                    'keterangan' => $keterangan,
                ])
                ->log("Status absen {$roleName} diubah menjadi {$status} pada {$shift} oleh {$user->nama} ({$user->role}).");
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Status {$roleName} {$statusMsg}",
                'data' => $delegasi,
            ]);
        }

        return redirect()->route('absen.index', ['active_date' => $delegasi->tanggal ? $delegasi->tanggal->toDateString() : $tanggal])
            ->with('success', "Status {$roleName} {$statusMsg}");
    }
}
