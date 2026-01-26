<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProsesMoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $prosesId;
    public $oldMesinId;
    public $newMesinId;
    public $statusData;

    /**
     * Create a new event instance.
     */
    public function __construct($prosesId, $oldMesinId, $newMesinId, $statusData)
    {
        $this->prosesId = $prosesId;
        $this->oldMesinId = $oldMesinId;
        $this->newMesinId = $newMesinId;
        $this->statusData = $statusData;
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
        return 'proses.moved';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'proses_id' => $this->prosesId,
            'old_mesin_id' => $this->oldMesinId,
            'new_mesin_id' => $this->newMesinId,
            'status' => $this->statusData,
        ];
    }
}
