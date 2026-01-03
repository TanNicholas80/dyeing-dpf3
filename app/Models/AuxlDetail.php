<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuxlDetail extends Model
{
    use HasFactory;
    protected $fillable = [
        'auxl_id', 'auxiliary', 'konsentrasi'
    ];

    public function auxl()
    {
        return $this->belongsTo(Auxl::class);
    }
}
