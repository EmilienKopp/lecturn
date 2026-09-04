<?php

declare(strict_types=1);

use App\Domain\Presentation\ValueObjects\FooterSettings;
use App\Domain\Presentation\ValueObjects\TalkSettings;

it('creates with defaults', function () {
    $footer = FooterSettings::defaults();

    expect($footer->enabled)->toBeFalse()
        ->and($footer->xHandle)->toBeNull()
        ->and($footer->githubHandle)->toBeNull()
        ->and($footer->hashtag)->toBeNull()
        ->and($footer->bgColor)->toBe('transparent')
        ->and($footer->fontColor)->toBe('#ffffff')
        ->and($footer->showInDock)->toBeFalse();
});

it('trims and strips leading @ or # from handles and hashtag', function () {
    $footer = FooterSettings::fromArray([
        'xHandle' => ' @emilienkopp ',
        'githubHandle' => '@EmilienKopp',
        'hashtag' => '#LaravelConf',
    ]);

    expect($footer->xHandle)->toBe('emilienkopp')
        ->and($footer->githubHandle)->toBe('EmilienKopp')
        ->and($footer->hashtag)->toBe('LaravelConf');
});

it('coerces blank handles to null', function () {
    $footer = FooterSettings::fromArray([
        'xHandle' => '   ',
        'githubHandle' => '',
        'hashtag' => '@',
    ]);

    expect($footer->xHandle)->toBeNull()
        ->and($footer->githubHandle)->toBeNull()
        ->and($footer->hashtag)->toBeNull();
});

it('defaults colors when blank or missing', function () {
    $footer = FooterSettings::fromArray(['bgColor' => '', 'fontColor' => '  ']);

    expect($footer->bgColor)->toBe('transparent')
        ->and($footer->fontColor)->toBe('#ffffff');
});

it('keeps a transparent background and custom colors', function () {
    $footer = FooterSettings::fromArray([
        'enabled' => true,
        'bgColor' => 'transparent',
        'fontColor' => '#ff8800',
        'showInDock' => true,
    ]);

    expect($footer->enabled)->toBeTrue()
        ->and($footer->bgColor)->toBe('transparent')
        ->and($footer->fontColor)->toBe('#ff8800')
        ->and($footer->showInDock)->toBeTrue();
});

it('roundtrips through fromArray and toArray', function () {
    $original = [
        'enabled' => true,
        'xHandle' => 'emilienkopp',
        'githubHandle' => 'EmilienKopp',
        'hashtag' => 'LaravelConf',
        'bgColor' => '#101010',
        'fontColor' => '#ffffff',
        'showInDock' => false,
    ];

    expect(FooterSettings::fromArray($original)->toArray())->toEqual($original);
});

it('nests inside TalkSettings when hydrating and serialising', function () {
    $settings = TalkSettings::fromArray([
        'footer' => ['enabled' => true, 'hashtag' => '#Conf'],
    ]);

    expect($settings->footer)->toBeInstanceOf(FooterSettings::class)
        ->and($settings->footer->enabled)->toBeTrue()
        ->and($settings->footer->hashtag)->toBe('Conf')
        ->and($settings->toArray()['footer'])->toBe($settings->footer->toArray());
});

it('defaults the nested footer when TalkSettings has no footer key', function () {
    $settings = TalkSettings::fromArray([]);

    expect($settings->footer->enabled)->toBeFalse();
});
