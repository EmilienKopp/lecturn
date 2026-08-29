<?php

namespace Database\Factories;

use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Models\PresentationModel;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PresentationModel>
 */
class PresentationModelFactory extends Factory
{
    protected $model = PresentationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->sentence(3),
            'content' => PresentationContent::empty()->toArray(),
        ];
    }

    public function withSlides(int $count): static
    {
        return $this->state(function () use ($count) {
            $slides = [];

            for ($i = 1; $i <= $count; $i++) {
                $slides[] = [
                    'id' => "slide-{$i}",
                    'layout' => 'free',
                    'background' => null,
                    'slots' => [
                        'main' => [
                            [
                                'id' => "block-{$i}-1",
                                'type' => 'text',
                                'content' => fake()->sentence(),
                                'style' => [
                                    'fontSize' => '2rem',
                                    'x' => '10',
                                    'y' => '20',
                                    'width' => '30',
                                ],
                                'transition' => null,
                            ],
                        ],
                    ],
                ];
            }

            return ['content' => ['version' => '1.0', 'slides' => $slides]];
        });
    }
}
