<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

readonly class BlockStyle
{
    public function __construct(
        public ?string $fontSize = null,
        public ?string $fontWeight = null,
        public ?string $color = null,
        public ?string $borderColor = null,
        public ?string $backgroundColor = null,
        public ?string $gridColumn = null,
        public ?string $gridRow = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            fontSize: isset($data['fontSize']) ? (string) $data['fontSize'] : null,
            fontWeight: isset($data['fontWeight']) ? (string) $data['fontWeight'] : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
            borderColor: isset($data['borderColor']) ? (string) $data['borderColor'] : null,
            backgroundColor: isset($data['backgroundColor']) ? (string) $data['backgroundColor'] : null,
            gridColumn: isset($data['gridColumn']) ? (string) $data['gridColumn'] : null,
            gridRow: isset($data['gridRow']) ? (string) $data['gridRow'] : null,
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'fontSize' => $this->fontSize,
            'fontWeight' => $this->fontWeight,
            'color' => $this->color,
            'borderColor' => $this->borderColor,
            'backgroundColor' => $this->backgroundColor,
            'gridColumn' => $this->gridColumn,
            'gridRow' => $this->gridRow,
        ], static fn (?string $value): bool => $value !== null);
    }
}
