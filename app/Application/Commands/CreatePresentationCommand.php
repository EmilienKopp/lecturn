<?php

declare(strict_types=1);

namespace App\Application\Commands;

readonly class CreatePresentationCommand
{
    public function __construct(
        public int $team_id,
        public string $name,
    ) {}
}
