<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsenDelegasi extends Model
{
    use HasFactory;

    protected $table = 'absen_delegasis';

    protected $fillable = [
        'role_target',
        'is_active',
        'current_shift',
        'keterangan',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
