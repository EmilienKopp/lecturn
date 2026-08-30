<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

readonly class NodePosition
{
    public function __construct(
        public float $x,
        public float $y,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            x: (float) ($data['x'] ?? 0),
            y: (float) ($data['y'] ?? 0),
        );
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
        ];
    }
}
