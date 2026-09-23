<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MesinUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $mesinData;

    /**
     * Create a new event instance.
     */
    public function __construct($mesinData)
    {
        $this->mesinData = $mesinData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('dashboard.proses-statuses'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'mesin.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        if ($this->mesinData instanceof \App\Models\Mesin) {
            $m = $this->mesinData;
            $lastSeenTs = $m->last_seen_at ? $m->last_seen_at->getTimestamp() : 0;
            $isTimeout = (now()->getTimestamp() - $lastSeenTs) > 90;
            return [
                'mesin' => [
                    'id' => $m->id,
                    'jenis_mesin' => $m->jenis_mesin,
                    'status' => (bool) $m->status,
                    'label' => $m->status ? 'Hidup' : 'Mati',
                    'iot_signal' => !$isTimeout,
                    'iot_label' => !$isTimeout ? 'Terhubung' : 'Terputus',
                    'last_on' => $m->last_on_at ? $m->last_on_at->translatedFormat('d-m-Y H:i:s') : '-',
                    'last_off' => $m->last_off_at ? $m->last_off_at->translatedFormat('d-m-Y H:i:s') : '-',
                    'force_alarm_off' => (bool) \Illuminate\Support\Facades\Cache::get("iot:mesin:{$m->id}:force_alarm_off", false),
                ],
            ];
        }

        return [
            'mesin' => (array) $this->mesinData,
        ];
    }
}
