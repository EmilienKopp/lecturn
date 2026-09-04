<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use Splitstack\Typewriter\Attributes\TypeScript;

#[TypeScript]
readonly class FooterSettings
{
    public function __construct(
        public bool $enabled = false,
        public ?string $xHandle = null,
        public ?string $githubHandle = null,
        public ?string $hashtag = null,
        public string $bgColor = 'transparent',
        public string $fontColor = '#ffffff',
        public bool $showInDock = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            xHandle: self::normalizeHandle($data['xHandle'] ?? null),
            githubHandle: self::normalizeHandle($data['githubHandle'] ?? null),
            hashtag: self::normalizeHandle($data['hashtag'] ?? null),
            bgColor: self::normalizeColor($data['bgColor'] ?? null, 'transparent'),
            fontColor: self::normalizeColor($data['fontColor'] ?? null, '#ffffff'),
            showInDock: (bool) ($data['showInDock'] ?? false),
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
            'enabled' => $this->enabled,
            'xHandle' => $this->xHandle,
            'githubHandle' => $this->githubHandle,
            'hashtag' => $this->hashtag,
            'bgColor' => $this->bgColor,
            'fontColor' => $this->fontColor,
            'showInDock' => $this->showInDock,
        ];
    }

    /**
     * Trim and strip a leading @ or # from a handle/hashtag; blanks become null.
     */
    private static function normalizeHandle(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $handle = ltrim(trim($value), '@#');

        return $handle === '' ? null : $handle;
    }

    private static function normalizeColor(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $color = trim($value);

        return $color === '' ? $default : $color;
    }
}
