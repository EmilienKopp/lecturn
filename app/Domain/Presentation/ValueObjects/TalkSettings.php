<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use Splitstack\Typewriter\Attributes\TypeScript;

#[TypeScript]
readonly class TalkSettings
{
    public function __construct(
        public bool $showReactions = false,
        public bool $showDock = true,
        public bool $showTranslation = true,
        public string $timerMode = 'elapsed',
        public ?int $durationMinutes = null,
        public bool $autoSave = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            showReactions: (bool) ($data['showReactions'] ?? false),
            showDock: (bool) ($data['showDock'] ?? true),
            showTranslation: (bool) ($data['showTranslation'] ?? true),
            timerMode: in_array($data['timerMode'] ?? 'elapsed', ['elapsed', 'countdown'], true)
                ? (string) ($data['timerMode'] ?? 'elapsed')
                : 'elapsed',
            durationMinutes: isset($data['durationMinutes']) && is_numeric($data['durationMinutes'])
                ? (int) $data['durationMinutes']
                : null,
            autoSave: (bool) ($data['autoSave'] ?? false),
        );
    }

    public static function defaults(): self
    {
        return new self;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'showReactions' => $this->showReactions,
            'showDock' => $this->showDock,
            'showTranslation' => $this->showTranslation,
            'timerMode' => $this->timerMode,
            'durationMinutes' => $this->durationMinutes,
            'autoSave' => $this->autoSave,
        ];
    }
}
