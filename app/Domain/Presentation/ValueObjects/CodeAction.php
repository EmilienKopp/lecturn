<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidPresentationContent;

/**
 * One page of a code block's animated sequence: the code state the block
 * morphs to when its step plays, plus an optional set of lines to highlight
 * (Animotion selectLines syntax: "3", "3,5-8" or "*" for all lines). The
 * "when" lives in the flow graph as a code-action node referencing this
 * action by id; this object only owns the "what".
 */
readonly class CodeAction
{
    public const string HIGHLIGHT_LINES_PATTERN = '/^(\*|\d+(-\d+)?(,\d+(-\d+)?)*)$/';

    public function __construct(
        public string $id,
        public string $code,
        public ?string $highlightLines = null,
        public ?string $label = null,
    ) {
        if ($this->id === '') {
            throw new InvalidPresentationContent('Code action id cannot be empty.');
        }

        if ($this->highlightLines !== null
            && preg_match(self::HIGHLIGHT_LINES_PATTERN, $this->highlightLines) !== 1) {
            throw new InvalidPresentationContent(
                "Code action highlight lines \"{$this->highlightLines}\" must be line numbers/ranges (e.g. \"3,5-8\") or \"*\"."
            );
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            code: (string) ($data['code'] ?? ''),
            highlightLines: isset($data['highlightLines']) ? (string) $data['highlightLines'] : null,
            label: isset($data['label']) ? (string) $data['label'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'code' => $this->code,
        ];

        foreach (['highlightLines' => $this->highlightLines, 'label' => $this->label] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
