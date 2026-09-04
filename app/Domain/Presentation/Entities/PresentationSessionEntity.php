<?php

declare(strict_types=1);

namespace App\Domain\Presentation\Entities;

use App\Domain\BaseEntity;
use DateTimeInterface;

class PresentationSessionEntity extends BaseEntity
{
    /**
     * A viewer is counted as present when their last heartbeat is within this
     * many seconds. Viewers heartbeat roughly every 12s from the client.
     */
    public const int PRESENCE_WINDOW_SECONDS = 25;

    /**
     * @param  array<string, int>  $reaction_counts  emoji => tally
     * @param  array<string, int>  $viewers  viewerId => last-seen unix timestamp
     */
    public function __construct(
        public int $presentation_id,
        public int $team_id,
        public DateTimeInterface $started_at,
        public ?DateTimeInterface $ended_at = null,
        public ?DateTimeInterface $last_seen_at = null,
        public array $reaction_counts = [],
        public int $reaction_total = 0,
        public array $viewers = [],
        public int $viewer_count = 0,
        public ?int $id = null,
    ) {}

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Folds a batch of client-side reaction tallies into the running totals.
     *
     * @param  array<string, int>  $counts  emoji => count since the last flush
     */
    public function recordReactions(array $counts, DateTimeInterface $at): void
    {
        foreach ($counts as $emoji => $count) {
            if ($count <= 0) {
                continue;
            }

            $this->reaction_counts[$emoji] = ($this->reaction_counts[$emoji] ?? 0) + $count;
            $this->reaction_total += $count;
        }

        $this->last_seen_at = $at;
    }

    /**
     * Records a viewer heartbeat. New viewer ids grow the unique count; repeat
     * ids only refresh presence.
     */
    public function touchViewer(string $viewerId, DateTimeInterface $at): void
    {
        if (! array_key_exists($viewerId, $this->viewers)) {
            $this->viewer_count++;
        }

        $this->viewers[$viewerId] = $at->getTimestamp();
        $this->last_seen_at = $at;
    }

    /**
     * Marks a viewer as gone without dropping them from the unique tally, so
     * total-viewers analytics stay accurate.
     */
    public function markViewerLeft(string $viewerId): void
    {
        if (array_key_exists($viewerId, $this->viewers)) {
            $this->viewers[$viewerId] = 0;
        }
    }

    /** Viewers seen within the presence window — the live "watching now" count. */
    public function activeViewerCount(DateTimeInterface $now): int
    {
        $cutoff = $now->getTimestamp() - self::PRESENCE_WINDOW_SECONDS;

        return count(array_filter(
            $this->viewers,
            static fn (int $lastSeen): bool => $lastSeen >= $cutoff,
        ));
    }

    public function end(DateTimeInterface $at): void
    {
        $this->ended_at = $at;
        $this->last_seen_at = $at;
    }

    /** The single most-used emoji this session, or null when there were none. */
    public function topEmoji(): ?string
    {
        if ($this->reaction_counts === []) {
            return null;
        }

        return array_key_first(
            array_filter(
                $this->reaction_counts,
                fn (int $count): bool => $count === max($this->reaction_counts),
            )
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'presentation_id' => $this->presentation_id,
            'team_id' => $this->team_id,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'last_seen_at' => $this->last_seen_at,
            'reaction_counts' => $this->reaction_counts,
            'reaction_total' => $this->reaction_total,
            'viewers' => $this->viewers,
            'viewer_count' => $this->viewer_count,
        ];
    }
}
