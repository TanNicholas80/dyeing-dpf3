<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DyeStuff extends Model
{
    use HasFactory;

    protected $table = 'dye_stuffs';

    protected $fillable = [
        'proses_id',
        'barcode',
        'tipe',
        'jenis',
        'liquor_ratio',
        'total_wt',
        'volume_litres',
        'step_proses',
        'matdok',
        'cancel',
    ];

    public static function getTipeOptions()
    {
        return [
            'normal'     => 'Normal (Utama)',
            'additional' => 'Addition (Topping)',
        ];
    }

    public static function getJenisOptions()
    {
        return [
            'normal'    => 'Normal',
            'reproses'  => 'Reproses FG',
            'perbaikan' => 'Perbaikan BDP',
        ];
    }

    public static function getStepProsesOptions()
    {
        return [
            1 => 'Step 1',
            2 => 'Step 2',
            3 => 'Step 3',
        ];
    }

    public static function generateBarcode(): string
    {
        $year = date('y');
        $prefix = 'DS-' . $year . '-';

        $last = static::where('barcode', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;
        if ($last && !empty($last->barcode)) {
            $parts = explode('-', $last->barcode);
            $lastNum = (int) end($parts);
            $nextNumber = $lastNum + 1;
        }

        return $prefix . str_pad($nextNumber, 10, '0', STR_PAD_LEFT);
    }

    // Relasi ke Planning Proses
    public function proses()
    {
        return $this->belongsTo(Proses::class, 'proses_id');
    }

    // Relasi ke Detail Bahan Kimia / Dye Stuff
    public function details()
    {
        return $this->hasMany(DyeStuffDetail::class, 'dyestuff_id');
    }

    // Relasi ke Approvals (untuk persetujuan 2 tahap FM & VP)
    public function approvals()
    {
        return $this->hasMany(Approval::class, 'dyestuff_id');
    }
}
