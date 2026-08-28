<?php

declare(strict_types=1);

use App\Models\PresentationModel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();

    $response = $this->get(route('presentations.index', ['current_team' => $user->currentTeam->slug]));

    $response->assertRedirect(route('login'));
});

test('the presentations index lists only the current team presentations', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    PresentationModel::factory()->count(2)->create(['team_id' => $team->id]);
    PresentationModel::factory()->create(); // other team

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.index', ['current_team' => $team->slug]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('presentations/Index')
        ->has('presentations', 2),
    );
});

test('a presentation can be created and redirects to the editor', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.store', ['current_team' => $team->slug]), [
            'name' => 'Launch deck',
        ]);

    $presentation = PresentationModel::query()->firstOrFail();

    $response->assertRedirect(route('presentations.edit', [
        'current_team' => $team->slug,
        'presentation' => $presentation->id,
    ]));

    expect($presentation->team_id)->toBe($team->id)
        ->and($presentation->content['slides'])->toHaveCount(1);
});

test('the editor page renders with the presentation content', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(2)->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.edit', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('presentations/Editor')
        ->where('presentation.id', $presentation->id)
        ->where('presentation.name', $presentation->name)
        ->has('presentation.content.slides', 2)
        ->where('presentation.talk_settings.showReactions', false)
        ->where('presentation.talk_settings.showDock', true)
        ->where('presentation.talk_settings.timerMode', 'elapsed')
        ->where('embed.url', route('presentations.embed', ['presentation' => $presentation->embed_token]))
        ->where('embed.tag', 'lecturn-deck-'.strtolower(substr($presentation->embed_token, 0, 8))),
    );
});

test('a presentation can be renamed', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['name' => 'Renamed deck']);

    $response->assertRedirect();
    $this->assertDatabaseHas('presentations', ['id' => $presentation->id, 'name' => 'Renamed deck']);
});

test('presentation content can be saved', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $content = [
        'version' => '1.0',
        'slides' => [
            [
                'id' => 'slide-1',
                'layout' => 'left-right',
                'background' => '#0f0f0f',
                'slots' => [
                    'left' => [
                        [
                            'id' => 'block-1',
                            'type' => 'text',
                            'content' => 'Hello world',
                            'style' => ['fontSize' => '2.5rem'],
                            'transition' => null,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['content' => $content]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $stored = PresentationModel::findOrFail($presentation->id);
    expect($stored->content['slides'][0]['slots']['left'][0]['content'])->toBe('Hello world');
});

test('invalid content is rejected with a validation error', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), [
            'content' => [
                'version' => '1.0',
                'slides' => [
                    [
                        'id' => 'slide-1',
                        'layout' => 'full',
                        'background' => null,
                        // "left" is not a slot of the "full" layout
                        'slots' => ['left' => []],
                    ],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('content');
});

test('an unknown layout is rejected by request validation', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), [
            'content' => [
                'version' => '1.0',
                'slides' => [
                    ['id' => 'slide-1', 'layout' => 'diagonal', 'background' => null, 'slots' => []],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('content.slides.0.layout');
});

test('the present page renders with the presentation content, talk settings and viewer url', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(2)->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.present', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('presentations/Present')
        ->where('presentation.id', $presentation->id)
        ->has('presentation.content.slides', 2)
        ->where('presentation.talk_settings.showReactions', false)
        ->where('presentation.talk_settings.showDock', true)
        ->where('presentation.talk_settings.timerMode', 'elapsed')
        ->where('presentation.talk_settings.durationMinutes', null)
        ->has('presentation.flow')
        ->has('viewerUrl'),
    );
});

test('the present page of another team presentation is not reachable', function () {
    $user = User::factory()->create();
    $otherTeamPresentation = PresentationModel::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.present', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $otherTeamPresentation->id,
        ]));

    $response->assertNotFound();
});

test('presentations of other teams cannot be reached through the current team', function () {
    $user = User::factory()->create();
    $otherTeamPresentation = PresentationModel::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.edit', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $otherTeamPresentation->id,
        ]));

    $response->assertNotFound();
});

test('a presentation can be deleted', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('presentations.destroy', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]));

    $response->assertRedirect(route('presentations.index', [
        'current_team' => $user->currentTeam->slug,
    ]));

    $this->assertDatabaseMissing('presentations', ['id' => $presentation->id]);
});
