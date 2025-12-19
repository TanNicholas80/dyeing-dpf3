<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarcodeLa extends Model
{
    use HasFactory;

    protected $table = 'barcode_la';

    protected $fillable = [
        'proses_id', 'no_op', 'no_partai', 'barcode', 'matdok', 'mesin_id', 'cancel'
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class);
    }
    
    public function proses()
    {
        return $this->belongsTo(Proses::class, 'mesin_id');
    }
}
