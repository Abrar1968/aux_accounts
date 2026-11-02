<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Broadcast on Pusher so open dashboards refresh instantly. */
class PaymentPosted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $kind, public string $code, public float $amount) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('auxtech')];
    }

    public function broadcastAs(): string
    {
        return 'ledger.changed';
    }
}
