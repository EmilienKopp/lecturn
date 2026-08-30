<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidFlowGraph;

readonly class FlowEdge
{
    public function __construct(
        public string $id,
        public string $source,
        public string $target,
        public ?string $label = null,
    ) {
        if ($this->id === '') {
            throw new InvalidFlowGraph('Flow edge id cannot be empty.');
        }

        if ($this->source === '' || $this->target === '') {
            throw new InvalidFlowGraph("Flow edge \"{$this->id}\" requires a source and a target.");
        }

        if ($this->source === $this->target) {
            throw new InvalidFlowGraph("Flow edge \"{$this->id}\" cannot connect a node to itself.");
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            source: (string) ($data['source'] ?? ''),
            target: (string) ($data['target'] ?? ''),
            label: isset($data['label']) ? (string) $data['label'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'target' => $this->target,
            'label' => $this->label,
        ];
    }
}
