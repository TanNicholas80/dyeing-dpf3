<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class DetailProses extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'proses_id',
        'no_op',
        'item_op',
        'kode_material',
        'konstruksi',
        'no_partai',
        'gramasi',
        'lebar',
        'hfeel',
        'warna',
        'kode_warna',
        'kategori_warna',
        'qty',
        'roll',
    ];

    /**
     * Relasi ke model Proses
     */
    public function proses()
    {
        return $this->belongsTo(Proses::class);
    }

    /**
     * Relasi ke BarcodeKain
     */
    public function barcodeKains()
    {
        return $this->hasMany(BarcodeKain::class, 'detail_proses_id', 'id');
    }

    /**
     * Relasi ke BarcodeLa
     */
    public function barcodeLas()
    {
        return $this->hasMany(BarcodeLa::class, 'detail_proses_id', 'id');
    }

    /**
     * Relasi ke BarcodeAux
     */
    public function barcodeAuxs()
    {
        return $this->hasMany(BarcodeAux::class, 'detail_proses_id', 'id');
    }

    /**
     * Konfigurasi logging untuk model DetailProses.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $logFields = [
            'proses_id',
            'no_op',
            'item_op',
            'kode_material',
            'konstruksi',
            'no_partai',
            'gramasi',
            'lebar',
            'hfeel',
            'warna',
            'kode_warna',
            'kategori_warna',
            'qty',
            'roll',
        ];

        return LogOptions::defaults()
            ->useLogName('Manajemen DetailProses')
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
            'item_op' => $this->item_op,
            'kode_material' => $this->kode_material,
            'konstruksi' => $this->konstruksi,
            'no_partai' => $this->no_partai,
            'gramasi' => $this->gramasi,
            'lebar' => $this->lebar,
            'hfeel' => $this->hfeel,
            'warna' => $this->warna,
            'kode_warna' => $this->kode_warna,
            'kategori_warna' => $this->kategori_warna,
            'qty' => $this->qty,
            'roll' => $this->roll,
        ];

        switch ($eventName) {
            case 'created':
                $activity->description = "DetailProses '{$this->no_op}' berhasil dibuat";
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
                $activity->description = "DetailProses '{$this->no_op}' telah dihapus";
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
