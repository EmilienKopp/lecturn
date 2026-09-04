<?php

declare(strict_types=1);

namespace App\Application\Sequences\Presentations;

use Splitstack\Conveyor\Contracts\SequencePayload;

/**
 * Travels through the import sequence. Constructor data is the decoded JSON
 * envelope; steps add `presentation` and `unresolvedImages` to the bag.
 */
class ImportPresentationPayload extends SequencePayload
{
    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $talkSettings
     * @param  array<string, mixed>|null  $flow
     */
    public function __construct(
        public readonly int $team_id,
        public readonly string $name,
        public readonly array $content,
        public readonly array $talkSettings = [],
        public readonly ?array $flow = null,
    ) {}
}
