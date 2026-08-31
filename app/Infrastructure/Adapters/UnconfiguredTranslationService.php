<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters;

use App\Domain\Presentation\Contracts\TranslationServiceContract;
use App\Domain\Presentation\ValueObjects\YoYoTranslateSession;
use RuntimeException;

/**
 * Bound while no YoYoTranslate API key is configured. Sessions are linked
 * manually (pasted event URLs), so closing is a local no-op and creating a
 * session through the API is impossible.
 */
class UnconfiguredTranslationService implements TranslationServiceContract
{
    public function createSession(string $presentationSlug, string $sourceLanguage): YoYoTranslateSession
    {
        throw new RuntimeException(
            'YoYoTranslate API is not configured. Set YOYOTRANSLATE_API_KEY, or link a session by pasting an event URL.'
        );
    }

    public function closeSession(string $sessionId): void
    {
        // Manually linked events are owned by YoYoTranslate's UI; nothing to close.
    }
}
