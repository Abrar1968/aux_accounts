<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AutomationFinished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $command, public array $summary)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('auxtech')];
    }

    public function broadcastAs(): string
    {
        return 'automation.finished';
    }
}
