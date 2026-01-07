<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;
class BarcodeKain extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'barcode_kain';

    protected $fillable = [
        'proses_id', 'no_op', 'no_partai', 'barcode', 'matdok', 'mesin_id', 'cancel'
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class);
    }
    
    public function proses()
    {
        return $this->belongsTo(Proses::class, 'proses_id');
    }

    
                /**
     * Konfigurasi logging untuk model BarcodeKain.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logFields = ['proses_id', 'no_op', 'no_partai', 'barcode', 'matdok', 'mesin_id', 'cancel'];

        return LogOptions::defaults()
            ->useLogName('Manajemen BarcodeKain')
            ->logOnly($logFields)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Kustomisasi payload activity log untuk event tertentu.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        if (! in_array($eventName, ['created', 'deleted'])) {
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
            'proses_id' => $this->proses_id,
            'no_op' => $this->no_op,
            'no_partai' => $this->no_partai,
            'barcode' => $this->barcode,
            'matdok' => $this->matdok,
            'mesin_id' => $this->mesin->jenis_mesin,
            'cancel' => $this->cancel,
        ];

        switch ($eventName) {
            case 'created':
                $activity->description = "BarcodeKain '{$this->no_op}' berhasil dibuat";
                $activity->properties = $activity->properties->merge([
                    'event_type' => 'created',
                    'created_data' => array_merge($baseData, [
                        'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
                    ]),
                    'causer_info' => $causerInfo,
                    'timestamp_info' => $timestampInfo,
                ]);
                break;

            case 'deleted':
                $activity->description = "BarcodeKain '{$this->no_op}' telah dihapus";
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
