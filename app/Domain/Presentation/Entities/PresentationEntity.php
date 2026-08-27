<?php

declare(strict_types=1);

namespace App\Domain\Presentation\Entities;

use App\Domain\BaseEntity;
use App\Domain\Presentation\Exceptions\InvalidPresentationContent;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use DateTimeInterface;

class PresentationEntity extends BaseEntity
{
    public function __construct(
        public int $team_id,
        public string $name,
        public PresentationContent $content,
        public ?int $id = null,
        public ?DateTimeInterface $created_at = null,
        public ?DateTimeInterface $updated_at = null,
        public ?string $yoyotranslateSessionId = null,
        public ?DateTimeInterface $yoyotranslateSessionStartedAt = null,
    ) {}

    public function rename(string $name): void
    {
        if (trim($name) === '' || mb_strlen($name) > 255) {
            throw new InvalidPresentationContent('Presentation name must be between 1 and 255 characters.');
        }

        $this->name = $name;
    }

    public function replaceContent(PresentationContent $content): void
    {
        $this->content = $content;
    }

    public function attachTranslationSession(string $sessionId, DateTimeInterface $startedAt): void
    {
        $this->yoyotranslateSessionId = $sessionId;
        $this->yoyotranslateSessionStartedAt = $startedAt;
    }

    public function detachTranslationSession(): void
    {
        $this->yoyotranslateSessionId = null;
        $this->yoyotranslateSessionStartedAt = null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'name' => $this->name,
            'content' => $this->content->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'yoyotranslate_session_id' => $this->yoyotranslateSessionId,
            'yoyotranslate_session_started_at' => $this->yoyotranslateSessionStartedAt,
        ];
    }
}
