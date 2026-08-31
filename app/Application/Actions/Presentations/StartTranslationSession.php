<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\StartTranslationSessionCommand;
use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Contracts\TranslationServiceContract;
use App\Domain\Presentation\Entities\PresentationEntity;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class StartTranslationSession
{
    public function __construct(
        private readonly PresentationRepository $presentations,
        private readonly TranslationServiceContract $translationService,
    ) {}

    public function execute(StartTranslationSessionCommand $command): PresentationEntity
    {
        $presentation = $this->presentations->findById($command->presentationId);

        if ($command->eventId !== null) {
            // Manual linking: the presenter created the event in YoYoTranslate's
            // own UI and pasted its id; no API call involved.
            $presentation->attachTranslationSession($command->eventId, Carbon::now());

            return $this->presentations->save($presentation);
        }

        if ($command->sourceLanguage === null) {
            throw new InvalidArgumentException('Either an event id or a source language is required.');
        }

        $session = $this->translationService->createSession(
            presentationSlug: (string) $presentation->id,
            sourceLanguage: $command->sourceLanguage,
        );

        $presentation->attachTranslationSession($session->sessionId, $session->startedAt);

        return $this->presentations->save($presentation);
    }
}
