<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\DeletePresentationCommand;
use App\Domain\Presentation\Contracts\PresentationRepository;

class DeletePresentation
{
    public function __construct(
        private readonly PresentationRepository $presentations,
    ) {}

    public function execute(DeletePresentationCommand $command): void
    {
        $this->presentations->delete($command->presentation_id);
    }
}
