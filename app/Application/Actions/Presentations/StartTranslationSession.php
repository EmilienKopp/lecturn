<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\StartTranslationSessionCommand;
use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Contracts\TranslationServiceContract;
use App\Domain\Presentation\Entities\PresentationEntity;

class StartTranslationSession
{
    public function __construct(
        private readonly PresentationRepository $presentations,
        private readonly TranslationServiceContract $translationService,
    ) {}

    public function execute(StartTranslationSessionCommand $command): PresentationEntity
    {
        $presentation = $this->presentations->findById($command->presentationId);

        $session = $this->translationService->createSession(
            presentationSlug: (string) $presentation->id,
            sourceLanguage: $command->sourceLanguage,
        );

        $presentation->attachTranslationSession($session->sessionId, $session->startedAt);

        return $this->presentations->save($presentation);
    }
}
