<?php

declare(strict_types=1);

use App\Models\PresentationModel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a background image can be uploaded and returns its url', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.background.store', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), [
            'image' => UploadedFile::fake()->image('backdrop.jpg', 1280, 720),
        ]);

    $response->assertOk();
    expect($response->json('url'))->toBeString()->toContain('background');

    expect($presentation->fresh()->backgroundImageUrl())->not->toBeNull();
});

test('a content image can be uploaded and returns its url', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.images.store', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), [
            'image' => UploadedFile::fake()->image('diagram.png', 800, 600),
        ]);

    $response->assertOk();
    expect($response->json('url'))->toBeString()->toContain('diagram');

    expect($presentation->fresh()->getMedia(PresentationModel::IMAGES_COLLECTION))
        ->toHaveCount(1);
});

test('uploading a non-image is rejected', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.background.store', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), [
            'image' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
        ]);

    $response->assertSessionHasErrors('image');
});

test('a background image can be removed', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $presentation
        ->addMedia(UploadedFile::fake()->image('backdrop.jpg'))
        ->toMediaCollection(PresentationModel::BACKGROUND_COLLECTION);

    expect($presentation->fresh()->backgroundImageUrl())->not->toBeNull();

    $response = $this
        ->actingAs($user)
        ->delete(route('presentations.background.destroy', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]));

    $response->assertOk();
    expect($presentation->fresh()->backgroundImageUrl())->toBeNull();
});

test('a deck background image is exported onto slides without their own color', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Backdrop Deck',
        'content' => [
            'version' => '1.0',
            'backgroundImage' => 'https://cdn.example.com/bg.jpg',
            'slides' => [
                [
                    'id' => 'slide-1',
                    'layout' => 'free',
                    'background' => null,
                    'slots' => [],
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
        ->toContain('image="https://cdn.example.com/bg.jpg"');
});
