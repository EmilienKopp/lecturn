<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\UploadPresentationImageCommand;
use App\Domain\Presentation\Contracts\PresentationRepository;

class UploadPresentationImage
{
    public function __construct(
        private readonly PresentationRepository $presentations,
    ) {}

    /**
     * Stores a content image and returns its public URL.
     */
    public function execute(UploadPresentationImageCommand $command): string
    {
        return $this->presentations->storeImage(
            $command->presentation_id,
            $command->filePath,
            $command->fileName,
        );
    }
}
