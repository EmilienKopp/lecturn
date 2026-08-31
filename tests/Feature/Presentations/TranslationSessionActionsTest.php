<?php

declare(strict_types=1);

use App\Application\Actions\Presentations\StartTranslationSession;
use App\Application\Actions\Presentations\StopTranslationSession;
use App\Application\Commands\StartTranslationSessionCommand;
use App\Application\Commands\StopTranslationSessionCommand;
use App\Domain\Presentation\Contracts\TranslationServiceContract;
use App\Domain\Presentation\ValueObjects\YoYoTranslateSession;
use App\Models\PresentationModel;
use App\Models\User;
use Illuminate\Support\Carbon;

function makeFakeTranslationService(string $sessionId = 'fake-session-abc'): TranslationServiceContract
{
    return new class($sessionId) implements TranslationServiceContract
    {
        public bool $closeCalled = false;

        public function __construct(private readonly string $sessionId) {}

        public function createSession(string $presentationSlug, string $sourceLanguage): YoYoTranslateSession
        {
            return new YoYoTranslateSession(
                sessionId: $this->sessionId,
                startedAt: Carbon::now(),
            );
        }

        public function closeSession(string $sessionId): void
        {
            $this->closeCalled = true;
        }
    };
}

it('starts a translation session and persists the session id', function () {
    $presentation = PresentationModel::factory()->create();
    $fake = makeFakeTranslationService('session-xyz');

    $this->app->instance(TranslationServiceContract::class, $fake);

    $entity = app(StartTranslationSession::class)->execute(
        new StartTranslationSessionCommand(
            presentationId: $presentation->id,
            userId: 1,
            sourceLanguage: 'en',
        ),
    );

    expect($entity->yoyotranslateSessionId)->toBe('session-xyz')
        ->and($entity->yoyotranslateSessionStartedAt)->not->toBeNull();

    $this->assertDatabaseHas('presentations', [
        'id' => $presentation->id,
        'yoyotranslate_session_id' => 'session-xyz',
    ]);
});

it('links a manually created event without calling the translation service', function () {
    $presentation = PresentationModel::factory()->create();

    $entity = app(StartTranslationSession::class)->execute(
        new StartTranslationSessionCommand(
            presentationId: $presentation->id,
            userId: 1,
            eventId: '01a05520-5454-7352-aa0f-b9bcb9a23517',
        ),
    );

    expect($entity->yoyotranslateSessionId)->toBe('01a05520-5454-7352-aa0f-b9bcb9a23517')
        ->and($entity->yoyotranslateSessionStartedAt)->not->toBeNull();
});

it('accepts a pasted event url over http and extracts the event id', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $response = $this->actingAs($user)->post(
        route('presentations.translation-session.start', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]),
        ['event_url' => 'https://yoyotranslate.app/events/01a05520-5454-7352-aa0f-b9bcb9a23517/live'],
    );

    $response->assertRedirect();

    $this->assertDatabaseHas('presentations', [
        'id' => $presentation->id,
        'yoyotranslate_session_id' => '01a05520-5454-7352-aa0f-b9bcb9a23517',
    ]);
});

it('rejects a start request with neither event url nor source language', function () {
    $user = User::factory()->create();
    $presentation = PresentationModel::factory()->create([
        'team_id' => $user->currentTeam->id,
    ]);

    $response = $this->actingAs($user)->post(
        route('presentations.translation-session.start', [
            'current_team' => $user->currentTeam->slug,
            'presentation' => $presentation->id,
        ]),
        [],
    );

    $response->assertSessionHasErrors(['event_url', 'source_language']);
});

it('stops a translation session and clears the session id', function () {
    $presentation = PresentationModel::factory()->create([
        'yoyotranslate_session_id' => 'session-to-close',
        'yoyotranslate_session_started_at' => now(),
    ]);

    $fake = makeFakeTranslationService();

    $this->app->instance(TranslationServiceContract::class, $fake);

    $entity = app(StopTranslationSession::class)->execute(
        new StopTranslationSessionCommand(
            presentationId: $presentation->id,
            userId: 1,
        ),
    );

    expect($entity->yoyotranslateSessionId)->toBeNull()
        ->and($entity->yoyotranslateSessionStartedAt)->toBeNull();

    $this->assertDatabaseHas('presentations', [
        'id' => $presentation->id,
        'yoyotranslate_session_id' => null,
    ]);
});

it('calls closeSession on the translation service when stopping', function () {
    $presentation = PresentationModel::factory()->create([
        'yoyotranslate_session_id' => 'active-session',
    ]);

    $fake = makeFakeTranslationService();

    $this->app->instance(TranslationServiceContract::class, $fake);

    app(StopTranslationSession::class)->execute(
        new StopTranslationSessionCommand(
            presentationId: $presentation->id,
            userId: 1,
        ),
    );

    expect($fake->closeCalled)->toBeTrue();
});

it('does not call closeSession when there is no active session', function () {
    $presentation = PresentationModel::factory()->create([
        'yoyotranslate_session_id' => null,
    ]);

    $fake = makeFakeTranslationService();

    $this->app->instance(TranslationServiceContract::class, $fake);

    app(StopTranslationSession::class)->execute(
        new StopTranslationSessionCommand(
            presentationId: $presentation->id,
            userId: 1,
        ),
    );

    expect($fake->closeCalled)->toBeFalse();
});
