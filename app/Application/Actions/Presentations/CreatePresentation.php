<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\CreatePresentationCommand;
use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\ValueObjects\PresentationContent;

class CreatePresentation
{
    public function __construct(
        private readonly PresentationRepository $presentations,
    ) {}

    public function execute(CreatePresentationCommand $command): PresentationEntity
    {
        $presentation = new PresentationEntity(
            team_id: $command->team_id,
            name: $command->name,
            content: PresentationContent::empty(),
        );

        return $this->presentations->save($presentation);
    }
}
