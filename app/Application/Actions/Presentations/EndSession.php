<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\EndSessionCommand;
use App\Domain\Presentation\Contracts\PresentationSessionRepository;
use App\Domain\Presentation\Entities\PresentationSessionEntity;

class EndSession
{
    public function __construct(
        private readonly PresentationSessionRepository $sessions,
    ) {}

    /**
     * Closes the live session for a deck. No-op when nothing is open, so a
     * duplicate unload beacon is harmless.
     */
    public function execute(EndSessionCommand $command): ?PresentationSessionEntity
    {
        $session = $this->sessions->findActiveByPresentationId($command->presentationId);

        if ($session === null) {
            return null;
        }

        $session->end($command->endedAt);

        return $this->sessions->save($session);
    }
}
