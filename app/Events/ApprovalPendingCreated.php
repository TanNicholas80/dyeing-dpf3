<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast ketika ada approval pending baru dibuat
 * (edit_cycle_time, delete_proses, move_machine, swap_position).
 * Digunakan agar semua browser langsung update tampilan (blok kuning) tanpa reload.
 */
class ApprovalPendingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var array<int> ID proses yang terpengaruh (akan tampil kuning / menunggu approval) */
    public $prosesIds;

    /**
     * Create a new event instance.
     *
     * @param array<int> $prosesIds
     */
    public function __construct(array $prosesIds)
    {
        $this->prosesIds = array_values(array_unique($prosesIds));
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
        return 'approval.pending.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'proses_ids' => $this->prosesIds,
        ];
    }
}
