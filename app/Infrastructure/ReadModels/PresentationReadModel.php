<?php

declare(strict_types=1);

namespace App\Infrastructure\ReadModels;

use App\Models\Views\PresentationsView;

class PresentationReadModel
{
    /**
     * @return array<int, array{id: int, name: string, slide_count: int, updated_at: string|null}>
     */
    public function listForTeam(int $teamId): array
    {
        return PresentationsView::query()
            ->where('team_id', $teamId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (PresentationsView $presentation): array => [
                'id' => $presentation->id,
                'name' => $presentation->name,
                'slide_count' => count($presentation->content['slides'] ?? []),
                'updated_at' => $presentation->updated_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, content: array<string, mixed>, updated_at: string|null}
     */
    public function findForEditor(int $presentationId): array
    {
        $presentation = PresentationsView::query()->findOrFail($presentationId);

        return [
            'id' => $presentation->id,
            'name' => $presentation->name,
            'content' => $presentation->content,
            'updated_at' => $presentation->updated_at?->toISOString(),
        ];
    }
}
