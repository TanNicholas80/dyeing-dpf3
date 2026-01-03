<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Auxl extends Model
{
    use HasFactory;

    protected $fillable = [
        'no_op', 'no_partai', 'barcode', 'matdok', 'mesin_id', 'cancel',
        'jenis', 'code', 'konstruksi', 'customer', 'marketing', 'date', 'color'
    ];

    public static function getJenisOptions()
    {
        return [
            'normal' => 'Normal',
            'reproses' => 'Reproses',
            'perbaikan' => 'Perbaikan',
        ];
    }

    public function details()
    {
        return $this->hasMany(AuxlDetail::class);
    }
}
