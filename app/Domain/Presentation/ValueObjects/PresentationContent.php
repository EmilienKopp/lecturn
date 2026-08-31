<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidPresentationContent;

readonly class PresentationContent
{
    public const string VERSION = '1.0';

    /**
     * @param  list<Slide>  $slides
     */
    public function __construct(
        public string $version,
        public array $slides,
        public ?string $backgroundImage = null,
    ) {
        if ($this->version !== self::VERSION) {
            throw new InvalidPresentationContent("Unsupported content version \"{$this->version}\".");
        }

        foreach ($this->slides as $slide) {
            if (! $slide instanceof Slide) {
                throw new InvalidPresentationContent('Slides must be Slide value objects.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (! is_array($data['slides'] ?? null)) {
            throw new InvalidPresentationContent('Content requires a slides array.');
        }

        return new self(
            version: (string) ($data['version'] ?? ''),
            slides: array_map(
                static fn (mixed $slide): Slide => is_array($slide)
                    ? Slide::fromArray($slide)
                    : throw new InvalidPresentationContent('Malformed slide entry.'),
                array_values($data['slides']),
            ),
            backgroundImage: isset($data['backgroundImage']) ? (string) $data['backgroundImage'] : null,
        );
    }

    public static function empty(): self
    {
        return new self(
            version: self::VERSION,
            slides: [
                new Slide(
                    id: 'slide-1',
                    layout: SlideLayout::Free,
                    background: null,
                    slots: [],
                ),
            ],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'version' => $this->version,
            'slides' => array_map(
                static fn (Slide $slide): array => $slide->toArray(),
                $this->slides,
            ),
        ];

        if ($this->backgroundImage !== null) {
            $data['backgroundImage'] = $this->backgroundImage;
        }

        return $data;
    }
}
