<?php

declare(strict_types=1);

use App\Application\Actions\Presentations\StartTranslationSession;
use App\Application\Actions\Presentations\StopTranslationSession;
use App\Application\Commands\StartTranslationSessionCommand;
use App\Application\Commands\StopTranslationSessionCommand;
use App\Domain\Presentation\Contracts\TranslationServiceContract;
use App\Domain\Presentation\ValueObjects\YoYoTranslateSession;
use App\Models\PresentationModel;
use Illuminate\Support\Carbon;

function makeFakeTranslationService(string $sessionId = 'fake-session-abc'): TranslationServiceContract
{
    return new class($sessionId) implements TranslationServiceContract {
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
