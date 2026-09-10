<?php

namespace App\Http\Controllers;

use App\Models\AbsenDelegasi;
use App\Models\AbsenHistory;
use App\Models\ShiftSchedule;
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

        // Cek apakah tanggal aktif memiliki jadwal kustom aktif
        $activeDateCustomSchedule = AbsenService::getActiveCustomScheduleForDate($activeDate);

        // Daftar penetapan jadwal kustom FM (aktif & riwayat)
        $customSchedules = ShiftSchedule::with(['creator', 'canceller'])
            ->orderByDesc('id')
            ->take(15)
            ->get();

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
            'activeDateCustomSchedule',
            'customSchedules',
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

        try {
            $delegasi = AbsenService::toggleStatus(
                $roleTarget,
                $status,
                $shift,
                $keterangan,
                $user->id,
                $tanggal
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 422);
            }
            return back()->with('error', $e->getMessage());
        }

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

    /**
     * Tetapkan jadwal kustom Shift 1 dan Shift 2 pada rentang tanggal tertentu oleh FM.
     */
    public function storeShiftSchedule(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['super_admin', 'fm'], true)) {
            abort(403, 'Hanya Factory Manager (FM) dan Super Admin yang berhak mengatur jadwal shift.');
        }

        $modeJadwal = $request->input('mode_jadwal', 'seragam');
        if (!in_array($modeJadwal, ['seragam', 'per_hari'], true)) {
            $modeJadwal = 'seragam';
        }

        $rules = [
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'nama_jadwal' => 'nullable|string|max:150',
            'keterangan' => 'nullable|string|max:500',
        ];

        if ($modeJadwal === 'seragam') {
            $rules['shift1_start'] = ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'];
            $rules['shift1_end'] = ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'];
            $rules['shift2_start'] = ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'];
            $rules['shift2_end'] = ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'];
        }

        $validated = $request->validate($rules);

        $tglMulai = $validated['tanggal_mulai'];
        $tglSelesai = $validated['tanggal_selesai'];

        // Cek apakah ada jadwal kustom aktif yang bertabrakan (overlapping)
        $overlap = ShiftSchedule::active()
            ->where(function ($q) use ($tglMulai, $tglSelesai) {
                $q->where('tanggal_mulai', '<=', $tglSelesai)
                  ->where('tanggal_selesai', '>=', $tglMulai);
            })
            ->first();

        if ($overlap) {
            $ovMulai = $overlap->tanggal_mulai->format('d/m/Y');
            $ovSelesai = $overlap->tanggal_selesai->format('d/m/Y');
            return back()->with('error', "Rentang tanggal ({$tglMulai} s/d {$tglSelesai}) bertabrakan dengan jadwal kustom aktif '{$overlap->nama_jadwal}' ({$ovMulai} s/d {$ovSelesai}). Silakan batalkan jadwal tersebut terlebih dahulu.");
        }

        // Format nama jadwal jika kosong
        $namaJadwal = $validated['nama_jadwal'];
        if (empty($namaJadwal)) {
            $modeLabel = $modeJadwal === 'per_hari' ? 'Jadwal Khusus' : 'Shift';
            $namaJadwal = "Penetapan {$modeLabel} " . Carbon::parse($tglMulai)->format('d/m') . ' - ' . Carbon::parse($tglSelesai)->format('d/m/Y');
        }

        if ($modeJadwal === 'per_hari') {
            $dailyConfig = [];

            // 1. Senin
            $seninUseDefault = $request->boolean('senin_use_default');
            if ($seninUseDefault) {
                $dailyConfig['senin'] = [
                    'use_default' => true,
                    'shift1_start' => '14:00',
                    'shift1_end' => '22:00',
                    'shift2_start' => '22:00',
                    'shift2_end' => '06:00',
                ];
            } else {
                $dailyConfig['senin'] = [
                    'use_default' => false,
                    'shift1_start' => substr($request->input('senin_shift1_start', '14:00'), 0, 5),
                    'shift1_end' => substr($request->input('senin_shift1_end', '22:00'), 0, 5),
                    'shift2_start' => substr($request->input('senin_shift2_start', '22:00'), 0, 5),
                    'shift2_end' => substr($request->input('senin_shift2_end', '06:00'), 0, 5),
                ];
            }

            // 2. Selasa s/d Kamis
            $dailyConfig['selasa_kamis'] = [
                'shift1_start' => substr($request->input('selasa_shift1_start', '06:00'), 0, 5),
                'shift1_end' => substr($request->input('selasa_shift1_end', '18:00'), 0, 5),
                'shift2_start' => substr($request->input('selasa_shift2_start', '18:00'), 0, 5),
                'shift2_end' => substr($request->input('selasa_shift2_end', '06:00'), 0, 5),
            ];

            // 3. Jumat
            $jumatUseDefault = $request->boolean('jumat_use_default');
            if ($jumatUseDefault) {
                $dailyConfig['jumat'] = [
                    'use_default' => true,
                    'shift1_start' => '06:00',
                    'shift1_end' => '19:00',
                    'shift2_start' => '19:00',
                    'shift2_end' => '08:00',
                ];
            } else {
                $dailyConfig['jumat'] = [
                    'use_default' => false,
                    'shift1_start' => substr($request->input('jumat_shift1_start', '06:00'), 0, 5),
                    'shift1_end' => substr($request->input('jumat_shift1_end', '19:00'), 0, 5),
                    'shift2_start' => substr($request->input('jumat_shift2_start', '19:00'), 0, 5),
                    'shift2_end' => substr($request->input('jumat_shift2_end', '08:00'), 0, 5),
                ];
            }

            // 4. Sabtu
            $sabtuIsOff = $request->input('sabtu_option', 'off') === 'off';
            if ($sabtuIsOff) {
                $dailyConfig['sabtu'] = [
                    'is_off' => true,
                    'off_description' => 'Libur Produksi mulai jam 08:00 (OFF)',
                ];
            } else {
                $dailyConfig['sabtu'] = [
                    'is_off' => false,
                    'shift1_start' => substr($request->input('sabtu_shift1_start', '06:00'), 0, 5),
                    'shift1_end' => substr($request->input('sabtu_shift1_end', '18:00'), 0, 5),
                    'shift2_start' => substr($request->input('sabtu_shift2_start', '18:00'), 0, 5),
                    'shift2_end' => substr($request->input('sabtu_shift2_end', '06:00'), 0, 5),
                ];
            }

            // Kolom utama tabel menggunakan jam Selasa-Kamis sebagai representasi umum
            $s1Start = $dailyConfig['selasa_kamis']['shift1_start'] . ':00';
            $s1End = $dailyConfig['selasa_kamis']['shift1_end'] . ':00';
            $s2Start = $dailyConfig['selasa_kamis']['shift2_start'] . ':00';
            $s2End = $dailyConfig['selasa_kamis']['shift2_end'] . ':00';
        } else {
            $dailyConfig = null;
            $s1Start = substr($validated['shift1_start'], 0, 5) . ':00';
            $s1End = substr($validated['shift1_end'], 0, 5) . ':00';
            $s2Start = substr($validated['shift2_start'], 0, 5) . ':00';
            $s2End = substr($validated['shift2_end'], 0, 5) . ':00';
        }

        $schedule = ShiftSchedule::create([
            'nama_jadwal' => $namaJadwal,
            'mode_jadwal' => $modeJadwal,
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'shift1_start' => $s1Start,
            'shift1_end' => $s1End,
            'shift2_start' => $s2Start,
            'shift2_end' => $s2End,
            'daily_config' => $dailyConfig,
            'is_active' => true,
            'keterangan' => $validated['keterangan'] ?? null,
            'created_by' => $user->id,
        ]);

        // Catat activity log
        if (function_exists('activity')) {
            activity('Penetapan Shift')
                ->performedOn($schedule)
                ->causedBy($user)
                ->withProperties([
                    'nama_jadwal' => $schedule->nama_jadwal,
                    'mode_jadwal' => $modeJadwal,
                    'tanggal_mulai' => $tglMulai,
                    'tanggal_selesai' => $tglSelesai,
                    'keterangan' => $validated['keterangan'] ?? null,
                ])
                ->log("Penetapan jadwal shift kustom ({$modeJadwal}) '{$schedule->nama_jadwal}' ({$tglMulai} s/d {$tglSelesai}) oleh {$user->nama} ({$user->role}).");
        }

        // Bersihkan cache jadwal
        AbsenService::clearScheduleCache($tglMulai, $tglSelesai);

        $msgMode = $modeJadwal === 'per_hari' ? 'Jadwal kustom per kelompok hari' : 'Jadwal shift seragam';
        return redirect()->route('absen.index', ['active_date' => $tglMulai])
            ->with('success', "{$msgMode} '{$schedule->nama_jadwal}' berhasil ditetapkan untuk rentang tanggal " . Carbon::parse($tglMulai)->format('d/m/Y') . " s/d " . Carbon::parse($tglSelesai)->format('d/m/Y') . " (Senin s/d Sabtu, Minggu otomatis libur).");
    }

    /**
     * Batalkan penetapan jadwal shift kustom (revert ke jadwal default pabrik).
     */
    public function cancelShiftSchedule(Request $request, $id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['super_admin', 'fm'], true)) {
            abort(403, 'Hanya Factory Manager (FM) dan Super Admin yang berhak membatalkan jadwal shift.');
        }

        $schedule = ShiftSchedule::findOrFail($id);
        $schedule->is_active = false;
        $schedule->cancelled_by = $user->id;
        $schedule->cancelled_at = now();
        $schedule->save();

        if (function_exists('activity')) {
            activity('Penetapan Shift')
                ->performedOn($schedule)
                ->causedBy($user)
                ->withProperties([
                    'schedule_id' => $schedule->id,
                    'nama_jadwal' => $schedule->nama_jadwal,
                    'tanggal_mulai' => $schedule->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $schedule->tanggal_selesai->toDateString(),
                ])
                ->log("Jadwal shift kustom '{$schedule->nama_jadwal}' dibatalkan oleh {$user->nama} ({$user->role}). Jam operasional otomatis kembali ke default pabrik.");
        }

        // Bersihkan cache jadwal
        AbsenService::clearScheduleCache(
            $schedule->tanggal_mulai->toDateString(),
            $schedule->tanggal_selesai->toDateString()
        );

        return back()->with('success', "Jadwal kustom '{$schedule->nama_jadwal}' berhasil dibatalkan. Jam kerja pada rentang tanggal tersebut otomatis kembali menggunakan jadwal default pabrik.");
    }
}
