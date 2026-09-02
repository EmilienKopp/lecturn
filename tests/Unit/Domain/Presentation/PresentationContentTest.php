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

it('serializes a slide with no slots as a JSON object, not an array', function () {
    $data = validContentArray();
    $data['slides'][0]['slots'] = [];

    $json = json_encode(PresentationContent::fromArray($data)->toArray());

    // An empty map must stay {} so the frontend keeps writing named slot keys;
    // a [] would arrive as an array and drop the first block added on save.
    expect($json)->toContain('"slots":{}')
        ->and($json)->not->toContain('"slots":[]');
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

it('creates an empty document with a single free slide', function () {
    // The editor only offers the free layout; seeding anything else crashes
    // SlideCanvas on a missing layout definition.
    $content = PresentationContent::empty();

    expect($content->version)->toBe('1.0')
        ->and($content->slides)->toHaveCount(1)
        ->and($content->slides[0]->layout)->toBe(SlideLayout::Free)
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

it('round-trips a code block with action pages', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['right'][0]['actions'] = [
        ['id' => 'action-1', 'code' => "echo 'v2';", 'highlightLines' => '1,3-5', 'label' => 'add echo'],
        ['id' => 'action-2', 'code' => "echo 'v3';"],
    ];

    $content = PresentationContent::fromArray($data);

    expect($content->toArray())->toEqual($data);
});

it('omits the actions key when a block has none', function () {
    $json = json_encode(PresentationContent::fromArray(validContentArray())->toArray());

    expect($json)->not->toContain('"actions"');
});

it('rejects code actions on a non-code block', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['left'][0]['actions'] = [
        ['id' => 'action-1', 'code' => 'nope'],
    ];

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'not a code block');

it('rejects duplicate code action ids on a block', function () {
    $data = validContentArray();
    $data['slides'][0]['slots']['right'][0]['actions'] = [
        ['id' => 'action-1', 'code' => 'a'],
        ['id' => 'action-1', 'code' => 'b'],
    ];

    PresentationContent::fromArray($data);
})->throws(InvalidPresentationContent::class, 'Duplicate code action id');

it('accepts every highlight lines shape the DSL allows', function (string $spec) {
    $data = validContentArray();
    $data['slides'][0]['slots']['right'][0]['actions'] = [
        ['id' => 'action-1', 'code' => 'x', 'highlightLines' => $spec],
    ];

    expect(PresentationContent::fromArray($data))->toBeInstanceOf(PresentationContent::class);
})->with(['3', '3,5', '3-5', '3,5-8,12', '*']);

it('rejects a malformed highlight lines spec', function (string $spec) {
    $data = validContentArray();
    $data['slides'][0]['slots']['right'][0]['actions'] = [
        ['id' => 'action-1', 'code' => 'x', 'highlightLines' => $spec],
    ];

    PresentationContent::fromArray($data);
})->with(['abc', '3;5', '3-', '-5', '3,', '**'])
    ->throws(InvalidPresentationContent::class, 'highlight lines');

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

it('round-trips a slide title', function () {
    $data = validContentArray();
    $data['slides'][0]['title'] = 'Introduction';

    $content = PresentationContent::fromArray($data);

    expect($content->slides[0]->title)->toBe('Introduction')
        ->and($content->toArray())->toEqual($data);
});

it('omits the title key when the slide is untitled', function () {
    $content = PresentationContent::fromArray(validContentArray());

    expect($content->slides[0]->title)->toBeNull()
        ->and($content->toArray()['slides'][0])->not->toHaveKey('title');
});

it('treats an empty-string title as untitled', function () {
    $data = validContentArray();
    $data['slides'][0]['title'] = '';

    $content = PresentationContent::fromArray($data);

    expect($content->slides[0]->title)->toBeNull()
        ->and($content->toArray()['slides'][0])->not->toHaveKey('title');
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

it('round-trips a block font family', function () {
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
                            'content' => 'Typeset',
                            'style' => ['fontFamily' => 'Lora', 'fontWeight' => 'bold'],
                            'transition' => null,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $content = PresentationContent::fromArray($data);
    $style = $content->toArray()['slides'][0]['slots']['main'][0]['style'];

    expect($style)->toBe(['fontWeight' => 'bold', 'fontFamily' => 'Lora']);
});
