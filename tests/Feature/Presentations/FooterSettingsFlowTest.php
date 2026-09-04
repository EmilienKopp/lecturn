<?php

declare(strict_types=1);

use App\Models\PresentationModel;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('footer settings persist through the update flow and return to the editor', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(1)->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $footer = [
        'enabled' => true,
        'xHandle' => 'emilienkopp',
        'githubHandle' => 'EmilienKopp',
        'hashtag' => 'LaravelConf',
        'bgColor' => 'transparent',
        'fontColor' => '#ff8800',
        'showInDock' => true,
    ];

    $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['talk_settings' => ['footer' => $footer]])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($presentation->refresh()->talk_settings['footer'])->toBe($footer);

    $this
        ->actingAs($user)
        ->get(route('presentations.edit', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('presentations/Editor')
            ->where('presentation.talk_settings.footer.enabled', true)
            ->where('presentation.talk_settings.footer.xHandle', 'emilienkopp')
            ->where('presentation.talk_settings.footer.showInDock', true),
        );
});

test('footer handles are normalized on save', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->withSlides(1)->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), ['talk_settings' => ['footer' => [
            'enabled' => true,
            'xHandle' => '@emilienkopp',
            'hashtag' => '#LaravelConf',
        ]]])
        ->assertSessionDoesntHaveErrors();

    $footer = $presentation->refresh()->talk_settings['footer'];

    expect($footer['xHandle'])->toBe('emilienkopp')
        ->and($footer['hashtag'])->toBe('LaravelConf');
});
