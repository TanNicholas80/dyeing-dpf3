<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proses extends Model
{
    use HasFactory, SoftDeletes;

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
        'roll',
        'cycle_time',
        'cycle_time_actual',
        'mesin_id',
        'order',
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class);
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'proses_id', 'id');
    }
    public function barcodeKains()
    {
        return $this->hasMany(BarcodeKain::class, 'proses_id', 'id');
    }
    public function barcodeLas()
    {
        return $this->hasMany(BarcodeLa::class, 'proses_id', 'id');
    }
    public function barcodeAuxs()
    {
        return $this->hasMany(BarcodeAux::class, 'proses_id', 'id');
    }
}
