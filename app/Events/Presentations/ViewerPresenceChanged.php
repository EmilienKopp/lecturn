<?php

declare(strict_types=1);

namespace App\Events\Presentations;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViewerPresenceChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $embedToken,
        public readonly int $count,
    ) {}

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [
            new Channel("presentation.{$this->embedToken}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'viewer.presence';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return [
            'count' => $this->count,
        ];
    }
}
