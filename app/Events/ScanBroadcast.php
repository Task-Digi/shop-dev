<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ScanBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $data;
    public $orderId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($orderId, $data)
    {
        $this->orderId = $orderId;
        $this->data = $data;
        \Illuminate\Support\Facades\Log::info("ScanBroadcast event instantiated for order: $orderId, Channel: " . (config('app.env') . '.order.' . $orderId));
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [
            new Channel(config('app.env') . '.order.' . $this->orderId),
            new Channel(config('app.env') . '.order.global')
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'scan.updated';
    }
}
