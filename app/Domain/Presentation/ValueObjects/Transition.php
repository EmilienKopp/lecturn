<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidPresentationContent;

readonly class Transition
{
    public function __construct(
        public int $order,
    ) {
        if ($this->order < 1) {
            throw new InvalidPresentationContent('Transition order must be 1 or greater.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (! isset($data['order']) || ! is_numeric($data['order'])) {
            throw new InvalidPresentationContent('Transition requires a numeric order.');
        }

        return new self(order: (int) $data['order']);
    }

    /** @return array{order: int} */
    public function toArray(): array
    {
        return ['order' => $this->order];
    }
}
