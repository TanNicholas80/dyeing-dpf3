<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsenDelegasi extends Model
{
    use HasFactory;

    protected $table = 'absen_delegasis';

    protected $fillable = [
        'tanggal',
        'shift',
        'role_target',
        'is_active',
        'keterangan',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Alias for legacy current_shift attribute.
     */
    public function getCurrentShiftAttribute(): ?string
    {
        return $this->shift;
    }

    public function setCurrentShiftAttribute(?string $value): void
    {
        $this->attributes['shift'] = $value;
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
