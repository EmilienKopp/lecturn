<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidPresentationContent;

readonly class Slide
{
    /**
     * @param  array<string, list<Block>>  $slots
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(
        public string $id,
        public SlideLayout $layout,
        public ?string $background,
        public array $slots,
        public ?array $config = null,
    ) {
        if ($this->id === '') {
            throw new InvalidPresentationContent('Slide id cannot be empty.');
        }

        // Freeform layouts use a single 'main' slot with free-form block placement.
        if (! $this->layout->usesFreeformSlots()) {
            $allowedSlots = $this->layout->slots();

            foreach (array_keys($this->slots) as $slotName) {
                if (! in_array($slotName, $allowedSlots, true)) {
                    throw new InvalidPresentationContent(
                        "Slot \"{$slotName}\" is not defined by layout \"{$this->layout->value}\"."
                    );
                }
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $layout = SlideLayout::tryFrom((string) ($data['layout'] ?? ''))
            ?? throw new InvalidPresentationContent('Unknown slide layout "'.(string) ($data['layout'] ?? '').'".');

        $slots = [];

        foreach (is_array($data['slots'] ?? null) ? $data['slots'] : [] as $slotName => $blocks) {
            if (! is_array($blocks)) {
                throw new InvalidPresentationContent("Slot \"{$slotName}\" must be an array of blocks.");
            }

            $slots[(string) $slotName] = array_map(
                static fn (mixed $block): Block => is_array($block)
                    ? Block::fromArray($block)
                    : throw new InvalidPresentationContent("Slot \"{$slotName}\" contains a malformed block."),
                array_values($blocks),
            );
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            layout: $layout,
            background: isset($data['background']) ? (string) $data['background'] : null,
            slots: $slots,
            config: is_array($data['config'] ?? null) ? $data['config'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'layout' => $this->layout->value,
            'background' => $this->background,
            'slots' => array_map(
                static fn (array $blocks): array => array_map(
                    static fn (Block $block): array => $block->toArray(),
                    $blocks,
                ),
                $this->slots,
            ),
        ];

        if ($this->config !== null) {
            $data['config'] = $this->config;
        }

        return $data;
    }
}
