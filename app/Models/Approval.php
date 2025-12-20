<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory;

    /**
     * Tabel utama model.
     */
    protected $table = 'approvals';

    /**
     * Kolom yang boleh diisi mass-assignment.
     */
    protected $fillable = [
        'proses_id',
        'status',
        'type',
        'action',
        'history_data',
        'note',
        'requested_by',
        'approved_by',
    ];

    /**
     * Casting atribut.
     */
    protected $casts = [
        'history_data' => 'array', // dari kolom JSON
    ];

    /**
     * Relasi ke Proses (termasuk yang sudah di-soft-delete untuk history/audit).
     */
    public function proses()
    {
        return $this->belongsTo(Proses::class, 'proses_id')->withTrashed();
    }

    /**
     * User yang mengajukan permintaan (FM / user biasa).
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * User yang menyetujui / menolak (FM / VP).
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
