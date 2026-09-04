<?php

namespace App\Models;

use App\Domain\Presentation\Entities\PresentationSessionEntity;
use Database\Factories\PresentationSessionModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $presentation_id
 * @property int $team_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $last_seen_at
 * @property array<string, int> $reaction_counts
 * @property int $reaction_total
 * @property array<string, int> $viewers
 * @property int $viewer_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PresentationModel $presentation
 */
#[Fillable(['presentation_id', 'team_id', 'started_at', 'ended_at', 'last_seen_at', 'reaction_counts', 'reaction_total', 'viewers', 'viewer_count'])]
class PresentationSessionModel extends Model
{
    /** @use HasFactory<PresentationSessionModelFactory> */
    use HasFactory;

    protected $table = 'presentation_sessions';

    /**
     * @return BelongsTo<PresentationModel, $this>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(PresentationModel::class, 'presentation_id');
    }

    public function toEntity(): PresentationSessionEntity
    {
        return new PresentationSessionEntity(
            presentation_id: $this->presentation_id,
            team_id: $this->team_id,
            started_at: $this->started_at->toDateTimeImmutable(),
            ended_at: $this->ended_at?->toDateTimeImmutable(),
            last_seen_at: $this->last_seen_at?->toDateTimeImmutable(),
            reaction_counts: $this->reaction_counts ?? [],
            reaction_total: $this->reaction_total,
            viewers: $this->viewers ?? [],
            viewer_count: $this->viewer_count,
            id: $this->id,
        );
    }

    protected static function newFactory(): PresentationSessionModelFactory
    {
        return PresentationSessionModelFactory::new();
    }

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
            'viewers' => 'array',
        ];
    }
}
