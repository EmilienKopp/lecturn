<?php

declare(strict_types=1);

namespace App\Application\Commands;

use DateTimeInterface;

readonly class StartSessionCommand
{
    public function __construct(
        public int $presentationId,
        public int $teamId,
        public DateTimeInterface $startedAt,
    ) {}
}
