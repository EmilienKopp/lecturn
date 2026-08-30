<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidFlowGraph;

readonly class FlowNode
{
    /**
     * @param  array{slideId?: string, label?: string|null, blockId?: string, actionId?: string}  $data  Slide nodes
     *                                                                                                   carry `slideId`; transition nodes carry an optional `label` and,
     *                                                                                                   once assigned, the `slideId` of the slide that owns the step.
     *                                                                                                   Code-action nodes reference the code block and action they order
     *                                                                                                   (`blockId` + `actionId`), plus an optional `label` and `slideId`.
     */
    public function __construct(
        public string $id,
        public FlowNodeType $type,
        public NodePosition $position,
        public array $data,
    ) {
        if ($this->id === '') {
            throw new InvalidFlowGraph('Flow node id cannot be empty.');
        }

        if ($this->type === FlowNodeType::Slide) {
            $slideId = $this->data['slideId'] ?? null;

            if (! is_string($slideId) || $slideId === '') {
                throw new InvalidFlowGraph("Slide node \"{$this->id}\" requires a non-empty slideId.");
            }
        }

        if ($this->type === FlowNodeType::Transition || $this->type === FlowNodeType::CodeAction) {
            $label = $this->data['label'] ?? null;

            if ($label !== null && ! is_string($label)) {
                throw new InvalidFlowGraph("Node \"{$this->id}\" label must be a string or null.");
            }

            // A step node belongs to a slide through a stable slideId, not
            // through edges — so a step keeps its identity when unwired.
            $slideId = $this->data['slideId'] ?? null;

            if ($slideId !== null && (! is_string($slideId) || $slideId === '')) {
                throw new InvalidFlowGraph("Node \"{$this->id}\" slideId must be a non-empty string.");
            }
        }

        if ($this->type === FlowNodeType::CodeAction) {
            foreach (['blockId', 'actionId'] as $key) {
                $value = $this->data[$key] ?? null;

                if (! is_string($value) || $value === '') {
                    throw new InvalidFlowGraph("Code-action node \"{$this->id}\" requires a non-empty {$key}.");
                }
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = FlowNodeType::tryFrom((string) ($data['type'] ?? ''))
            ?? throw new InvalidFlowGraph('Unknown flow node type "'.(string) ($data['type'] ?? '').'".');

        return new self(
            id: (string) ($data['id'] ?? ''),
            type: $type,
            position: NodePosition::fromArray(is_array($data['position'] ?? null) ? $data['position'] : []),
            data: is_array($data['data'] ?? null) ? $data['data'] : [],
        );
    }

    public function slideId(): ?string
    {
        $slideId = $this->data['slideId'] ?? null;

        return is_string($slideId) ? $slideId : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'position' => $this->position->toArray(),
            'data' => $this->data,
        ];
    }
}
