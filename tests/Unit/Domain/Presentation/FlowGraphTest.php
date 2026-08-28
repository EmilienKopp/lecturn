<?php

declare(strict_types=1);

use App\Domain\Presentation\Exceptions\InvalidFlowGraph;
use App\Domain\Presentation\ValueObjects\FlowGraph;

/**
 * @param  list<array<string, mixed>>  $nodes
 * @param  list<array<string, mixed>>  $edges
 * @return array<string, mixed>
 */
function flowData(array $nodes, array $edges = []): array
{
    return [
        'version' => FlowGraph::VERSION,
        'nodes' => $nodes,
        'edges' => $edges,
    ];
}

/** @return array<string, mixed> */
function slideNode(string $id, string $slideId, float $y = 0): array
{
    return ['id' => $id, 'type' => 'slide', 'position' => ['x' => 0.0, 'y' => $y], 'data' => ['slideId' => $slideId]];
}

/** @return array<string, mixed> */
function transitionNode(string $id, ?string $label = null, float $y = 0): array
{
    return ['id' => $id, 'type' => 'transition', 'position' => ['x' => 200.0, 'y' => $y], 'data' => ['label' => $label]];
}

/** @return array<string, mixed> */
function edge(string $id, string $source, string $target): array
{
    return ['id' => $id, 'source' => $source, 'target' => $target, 'label' => null];
}

it('round-trips a two-lane graph through fromArray and toArray', function () {
    $data = flowData(
        [
            slideNode('n1', 'slide-a'),
            slideNode('n2', 'slide-b', 200),
            transitionNode('t1', 'reveal bullets'),
            transitionNode('t2', null, 100),
        ],
        [
            edge('e-nav', 'n1', 'n2'),
            edge('e-chain', 'n1', 't1'),
            edge('e-chain2', 't1', 't2'),
        ],
    );

    expect(FlowGraph::fromArray($data)->toArray())->toBe($data);
});

it('accepts a slide node with zero edges', function () {
    $graph = FlowGraph::fromArray(flowData([slideNode('n1', 'slide-a')]));

    expect($graph->nodes)->toHaveCount(1)
        ->and($graph->edges)->toBeEmpty();
});

it('allows a slide to have both a navigation edge and a chain-start edge', function () {
    $graph = FlowGraph::fromArray(flowData(
        [slideNode('n1', 'slide-a'), slideNode('n2', 'slide-b'), transitionNode('t1')],
        [edge('e1', 'n1', 'n2'), edge('e2', 'n1', 't1')],
    ));

    expect($graph->edges)->toHaveCount(2);
});

it('lists referenced slide ids', function () {
    $graph = FlowGraph::fromArray(flowData([
        slideNode('n1', 'slide-a'),
        slideNode('n2', 'slide-b'),
        transitionNode('t1'),
    ], [edge('e1', 'n1', 't1')]));

    expect($graph->referencedSlideIds())->toBe(['slide-a', 'slide-b']);
});

it('rejects an unsupported version', function () {
    FlowGraph::fromArray([...flowData([]), 'version' => '2.0']);
})->throws(InvalidFlowGraph::class, 'Unsupported flow version');

it('rejects duplicate node ids', function () {
    FlowGraph::fromArray(flowData([slideNode('n1', 'slide-a'), slideNode('n1', 'slide-b')]));
})->throws(InvalidFlowGraph::class, 'Duplicate flow node id');

it('rejects duplicate edge ids', function () {
    FlowGraph::fromArray(flowData(
        [slideNode('n1', 'slide-a'), transitionNode('t1'), transitionNode('t2')],
        [edge('e1', 'n1', 't1'), edge('e1', 't1', 't2')],
    ));
})->throws(InvalidFlowGraph::class, 'Duplicate flow edge id');

it('rejects an edge with an unknown node', function () {
    FlowGraph::fromArray(flowData([slideNode('n1', 'slide-a')], [edge('e1', 'n1', 'ghost')]));
})->throws(InvalidFlowGraph::class, 'unknown target node');

