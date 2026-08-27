<?php

declare(strict_types=1);

namespace App\Events\Presentations;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $embedToken,
        public readonly string $emoji,
    ) {}

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [
            new Channel("presentation.{$this->embedToken}"),
        ];
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'emoji' => $this->emoji,
        ];
    }
}
