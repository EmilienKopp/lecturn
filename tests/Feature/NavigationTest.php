<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('shared navigation reflects the current team routes', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('navigation', 2)
        ->where('navigation.0.title', 'Platform')
        ->where('navigation.0.children.0.title', 'Dashboard')
        ->where('navigation.0.children.0.url', route('dashboard', $team->slug))
        ->where('navigation.0.children.0.active', true)
        ->where('navigation.0.children.1.title', 'Presentations')
        ->where('navigation.0.children.1.url', route('presentations.index', $team->slug))
        ->where('navigation.0.children.1.active', false)
        ->where('navigation.1.title', 'Settings')
        ->where('navigation.1.children.0.url', route('profile.edit'))
        ->where('navigation.1.children.1.url', route('teams.index')),
    );
});

test('navigation marks the presentations section active on the presentations page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('presentations.index', $team->slug));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('navigation.0.children.0.active', false)
        ->where('navigation.0.children.1.active', true),
    );
});
