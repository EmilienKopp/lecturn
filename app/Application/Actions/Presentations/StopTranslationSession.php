<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\StopTranslationSessionCommand;
use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Contracts\TranslationServiceContract;
use App\Domain\Presentation\Entities\PresentationEntity;

class StopTranslationSession
{
    public function __construct(
        private readonly PresentationRepository $presentations,
        private readonly TranslationServiceContract $translationService,
    ) {}

    public function execute(StopTranslationSessionCommand $command): PresentationEntity
    {
        $presentation = $this->presentations->findById($command->presentationId);

        if ($presentation->yoyotranslateSessionId !== null) {
            $this->translationService->closeSession($presentation->yoyotranslateSessionId);
        }

        $presentation->detachTranslationSession();

        return $this->presentations->save($presentation);
    }
}
