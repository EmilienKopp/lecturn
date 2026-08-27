<?php

declare(strict_types=1);

use App\Events\Presentations\ReactionSent;
use App\Models\PresentationModel;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('the viewer page renders for any visitor using the embed token', function () {
    $presentation = PresentationModel::factory()->create();

    $response = $this->get(
        route('presentations.viewer', ['presentation' => $presentation->embed_token]),
    );

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('presentations/Viewer')
        ->where('presentationName', $presentation->name)
        ->where('embedToken', $presentation->embed_token),
    );
});

test('sending a valid reaction dispatches a ReactionSent event', function () {
    Event::fake();

    $presentation = PresentationModel::factory()->create();

    $response = $this->post(
        route('presentations.reactions', ['presentation' => $presentation->embed_token]),
        ['emoji' => '👏'],
    );

    $response->assertNoContent();

    Event::assertDispatched(ReactionSent::class, function (ReactionSent $event) use ($presentation) {
        return $event->embedToken === $presentation->embed_token && $event->emoji === '👏';
    });
});

test('sending an unsupported emoji is rejected', function () {
    $presentation = PresentationModel::factory()->create();

    $response = $this->post(
        route('presentations.reactions', ['presentation' => $presentation->embed_token]),
        ['emoji' => '💩'],
    );

    $response->assertUnprocessable();
});

test('talk settings can be saved via the update route', function () {
    $user = \App\Models\User::factory()->create();
    $presentation = PresentationModel::factory()->create(['team_id' => $user->currentTeam->id]);

    $response = $this
        ->actingAs($user)
        ->put(route('presentations.update', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]), [
            'talk_settings' => [
                'showReactions' => true,
                'timerMode' => 'countdown',
                'durationMinutes' => 20,
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $stored = PresentationModel::findOrFail($presentation->id);
    expect($stored->talk_settings['showReactions'])->toBeTrue()
        ->and($stored->talk_settings['timerMode'])->toBe('countdown')
        ->and($stored->talk_settings['durationMinutes'])->toBe(20);
});
