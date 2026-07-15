<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidPresentationContent;

readonly class Block
{
    public const array TYPES = ['text', 'code', 'image'];

    public function __construct(
        public string $id,
        public string $type,
        public string $content,
        public BlockStyle $style,
        public ?Transition $transition = null,
        public ?string $lang = null,
        public ?string $src = null,
        public ?string $alt = null,
    ) {
        if ($this->id === '') {
            throw new InvalidPresentationContent('Block id cannot be empty.');
        }

        if (! in_array($this->type, self::TYPES, true)) {
            throw new InvalidPresentationContent("Unknown block type \"{$this->type}\".");
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            content: (string) ($data['content'] ?? ''),
            style: BlockStyle::fromArray(is_array($data['style'] ?? null) ? $data['style'] : []),
            transition: is_array($data['transition'] ?? null) ? Transition::fromArray($data['transition']) : null,
            lang: isset($data['lang']) ? (string) $data['lang'] : null,
            src: isset($data['src']) ? (string) $data['src'] : null,
            alt: isset($data['alt']) ? (string) $data['alt'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'content' => $this->content,
            'style' => $this->style->toArray(),
            'transition' => $this->transition?->toArray(),
        ];

        foreach (['lang' => $this->lang, 'src' => $this->src, 'alt' => $this->alt] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
