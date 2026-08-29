<?php

declare(strict_types=1);

use App\Models\PresentationModel;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(storage_path('app/embeds'));
});

test('a guest can load the embed script and it is generated on first access', function () {
    $presentation = PresentationModel::factory()->withSlides(1)->create();

    $response = $this->get(route('presentations.embed', ['presentation' => $presentation->embed_token]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/javascript; charset=utf-8');

    $tag = 'lecturn-deck-'.strtolower(substr($presentation->embed_token, 0, 8));
    expect($response->getFile()->getContent())->toContain($tag);
    expect(storage_path("app/embeds/{$presentation->embed_token}.js"))->toBeFile();
});

test('subsequent requests are served from the cached file', function () {
    $presentation = PresentationModel::factory()->withSlides(1)->create();

    $this->get(route('presentations.embed', ['presentation' => $presentation->embed_token]))->assertOk();

    File::put(storage_path("app/embeds/{$presentation->embed_token}.js"), '/* sentinel */');

    $response = $this->get(route('presentations.embed', ['presentation' => $presentation->embed_token]));

    $response->assertOk();
    expect($response->getFile()->getContent())->toBe('/* sentinel */');
});

test('saving content regenerates an existing embed file', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(1)->create(['team_id' => $user->currentTeam->id]);
    $path = storage_path("app/embeds/{$presentation->embed_token}.js");

    $this->get(route('presentations.embed', ['presentation' => $presentation->embed_token]))->assertOk();
    File::put($path, '/* stale */');

    $this
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
                        'layout' => 'free',
                        'background' => null,
                        'slots' => [
                            'main' => [
                                [
                                    'id' => 'block-1',
                                    'type' => 'text',
                                    'content' => 'Regenerated embed marker',
                                    'style' => ['x' => '10', 'y' => '20', 'width' => '30'],
                                    'transition' => null,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    expect(File::get($path))
        ->not->toBe('/* stale */')
        ->toContain('Regenerated embed marker');
});

test('saving content does not create an embed file that was never requested', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(1)->create(['team_id' => $user->currentTeam->id]);

    $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['name' => 'Renamed, and content untouched'])
        ->assertRedirect();

    expect(storage_path("app/embeds/{$presentation->embed_token}.js"))->not->toBeFile();
});

test('an unknown embed token returns 404', function () {
    $this->get(route('presentations.embed', ['presentation' => 'no-such-token']))->assertNotFound();
});

test('every presentation gets an embed token on creation', function () {
    $presentation = PresentationModel::factory()->create();

    expect($presentation->embed_token)->toBeString()->toHaveLength(32);
});
