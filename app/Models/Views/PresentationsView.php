<?php

namespace App\Models\Views;

use Illuminate\Support\Carbon;
use Splitstack\Rome\Models\ReadOnlyModel;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property array<string, mixed> $content
 * @property string $embed_token
 * @property string|null $yoyotranslate_session_id
 * @property Carbon|null $yoyotranslate_session_started_at
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'yoyotranslate_session_started_at' => 'datetime',
        ];
    }
}
