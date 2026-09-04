<?php

declare(strict_types=1);

namespace App\Application\Sequences\Presentations\Steps;

use App\Application\Sequences\Presentations\ImportPresentationPayload;
use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Domain\Presentation\ValueObjects\TalkSettings;
use Splitstack\Conveyor\Concerns\IsSteppable;
use Splitstack\Conveyor\Contracts\CompensatesData;
use Splitstack\Conveyor\Contracts\Steppable;

/**
 * Persists the imported deck. The sequence runs without a transaction (image
 * downloads are slow and must not hold DB locks), so this committed row is
 * reversed by hand through compensateData() if a later step fails.
 */
class CreatePresentationStep implements CompensatesData, Steppable
{
    use IsSteppable;

    private ?int $createdPresentationId = null;

    public function __construct(private readonly PresentationRepository $presentations) {}

    public function handle(ImportPresentationPayload $payload): void
    {
        $presentation = new PresentationEntity(
            team_id: $payload->team_id,
            name: $payload->name,
            content: PresentationContent::fromArray($payload->content),
            talkSettings: TalkSettings::fromArray($payload->talkSettings),
        );

        if ($payload->flow !== null) {
            $presentation->replaceFlow(FlowGraph::fromArray($payload->flow));
        }

        $saved = $this->presentations->save($presentation);

        $this->createdPresentationId = $saved->id;
        $payload->set('presentation', $saved);
    }

    public function compensateData(): void
    {
        if ($this->createdPresentationId === null) {
            return;
        }

        // Drop any images already re-hosted, then the row itself.
        $this->presentations->clearImages($this->createdPresentationId);
        $this->presentations->delete($this->createdPresentationId);
    }
}
