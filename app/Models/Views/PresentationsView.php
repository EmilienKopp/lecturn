<?php

namespace App\Models\Views;

use Illuminate\Support\Carbon;
use Splitstack\Rome\Models\ReadOnlyModel;

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
 */
class PresentationsView extends ReadOnlyModel
{
    protected $table = 'presentations_view';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'talk_settings' => 'array',
            'flow' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
