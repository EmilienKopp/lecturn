<?php

declare(strict_types=1);

use App\Application\Actions\Presentations\CreatePresentation;
use App\Application\Actions\Presentations\DeletePresentation;
use App\Application\Actions\Presentations\UpdatePresentation;
use App\Application\Commands\CreatePresentationCommand;
use App\Application\Commands\DeletePresentationCommand;
use App\Application\Commands\UpdatePresentationCommand;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Models\PresentationModel;
use App\Models\Team;

it('creates a presentation with empty content', function () {
    $team = Team::factory()->create();

    $entity = app(CreatePresentation::class)->execute(
        new CreatePresentationCommand(team_id: $team->id, name: 'Launch deck'),
    );

    expect($entity->id)->not->toBeNull()
        ->and($entity->name)->toBe('Launch deck')
        ->and($entity->content->slides)->toHaveCount(1);

    $this->assertDatabaseHas('presentations', [
        'id' => $entity->id,
        'team_id' => $team->id,
        'name' => 'Launch deck',
    ]);
});

it('renames a presentation', function () {
    $presentation = PresentationModel::factory()->create();

    $entity = app(UpdatePresentation::class)->execute(
        new UpdatePresentationCommand(presentation_id: $presentation->id, name: 'Renamed'),
    );

    expect($entity->name)->toBe('Renamed');
    $this->assertDatabaseHas('presentations', ['id' => $presentation->id, 'name' => 'Renamed']);
});

it('replaces presentation content', function () {
    $presentation = PresentationModel::factory()->create();

    $newContent = PresentationContent::fromArray([
        'version' => '1.0',
        'slides' => [
            [
                'id' => 'slide-1',
                'layout' => 'left-right',
                'background' => '#000000',
                'slots' => [
                    'left' => [
                        [
                            'id' => 'block-1',
                            'type' => 'text',
                            'content' => 'Updated',
                            'style' => [],
                            'transition' => null,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    app(UpdatePresentation::class)->execute(
        new UpdatePresentationCommand(presentation_id: $presentation->id, content: $newContent),
    );

    $stored = PresentationModel::findOrFail($presentation->id);

    expect($stored->content['slides'][0]['layout'])->toBe('left-right')
        ->and($stored->content['slides'][0]['slots']['left'][0]['content'])->toBe('Updated');
});

it('deletes a presentation', function () {
    $presentation = PresentationModel::factory()->create();

    app(DeletePresentation::class)->execute(
        new DeletePresentationCommand(presentation_id: $presentation->id),
    );

    $this->assertDatabaseMissing('presentations', ['id' => $presentation->id]);
});

it('hydrates factory content with slides state', function () {
    $presentation = PresentationModel::factory()->withSlides(3)->create();

    expect($presentation->toEntity()->content->slides)->toHaveCount(3);
});
