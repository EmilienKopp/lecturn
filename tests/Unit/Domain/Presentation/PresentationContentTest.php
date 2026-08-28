<?php

declare(strict_types=1);

use App\Domain\Presentation\Exceptions\InvalidPresentationContent;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use App\Domain\Presentation\ValueObjects\SlideLayout;

function validContentArray(): array
{
    return [
        'version' => '1.0',
        'slides' => [
            [
                'id' => 'slide-1',
                'layout' => 'left-right',
                'background' => '#0f0f0f',
                'slots' => [
                    'left' => [
                        [
                            'id' => 'block-1',
                            'type' => 'text',
                            'content' => 'Hello world',
                            'style' => ['fontSize' => '2.5rem', 'fontWeight' => 'bold', 'color' => '#ffffff'],
                            'transition' => null,
                        ],
                    ],
                    'right' => [
                        [
                            'id' => 'block-2',
                            'type' => 'code',
                            'lang' => 'php',
                            'content' => "echo 'hello';",
                            'style' => [],
                            'transition' => ['order' => 1],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('round-trips a valid document through fromArray and toArray', function () {
    $content = PresentationContent::fromArray(validContentArray());

    expect($content->toArray())->toEqual(validContentArray());
});

it('rejects an unknown layout', function () {
    $data = validContentArray();
    $data['slides'][0]['layout'] = 'diagonal';

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'Unknown slide layout');

it('rejects an unknown block type', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['left'][0]['type'] = 'video';

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'Unknown block type');

it('rejects a slot name not defined by the layout', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['footer'] = [];

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'not defined by layout');

it('rejects a transition order below one', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['right'][0]['transition'] = ['order' => 0];

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'order must be 1 or greater');

it('rejects an unsupported version', function () {
    $data = validContentArray();
    $data['version'] = '2.0';

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'Unsupported content version');

it('rejects a document without slides', function () {
    PresentationContent::fromArray(['version' => '1.0']);
})->throws(InvalidPresentationContent::class, 'requires a slides array');

it('creates an empty document with a single center slide', function () {
    $content = PresentationContent::empty();

    expect($content->version)->toBe('1.0')
        ->and($content->slides)->toHaveCount(1)
        ->and($content->slides[0]->layout)->toBe(SlideLayout::Center)
        ->and($content->slides[0]->slots)->toBe([]);
});

it('accepts a block pinned to a flow transition node', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['right'][0]['transition'] = ['nodeId' => 'node-1'];

    $content = PresentationContent::fromArray($data);

    expect($content->toArray()['slides'][0]['slots']['right'][0]['transition'])
        ->toBe(['nodeId' => 'node-1']);
});

it('rejects a transition pin with an empty nodeId', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['right'][0]['transition'] = ['nodeId' => ''];

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'nodeId cannot be empty');

it('rejects a transition pin with neither nodeId nor order', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['right'][0]['transition'] = [];

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'requires a nodeId or a legacy order');

it('round-trips a deck-wide background image url', function () {
    $data = validContentArray();
    $data['backgroundImage'] = 'https://cdn.example.com/bg.jpg';

    $content = PresentationContent::fromArray($data);

    expect($content->backgroundImage)->toBe('https://cdn.example.com/bg.jpg')
        ->and($content->toArray())->toEqual($data);
});

it('omits the background image key when none is set', function () {
    $content = PresentationContent::fromArray(validContentArray());

    expect($content->backgroundImage)->toBeNull()
        ->and($content->toArray())->not->toHaveKey('backgroundImage');
});

it('exposes a single main slot for the free layout', function () {
    expect(SlideLayout::Free->slots())->toBe(['main'])
        ->and(SlideLayout::Free->usesFreeformSlots())->toBeTrue();
});

it('round-trips a free-layout slide with block position styles', function () {
    $data = [
        'version' => '1.0',
        'slides' => [
            [
                'id' => 'slide-1',
                'layout' => 'free',
                'background' => '#ffffff',
                'slots' => [
                    'main' => [
                        [
                            'id' => 'block-1',
                            'type' => 'text',
                            'content' => 'Floating',
                            'style' => ['x' => '12.5', 'y' => '20', 'width' => '30'],
                            'transition' => null,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $content = PresentationContent::fromArray($data);
    $style = $content->toArray()['slides'][0]['slots']['main'][0]['style'];

    expect($style)->toBe(['x' => '12.5', 'y' => '20', 'width' => '30'])
        ->and($style)->not->toHaveKey('height');
});
