<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakHistory extends Model
{
    use HasFactory;

    protected $table = 'break_histories';

    protected $fillable = [
        'proses_id',
        'mesin_id',
        'alasan',
        'break_at',
        'selesai_at',
        'break_by',
        'selesai_by',
    ];

    protected $casts = [
        'break_at' => 'datetime',
        'selesai_at' => 'datetime',
    ];

    public function proses()
    {
        return $this->belongsTo(Proses::class, 'proses_id');
    }

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'mesin_id');
    }

    public function userBreak()
    {
        return $this->belongsTo(User::class, 'break_by');
    }

    public function userSelesai()
    {
        return $this->belongsTo(User::class, 'selesai_by');
    }
}
