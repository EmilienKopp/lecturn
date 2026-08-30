<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Presentation\Events\PresentationContentReplaced;
use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Infrastructure\ReadModels\PresentationReadModel;
use App\Presentation\EmbedCache;

class RefreshPresentationEmbed
{
    public function __construct(
        private readonly PresentationReadModel $presentations,
        private readonly EmbedCache $embeds,
    ) {}

    public function handle(PresentationContentReplaced $event): void
    {
        $data = $this->presentations->findForEmbed($event->presentation_id);

        $this->embeds->refresh(
            $data['embed_token'],
            PresentationContent::fromArray($data['content']),
            $data['flow'] !== null ? FlowGraph::fromArray($data['flow']) : null,
        );
    }
}
