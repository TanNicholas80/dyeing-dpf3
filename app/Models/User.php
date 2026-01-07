<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    use HasFactory, Notifiable, LogsActivity;

    protected $fillable = [
        'nama',
        'username',
        'password',
        'role',
        'mesin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Otomatis hash password ketika diset.
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Hash::needsRehash($value)
                ? Hash::make($value)
                : $value;
        }
    }

        /**
     * Konfigurasi logging untuk model User.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logFields = ['nama', 'username', 'role', 'mesin'];

        return LogOptions::defaults()
            ->useLogName('Manajemen User')
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
            'nama' => $this->nama,
            'username' => $this->username,
            'role' => $this->role,
            'mesin' => $this->mesin,
        ];

        switch ($eventName) {
            case 'created':
                $activity->description = "User '{$this->nama}' berhasil dibuat";
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
                unset($changes['password'], $changes['updated_at']);

                if (empty($changes)) {
                    return;
                }

                $original = array_intersect_key($this->getOriginal(), $changes);
                unset($original['password']);

                $activity->description = "User '{$this->nama}' berhasil diperbarui";
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
                $activity->description = "User '{$this->nama}' telah dihapus";
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
