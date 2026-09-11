<?php

namespace App\Services;

use App\Models\AbsenDelegasi;
use App\Models\AbsenHistory;
use App\Models\ShiftSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AbsenService
{
    const CACHE_KEY_KASHIFT = 'absen_status_kashift_is_active';
    const CACHE_KEY_KARU = 'absen_status_karu_is_active';

    /**
     * Cari apakah ada jadwal kustom (ShiftSchedule) aktif yang ditetapkan FM untuk tanggal tertentu.
     * Catatan: Hari Minggu selalu mengembalikan null (Minggu selalu Libur Produksi / OFF).
     */
    public static function getActiveCustomScheduleForDate(string $dateString): ?ShiftSchedule
    {
        $date = Carbon::parse($dateString, 'Asia/Jakarta');
        if ((int) $date->dayOfWeek === Carbon::SUNDAY) {
            return null;
        }

        $cacheKey = "shift_custom_schedule_{$dateString}";
        return Cache::remember($cacheKey, 60, function () use ($dateString) {
            return ShiftSchedule::active()
                ->where('tanggal_mulai', '<=', $dateString)
                ->where('tanggal_selesai', '>=', $dateString)
                ->latest('id')
                ->first();
        });
    }

    /**
     * Hitung durasi jam kerja antara jam mulai dan jam selesai.
     */
    public static function calculateShiftDuration(string $start, string $end): string
    {
        $s = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $e = Carbon::createFromFormat('H:i', substr($end, 0, 5));
        if ($e->lessThanOrEqualTo($s)) {
            $e->addDay();
        }
        $diff = $s->diffInMinutes($e);
        $hours = round($diff / 60, 1);
        return ($hours == (int)$hours ? (int)$hours : $hours) . ' Jam';
    }

    /**
     * Mendapatkan rincian shift kerja produksi aktif saat ini (atau pada waktu tertentu).
     *
     * Aturan Jadwal Default Pabrik:
     * 1. Senin:
     *    - 00:00 - 13:59: Libur Produksi (lanjutan weekend)
     *    - 14:00 - 21:59: Shift 1 (14:00 - 22:00)
     *    - 22:00 - 23:59: Shift 2 (22:00 - 06:00, tgl produksi Senin)
     * 2. Selasa s/d Kamis:
     *    - 00:00 - 05:59: Shift 2 hari sebelumnya (tgl produksi H-1)
     *    - 06:00 - 17:59: Shift 1 (06:00 - 18:00, tgl produksi hari ini)
     *    - 18:00 - 23:59: Shift 2 (18:00 - 06:00, tgl produksi hari ini)
     * 3. Jumat (Aturan Resmi Pabrik):
     *    - 00:00 - 05:59: Shift 2 hari Kamis (18:00 - 06:00, tgl produksi Kamis)
     *    - 06:00 - 18:59: Shift 1 (06:00 - 19:00, tgl produksi Jumat)
     *    - 19:00 - 23:59: Shift 2 (19:00 - 08:00, tgl produksi Jumat)
     * 4. Sabtu:
     *    - 00:00 - 07:59: Shift 2 hari Jumat (19:00 - 08:00, tgl produksi Jumat)
     *    - 08:00 - 23:59: Libur Produksi (OFF)
     * 5. Minggu:
     *    - Seharian: Libur Produksi (OFF)
     *
     * Jika FM menetapkan jadwal custom pada range tanggal (Senin - Sabtu), jam Shift 1 & Shift 2
     * kustom tersebut yang akan digunakan pada rentang tanggal tersebut.
     */
    public static function getCurrentShiftDetails(?Carbon $time = null): array
    {
        $now = $time ? $time->copy()->setTimezone('Asia/Jakarta') : Carbon::now('Asia/Jakarta');
        $dayOfWeek = (int) $now->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
        $timeStr = $now->format('H:i:s');
        $todayDate = $now->toDateString();
        $yesterday = $now->copy()->subDay();
        $yesterdayDate = $yesterday->toDateString();
        $yesterdayDayOfWeek = (int) $yesterday->dayOfWeek;

        $dayNames = [
            Carbon::SUNDAY => 'Minggu',
            Carbon::MONDAY => 'Senin',
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
            Carbon::SATURDAY => 'Sabtu',
        ];

        // ------------------------------------------------------------------------------------
        // A. CEK APAKAH WAKTU SAAT INI ADALAH LANJUTAN SHIFT 2 HARI KEMARIN (CROSS-MIDNIGHT)
        // ------------------------------------------------------------------------------------
        $prevCustom = self::getActiveCustomScheduleForDate($yesterdayDate);
        if ($prevCustom) {
            $prevCfg = $prevCustom->getConfigForDate($yesterdayDate);
            if (!$prevCfg['is_holiday'] && !empty($prevCfg['shift2_start']) && !empty($prevCfg['shift2_end'])) {
                $prevS2Start = $prevCfg['shift2_start'];
                $prevS2End = $prevCfg['shift2_end'];
                // Cross midnight jika end <= start
                if ($prevS2End < $prevS2Start) {
                    if ($timeStr < $prevS2End) {
                        $range = "{$prevS2Start} - {$prevS2End}";
                        $pName = $dayNames[$yesterdayDayOfWeek];
                        return [
                            'shift' => 'Shift 2',
                            'production_date' => $yesterdayDate,
                            'is_production_off' => false,
                            'time_range' => $range,
                            'label' => "{$pName} - Shift 2 ({$range})",
                        ];
                    }
                }
            }
        } else {
            // Default cross-midnight rules:
            if ($dayOfWeek === Carbon::SATURDAY) {
                // Hari Sabtu pagi: lanjutan Shift 2 hari Jumat (19:00 - 08:00)
                if ($timeStr < '08:00:00') {
                    return [
                        'shift' => 'Shift 2',
                        'production_date' => $yesterdayDate,
                        'is_production_off' => false,
                        'time_range' => '19:00 - 08:00',
                        'label' => 'Jumat - Shift 2 (19:00 - 08:00)',
                    ];
                }
            } elseif ($dayOfWeek === Carbon::TUESDAY) {
                // Hari Selasa pagi: lanjutan Shift 2 hari Senin (22:00 - 06:00)
                if ($timeStr < '06:00:00') {
                    return [
                        'shift' => 'Shift 2',
                        'production_date' => $yesterdayDate,
                        'is_production_off' => false,
                        'time_range' => '22:00 - 06:00',
                        'label' => 'Senin - Shift 2 (22:00 - 06:00)',
                    ];
                }
            } elseif (in_array($dayOfWeek, [Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY], true)) {
                // Hari Rabu, Kamis, Jumat pagi: lanjutan Shift 2 hari sebelumnya (18:00 - 06:00)
                if ($timeStr < '06:00:00') {
                    $pName = $dayNames[$yesterdayDayOfWeek];
                    return [
                        'shift' => 'Shift 2',
                        'production_date' => $yesterdayDate,
                        'is_production_off' => false,
                        'time_range' => '18:00 - 06:00',
                        'label' => "{$pName} - Shift 2 (18:00 - 06:00)",
                    ];
                }
            }
        }

        // ------------------------------------------------------------------------------------
        // B. HARI MINGGU (SUNDAY) SELALU LIBUR PRODUKSI (OFF)
        // ------------------------------------------------------------------------------------
        if ($dayOfWeek === Carbon::SUNDAY) {
            return [
                'shift' => 'Libur Produksi',
                'production_date' => $todayDate,
                'is_production_off' => true,
                'time_range' => 'Libur Akhir Pekan',
                'label' => 'Libur Produksi (Sabtu 08:00 - Senin 14:00)',
            ];
        }

        // ------------------------------------------------------------------------------------
        // C. CEK JADWAL KUSTOM HARI INI (JIKA DITETAPKAN FM UNTUK TANGGAL INI)
        // ------------------------------------------------------------------------------------
        $todayCustom = self::getActiveCustomScheduleForDate($todayDate);
        if ($todayCustom) {
            $curName = $dayNames[$dayOfWeek];
            $todayCfg = $todayCustom->getConfigForDate($todayDate);

            // Jika hari ini pada jadwal kustom diset Libur Produksi (OFF), misal Sabtu libur
            if ($todayCfg['is_holiday']) {
                return [
                    'shift' => 'Libur Produksi',
                    'production_date' => $todayDate,
                    'is_production_off' => true,
                    'time_range' => 'Libur Produksi',
                    'label' => ($todayCfg['description'] ?? 'Libur Produksi (OFF)') . " ({$curName})",
                ];
            }

            $s1Start = $todayCfg['shift1_start'];
            $s1End = $todayCfg['shift1_end'];
            $s2Start = $todayCfg['shift2_start'];
            $s2End = $todayCfg['shift2_end'];
            $s1Range = "{$s1Start} - {$s1End}";
            $s2Range = "{$s2Start} - {$s2End}";

            // Sebelum Shift 1 mulai
            if ($timeStr < $s1Start) {
                return [
                    'shift' => 'Libur Produksi',
                    'production_date' => $todayDate,
                    'is_production_off' => true,
                    'time_range' => "Sebelum Shift 1 (Mulai {$s1Start})",
                    'label' => "Libur / Belum Mulai Produksi ({$curName})",
                ];
            }

            // Sedang berjalan Shift 1
            if ($timeStr < $s1End) {
                return [
                    'shift' => 'Shift 1',
                    'production_date' => $todayDate,
                    'is_production_off' => false,
                    'time_range' => $s1Range,
                    'label' => "{$curName} - Shift 1 ({$s1Range})",
                ];
            }

            // Cek Shift 2
            $isOvernight = ($s2End < $s2Start);
            if ($isOvernight) {
                if ($timeStr >= $s2Start) {
                    return [
                        'shift' => 'Shift 2',
                        'production_date' => $todayDate,
                        'is_production_off' => false,
                        'time_range' => $s2Range,
                        'label' => "{$curName} - Shift 2 ({$s2Range})",
                    ];
                }
            } else {
                if ($timeStr >= $s2Start && $timeStr < $s2End) {
                    return [
                        'shift' => 'Shift 2',
                        'production_date' => $todayDate,
                        'is_production_off' => false,
                        'time_range' => $s2Range,
                        'label' => "{$curName} - Shift 2 ({$s2Range})",
                    ];
                }
            }

            // Jika berada di sela-sela antar shift atau sudah selesai shift 2
            return [
                'shift' => 'Libur Produksi',
                'production_date' => $todayDate,
                'is_production_off' => true,
                'time_range' => 'Luar Jam Shift',
                'label' => "Luar Jam Operasional Shift ({$curName})",
            ];
        }

        // ------------------------------------------------------------------------------------
        // D. JADWAL DEFAULT PABRIK (TIDAK ADA JADWAL KUSTOM)
        // ------------------------------------------------------------------------------------

        // 1. SABTU (Saturday) - Setelah jam 08:00 adalah Libur Produksi
        if ($dayOfWeek === Carbon::SATURDAY) {
            return [
                'shift' => 'Libur Produksi',
                'production_date' => $todayDate,
                'is_production_off' => true,
                'time_range' => 'Libur Akhir Pekan',
                'label' => 'Libur Produksi (Sabtu 08:00 - Senin 14:00)',
            ];
        }

        // 2. SENIN (Monday)
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

        // 3. JUMAT (Friday) - Shift 1: 06:00 - 19:00, Shift 2: 19:00 - 08:00
        if ($dayOfWeek === Carbon::FRIDAY) {
            if ($timeStr < '19:00:00') {
                return [
                    'shift' => 'Shift 1',
                    'production_date' => $todayDate,
                    'is_production_off' => false,
                    'time_range' => '06:00 - 19:00',
                    'label' => 'Jumat - Shift 1 (06:00 - 19:00)',
                ];
            } else {
                return [
                    'shift' => 'Shift 2',
                    'production_date' => $todayDate,
                    'is_production_off' => false,
                    'time_range' => '19:00 - 08:00',
                    'label' => 'Jumat - Shift 2 (19:00 - 08:00)',
                ];
            }
        }

        // 4. SELASA, RABU, KAMIS (Tuesday, Wednesday, Thursday) - Shift 1: 06:00 - 18:00, Shift 2: 18:00 - 06:00
        $curName = $dayNames[$dayOfWeek];
        if ($timeStr < '18:00:00') {
            return [
                'shift' => 'Shift 1',
                'production_date' => $todayDate,
                'is_production_off' => false,
                'time_range' => '06:00 - 18:00',
                'label' => "{$curName} - Shift 1 (06:00 - 18:00)",
            ];
        } else {
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

        $dayNames = [
            Carbon::SUNDAY => 'Minggu',
            Carbon::MONDAY => 'Senin',
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
            Carbon::SATURDAY => 'Sabtu',
        ];

        // 1. MINGGU: Selalu Libur Produksi (OFF)
        if ($dayOfWeek === Carbon::SUNDAY) {
            return [
                'day_name' => 'Minggu',
                'is_holiday' => true,
                'is_custom' => false,
                'custom_schedule_id' => null,
                'description' => 'Libur Produksi (OFF)',
                'shifts' => [],
            ];
        }

        // 2. CEK JADWAL KUSTOM FM
        $custom = self::getActiveCustomScheduleForDate($dateString);
        if ($custom) {
            $cfg = $custom->getConfigForDate($dateString);

            if ($cfg['is_holiday']) {
                return [
                    'day_name' => $dayNames[$dayOfWeek],
                    'is_holiday' => true,
                    'is_custom' => true,
                    'custom_schedule_id' => $custom->id,
                    'custom_schedule_name' => $custom->nama_jadwal ?: 'Jadwal Kustom FM',
                    'description' => ($cfg['description'] ?? 'Libur Produksi (OFF)') . ' (Kustom FM)',
                    'shifts' => [],
                ];
            }

            $s1Start = $cfg['shift1_start'];
            $s1End = $cfg['shift1_end'];
            $s2Start = $cfg['shift2_start'];
            $s2End = $cfg['shift2_end'];
            $s1Dur = self::calculateShiftDuration($s1Start, $s1End);
            $s2Dur = self::calculateShiftDuration($s2Start, $s2End);

            return [
                'day_name' => $dayNames[$dayOfWeek],
                'is_holiday' => false,
                'is_custom' => true,
                'custom_schedule_id' => $custom->id,
                'custom_schedule_name' => $custom->nama_jadwal ?: 'Jadwal Kustom FM',
                'description' => "Shift 1: {$s1Start} - {$s1End} | Shift 2: {$s2Start} - {$s2End} (Kustom FM)",
                'shifts' => [
                    'Shift 1' => [
                        'name' => 'Shift 1',
                        'start' => $s1Start,
                        'end' => $s1End,
                        'range' => "{$s1Start} - {$s1End}",
                        'duration' => $s1Dur,
                    ],
                    'Shift 2' => [
                        'name' => 'Shift 2',
                        'start' => $s2Start,
                        'end' => $s2End,
                        'range' => "{$s2Start} - {$s2End}",
                        'duration' => $s2Dur,
                    ],
                ],
            ];
        }

        // 3. JADWAL DEFAULT PABRIK

        // SABTU: Libur produksi mulai jam 08:00
        if ($dayOfWeek === Carbon::SATURDAY) {
            return [
                'day_name' => 'Sabtu',
                'is_holiday' => true,
                'is_custom' => false,
                'custom_schedule_id' => null,
                'description' => 'Libur Produksi mulai jam 08:00 (OFF)',
                'shifts' => [],
            ];
        }

        // SENIN: Shift 1 (14:00 - 22:00), Shift 2 (22:00 - 06:00)
        if ($dayOfWeek === Carbon::MONDAY) {
            return [
                'day_name' => 'Senin',
                'is_holiday' => false,
                'is_custom' => false,
                'custom_schedule_id' => null,
                'description' => 'Produksi mulai jam 14:00 (Pagi Libur) | Shift 1: 14:00 - 22:00 | Shift 2: 22:00 - 06:00',
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

        // JUMAT: Shift 1 (06:00 - 19:00), Shift 2 (19:00 - 08:00)
        if ($dayOfWeek === Carbon::FRIDAY) {
            return [
                'day_name' => 'Jumat',
                'is_holiday' => false,
                'is_custom' => false,
                'custom_schedule_id' => null,
                'description' => 'Shift 1: 06:00 - 19:00 | Shift 2: 19:00 - 08:00 (s/d Sabtu 08:00)',
                'shifts' => [
                    'Shift 1' => [
                        'name' => 'Shift 1',
                        'start' => '06:00',
                        'end' => '19:00',
                        'range' => '06:00 - 19:00',
                        'duration' => '13 Jam',
                    ],
                    'Shift 2' => [
                        'name' => 'Shift 2',
                        'start' => '19:00',
                        'end' => '08:00',
                        'range' => '19:00 - 08:00',
                        'duration' => '13 Jam',
                    ],
                ],
            ];
        }

        // SELASA, RABU, KAMIS: Shift 1 (06:00 - 18:00), Shift 2 (18:00 - 06:00)
        return [
            'day_name' => $dayNames[$dayOfWeek] ?? 'Hari Kerja',
            'is_holiday' => false,
            'is_custom' => false,
            'custom_schedule_id' => null,
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
     * Hapus cache status absen dan jadwal shift agar perubahan seketika aktif.
     */
    public static function clearCache(?string $date = null, ?string $shift = null): void
    {
        if ($date && $shift) {
            Cache::forget("absen_status_kashift_{$date}_{$shift}");
            Cache::forget("absen_status_karu_{$date}_{$shift}");
        }

        if ($date) {
            Cache::forget("shift_custom_schedule_{$date}");
        }

        // Hapus juga cache untuk shift yang saat ini berjalan
        $details = self::getCurrentShiftDetails();
        $d = $details['production_date'];
        $s = $details['shift'];
        Cache::forget("absen_status_kashift_{$d}_{$s}");
        Cache::forget("absen_status_karu_{$d}_{$s}");
        Cache::forget("shift_custom_schedule_{$d}");

        // Legacy keys fallback
        Cache::forget(self::CACHE_KEY_KASHIFT);
        Cache::forget(self::CACHE_KEY_KARU);
    }

    /**
     * Hapus cache khusus jadwal shift kustom pada rentang tanggal tertentu.
     */
    public static function clearScheduleCache(?string $startDate = null, ?string $endDate = null): void
    {
        if ($startDate && $endDate) {
            try {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
                while ($start->lte($end)) {
                    Cache::forget("shift_custom_schedule_" . $start->toDateString());
                    $start->addDay();
                }
            } catch (\Exception $e) {
                // Ignore parse errors
            }
        }
        self::clearCache();
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

        $finalKeterangan = !empty(trim((string) $keterangan)) 
            ? trim((string) $keterangan) 
            : ($isActive ? 'Hadir kembali / Normal' : 'Izin / Sakit');

        $delegasi = AbsenDelegasi::updateOrCreate(
            [
                'tanggal' => $dateToUse,
                'shift' => $shiftToUse,
                'role_target' => $roleTarget,
            ],
            [
                'is_active' => $isActive,
                'keterangan' => $finalKeterangan,
                'updated_by' => $userId,
            ]
        );

        // Catat ke riwayat
        AbsenHistory::create([
            'tanggal' => $dateToUse,
            'shift' => $shiftToUse,
            'role_target' => $roleTarget,
            'status' => $normalizedStatus,
            'keterangan' => $finalKeterangan,
            'user_id' => $userId,
        ]);

        self::clearCache($dateToUse, $shiftToUse);

        return $delegasi;
    }
}
