<?php

declare(strict_types=1);

namespace App\Domain\Presentation\ValueObjects;

use App\Domain\Presentation\Exceptions\InvalidFlowGraph;

/**
 * The slideshow flow DSL (v1.0): slide nodes and transition nodes.
 *
 * Two-lane edge model — a slide node has at most one navigation edge to the
 * next slide and at most one edge to its first transition; transition chains
 * are isolated linear subgraphs (transition→transition only). A slide node
 * with no edges is valid: no reveals, implicit next slide by content order.
 */
readonly class FlowGraph
{
    public const string VERSION = '1.0';

    /**
     * @param  list<FlowNode>  $nodes
     * @param  list<FlowEdge>  $edges
     */
    public function __construct(
        public string $version,
        public array $nodes,
        public array $edges,
    ) {
        if ($this->version !== self::VERSION) {
            throw new InvalidFlowGraph("Unsupported flow version \"{$this->version}\".");
        }

        $nodesById = [];

        foreach ($this->nodes as $node) {
            if (! $node instanceof FlowNode) {
                throw new InvalidFlowGraph('Nodes must be FlowNode value objects.');
            }

            if (isset($nodesById[$node->id])) {
                throw new InvalidFlowGraph("Duplicate flow node id \"{$node->id}\".");
            }

            $nodesById[$node->id] = $node;
        }

        $edgeIds = [];
        $incomingByTarget = [];
        $outgoingNavBySource = [];
        $outgoingChainBySource = [];

        foreach ($this->edges as $edge) {
            if (! $edge instanceof FlowEdge) {
                throw new InvalidFlowGraph('Edges must be FlowEdge value objects.');
            }

            if (isset($edgeIds[$edge->id])) {
                throw new InvalidFlowGraph("Duplicate flow edge id \"{$edge->id}\".");
            }

            $edgeIds[$edge->id] = true;

            $source = $nodesById[$edge->source]
                ?? throw new InvalidFlowGraph("Edge \"{$edge->id}\" references unknown source node \"{$edge->source}\".");
            $target = $nodesById[$edge->target]
                ?? throw new InvalidFlowGraph("Edge \"{$edge->id}\" references unknown target node \"{$edge->target}\".");

            if ($source->type === FlowNodeType::Transition && $target->type === FlowNodeType::Slide) {
                throw new InvalidFlowGraph(
                    "Edge \"{$edge->id}\" leaves the transition subgraph — transition nodes may only connect to transition nodes."
                );
            }

            if ($target->type === FlowNodeType::Slide) {
                if (isset($outgoingNavBySource[$edge->source])) {
                    throw new InvalidFlowGraph("Slide node \"{$edge->source}\" has more than one navigation edge.");
                }

                $outgoingNavBySource[$edge->source] = true;
            } else {
                if (isset($outgoingChainBySource[$edge->source])) {
                    throw new InvalidFlowGraph("Node \"{$edge->source}\" has more than one transition edge.");
                }

                $outgoingChainBySource[$edge->source] = true;

                $incomingByTarget[$edge->target] = ($incomingByTarget[$edge->target] ?? 0) + 1;

                if ($incomingByTarget[$edge->target] > 1) {
                    throw new InvalidFlowGraph("Transition node \"{$edge->target}\" has more than one incoming edge.");
                }
            }
        }

        // Every transition must be part of a chain anchored at a slide node —
        // walking incoming edges backwards must reach a slide before looping.
        $chainSourceByTarget = [];

        foreach ($this->edges as $edge) {
            if ($nodesById[$edge->target]->type === FlowNodeType::Transition) {
                $chainSourceByTarget[$edge->target] = $edge->source;
            }
        }

        foreach ($nodesById as $node) {
            if ($node->type !== FlowNodeType::Transition) {
                continue;
            }

            if (! isset($chainSourceByTarget[$node->id])) {
                throw new InvalidFlowGraph("Transition node \"{$node->id}\" is not connected to any slide.");
            }

            $cursor = $node->id;
            $steps = 0;

            while ($nodesById[$cursor]->type === FlowNodeType::Transition) {
                $cursor = $chainSourceByTarget[$cursor]
                    ?? throw new InvalidFlowGraph("Transition node \"{$cursor}\" is not connected to any slide.");

                if (++$steps > count($nodesById)) {
                    throw new InvalidFlowGraph("Transition node \"{$node->id}\" is part of a cycle.");
                }
            }
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (! is_array($data['nodes'] ?? null)) {
            throw new InvalidFlowGraph('Flow requires a nodes array.');
        }

        if (! is_array($data['edges'] ?? null)) {
            throw new InvalidFlowGraph('Flow requires an edges array.');
        }

        return new self(
            version: (string) ($data['version'] ?? ''),
            nodes: array_map(
                static fn (mixed $node): FlowNode => is_array($node)
                    ? FlowNode::fromArray($node)
                    : throw new InvalidFlowGraph('Malformed flow node entry.'),
                array_values($data['nodes']),
            ),
            edges: array_map(
                static fn (mixed $edge): FlowEdge => is_array($edge)
                    ? FlowEdge::fromArray($edge)
                    : throw new InvalidFlowGraph('Malformed flow edge entry.'),
                array_values($data['edges']),
            ),
        );
    }

    /** @return list<string> */
    public function referencedSlideIds(): array
    {
        $slideIds = [];

        foreach ($this->nodes as $node) {
            if ($node->type === FlowNodeType::Slide) {
                $slideIds[] = (string) $node->slideId();
            }
        }

        return $slideIds;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'nodes' => array_map(
                static fn (FlowNode $node): array => $node->toArray(),
                $this->nodes,
            ),
            'edges' => array_map(
                static fn (FlowEdge $edge): array => $edge->toArray(),
                $this->edges,
            ),
        ];
    }
}
