<?php

namespace App\Http\Controllers;

use App\Models\AbsenDelegasi;
use App\Models\AbsenHistory;
use App\Services\AbsenService;
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

        // Ambil status delegasi saat ini
        $delegasiKashift = AbsenDelegasi::firstOrCreate(
            ['role_target' => 'kepala_shift'],
            [
                'is_active' => true,
                'current_shift' => AbsenService::detectCurrentShift(),
                'keterangan' => 'Default sistem: Hadir / Aktif',
                'updated_by' => $user->id,
            ]
        );

        $delegasiKaru = AbsenDelegasi::firstOrCreate(
            ['role_target' => 'kepala_ruangan'],
            [
                'is_active' => true,
                'current_shift' => AbsenService::detectCurrentShift(),
                'keterangan' => 'Default sistem: Hadir / Aktif',
                'updated_by' => $user->id,
            ]
        );

        $currentShift = AbsenService::detectCurrentShift();

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
            'delegasiKashift',
            'delegasiKaru',
            'currentShift',
            'histories'
        ));
    }

    /**
     * Ubah status absen ON / OFF oleh FM atau Super Admin.
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
            'shift' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $roleTarget = $validated['role_target'];
        $status = strtoupper($validated['status']);
        $shift = $validated['shift'] ?: AbsenService::detectCurrentShift();
        $keterangan = $validated['keterangan'] ?? null;

        $delegasi = AbsenService::toggleStatus(
            $roleTarget,
            $status,
            $shift,
            $keterangan,
            $user->id
        );

        $roleName = $roleTarget === 'kepala_shift' ? 'Kepala Shift' : 'Kepala Ruangan';
        $statusMsg = $status === 'OFF' 
            ? "berhasil diubah menjadi OFF (Absen). Wewenang dialihkan ke " . ($roleTarget === 'kepala_shift' ? 'Kepala Ruangan (KARU)' : 'Kepala Shift')
            : "berhasil diubah menjadi ON (Aktif kembali). Akses kembali normal.";

        activity('Manajemen Absen')
            ->performedOn($delegasi)
            ->causedBy($user)
            ->withProperties([
                'role_target' => $roleTarget,
                'status' => $status,
                'shift' => $shift,
                'keterangan' => $keterangan,
            ])
            ->log("Status absen {$roleName} diubah menjadi {$status} pada {$shift} oleh {$user->nama} ({$user->role}).");

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Status {$roleName} {$statusMsg}",
                'data' => $delegasi,
            ]);
        }

        return redirect()->route('absen.index')->with('success', "Status {$roleName} {$statusMsg}");
    }
}
