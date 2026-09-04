<?php

namespace Database\Factories;

use App\Models\PresentationModel;
use App\Models\PresentationSessionModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PresentationSessionModel>
 */
class PresentationSessionModelFactory extends Factory
{
    protected $model = PresentationSessionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presentation_id' => PresentationModel::factory(),
            'team_id' => fn (array $attributes) => PresentationModel::findOrFail($attributes['presentation_id'])->team_id,
            'started_at' => Carbon::now()->subMinutes(30),
            'ended_at' => null,
            'last_seen_at' => Carbon::now()->subMinutes(30),
            'reaction_counts' => [],
            'reaction_total' => 0,
            'viewers' => [],
            'viewer_count' => 0,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'ended_at' => Carbon::now()->subMinutes(5),
            'last_seen_at' => Carbon::now()->subMinutes(5),
        ]);
    }

    /**
     * @param  array<string, int>  $counts
     */
    public function withReactions(array $counts): static
    {
        return $this->state(fn () => [
            'reaction_counts' => $counts,
            'reaction_total' => array_sum($counts),
        ]);
    }
}
