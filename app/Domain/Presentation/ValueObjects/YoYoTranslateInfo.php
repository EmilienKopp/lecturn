<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

/**
 * Read-side shape of the YoYoTranslate session state sent to the presenter
 * page. Property names are snake_case to match the Inertia payload built by
 * PresentationReadModel::findForPresent().
 */
readonly class YoYoTranslateInfo
{
    public function __construct(
        public ?string $session_id,
        public ?string $websocket_url,
        public bool $active,
        public ?string $started_at,
    ) {}
}
