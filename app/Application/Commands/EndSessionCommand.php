<?php

declare(strict_types=1);

namespace App\Application\Commands;

use DateTimeInterface;

readonly class EndSessionCommand
{
    public function __construct(
        public int $presentationId,
        public DateTimeInterface $endedAt,
    ) {}
}
