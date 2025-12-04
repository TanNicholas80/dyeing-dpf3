<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarcodeKain extends Model
{
    use HasFactory;

    protected $table = 'barcode_kain';

    protected $fillable = [
        'proses_id', 'no_op', 'no_partai', 'barcode', 'matdok', 'mesin_id', 'cancel'
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class);
    }
    
    public function proses()
    {
        return $this->belongsTo(Proses::class, 'proses_id');
    }
}
