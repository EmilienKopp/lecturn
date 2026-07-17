<?php

declare(strict_types=1);

namespace App\Presentation;

readonly class PresenterOutput
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $filename,
    ) {}
}
