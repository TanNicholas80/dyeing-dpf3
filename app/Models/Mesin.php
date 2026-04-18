<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class Mesin extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'jenis_mesin',
        'status',
        'last_seen_at',
        'last_on_at',
        'last_off_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_on_at' => 'datetime',
        'last_off_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($mesin) {
            if ($mesin->isDirty('status')) {
                if ($mesin->status) {
                    $mesin->last_on_at = now();
                } else {
                    $mesin->last_off_at = now();
                }
            }
        });
    }

    public function proses()
    {
        return $this->hasMany(\App\Models\Proses::class, 'mesin_id');
    }
    public function barcodeKains()
    {
        return $this->hasMany(BarcodeKain::class, 'mesin_id', 'id');
    }
    public function barcodeLas()
    {
        return $this->hasMany(BarcodeLa::class, 'mesin_id', 'id');
    }
    public function barcodeAuxs()
    {
        return $this->hasMany(BarcodeAux::class, 'mesin_id', 'id');
    }

            /**
     * Konfigurasi logging untuk model Mesin.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logFields = ['jenis_mesin', 'status'];

        return LogOptions::defaults()
            ->useLogName('Manajemen Mesin')
            ->logOnly($logFields)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Kustomisasi payload activity log untuk event tertentu.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        if (! in_array($eventName, ['created', 'updated', 'deleted'])) {
            return;
        }

        $causer = Auth::user();
        $causerInfo = null;

        if ($causer) {
            $causerInfo = [
                'causer_id' => $causer->id,
                'causer_type' => get_class($causer),
                'causer_name' => $causer->nama ?? null,
                'causer_username' => $causer->username ?? null,
                'causer_role' => $causer->role ?? null,
                'causer_mesin' => $causer->mesin ?? null,
            ];
        }

        $timestampInfo = [
            'action_date' => now()->format('Y-m-d'),
            'action_time' => now()->format('H:i:s'),
            'action_datetime' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone', 'Asia/Jakarta'),
        ];

        $baseData = [
            'jenis_mesin' => $this->jenis_mesin,
            'status' => $this->status,
        ];

        switch ($eventName) {
            case 'created':
                $activity->description = "Mesin '{$this->jenis_mesin}' berhasil dibuat";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'created',
                    'created_data' => array_merge($baseData, [
                        'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
                    ]),
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo,
                ]);
                break;

            case 'updated':
                $changes = $this->getChanges();

                if (empty($changes)) {
                    return;
                }

                $original = array_intersect_key($this->getOriginal(), $changes);

                $activity->description = "Mesin '{$this->jenis_mesin}' berhasil diperbarui";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'updated',
                    'before_update' => $original,
                    'after_update' => $changes,
                    'updated_fields' => array_keys($changes),
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo,
                ]);
                break;

            case 'deleted':
                $activity->description = "Mesin '{$this->jenis_mesin}' telah dihapus";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'deleted',
                    'deleted_data' => array_merge($baseData, [
                        'deleted_at' => now()->format('Y-m-d H:i:s'),
                    ]),
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo,
                ]);
                break;
        }
    }
}
