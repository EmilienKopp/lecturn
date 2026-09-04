<?php

declare(strict_types=1);

use App\Domain\Presentation\Entities\PresentationSessionEntity;
use Illuminate\Support\Carbon;

function makeSession(): PresentationSessionEntity
{
    return new PresentationSessionEntity(
        presentation_id: 1,
        team_id: 1,
        started_at: Carbon::parse('2026-09-04 10:00:00'),
    );
}

it('folds batched reactions into running totals', function () {
    $session = makeSession();

    $session->recordReactions(['🔥' => 3, '❤️' => 1], Carbon::parse('2026-09-04 10:01:00'));
    $session->recordReactions(['🔥' => 2], Carbon::parse('2026-09-04 10:02:00'));

    expect($session->reaction_counts)->toBe(['🔥' => 5, '❤️' => 1])
        ->and($session->reaction_total)->toBe(6)
        ->and($session->topEmoji())->toBe('🔥');
});

it('ignores non-positive reaction counts', function () {
    $session = makeSession();

    $session->recordReactions(['🔥' => 0, '❤️' => -2], Carbon::now());

    expect($session->reaction_counts)->toBe([])
        ->and($session->reaction_total)->toBe(0);
});

it('counts unique viewers once but refreshes presence on repeat heartbeats', function () {
    $session = makeSession();
    $now = Carbon::parse('2026-09-04 10:00:00');

    $session->touchViewer('a', $now);
    $session->touchViewer('b', $now);
    $session->touchViewer('a', $now->copy()->addSeconds(10));

    expect($session->viewer_count)->toBe(2)
        ->and($session->activeViewerCount($now->copy()->addSeconds(10)))->toBe(2);
});

it('drops stale viewers from the live count but keeps them in the unique total', function () {
    $session = makeSession();
    $now = Carbon::parse('2026-09-04 10:00:00');

    $session->touchViewer('a', $now);
    $session->touchViewer('b', $now);

    // 'a' heartbeats again 30s later; 'b' has gone quiet past the window.
    $later = $now->copy()->addSeconds(30);
    $session->touchViewer('a', $later);

    expect($session->activeViewerCount($later))->toBe(1)
        ->and($session->viewer_count)->toBe(2);
});

it('marks a viewer as left without losing the unique tally', function () {
    $session = makeSession();
    $now = Carbon::parse('2026-09-04 10:00:00');

    $session->touchViewer('a', $now);
    $session->markViewerLeft('a');

    expect($session->activeViewerCount($now))->toBe(0)
        ->and($session->viewer_count)->toBe(1);
});

it('closes the session', function () {
    $session = makeSession();

    expect($session->isActive())->toBeTrue();

    $session->end(Carbon::parse('2026-09-04 10:30:00'));

    expect($session->isActive())->toBeFalse()
        ->and($session->ended_at)->not->toBeNull();
});
