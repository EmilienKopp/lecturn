<?php

declare(strict_types=1);

use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\Exceptions\InvalidFlowGraph;
use App\Domain\Presentation\Exceptions\InvalidPresentationContent;
use App\Domain\Presentation\ValueObjects\FlowGraph;
use App\Domain\Presentation\ValueObjects\PresentationContent;

function makePresentationEntity(): PresentationEntity
{
    return new PresentationEntity(
        team_id: 1,
        name: 'My deck',
        content: PresentationContent::empty(),
    );
}

it('renames the presentation', function () {
    $entity = makePresentationEntity();

    $entity->rename('New name');

    expect($entity->name)->toBe('New name');
});

it('rejects an empty name', function () {
    makePresentationEntity()->rename('   ');
})->throws(InvalidPresentationContent::class);

it('rejects a name longer than 255 characters', function () {
    makePresentationEntity()->rename(str_repeat('a', 256));
})->throws(InvalidPresentationContent::class);

it('replaces content', function () {
    $entity = makePresentationEntity();
    $replacement = PresentationContent::fromArray([
        'version' => '1.0',
        'slides' => [
            ['id' => 'slide-9', 'layout' => 'full', 'background' => null, 'slots' => []],
        ],
    ]);

    $entity->replaceContent($replacement);

    expect($entity->content->slides[0]->id)->toBe('slide-9');
});

/** @return array<string, mixed> */
function makeFlowData(string $slideId = 'slide-1'): array
{
    return [
        'version' => FlowGraph::VERSION,
        'nodes' => [
            ['id' => 'n1', 'type' => 'slide', 'position' => ['x' => 0.0, 'y' => 0.0], 'data' => ['slideId' => $slideId]],
            ['id' => 't1', 'type' => 'transition', 'position' => ['x' => 200.0, 'y' => 0.0], 'data' => ['label' => 'reveal']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'n1', 'target' => 't1', 'label' => null],
        ],
    ];
}

it('replaces the flow when slide references are valid', function () {
    $entity = makePresentationEntity();

    $entity->replaceFlow(FlowGraph::fromArray(makeFlowData()));

    expect($entity->flow?->nodes)->toHaveCount(2)
        ->and($entity->toArray()['flow'])->toBe(makeFlowData());
});

it('rejects a flow referencing an unknown slide', function () {
    makePresentationEntity()->replaceFlow(FlowGraph::fromArray(makeFlowData('slide-ghost')));
})->throws(InvalidFlowGraph::class, 'unknown slide');

it('rejects a flow where two nodes reference the same slide', function () {
    $data = makeFlowData();
    $data['nodes'][] = ['id' => 'n2', 'type' => 'slide', 'position' => ['x' => 0.0, 'y' => 200.0], 'data' => ['slideId' => 'slide-1']];

    makePresentationEntity()->replaceFlow(FlowGraph::fromArray($data));
})->throws(InvalidFlowGraph::class, 'referenced by more than one flow node');

it('serializes a null flow', function () {
    expect(makePresentationEntity()->toArray()['flow'])->toBeNull();
});
