<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class Auxl extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'no_op', 'no_partai', 'barcode', 'matdok', 'mesin_id', 'cancel',
        'jenis', 'code', 'konstruksi', 'customer', 'marketing', 'date', 'color'
    ];

    public static function getJenisOptions()
    {
        return [
            'normal' => 'Normal',
            'reproses' => 'Reproses',
            'perbaikan' => 'Perbaikan',
        ];
    }

    public function details()
    {
        return $this->hasMany(AuxlDetail::class);
    }

                /**
     * Konfigurasi logging untuk model Mesin.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logFields = ['barcode', 'jenis', 'code', 'konstruksi', 'customer', 'marketing', 'date', 'color', 'no_op', 'no_partai', 'mesin_id', 'cancel'];

        return LogOptions::defaults()
            ->useLogName('Manajemen Auxl')
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
            'barcode' => $this->barcode,
            'jenis' => $this->jenis,
            'code' => $this->code,
            'konstruksi' => $this->konstruksi,
            'customer' => $this->customer,
            'marketing' => $this->marketing,
            'date' => $this->date,
            'color' => $this->color,
            'no_op' => $this->no_op,
            'no_partai' => $this->no_partai,
            'mesin_id' => $this->mesin_id,
            'cancel' => $this->cancel,
        ];

        switch ($eventName) {
            case 'created':
                $activity->description = "Auxl '{$this->barcode}' berhasil dibuat";
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

                $activity->description = "Auxl '{$this->barcode}' berhasil diperbarui";
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
                $activity->description = "Auxl '{$this->barcode}' telah dihapus";
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
