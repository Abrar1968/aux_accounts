<?php

namespace App\Events;

use App\Models\Period;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PeriodClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Period $period) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('auxtech')];
    }

    public function broadcastAs(): string
    {
        return 'period.closed';
    }
}
