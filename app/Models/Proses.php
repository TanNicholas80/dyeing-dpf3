<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proses extends Model
{
    use HasFactory;

    protected $fillable = [
        'jenis',
        'no_op',
        'item_op',
        'kode_material',
        'konstruksi',
        'no_partai',
        'gramasi',
        'lebar',
        'hfeel',
        'warna',
        'kode_warna',
        'kategori_warna',
        'qty',
        'cycle_time', // integer (detik)
        'cycle_time_actual', // integer (detik)
        'barcode_kain',
        'barcode_la',
        'barcode_aux',
        'mesin_id',
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class);
    }
}
