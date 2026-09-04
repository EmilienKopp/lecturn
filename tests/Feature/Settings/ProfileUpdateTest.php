<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Updated Name',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Updated Name');
});

test('social handles are stored and normalized', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'social_x_handle' => '@emilienkopp',
            'social_github_handle' => 'EmilienKopp',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->social_x_handle)->toBe('emilienkopp')
        ->and($user->social_github_handle)->toBe('EmilienKopp');
});

test('social handles are shared on the auth user prop', function () {
    $user = User::factory()->create([
        'social_x_handle' => 'emilienkopp',
        'social_github_handle' => 'EmilienKopp',
    ]);

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.social_x_handle', 'emilienkopp')
            ->where('auth.user.social_github_handle', 'EmilienKopp'),
        );
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});
