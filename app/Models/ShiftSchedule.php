<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $table = 'shift_schedules';

    protected $fillable = [
        'nama_jadwal',
        'mode_jadwal',
        'tanggal_mulai',
        'tanggal_selesai',
        'shift1_start',
        'shift1_end',
        'shift2_start',
        'shift2_end',
        'daily_config',
        'is_active',
        'keterangan',
        'created_by',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'daily_config' => 'array',
        'is_active' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('is_active', true)
            ->where('tanggal_mulai', '<=', $date)
            ->where('tanggal_selesai', '>=', $date);
    }

    /**
     * Format time string to 'H:i'
     */
    public static function formatTime(?string $time): string
    {
        if (!$time) {
            return '';
        }
        return substr($time, 0, 5);
    }

    /**
     * Mendapatkan konfigurasi jam shift untuk tanggal tertentu berdasarkan mode_jadwal.
     * Mengembalikan array dengan format:
     * [
     *   'is_holiday' => bool,
     *   'shift1_start' => 'HH:MM',
     *   'shift1_end' => 'HH:MM',
     *   'shift2_start' => 'HH:MM',
     *   'shift2_end' => 'HH:MM',
     *   'description' => string,
     * ]
     */
    public function getConfigForDate($date): array
    {
        $d = $date instanceof Carbon ? $date : Carbon::parse($date, 'Asia/Jakarta');
        $dayOfWeek = (int) $d->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday

        // Hari Minggu selalu OFF
        if ($dayOfWeek === Carbon::SUNDAY) {
            return [
                'is_holiday' => true,
                'shift1_start' => null,
                'shift1_end' => null,
                'shift2_start' => null,
                'shift2_end' => null,
                'description' => 'Libur Produksi (OFF)',
            ];
        }

        // Jika mode seragam atau daily_config kosong, gunakan konfigurasi kolom utama
        if ($this->mode_jadwal !== 'per_hari' || empty($this->daily_config)) {
            $s1Start = self::formatTime($this->shift1_start);
            $s1End = self::formatTime($this->shift1_end);
            $s2Start = self::formatTime($this->shift2_start);
            $s2End = self::formatTime($this->shift2_end);

            return [
                'is_holiday' => false,
                'shift1_start' => $s1Start,
                'shift1_end' => $s1End,
                'shift2_start' => $s2Start,
                'shift2_end' => $s2End,
                'description' => "Shift 1: {$s1Start} - {$s1End} | Shift 2: {$s2Start} - {$s2End}",
            ];
        }

        // Mode per_hari: ambil konfigurasi per kelompok hari
        // Kelompok: 'senin', 'selasa_kamis', 'jumat', 'sabtu'
        $cfg = $this->daily_config;
        $dayKey = 'selasa_kamis';
        if ($dayOfWeek === Carbon::MONDAY) {
            $dayKey = 'senin';
        } elseif ($dayOfWeek === Carbon::FRIDAY) {
            $dayKey = 'jumat';
        } elseif ($dayOfWeek === Carbon::SATURDAY) {
            $dayKey = 'sabtu';
        }

        $dayData = $cfg[$dayKey] ?? null;
        if (!$dayData) {
            $s1Start = self::formatTime($this->shift1_start);
            $s1End = self::formatTime($this->shift1_end);
            $s2Start = self::formatTime($this->shift2_start);
            $s2End = self::formatTime($this->shift2_end);

            return [
                'is_holiday' => false,
                'shift1_start' => $s1Start,
                'shift1_end' => $s1End,
                'shift2_start' => $s2Start,
                'shift2_end' => $s2End,
                'description' => "Shift 1: {$s1Start} - {$s1End} | Shift 2: {$s2Start} - {$s2End}",
            ];
        }

        // Cek apakah hari ini diset Libur Produksi (OFF)
        if (!empty($dayData['is_off'])) {
            return [
                'is_holiday' => true,
                'shift1_start' => null,
                'shift1_end' => null,
                'shift2_start' => null,
                'shift2_end' => null,
                'description' => $dayData['off_description'] ?? 'Libur Produksi (OFF)',
            ];
        }

        $s1Start = self::formatTime($dayData['shift1_start'] ?? $this->shift1_start);
        $s1End = self::formatTime($dayData['shift1_end'] ?? $this->shift1_end);
        $s2Start = self::formatTime($dayData['shift2_start'] ?? $this->shift2_start);
        $s2End = self::formatTime($dayData['shift2_end'] ?? $this->shift2_end);

        return [
            'is_holiday' => false,
            'shift1_start' => $s1Start,
            'shift1_end' => $s1End,
            'shift2_start' => $s2Start,
            'shift2_end' => $s2End,
            'description' => "Shift 1: {$s1Start} - {$s1End} | Shift 2: {$s2Start} - {$s2End}",
        ];
    }
}
