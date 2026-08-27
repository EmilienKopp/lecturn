<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters;

use App\Domain\Presentation\Contracts\TranslationServiceContract;
use App\Domain\Presentation\ValueObjects\YoYoTranslateSession;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use RuntimeException;

class YoYoTranslateAdapter implements TranslationServiceContract
{
    public function __construct(private readonly PendingRequest $http) {}

    public function createSession(string $presentationSlug, string $sourceLanguage): YoYoTranslateSession
    {
        $response = $this->http->post('/sessions', [
            'slug' => $presentationSlug,
            'source_language' => $sourceLanguage,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "YoYoTranslate createSession failed [{$response->status()}]: {$response->body()}"
            );
        }

        $data = $response->json();

        return new YoYoTranslateSession(
            sessionId: $data['session_id'],
            startedAt: Carbon::now(),
            wsBaseUrl: (string) config('yoyotranslate.ws_base_url'),
        );
    }

    public function closeSession(string $sessionId): void
    {
        $response = $this->http->delete("/sessions/{$sessionId}");

        if (! $response->successful()) {
            throw new RuntimeException(
                "YoYoTranslate closeSession failed [{$response->status()}]: {$response->body()}"
            );
        }
    }
}
