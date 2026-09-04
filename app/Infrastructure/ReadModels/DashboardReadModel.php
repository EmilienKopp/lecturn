<?php

declare(strict_types=1);

namespace App\Infrastructure\ReadModels;

use App\Models\Views\SessionAnalyticsView;

class DashboardReadModel
{
    /**
     * Recent talks given by a team, most recent first, shaped for the
     * post-talk analytics feed.
     *
     * @return array<int, array{
     *     id: int,
     *     presentation_id: int,
     *     presentation_name: string,
     *     started_at: string,
     *     ended_at: string|null,
     *     duration_seconds: int,
     *     is_live: bool,
     *     viewer_count: int,
     *     reaction_total: int,
     *     reaction_counts: array<string, int>,
     *     top_emoji: string|null
     * }>
     */
    public function recentSessionsForTeam(int $teamId, int $limit = 8): array
    {
        return SessionAnalyticsView::query()
            ->where('team_id', $teamId)
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (SessionAnalyticsView $session): array => [
                'id' => $session->id,
                'presentation_id' => $session->presentation_id,
                'presentation_name' => $session->presentation_name,
                'started_at' => $session->started_at->toISOString(),
                'ended_at' => $session->ended_at?->toISOString(),
                'duration_seconds' => $this->durationSeconds($session),
                'is_live' => $session->ended_at === null,
                'viewer_count' => $session->viewer_count,
                'reaction_total' => $session->reaction_total,
                'reaction_counts' => $session->reaction_counts ?? [],
                'top_emoji' => $this->topEmoji($session->reaction_counts ?? []),
            ])
            ->all();
    }

    /**
     * Lifetime engagement roll-up across all of a team's sessions.
     *
     * @return array{
     *     total_sessions: int,
     *     total_reactions: int,
     *     total_viewers: int,
     *     avg_reactions_per_session: int,
     *     top_emoji: string|null
     * }
     */
    public function teamEngagementSummary(int $teamId): array
    {
        $sessions = SessionAnalyticsView::query()
            ->where('team_id', $teamId)
            ->get();

        $totalSessions = $sessions->count();
        $totalReactions = (int) $sessions->sum('reaction_total');
        $totalViewers = (int) $sessions->sum('viewer_count');

        $combinedCounts = [];

        foreach ($sessions as $session) {
            foreach ($session->reaction_counts ?? [] as $emoji => $count) {
                $combinedCounts[$emoji] = ($combinedCounts[$emoji] ?? 0) + $count;
            }
        }

        return [
            'total_sessions' => $totalSessions,
            'total_reactions' => $totalReactions,
            'total_viewers' => $totalViewers,
            'avg_reactions_per_session' => $totalSessions > 0
                ? (int) round($totalReactions / $totalSessions)
                : 0,
            'top_emoji' => $this->topEmoji($combinedCounts),
        ];
    }

    private function durationSeconds(SessionAnalyticsView $session): int
    {
        $end = $session->ended_at ?? $session->last_seen_at ?? $session->started_at;

        return max(0, $end->getTimestamp() - $session->started_at->getTimestamp());
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function topEmoji(array $counts): ?string
    {
        if ($counts === []) {
            return null;
        }

        return array_key_first(
            array_filter($counts, fn (int $count): bool => $count === max($counts))
        );
    }
}
