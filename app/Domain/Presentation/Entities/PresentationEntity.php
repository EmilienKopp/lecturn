<?php

declare(strict_types=1);

namespace App\Domain\Presentation\Entities;

use App\Domain\BaseEntity;
use App\Domain\Presentation\Exceptions\InvalidFlowGraph;
use App\Domain\Presentation\Exceptions\InvalidPresentationContent;
use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Domain\Presentation\ValueObjects\Slide;
use App\Domain\Presentation\ValueObjects\TalkSettings;
use DateTimeInterface;

class PresentationEntity extends BaseEntity
{
    public function __construct(
        public int $team_id,
        public string $name,
        public PresentationContent $content,
        public TalkSettings $talkSettings = new TalkSettings,
        public ?FlowGraph $flow = null,
        public ?int $id = null,
        public ?DateTimeInterface $created_at = null,
        public ?DateTimeInterface $updated_at = null,
    ) {}

    public function rename(string $name): void
    {
        if (trim($name) === '' || mb_strlen($name) > 255) {
            throw new InvalidPresentationContent('Presentation name must be between 1 and 255 characters.');
        }

        $this->name = $name;
    }

    public function replaceContent(PresentationContent $content): void
    {
        $this->content = $content;
    }

    public function changeTalkSettings(TalkSettings $talkSettings): void
    {
        $this->talkSettings = $talkSettings;
    }

    /**
     * Cross-aggregate invariants live here — the flow VO cannot see the
     * slides, so slide references are validated against the current content.
     */
    public function replaceFlow(FlowGraph $flow): void
    {
        $slideIds = array_map(
            static fn (Slide $slide): string => $slide->id,
            $this->content->slides,
        );

        $seen = [];

        foreach ($flow->referencedSlideIds() as $slideId) {
            if (! in_array($slideId, $slideIds, true)) {
                throw new InvalidFlowGraph("Flow references unknown slide \"{$slideId}\".");
            }

            if (isset($seen[$slideId])) {
                throw new InvalidFlowGraph("Slide \"{$slideId}\" is referenced by more than one flow node.");
            }

            $seen[$slideId] = true;
        }

        $this->flow = $flow;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'name' => $this->name,
            'content' => $this->content->toArray(),
            'talk_settings' => $this->talkSettings->toArray(),
            'flow' => $this->flow?->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
