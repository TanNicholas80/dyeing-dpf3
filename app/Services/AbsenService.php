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
     * Mendapatkan rincian shift kerja produksi aktif saat ini (atau pada waktu tertentu).
     *
     * Aturan Jadwal:
     * 1. Senin:
     *    - 00:00 - 13:59: Libur Produksi (lanjutan weekend)
     *    - 14:00 - 21:59: Shift 1 (14:00 - 22:00)
     *    - 22:00 - 23:59: Shift 2 (22:00 - 06:00, tgl produksi Senin)
     * 2. Selasa s/d Jumat:
     *    - 00:00 - 05:59: Shift 2 hari sebelumnya (tgl produksi H-1)
     *    - 06:00 - 17:59: Shift 1 (06:00 - 18:00, tgl produksi hari ini)
     *    - 18:00 - 23:59: Shift 2 (18:00 - 06:00, tgl produksi hari ini)
     * 3. Sabtu:
     *    - 00:00 - 05:59: Shift 2 hari Jumat (18:00 - 06:00, tgl produksi Jumat)
     *    - 06:00 - 23:59: Libur Produksi (OFF)
     * 4. Minggu:
     *    - Seharian: Libur Produksi (OFF)
     */
    public static function getCurrentShiftDetails(?Carbon $time = null): array
    {
        $now = $time ? $time->copy()->setTimezone('Asia/Jakarta') : Carbon::now('Asia/Jakarta');
        $dayOfWeek = (int) $now->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
        $timeStr = $now->format('H:i:s');
        $todayDate = $now->toDateString();

        // 1. MINGGU (Sunday)
        if ($dayOfWeek === Carbon::SUNDAY) {
            return [
                'shift' => 'Libur Produksi',
                'production_date' => $todayDate,
                'is_production_off' => true,
                'time_range' => 'Libur Akhir Pekan',
                'label' => 'Libur Produksi (Sabtu 06:00 - Senin 14:00)',
            ];
        }

        // 2. SABTU (Saturday)
        if ($dayOfWeek === Carbon::SATURDAY) {
            if ($timeStr < '06:00:00') {
                $fridayDate = $now->copy()->subDay()->toDateString();
                return [
                    'shift' => 'Shift 2',
                    'production_date' => $fridayDate,
                    'is_production_off' => false,
                    'time_range' => '18:00 - 06:00',
                    'label' => 'Jumat - Shift 2 (18:00 - 06:00)',
                ];
            } else {
                return [
                    'shift' => 'Libur Produksi',
                    'production_date' => $todayDate,
                    'is_production_off' => true,
                    'time_range' => 'Libur Akhir Pekan',
                    'label' => 'Libur Produksi (Sabtu 06:00 - Senin 14:00)',
                ];
            }
        }

        // 3. SENIN (Monday)
        if ($dayOfWeek === Carbon::MONDAY) {
            if ($timeStr < '14:00:00') {
                return [
                    'shift' => 'Libur Produksi',
                    'production_date' => $todayDate,
                    'is_production_off' => true,
                    'time_range' => 'Libur Akhir Pekan (s/d 14:00)',
                    'label' => 'Libur Produksi (Mulai Produksi 14:00)',
                ];
            } elseif ($timeStr < '22:00:00') {
                return [
                    'shift' => 'Shift 1',
                    'production_date' => $todayDate,
                    'is_production_off' => false,
                    'time_range' => '14:00 - 22:00',
                    'label' => 'Senin - Shift 1 (14:00 - 22:00)',
                ];
            } else {
                return [
                    'shift' => 'Shift 2',
                    'production_date' => $todayDate,
                    'is_production_off' => false,
                    'time_range' => '22:00 - 06:00',
                    'label' => 'Senin - Shift 2 (22:00 - 06:00)',
                ];
            }
        }

        // 4. SELASA (Tuesday)
        if ($dayOfWeek === Carbon::TUESDAY) {
            if ($timeStr < '06:00:00') {
                $mondayDate = $now->copy()->subDay()->toDateString();
                return [
                    'shift' => 'Shift 2',
                    'production_date' => $mondayDate,
                    'is_production_off' => false,
                    'time_range' => '22:00 - 06:00',
                    'label' => 'Senin - Shift 2 (22:00 - 06:00)',
                ];
            } elseif ($timeStr < '18:00:00') {
                return [
                    'shift' => 'Shift 1',
                    'production_date' => $todayDate,
                    'is_production_off' => false,
                    'time_range' => '06:00 - 18:00',
                    'label' => 'Selasa - Shift 1 (06:00 - 18:00)',
                ];
            } else {
                return [
                    'shift' => 'Shift 2',
                    'production_date' => $todayDate,
                    'is_production_off' => false,
                    'time_range' => '18:00 - 06:00',
                    'label' => 'Selasa - Shift 2 (18:00 - 06:00)',
                ];
            }
        }

        // 5. RABU, KAMIS, JUMAT (Wednesday, Thursday, Friday)
        $dayNames = [
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
        ];
        $prevDayNames = [
            Carbon::WEDNESDAY => 'Selasa',
            Carbon::THURSDAY => 'Rabu',
            Carbon::FRIDAY => 'Kamis',
        ];

        if ($timeStr < '06:00:00') {
            $prevDate = $now->copy()->subDay()->toDateString();
            $pName = $prevDayNames[$dayOfWeek] ?? 'Kemarin';
            return [
                'shift' => 'Shift 2',
                'production_date' => $prevDate,
                'is_production_off' => false,
                'time_range' => '18:00 - 06:00',
                'label' => "{$pName} - Shift 2 (18:00 - 06:00)",
            ];
        } elseif ($timeStr < '18:00:00') {
            $curName = $dayNames[$dayOfWeek];
            return [
                'shift' => 'Shift 1',
                'production_date' => $todayDate,
                'is_production_off' => false,
                'time_range' => '06:00 - 18:00',
                'label' => "{$curName} - Shift 1 (06:00 - 18:00)",
            ];
        } else {
            $curName = $dayNames[$dayOfWeek];
            return [
                'shift' => 'Shift 2',
                'production_date' => $todayDate,
                'is_production_off' => false,
                'time_range' => '18:00 - 06:00',
                'label' => "{$curName} - Shift 2 (18:00 - 06:00)",
            ];
        }
    }

    /**
     * Deteksi Shift kerja saat ini (string: 'Shift 1', 'Shift 2', atau 'Libur Produksi').
     */
    public static function detectCurrentShift(): string
    {
        $details = self::getCurrentShiftDetails();
        return $details['shift'];
    }

    /**
     * Konfigurasi jadwal shift untuk tanggal tertentu.
     */
    public static function getScheduleConfigForDate(string $dateString): array
    {
        $date = Carbon::parse($dateString, 'Asia/Jakarta');
        $dayOfWeek = (int) $date->dayOfWeek;

        if ($dayOfWeek === Carbon::SUNDAY) {
            return [
                'day_name' => 'Minggu',
                'is_holiday' => true,
                'description' => 'Libur Produksi (OFF)',
                'shifts' => [],
            ];
        }

        if ($dayOfWeek === Carbon::SATURDAY) {
            return [
                'day_name' => 'Sabtu',
                'is_holiday' => true,
                'description' => 'Libur Produksi mulai jam 06:00 (OFF)',
                'shifts' => [],
            ];
        }

        if ($dayOfWeek === Carbon::MONDAY) {
            return [
                'day_name' => 'Senin',
                'is_holiday' => false,
                'description' => 'Produksi mulai jam 14:00 (Pagi Libur)',
                'shifts' => [
                    'Shift 1' => [
                        'name' => 'Shift 1',
                        'start' => '14:00',
                        'end' => '22:00',
                        'range' => '14:00 - 22:00',
                        'duration' => '8 Jam',
                    ],
                    'Shift 2' => [
                        'name' => 'Shift 2',
                        'start' => '22:00',
                        'end' => '06:00',
                        'range' => '22:00 - 06:00',
                        'duration' => '8 Jam',
                    ],
                ],
            ];
        }

        $names = [
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
        ];

        return [
            'day_name' => $names[$dayOfWeek] ?? 'Hari Kerja',
            'is_holiday' => false,
            'description' => 'Shift 1: 06:00 - 18:00 | Shift 2: 18:00 - 06:00',
            'shifts' => [
                'Shift 1' => [
                    'name' => 'Shift 1',
                    'start' => '06:00',
                    'end' => '18:00',
                    'range' => '06:00 - 18:00',
                    'duration' => '12 Jam',
                ],
                'Shift 2' => [
                    'name' => 'Shift 2',
                    'start' => '18:00',
                    'end' => '06:00',
                    'range' => '18:00 - 06:00',
                    'duration' => '12 Jam',
                ],
            ],
        ];
    }

    /**
     * Melakukan Auto-Reset otomatis secara lazy (on-demand):
     * Merapikan record shift lampau / hari kemarin yang masih berstatus OFF menjadi ON,
     * mencatat audit log ke riwayat, dan membersihkan cache wewenang.
     * 
     * Mekanisme ini berjalan langsung di dalam request web tanpa memerlukan scheduler / cron / service Docker tambahan.
     */
    public static function checkAndPerformAutoReset(): int
    {
        $details = self::getCurrentShiftDetails();
        $curDate = $details['production_date'];
        $curShift = $details['shift'];
        $isOff = $details['is_production_off'];

        // Cari record yang masih berstatus OFF (is_active = false)
        $inactiveRecords = AbsenDelegasi::where('is_active', false)->get();
        if ($inactiveRecords->isEmpty()) {
            return 0;
        }

        $resetCount = 0;
        foreach ($inactiveRecords as $record) {
            $isExpired = false;

            $recDate = $record->tanggal ? $record->tanggal->toDateString() : null;

            // Jika tanggal record lebih lama dari tanggal produksi saat ini (hari kemarin/lampau)
            if ($recDate && $recDate < $curDate) {
                $isExpired = true;
            } elseif ($recDate && $recDate == $curDate) {
                // Jika tanggal sama, tapi shift-nya sudah selesai
                if ($curShift === 'Shift 2' && $record->shift === 'Shift 1') {
                    $isExpired = true;
                } elseif ($isOff) {
                    $isExpired = true;
                }
            }

            if ($isExpired) {
                $record->is_active = true;
                $record->keterangan = 'Auto Reset: Shift telah selesai, wewenang kembali normal/hadir';
                $record->save();

                // Catat ke riwayat
                AbsenHistory::create([
                    'tanggal' => $recDate ?: $curDate,
                    'shift' => $record->shift,
                    'role_target' => $record->role_target,
                    'status' => 'ON',
                    'keterangan' => 'Sistem Auto-Reset: Shift telah selesai, wewenang otomatis kembali Hadir/Normal.',
                    'user_id' => null, // Sistem
                ]);

                self::clearCache($recDate, $record->shift);
                $resetCount++;
            }
        }

        if ($resetCount > 0) {
            self::clearCache();
        }

        return $resetCount;
    }

    /**
     * Mengambil data delegasi (Shift 1 & Shift 2) untuk tanggal tertentu.
     * Bila record belum ada di database, dibuatkan instance default ON (Hadir).
     */
    public static function getDayShiftDelegations(string $date): array
    {
        // Jalankan lazy auto-reset terlebih dahulu
        self::checkAndPerformAutoReset();

        $shifts = ['Shift 1', 'Shift 2'];
        $roles = ['kepala_shift', 'kepala_ruangan'];
        $result = [];

        foreach ($roles as $role) {
            $result[$role] = [];
            foreach ($shifts as $shift) {
                $record = AbsenDelegasi::with('updater')
                    ->where('tanggal', $date)
                    ->where('shift', $shift)
                    ->where('role_target', $role)
                    ->first();

                if (!$record) {
                    $record = new AbsenDelegasi([
                        'tanggal' => $date,
                        'shift' => $shift,
                        'role_target' => $role,
                        'is_active' => true,
                        'keterangan' => 'Default sistem: Aktif / Hadir',
                        'updated_by' => null,
                    ]);
                }

                $result[$role][$shift] = $record;
            }
        }

        return $result;
    }

    /**
     * Cek apakah status Kepala Shift sedang OFF (absen / wewenang dialihkan ke Karu).
     */
    public static function isKashiftOff(?Carbon $time = null): bool
    {
        $details = self::getCurrentShiftDetails($time);
        if ($details['is_production_off']) {
            return false;
        }

        $prodDate = $details['production_date'];
        $shift = $details['shift'];
        $cacheKey = "absen_status_kashift_{$prodDate}_{$shift}";

        $isActive = Cache::remember($cacheKey, 60, function () use ($prodDate, $shift) {
            $row = AbsenDelegasi::where('tanggal', $prodDate)
                ->where('shift', $shift)
                ->where('role_target', 'kepala_shift')
                ->first();

            return $row ? (bool) $row->is_active : true;
        });

        return !$isActive;
    }

    /**
     * Cek apakah status Kepala Ruangan sedang OFF (absen / wewenang dialihkan ke Kashift).
     */
    public static function isKaruOff(?Carbon $time = null): bool
    {
        $details = self::getCurrentShiftDetails($time);
        if ($details['is_production_off']) {
            return false;
        }

        $prodDate = $details['production_date'];
        $shift = $details['shift'];
        $cacheKey = "absen_status_karu_{$prodDate}_{$shift}";

        $isActive = Cache::remember($cacheKey, 60, function () use ($prodDate, $shift) {
            $row = AbsenDelegasi::where('tanggal', $prodDate)
                ->where('shift', $shift)
                ->where('role_target', 'kepala_ruangan')
                ->first();

            return $row ? (bool) $row->is_active : true;
        });

        return !$isActive;
    }

    /**
     * Hapus cache status absen agar perubahan seketika aktif.
     */
    public static function clearCache(?string $date = null, ?string $shift = null): void
    {
        if ($date && $shift) {
            Cache::forget("absen_status_kashift_{$date}_{$shift}");
            Cache::forget("absen_status_karu_{$date}_{$shift}");
        }

        // Hapus juga cache untuk shift yang saat ini berjalan
        $details = self::getCurrentShiftDetails();
        $d = $details['production_date'];
        $s = $details['shift'];
        Cache::forget("absen_status_kashift_{$d}_{$s}");
        Cache::forget("absen_status_karu_{$d}_{$s}");

        // Legacy keys fallback
        Cache::forget(self::CACHE_KEY_KASHIFT);
        Cache::forget(self::CACHE_KEY_KARU);
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
     * Toggle status absen (ON/OFF) untuk shift dan tanggal spesifik.
     */
    public static function toggleStatus(
        string $roleTarget,
        string $status,
        ?string $shift = null,
        ?string $keterangan = null,
        int $userId = 1,
        ?string $tanggal = null
    ): AbsenDelegasi {
        $normalizedStatus = strtoupper(trim($status)) === 'ON' ? 'ON' : 'OFF';
        $isActive = ($normalizedStatus === 'ON');

        $details = self::getCurrentShiftDetails();
        $dateToUse = $tanggal ?: $details['production_date'];
        $shiftToUse = $shift ?: ($details['is_production_off'] ? 'Shift 1' : $details['shift']);

        // Validasi: Tidak boleh kedua role (Kashift & Karu) sama-sama OFF pada shift dan tanggal yang sama
        if (!$isActive) {
            $counterpartRole = $roleTarget === 'kepala_shift' ? 'kepala_ruangan' : 'kepala_shift';
            $counterpartRecord = AbsenDelegasi::where('tanggal', $dateToUse)
                ->where('shift', $shiftToUse)
                ->where('role_target', $counterpartRole)
                ->first();

            if ($counterpartRecord && !$counterpartRecord->is_active) {
                $counterpartTitle = $counterpartRole === 'kepala_shift' ? 'Kepala Shift (Kashift)' : 'Kepala Ruangan (Karu)';
                $currentTitle = $roleTarget === 'kepala_shift' ? 'Kepala Shift' : 'Kepala Ruangan';
                throw new \InvalidArgumentException("Tidak dapat mengubah {$currentTitle} menjadi OFF. Pada {$shiftToUse}, {$counterpartTitle} sudah berstatus OFF (Izin/Sakit). Salah satu harus tetap hadir (ON) untuk pendelegasian wewenang.");
            }
        }

        $delegasi = AbsenDelegasi::updateOrCreate(
            [
                'tanggal' => $dateToUse,
                'shift' => $shiftToUse,
                'role_target' => $roleTarget,
            ],
            [
                'is_active' => $isActive,
                'keterangan' => $keterangan,
                'updated_by' => $userId,
            ]
        );

        // Catat ke riwayat
        AbsenHistory::create([
            'tanggal' => $dateToUse,
            'shift' => $shiftToUse,
            'role_target' => $roleTarget,
            'status' => $normalizedStatus,
            'keterangan' => $keterangan,
            'user_id' => $userId,
        ]);

        self::clearCache($dateToUse, $shiftToUse);

        return $delegasi;
    }
}
