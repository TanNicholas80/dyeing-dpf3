<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PinjamMesinHistory extends Model
{
    use HasFactory;

    protected $table = 'pinjam_mesin_histories';

    protected $fillable = [
        'proses_id',
        'mesin_id',
        'alasan',
        'pinjam_at',
        'selesai_at',
        'pinjam_by',
        'selesai_by',
    ];

    protected $casts = [
        'pinjam_at' => 'datetime',
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

    public function userPinjam()
    {
        return $this->belongsTo(User::class, 'pinjam_by');
    }

    public function userSelesai()
    {
        return $this->belongsTo(User::class, 'selesai_by');
    }
}
