<?php

declare(strict_types=1);

namespace App\Application\Commands;

use DateTimeInterface;

readonly class RecordReactionsCommand
{
    /**
     * @param  array<string, int>  $counts  emoji => tally accumulated on the client
     */
    public function __construct(
        public string $embedToken,
        public string $viewerId,
        public array $counts,
        public bool $leaving,
        public DateTimeInterface $at,
    ) {}
}
