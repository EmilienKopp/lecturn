<?php

declare(strict_types=1);

namespace App\Application\Commands;

readonly class DeletePresentationCommand
{
    public function __construct(
        public int $presentation_id,
    ) {}
}
