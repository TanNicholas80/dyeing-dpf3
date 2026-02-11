<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class Proses extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'jenis',
        'mode',
        'jenis_op',
        'cycle_time',
        'cycle_time_actual',
        'mulai',
        'selesai',
        'mesin_id',
        'order',
    ];

    protected $casts = [
        'mulai' => 'datetime',
        'selesai' => 'datetime',
        'cycle_time' => 'integer',
        'cycle_time_actual' => 'integer',
        'order' => 'integer',
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class);
    }
    public function details()
    {
        return $this->hasMany(DetailProses::class);
    }
    public function approvals()
    {
        return $this->hasMany(Approval::class, 'proses_id', 'id');
    }
    
    /**
     * Konfigurasi logging untuk model Proses.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logFields = ['jenis', 'jenis_op', 'cycle_time', 'cycle_time_actual', 'mulai', 'selesai', 'mesin_id', 'order'];

        return LogOptions::defaults()
            ->useLogName('Manajemen Proses')
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
            'jenis' => $this->jenis,
            'jenis_op' => $this->jenis_op,
            'cycle_time' => $this->cycle_time,
            'cycle_time_actual' => $this->cycle_time_actual,
            'mulai' => $this->mulai,
            'selesai' => $this->selesai,
            'mesin_id' => $this->mesin->jenis_mesin,
            'order' => $this->order,
        ];

        switch ($eventName) {
            case 'created':
                $activity->description = "Proses '{$this->jenis}' berhasil dibuat";
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

                $activity->description = "Proses '{$this->jenis}' berhasil diperbarui";
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
                $activity->description = "Proses '{$this->jenis}' telah dihapus";
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
