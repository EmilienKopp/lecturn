<?php

declare(strict_types=1);

namespace App\Application\Actions\Presentations;

use App\Application\Commands\RecordReactionsCommand;
use App\Domain\Presentation\Contracts\PresentationSessionRepository;
use App\Domain\Presentation\Entities\PresentationSessionEntity;
use App\Events\Presentations\ViewerPresenceChanged;

class RecordReactions
{
    public function __construct(
        private readonly PresentationSessionRepository $sessions,
    ) {}

    /**
     * Folds a viewer's batched reactions and heartbeat into the live session,
     * then broadcasts the current watching-now count to the presenter. Returns
     * null when no session is live — reactions are only kept during a talk.
     */
    public function execute(RecordReactionsCommand $command): ?PresentationSessionEntity
    {
        $session = $this->sessions->findActiveByEmbedToken($command->embedToken);

        if ($session === null) {
            return null;
        }

        if ($command->counts !== []) {
            $session->recordReactions($command->counts, $command->at);
        }

        if ($command->leaving) {
            $session->markViewerLeft($command->viewerId);
        } else {
            $session->touchViewer($command->viewerId, $command->at);
        }

        $saved = $this->sessions->save($session);

        ViewerPresenceChanged::dispatch(
            $command->embedToken,
            $saved->activeViewerCount($command->at),
        );

        return $saved;
    }
}
