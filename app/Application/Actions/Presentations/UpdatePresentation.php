<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\UpdatePresentationCommand;
use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\Events\PresentationContentReplaced;

class UpdatePresentation
{
    public function __construct(
        private readonly PresentationRepository $presentations,
    ) {}

    public function execute(UpdatePresentationCommand $command): PresentationEntity
    {
        $presentation = $this->presentations->findById($command->presentation_id);

        if ($command->name !== null) {
            $presentation->rename($command->name);
        }

        if ($command->content !== null) {
            $presentation->replaceContent($command->content);
        }

        $saved = $this->presentations->save($presentation);

        if ($command->content !== null) {
            PresentationContentReplaced::dispatch($saved->id);
        }

        return $saved;
    }
}
