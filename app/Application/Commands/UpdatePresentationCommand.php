<?php

declare(strict_types=1);

namespace App\Application\Commands;

use App\Domain\Presentation\ValueObjects\PresentationContent;

readonly class UpdatePresentationCommand
{
    public function __construct(
        public int $presentation_id,
        public ?string $name = null,
        public ?PresentationContent $content = null,
    ) {}
}
