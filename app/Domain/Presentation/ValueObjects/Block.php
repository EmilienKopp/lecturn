<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidPresentationContent;

readonly class Block
{
    public const array TYPES = ['text', 'code', 'image', 'box', 'richtext'];

    /** @param list<CodeAction> $actions */
    public function __construct(
        public string $id,
        public string $type,
        public string $content,
        public BlockStyle $style,
        public ?Transition $transition = null,
        public ?string $lang = null,
        public ?string $src = null,
        public ?string $alt = null,
        public array $actions = [],
    ) {
        if ($this->id === '') {
            throw new InvalidPresentationContent('Block id cannot be empty.');
        }

        if (! in_array($this->type, self::TYPES, true)) {
            throw new InvalidPresentationContent("Unknown block type \"{$this->type}\".");
        }

        if ($this->actions !== [] && $this->type !== 'code') {
            throw new InvalidPresentationContent("Block \"{$this->id}\" has code actions but is not a code block.");
        }

        $actionIds = [];

        foreach ($this->actions as $action) {
            if (! $action instanceof CodeAction) {
                throw new InvalidPresentationContent('Block actions must be CodeAction value objects.');
            }

            if (isset($actionIds[$action->id])) {
                throw new InvalidPresentationContent("Duplicate code action id \"{$action->id}\" on block \"{$this->id}\".");
            }

            $actionIds[$action->id] = true;
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
            actions: array_map(
                static fn (mixed $action): CodeAction => is_array($action)
                    ? CodeAction::fromArray($action)
                    : throw new InvalidPresentationContent('Malformed code action entry.'),
                is_array($data['actions'] ?? null) ? array_values($data['actions']) : [],
            ),
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

        if ($this->actions !== []) {
            $data['actions'] = array_map(
                static fn (CodeAction $action): array => $action->toArray(),
                $this->actions,
            );
        }

        return $data;
    }
}
