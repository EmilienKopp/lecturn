<?php

namespace App\Models;

use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Domain\Presentation\ValueObjects\TalkSettings;
use App\Policies\PresentationPolicy;
use Database\Factories\PresentationModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property array<string, mixed> $content
 * @property array<string, mixed>|null $talk_settings
 * @property array<string, mixed>|null $flow
 * @property string $embed_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'content', 'talk_settings', 'flow'])]
#[UsePolicy(PresentationPolicy::class)]
class PresentationModel extends Model implements HasMedia
{
    /** @use HasFactory<PresentationModelFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const string BACKGROUND_COLLECTION = 'background';

    public const string IMAGES_COLLECTION = 'images';

    protected $table = 'presentations';

    public function registerMediaCollections(): void
    {
        $imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        $this->addMediaCollection(self::BACKGROUND_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes($imageMimes);

        $this->addMediaCollection(self::IMAGES_COLLECTION)
            ->acceptsMimeTypes($imageMimes);
    }

    /** Public URL of the deck-wide background image, or null when unset. */
    public function backgroundImageUrl(): ?string
    {
        $media = $this->getFirstMedia(self::BACKGROUND_COLLECTION);

        return $media instanceof Media ? $media->getFullUrl() : null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $presentation) {
            $presentation->embed_token ??= Str::random(32);
        });
    }

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
            talkSettings: TalkSettings::fromArray($this->talk_settings ?? []),
            flow: $this->flow !== null ? FlowGraph::fromArray($this->flow) : null,
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
            'talk_settings' => 'array',
            'flow' => 'array',
        ];
    }
}
