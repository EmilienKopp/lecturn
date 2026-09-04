<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Presentation\Contracts\PresentationSessionRepository;
use App\Domain\Presentation\Entities\PresentationSessionEntity;
use App\Models\PresentationModel;
use App\Models\PresentationSessionModel;

class EloquentPresentationSessionRepository implements PresentationSessionRepository
{
    public function save(PresentationSessionEntity $session): PresentationSessionEntity
    {
        $attributes = [
            'presentation_id' => $session->presentation_id,
            'team_id' => $session->team_id,
            'started_at' => $session->started_at,
            'ended_at' => $session->ended_at,
            'last_seen_at' => $session->last_seen_at,
            'reaction_counts' => (object) $session->reaction_counts,
            'reaction_total' => $session->reaction_total,
            'viewers' => (object) $session->viewers,
            'viewer_count' => $session->viewer_count,
        ];

        if ($session->id === null) {
            $model = PresentationSessionModel::create($attributes);
        } else {
            $model = PresentationSessionModel::findOrFail($session->id);
            $model->update($attributes);
        }

        return $model->refresh()->toEntity();
    }

    public function findActiveByPresentationId(int $presentationId): ?PresentationSessionEntity
    {
        return PresentationSessionModel::query()
            ->where('presentation_id', $presentationId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first()
            ?->toEntity();
    }

    public function findActiveByEmbedToken(string $embedToken): ?PresentationSessionEntity
    {
        $presentationId = PresentationModel::query()
            ->where('embed_token', $embedToken)
            ->value('id');

        if ($presentationId === null) {
            return null;
        }

        return $this->findActiveByPresentationId((int) $presentationId);
    }
}
