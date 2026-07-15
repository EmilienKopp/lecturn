<?php

namespace App\Models;

use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Policies\PresentationPolicy;
use Database\Factories\PresentationModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property array<string, mixed> $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'content'])]
#[UsePolicy(PresentationPolicy::class)]
class PresentationModel extends Model
{
    /** @use HasFactory<PresentationModelFactory> */
    use HasFactory;

    protected $table = 'presentations';

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function toEntity(): PresentationEntity
    {
        return new PresentationEntity(
            id: $this->id,
            team_id: $this->team_id,
            name: $this->name,
            content: PresentationContent::fromArray($this->content),
            created_at: $this->created_at?->toDateTimeImmutable(),
            updated_at: $this->updated_at?->toDateTimeImmutable(),
        );
    }

    protected static function newFactory(): PresentationModelFactory
    {
        return PresentationModelFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
