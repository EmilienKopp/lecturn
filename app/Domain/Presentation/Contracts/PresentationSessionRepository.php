<?php

declare(strict_types=1);

namespace App\Domain\Presentation\Contracts;

use App\Domain\Presentation\Entities\PresentationSessionEntity;

interface PresentationSessionRepository
{
    public function save(PresentationSessionEntity $session): PresentationSessionEntity;

    /**
     * The open session for a presentation (no ended_at), or null when the
     * presenter is not currently live.
     */
    public function findActiveByPresentationId(int $presentationId): ?PresentationSessionEntity;

    /**
     * The open session for a presentation's embed token, or null when there is
     * no live session. Used by the anonymous viewer endpoints.
     */
    public function findActiveByEmbedToken(string $embedToken): ?PresentationSessionEntity;
}
