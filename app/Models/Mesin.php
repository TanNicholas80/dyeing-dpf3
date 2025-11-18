<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mesin extends Model
{
    use HasFactory;

    protected $fillable = [
        'jenis_mesin',
        'status',
    ];

    public function proses()
    {
        return $this->hasMany(\App\Models\Proses::class, 'mesin_id');
    }
}
