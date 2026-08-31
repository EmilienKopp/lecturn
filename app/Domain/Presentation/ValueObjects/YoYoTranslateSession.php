<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use DateTimeInterface;

readonly class YoYoTranslateSession
{
    public string $websocketUrl;

    public function __construct(
        public string $sessionId,
        public DateTimeInterface $startedAt,
        string $wsBaseUrl = 'wss://yoyotranslate.app/session',
    ) {
        $this->websocketUrl = rtrim($wsBaseUrl, '/') . '/' . $sessionId;
    }
}
