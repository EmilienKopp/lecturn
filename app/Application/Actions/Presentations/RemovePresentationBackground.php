<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Domain\Presentation\Contracts\PresentationRepository;

class RemovePresentationBackground
{
    public function __construct(
        private readonly PresentationRepository $presentations,
    ) {}

    public function execute(int $presentationId): void
    {
        $this->presentations->clearBackgroundImage($presentationId);
    }
}
