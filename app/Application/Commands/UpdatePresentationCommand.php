<?php

declare(strict_types=1);

namespace App\Application\Commands;

use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Domain\Presentation\ValueObjects\TalkSettings;

readonly class UpdatePresentationCommand
{
    public function __construct(
        public int $presentation_id,
        public ?string $name = null,
        public ?PresentationContent $content = null,
        public ?TalkSettings $talkSettings = null,
        public ?FlowGraph $flow = null,
    ) {}
}
