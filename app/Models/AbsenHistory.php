<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsenHistory extends Model
{
    use HasFactory;

    protected $table = 'absen_histories';

    protected $fillable = [
        'tanggal',
        'shift',
        'role_target',
        'status',
        'keterangan',
        'user_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
