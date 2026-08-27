<?php

declare(strict_types=1);

namespace App\Domain\Presentation\Contracts;

use App\Domain\Presentation\ValueObjects\YoYoTranslateSession;

interface TranslationServiceContract
{
    /**
     * Creates a new translation session on the YoYoTranslate service and returns
     * the resulting session value object containing the session ID and WebSocket URL.
     */
    public function createSession(string $presentationSlug, string $sourceLanguage): YoYoTranslateSession;

    /**
     * Signals the YoYoTranslate service to close an active translation session.
     */
    public function closeSession(string $sessionId): void;
}
