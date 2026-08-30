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
        ->toContain('top: 20%')
        // The stage fits itself to the slide area so it letterboxes rather
        // than stretching on unusually wide or short screens.
        ->toContain('min(100cqw, calc(100cqh * 16 / 9))');
});

test('an image block exports with sizing that keeps it inside its slot', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Image Deck',
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
                                'type' => 'image',
                                'src' => 'https://example.com/logo.png',
                                'alt' => 'Logo',
                                'content' => '',
                                'style' => ['x' => '10', 'y' => '10', 'width' => '40'],
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

    // Without a size constraint the image renders at its natural dimensions and
    // blows out of the slot. Inline style (not a class) because the embed has no
    // Tailwind and injects its CSS onto the host page.
    expect($response->streamedContent())
        ->toContain('src="https://example.com/logo.png"')
        ->toContain('max-width: 100%; max-height: 100%; object-fit: contain;');
});

test('a code block exports through Animotion Code via the code prop', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Code Deck',
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
                                'type' => 'code',
                                'content' => '//test',
                                'lang' => 'typescript',
                                'style' => ['x' => '39.52', 'y' => '51.21', 'width' => '30'],
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
        // The source must reach <Code> as the `code` prop; passing it as
        // children renders an empty block.
        ->toContain('<Code code={`//test`} lang="typescript"')
        // A null height must not leak into the style as "undefined%".
        ->not->toContain('undefined%')
        // Animotion ships no font-size/padding for the code <pre>, so the deck
        // must supply a readable code-card style or it renders as tiny raw text.
        ->toContain('pre.shiki-magic-move-container');
});

test('a slide with no incoming nav edge is dropped from the exported deck', function () {
    $user = User::factory()->create();

    $slide = fn (string $id, string $marker): array => [
        'id' => $id,
        'layout' => 'free',
        'background' => '#ffffff',
        'slots' => [
            'main' => [
                [
                    'id' => "block-{$id}",
                    'type' => 'text',
                    'content' => $marker,
                    'style' => ['x' => '10', 'y' => '10', 'width' => '40'],
                    'transition' => null,
                ],
            ],
        ],
    ];

    $node = fn (string $id, string $slideId, int $y): array => [
        'id' => $id,
        'type' => 'slide',
        'position' => ['x' => 0, 'y' => $y],
        'data' => ['slideId' => $slideId],
    ];

    $presentation = PresentationModel::factory()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Gated Deck',
        'content' => [
            'version' => '1.0',
            'slides' => [
                $slide('slide-1', 'MARKER_ENTRY'),
                $slide('slide-2', 'MARKER_LINKED'),
                $slide('slide-3', 'MARKER_ORPHAN'),
            ],
        ],
        // Once any nav edge exists, gating is on: slide-1 is the entry, slide-2
        // has an incoming edge, slide-3 has none, so slide-3 is disabled.
        'flow' => [
            'version' => '1.0',
            'nodes' => [
                $node('node-1', 'slide-1', 0),
                $node('node-2', 'slide-2', 160),
                $node('node-3', 'slide-3', 320),
            ],
            'edges' => [
                ['id' => 'edge-1', 'source' => 'node-1', 'target' => 'node-2', 'label' => null],
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
        ->toContain('MARKER_ENTRY')
        ->toContain('MARKER_LINKED')
        ->not->toContain('MARKER_ORPHAN');
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
