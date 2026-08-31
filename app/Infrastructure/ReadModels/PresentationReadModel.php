<?php

declare(strict_types=1);

namespace App\Infrastructure\ReadModels;

use App\Domain\Presentation\ValueObjects\TalkSettings;
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
     * @return array{embed_token: string, content: array<string, mixed>, flow: array<string, mixed>|null}
     */
    public function findForEmbed(int $presentationId): array
    {
        $presentation = PresentationsView::query()->findOrFail($presentationId);

        return [
            'embed_token' => $presentation->embed_token,
            'content' => $presentation->content,
            'flow' => $presentation->flow,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     content: array<string, mixed>,
     *     talk_settings: array<string, mixed>,
     *     flow: array<string, mixed>|null,
     *     embed_token: string,
     *     updated_at: string|null,
     *     yoyotranslate: array{
     *         session_id: string|null,
     *         websocket_url: string|null,
     *         active: bool,
     *         started_at: string|null
     *     }
     * }
     */
    public function findForPresent(int $presentationId): array
    {
        $presentation = PresentationsView::query()->findOrFail($presentationId);

        $sessionId = $presentation->yoyotranslate_session_id;
        $wsBaseUrl = (string) config('yoyotranslate.ws_base_url');
        $wsLang = (string) config('yoyotranslate.ws_lang', 'all');

        return [
            'id' => $presentation->id,
            'name' => $presentation->name,
            'content' => $presentation->content,
            'talk_settings' => TalkSettings::fromArray($presentation->talk_settings ?? [])->toArray(),
            'flow' => $presentation->flow,
            'embed_token' => $presentation->embed_token,
            'updated_at' => $presentation->updated_at?->toISOString(),
            'yoyotranslate' => [
                'session_id' => $sessionId,
                'websocket_url' => $sessionId !== null
                    ? rtrim($wsBaseUrl, '/').'/'.$sessionId.'?lang='.$wsLang
                    : null,
                'active' => $sessionId !== null,
                'started_at' => $presentation->yoyotranslate_session_started_at?->toISOString(),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, content: array<string, mixed>, talk_settings: array<string, mixed>, flow: array<string, mixed>|null, updated_at: string|null}
     */
    public function findForEditor(int $presentationId): array
    {
        $presentation = PresentationsView::query()->findOrFail($presentationId);

        return [
            'id' => $presentation->id,
            'name' => $presentation->name,
            'content' => $presentation->content,
            'talk_settings' => TalkSettings::fromArray($presentation->talk_settings ?? [])->toArray(),
            'flow' => $presentation->flow,
            'updated_at' => $presentation->updated_at?->toISOString(),
        ];
    }
}
