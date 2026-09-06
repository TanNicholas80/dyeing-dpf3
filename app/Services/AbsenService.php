<?php

namespace App\Services;

use App\Models\AbsenDelegasi;
use App\Models\AbsenHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AbsenService
{
    const CACHE_KEY_KASHIFT = 'absen_status_kashift_is_active';
    const CACHE_KEY_KARU = 'absen_status_karu_is_active';

    /**
     * Cek apakah status Kepala Shift sedang OFF (absen / wewenang dialihkan ke Karu).
     */
    public static function isKashiftOff(): bool
    {
        $isActive = Cache::remember(self::CACHE_KEY_KASHIFT, 60, function () {
            $row = AbsenDelegasi::where('role_target', 'kepala_shift')->first();
            return $row ? (bool) $row->is_active : true;
        });

        return !$isActive;
    }

    /**
     * Cek apakah status Kepala Ruangan sedang OFF (absen / wewenang dialihkan ke Kashift).
     */
    public static function isKaruOff(): bool
    {
        $isActive = Cache::remember(self::CACHE_KEY_KARU, 60, function () {
            $row = AbsenDelegasi::where('role_target', 'kepala_ruangan')->first();
            return $row ? (bool) $row->is_active : true;
        });

        return !$isActive;
    }

    /**
     * Hapus cache status absen agar perubahan seketika aktif.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_KASHIFT);
        Cache::forget(self::CACHE_KEY_KARU);
    }

    /**
     * Deteksi Shift kerja saat ini berdasarkan jam lokal Asia/Jakarta:
     * Shift 1: 07:00 - 14:59
     * Shift 2: 15:00 - 22:59
     * Shift 3: 23:00 - 06:59
     */
    public static function detectCurrentShift(): string
    {
        $now = Carbon::now('Asia/Jakarta');
        $hour = (int) $now->format('H');

        if ($hour >= 7 && $hour < 15) {
            return 'Shift 1';
        } elseif ($hour >= 15 && $hour < 23) {
            return 'Shift 2';
        } else {
            return 'Shift 3';
        }
    }

    /**
     * Validasi apakah user berhak melakukan Approval Kepala Shift:
     * - super_admin: selalu bisa
     * - kepala_shift: selalu bisa
     * - kepala_ruangan: HANYA BISA jika Kashift berstatus OFF (delegasi)
     */
    public static function canApproveKepalaShift(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'super_admin' || $user->role === 'kepala_shift') {
            return true;
        }

        if ($user->role === 'kepala_ruangan' && self::isKashiftOff()) {
            return true;
        }

        return false;
    }

    /**
     * Validasi apakah user berhak mengajukan Request Topping LA / AUX:
     * - super_admin: selalu bisa
     * - kepala_ruangan: selalu bisa
     * - kepala_shift: HANYA BISA jika Karu berstatus OFF (delegasi)
     */
    public static function canRequestTopping(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'super_admin' || $user->role === 'kepala_ruangan') {
            return true;
        }

        if ($user->role === 'kepala_shift' && self::isKaruOff()) {
            return true;
        }

        return false;
    }

    /**
     * Validasi apakah user berhak melakukan Force Finish (Selesai Paksa Proses):
     * - super_admin: selalu bisa
     * - kepala_shift: selalu bisa
     * - kepala_ruangan: HANYA BISA jika Kashift berstatus OFF (delegasi)
     */
    public static function canForceFinish(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'super_admin' || $user->role === 'kepala_shift') {
            return true;
        }

        if ($user->role === 'kepala_ruangan' && self::isKashiftOff()) {
            return true;
        }

        return false;
    }

    /**
     * Validasi apakah user berhak membatalkan barcode (Cancel Barcode):
     * - super_admin, ppic, kepala_shift: selalu bisa
     * - kepala_ruangan: BISA jika Kashift berstatus OFF (delegasi)
     */
    public static function canCancelBarcode(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (in_array($user->role, ['super_admin', 'ppic', 'kepala_shift'], true)) {
            return true;
        }

        if ($user->role === 'kepala_ruangan' && self::isKashiftOff()) {
            return true;
        }

        return false;
    }

    /**
     * Toggle status absen (ON/OFF), perbarui record absen_delegasis dan simpan ke absen_histories.
     */
    public static function toggleStatus(
        string $roleTarget,
        string $status,
        ?string $shift,
        ?string $keterangan,
        int $userId
    ): AbsenDelegasi {
        $normalizedStatus = strtoupper(trim($status)) === 'ON' ? 'ON' : 'OFF';
        $isActive = ($normalizedStatus === 'ON');
        $shiftToUse = $shift ?: self::detectCurrentShift();

        $delegasi = AbsenDelegasi::updateOrCreate(
            ['role_target' => $roleTarget],
            [
                'is_active' => $isActive,
                'current_shift' => $shiftToUse,
                'keterangan' => $keterangan,
                'updated_by' => $userId,
            ]
        );

        // Catat ke riwayat
        AbsenHistory::create([
            'tanggal' => Carbon::today('Asia/Jakarta')->toDateString(),
            'shift' => $shiftToUse,
            'role_target' => $roleTarget,
            'status' => $normalizedStatus,
            'keterangan' => $keterangan,
            'user_id' => $userId,
        ]);

        self::clearCache();

        return $delegasi;
    }
}
