<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DyeStuffDetail extends Model
{
    use HasFactory;

    protected $table = 'dye_stuff_details';

    protected $fillable = [
        'dyestuff_id',
        'code',
        'chemical_name',
        'konsentrasi',
        'weight',
        'unit',
        'remark',
    ];

    public function dyeStuff()
    {
        return $this->belongsTo(DyeStuff::class, 'dyestuff_id');
    }
}
