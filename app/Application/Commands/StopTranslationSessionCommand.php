<?php

declare(strict_types=1);

namespace App\Application\Commands;

readonly class StopTranslationSessionCommand
{
    public function __construct(
        public int $presentationId,
        public int $userId,
    ) {}
}
