<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidPresentationContent;

/**
 * Pins a block to a transition node in the flow graph: the block reveals at
 * that node's step in its slide's chain. The legacy shape ({order: int},
 * pre-flow-graph) is still parseable so stored content keeps validating; the
 * editor lazily migrates it to nodeId pins on load.
 */
readonly class Transition
{
    public function __construct(
        public ?string $nodeId = null,
        public ?int $order = null,
    ) {
        if ($this->nodeId === null && $this->order === null) {
            throw new InvalidPresentationContent('Transition requires a nodeId or a legacy order.');
        }

        if ($this->nodeId !== null && $this->nodeId === '') {
            throw new InvalidPresentationContent('Transition nodeId cannot be empty.');
        }

        if ($this->order !== null && $this->order < 1) {
            throw new InvalidPresentationContent('Transition order must be 1 or greater.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $nodeId = $data['nodeId'] ?? null;

        if ($nodeId !== null && ! is_string($nodeId)) {
            throw new InvalidPresentationContent('Transition nodeId must be a string.');
        }

        if ($nodeId === null && isset($data['order']) && ! is_numeric($data['order'])) {
            throw new InvalidPresentationContent('Transition requires a numeric order.');
        }

        return new self(
            nodeId: $nodeId,
            order: isset($data['order']) ? (int) $data['order'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->nodeId !== null) {
            return ['nodeId' => $this->nodeId];
        }

        return ['order' => $this->order];
    }
}
