<?php

declare(strict_types=1);

use App\Models\PresentationModel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array<string, mixed> */
function validFlowPayload(): array
{
    return [
        'version' => '1.0',
        'nodes' => [
            ['id' => 'n1', 'type' => 'slide', 'position' => ['x' => 0, 'y' => 0], 'data' => ['slideId' => 'slide-1']],
            ['id' => 'n2', 'type' => 'slide', 'position' => ['x' => 0, 'y' => 200], 'data' => ['slideId' => 'slide-2']],
            ['id' => 't1', 'type' => 'transition', 'position' => ['x' => 240, 'y' => 0], 'data' => ['label' => 'reveal bullets']],
        ],
        'edges' => [
            ['id' => 'e-nav', 'source' => 'n1', 'target' => 'n2', 'label' => null],
            ['id' => 'e-chain', 'source' => 'n1', 'target' => 't1', 'label' => null],
        ],
    ];
}

test('a valid flow persists and is returned to the editor', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(2)->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['flow' => validFlowPayload()]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    expect($presentation->refresh()->flow)->toBe(validFlowPayload());

    $this
        ->actingAs($user)
        ->get(route('presentations.edit', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('presentations/Editor')
            ->has('presentation.flow.nodes', 3)
            ->has('presentation.flow.edges', 2),
        );
});

test('a flow-only update leaves the content untouched', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(2)->create([
        'team_id' => $user->currentTeam->id,
    ]);
    $originalContent = $presentation->content;

    $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['flow' => validFlowPayload()])
        ->assertSessionDoesntHaveErrors();

    expect($presentation->refresh()->content)->toBe($originalContent);
});

test('a structurally invalid flow is rejected', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(2)->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $flow = validFlowPayload();
    // Second navigation edge from n1 violates the two-lane rule.
    $flow['edges'][] = ['id' => 'e-nav2', 'source' => 'n2', 'target' => 'n1', 'label' => null];
    $flow['edges'][] = ['id' => 'e-nav3', 'source' => 'n1', 'target' => 'n2', 'label' => null];

    $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['flow' => $flow])
        ->assertSessionHasErrors('flow');

    expect($presentation->refresh()->flow)->toBeNull();
});

test('a flow referencing a nonexistent slide is rejected', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(1)->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $flow = [
        'version' => '1.0',
        'nodes' => [
            ['id' => 'n1', 'type' => 'slide', 'position' => ['x' => 0, 'y' => 0], 'data' => ['slideId' => 'slide-ghost']],
        ],
        'edges' => [],
    ];

    $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['flow' => $flow])
        ->assertSessionHasErrors('flow');
});

test('the editor loads a presentation without a flow', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $this
        ->actingAs($user)
        ->get(route('presentations.edit', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('presentations/Editor')
            ->where('presentation.flow', null),
        );
});
