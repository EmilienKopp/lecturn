<?php

declare(strict_types=1);

use App\Models\PresentationModel;
use App\Models\User;

test('a presentation can be exported as svelte source', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(2)->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Launch Deck',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.export', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
            'format' => 'svelte',
        ]));

    $response->assertOk();
    $response->assertDownload('launch-deck.svelte');

    expect($response->streamedContent())
        ->toContain('<Presentation>')
        ->toContain("from '@animotion/core'")
        ->toContain('color: #1a1a1a');
});

test('a free-layout slide exports absolutely positioned blocks', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Free Deck',
        'content' => [
            'version' => '1.0',
            'slides' => [
                [
                    'id' => 'slide-1',
                    'layout' => 'free',
                    'background' => '#ffffff',
                    'slots' => [
                        'main' => [
                            [
                                'id' => 'block-1',
                                'type' => 'text',
                                'content' => 'Floating',
                                'style' => ['x' => '12.5', 'y' => '20', 'width' => '30'],
                                'transition' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.export', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
            'format' => 'svelte',
        ]));

    $response->assertOk();

    expect($response->streamedContent())
        ->toContain('.layout-free')
        ->toContain('class="free-block"')
        ->toContain('left: 12.5%')
        ->toContain('top: 20%');
});

test('a presentation can be exported as a web component', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(1)->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Launch Deck',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.export', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
            'format' => 'web-component',
        ]));

    $response->assertOk();
    $response->assertDownload('launch-deck.js');

    expect($response->streamedContent())->toContain('lecturn-presentation');
});

test('an unknown export format is rejected', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('presentations.export', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
            'format' => 'pdf',
        ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('format');
});

test('presentations of other teams cannot be exported through the current team', function () {
    $user = User::factory()->create();
    $otherTeamPresentation = PresentationModel::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.export', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $otherTeamPresentation->id,
            'format' => 'svelte',
        ]));

    $response->assertNotFound();
});