it('rejects a self-loop', function () {
    FlowGraph::fromArray(flowData([slideNode('n1', 'slide-a')], [edge('e1', 'n1', 'n1')]));
})->throws(InvalidFlowGraph::class, 'cannot connect a node to itself');

it('rejects a transition edge back into a slide', function () {
    FlowGraph::fromArray(flowData(
        [slideNode('n1', 'slide-a'), slideNode('n2', 'slide-b'), transitionNode('t1')],
        [edge('e1', 'n1', 't1'), edge('e2', 't1', 'n2')],
    ));
})->throws(InvalidFlowGraph::class, 'leaves the transition subgraph');

it('rejects two navigation edges from one slide', function () {
    FlowGraph::fromArray(flowData(
        [slideNode('n1', 'slide-a'), slideNode('n2', 'slide-b'), slideNode('n3', 'slide-c')],
        [edge('e1', 'n1', 'n2'), edge('e2', 'n1', 'n3')],
    ));
})->throws(InvalidFlowGraph::class, 'more than one navigation edge');

it('rejects two chain edges from one node', function () {
    FlowGraph::fromArray(flowData(
        [slideNode('n1', 'slide-a'), transitionNode('t1'), transitionNode('t2')],
        [edge('e1', 'n1', 't1'), edge('e2', 'n1', 't2')],
    ));
})->throws(InvalidFlowGraph::class, 'more than one transition edge');

it('rejects a transition with two incoming edges', function () {
    FlowGraph::fromArray(flowData(
        [slideNode('n1', 'slide-a'), slideNode('n2', 'slide-b'), transitionNode('t1')],
        [edge('e1', 'n1', 't1'), edge('e2', 'n2', 't1')],
    ));
})->throws(InvalidFlowGraph::class, 'more than one incoming edge');

it('rejects an orphan transition node', function () {
    FlowGraph::fromArray(flowData([slideNode('n1', 'slide-a'), transitionNode('t1')]));
})->throws(InvalidFlowGraph::class, 'not connected to any slide');

it('rejects a transition cycle', function () {
    FlowGraph::fromArray(flowData(
        [transitionNode('t1'), transitionNode('t2')],
        [edge('e1', 't1', 't2'), edge('e2', 't2', 't1')],
    ));
})->throws(InvalidFlowGraph::class);

it('rejects a slide node without a slideId', function () {
    FlowGraph::fromArray(flowData([
        ['id' => 'n1', 'type' => 'slide', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
    ]));
})->throws(InvalidFlowGraph::class, 'requires a non-empty slideId');

it('rejects an unknown node type', function () {
    FlowGraph::fromArray(flowData([
        ['id' => 'n1', 'type' => 'portal', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
    ]));
})->throws(InvalidFlowGraph::class, 'Unknown flow node type');

it('rejects duplicate transition labels within one chain', function () {
    FlowGraph::fromArray(flowData(
        [slideNode('n1', 'slide-a'), transitionNode('t1', 'reveal'), transitionNode('t2', 'reveal', 100)],
        [edge('e1', 'n1', 't1'), edge('e2', 't1', 't2')],
    ));
})->throws(InvalidFlowGraph::class, 'Duplicate transition label');

it('allows the same transition label on different slides', function () {
    $graph = FlowGraph::fromArray(flowData(
        [
            slideNode('n1', 'slide-a'),
            slideNode('n2', 'slide-b', 200),
            transitionNode('t1', 'reveal'),
            transitionNode('t2', 'reveal', 100),
        ],
        [edge('e1', 'n1', 't1'), edge('e2', 'n2', 't2')],
    ));

    expect($graph->nodes)->toHaveCount(4);
});

it('allows unlabeled transitions to repeat within a chain', function () {
    $graph = FlowGraph::fromArray(flowData(
        [slideNode('n1', 'slide-a'), transitionNode('t1'), transitionNode('t2', null, 100)],
        [edge('e1', 'n1', 't1'), edge('e2', 't1', 't2')],
    ));

    expect($graph->nodes)->toHaveCount(3);
});
