<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\UploadPresentationBackgroundCommand;
use App\Domain\Presentation\Contracts\PresentationRepository;

class UploadPresentationBackground
{
    public function __construct(
        private readonly PresentationRepository $presentations,
    ) {}

    /**
     * Stores the deck-wide background image and returns its public URL.
     */
    public function execute(UploadPresentationBackgroundCommand $command): string
    {
        return $this->presentations->storeBackgroundImage(
            $command->presentation_id,
            $command->filePath,
            $command->fileName,
        );
    }
}
