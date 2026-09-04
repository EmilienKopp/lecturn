<?php

namespace App\Models\Views;

use Illuminate\Support\Carbon;
use Splitstack\Rome\Models\ReadOnlyModel;

/**
 * @property int $id
 * @property int $presentation_id
 * @property int $team_id
 * @property string $presentation_name
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $last_seen_at
 * @property array<string, int> $reaction_counts
 * @property int $reaction_total
 * @property int $viewer_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SessionAnalyticsView extends ReadOnlyModel
{
    protected $table = 'session_analytics';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'reaction_counts' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
