<?php

declare(strict_types=1);

use App\Domain\Presentation\ValueObjects\TalkSettings;

it('creates with defaults', function () {
    $settings = TalkSettings::defaults();

    expect($settings->showReactions)->toBeFalse()
        ->and($settings->showTranslation)->toBeTrue()
        ->and($settings->timerMode)->toBe('elapsed')
        ->and($settings->durationMinutes)->toBeNull();
});

it('hydrates from array', function () {
    $settings = TalkSettings::fromArray([
        'showReactions' => true,
        'showTranslation' => false,
        'timerMode' => 'countdown',
        'durationMinutes' => 30,
    ]);

    expect($settings->showReactions)->toBeTrue()
        ->and($settings->showTranslation)->toBeFalse()
        ->and($settings->timerMode)->toBe('countdown')
        ->and($settings->durationMinutes)->toBe(30);
});

it('ignores unknown timerMode values and falls back to elapsed', function () {
    $settings = TalkSettings::fromArray(['timerMode' => 'invalid']);

    expect($settings->timerMode)->toBe('elapsed');
});

it('serialises to array', function () {
    $settings = new TalkSettings(showReactions: true, timerMode: 'countdown', durationMinutes: 45);

    expect($settings->toArray())->toMatchArray([
        'showReactions' => true,
        'timerMode' => 'countdown',
        'durationMinutes' => 45,
    ]);
});

it('roundtrips through fromArray and toArray', function () {
    $original = ['showReactions' => false, 'showDock' => true, 'showTranslation' => true, 'timerMode' => 'elapsed', 'durationMinutes' => null];

    expect(TalkSettings::fromArray($original)->toArray())->toEqual($original);
});
