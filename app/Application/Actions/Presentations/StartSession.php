<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\StartSessionCommand;
use App\Domain\Presentation\Contracts\PresentationSessionRepository;
use App\Domain\Presentation\Entities\PresentationSessionEntity;

class StartSession
{
    public function __construct(
        private readonly PresentationSessionRepository $sessions,
    ) {}

    /**
     * Opens a live session for a deck. Idempotent: reloading the present view
     * reuses the already-open session instead of spawning a second one.
     */
    public function execute(StartSessionCommand $command): PresentationSessionEntity
    {
        $existing = $this->sessions->findActiveByPresentationId($command->presentationId);

        if ($existing !== null) {
            return $existing;
        }

        return $this->sessions->save(new PresentationSessionEntity(
            presentation_id: $command->presentationId,
            team_id: $command->teamId,
            started_at: $command->startedAt,
            last_seen_at: $command->startedAt,
        ));
    }
}
