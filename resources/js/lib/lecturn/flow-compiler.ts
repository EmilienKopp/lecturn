import type {
    Block,
    FlowEdge,
    FlowGraph,
    FlowNode,
    PresentationContent,
    Slide,
} from '@/types/generated';

export type CompiledTransition = {
    nodeId: string;
    label: string | null;
};

export type CompiledSlide = {
    slide: Slide;
    nodeId: string | null;
    transitions: CompiledTransition[];
    /** Target slide id of the navigation edge; null means implicit next slide. */
    nextSlideId: string | null;
};

export type CompiledDeck = {
    slides: CompiledSlide[];
};

/**
 * Walks the two-lane flow graph into the shape Animotion renders: for each
 * slide (in content order), the ordered <Transition> chain hanging off its
 * node, and the navigation edge target for future branch-aware playback.
 */
export function compileFlow(
    flow: FlowGraph,
    content: PresentationContent,
): CompiledDeck {
    const nodesById = new Map<string, FlowNode>(
        flow.nodes.map((node) => [node.id, node]),
    );
    const nodesBySlideId = new Map<string, FlowNode>();

    for (const node of flow.nodes) {
        if (node.type === 'slide' && node.data.slideId) {
            nodesBySlideId.set(node.data.slideId, node);
        }
    }

    const outgoingBySource = new Map<string, FlowEdge[]>();

    for (const edge of flow.edges) {
        const existing = outgoingBySource.get(edge.source) ?? [];
        outgoingBySource.set(edge.source, [...existing, edge]);
    }

    const chainTarget = (sourceId: string): FlowNode | null => {
        const edges = outgoingBySource.get(sourceId) ?? [];
        const chainEdge = edges.find(
            (edge) => nodesById.get(edge.target)?.type === 'transition',
        );

        return chainEdge ? (nodesById.get(chainEdge.target) ?? null) : null;
    };

    const navTarget = (sourceId: string): string | null => {
        const edges = outgoingBySource.get(sourceId) ?? [];
        const navEdge = edges.find(
            (edge) => nodesById.get(edge.target)?.type === 'slide',
        );
        const target = navEdge ? nodesById.get(navEdge.target) : null;

        return target?.data.slideId ?? null;
    };

    const slides = content.slides.map((slide): CompiledSlide => {
        const node = nodesBySlideId.get(slide.id) ?? null;
        const transitions: CompiledTransition[] = [];

        if (node) {
            let cursor = chainTarget(node.id);

            while (cursor && transitions.length <= flow.nodes.length) {
                transitions.push({
                    nodeId: cursor.id,
                    label: cursor.data.label ?? null,
                });
                cursor = chainTarget(cursor.id);
            }
        }

        return {
            slide,
            nodeId: node?.id ?? null,
            transitions,
            nextSlideId: node ? navTarget(node.id) : null,
        };
    });

    return { slides };
}

/** Chain position (1-based reveal step) per transition node id. */
export type StepIndex = Map<string, number>;

/** Reveal step per transition node id, keyed by slide id. */
export function stepIndexBySlide(deck: CompiledDeck): Map<string, StepIndex> {
    return new Map(
        deck.slides.map((compiled) => [
            compiled.slide.id,
            new Map(
                compiled.transitions.map((transition, index) => [
                    transition.nodeId,
                    index + 1,
                ]),
            ),
        ]),
    );
}

export type SteppedBlocks = {
    staticBlocks: Block[];
    /** One group per reveal step, in chain order; blocks reveal together. */
    stepGroups: { order: number; blocks: Block[] }[];
};

/**
 * Splits a slot's blocks into always-visible ones and per-step reveal groups.
 * A pin whose node left the slide's chain has lost its meaning — the block
 * simply renders static.
 */
export function groupBlocksIntoSteps(
    blocks: Block[],
    steps: StepIndex,
): SteppedBlocks {
    const stepOf = (block: Block): number | null =>
        block.transition?.nodeId != null
            ? (steps.get(block.transition.nodeId) ?? null)
            : null;

    const staticBlocks = blocks.filter((block) => stepOf(block) === null);
    const groups = new Map<number, Block[]>();

    for (const block of blocks) {
        const step = stepOf(block);

        if (step !== null) {
            groups.set(step, [...(groups.get(step) ?? []), block]);
        }
    }

    return {
        staticBlocks,
        stepGroups: [...groups.entries()]
            .sort(([a], [b]) => a - b)
            .map(([order, grouped]) => ({ order, blocks: grouped })),
    };
}

/** Fallback graph for presentations saved before the flow builder existed. */
export function defaultFlowFromContent(
    content: PresentationContent,
): FlowGraph {
    return {
        version: '1.0',
        nodes: content.slides.map((slide, index) => ({
            id: `node-${slide.id}`,
            type: 'slide',
            position: { x: 0, y: index * 160 },
            data: { slideId: slide.id },
        })),
        edges: [],
    };
}

/**
 * Upgrades pre-flow content in place conceptually, but without mutating the
 * inputs: blocks pinned with the legacy `{order}` shape get one transition
 * node each, appended to their slide's chain in order, and the pin is
 * rewritten to `{nodeId}`. Already-migrated content passes through untouched.
 */
export function migrateLegacyTransitions(
    content: PresentationContent,
    flow: FlowGraph,
): { content: PresentationContent; flow: FlowGraph } {
    type Mutable<T> = { -readonly [K in keyof T]: Mutable<T[K]> };
    const nextContent = structuredClone(
        content,
    ) as Mutable<PresentationContent>;
    const nextFlow = structuredClone(flow) as Mutable<FlowGraph>;

    const nodesById = new Map(nextFlow.nodes.map((node) => [node.id, node]));
    const chainTargetBySource = new Map<string, string>();

    for (const edge of nextFlow.edges) {
        if (nodesById.get(edge.target)?.type === 'transition') {
            chainTargetBySource.set(edge.source, edge.target);
        }
    }

    for (const slide of nextContent.slides) {
        const legacyBlocks = Object.values(slide.slots)
            .flat()
            .filter(
                (block) =>
                    block.transition &&
                    !block.transition.nodeId &&
                    block.transition.order,
            )
            .sort(
                (a, b) =>
                    (a.transition?.order ?? 0) - (b.transition?.order ?? 0),
            );

        if (legacyBlocks.length === 0) {
            continue;
        }

        let slideNode = nextFlow.nodes.find(
            (node) => node.type === 'slide' && node.data.slideId === slide.id,
        );

        if (!slideNode) {
            slideNode = {
                id: `node-${slide.id}`,
                type: 'slide',
                position: {
                    x: 0,
                    y:
                        Math.max(
                            0,
                            ...nextFlow.nodes.map((node) => node.position.y),
                        ) + 160,
                },
                data: { slideId: slide.id },
            };
            nextFlow.nodes.push(slideNode);
            nodesById.set(slideNode.id, slideNode);
        }

        // Walk to the chain tail so migrated steps append after any
        // transitions the user already built in the flow view.
        let tailId = slideNode.id;

        for (
            let guard = 0;
            chainTargetBySource.has(tailId) && guard <= nextFlow.nodes.length;
            guard++
        ) {
            tailId = chainTargetBySource.get(tailId)!;
        }

        legacyBlocks.forEach((block, index) => {
            const node = {
                id: `node-legacy-${block.id}`,
                type: 'transition' as const,
                position: {
                    x: slideNode.position.x + 260,
                    y: slideNode.position.y + index * 80,
                },
                data: { label: null },
            };

            nextFlow.nodes.push(node);
            nextFlow.edges.push({
                id: `edge-legacy-${block.id}`,
                source: tailId,
                target: node.id,
                label: null,
            });
            tailId = node.id;
            block.transition = { nodeId: node.id, order: null };
        });
    }

    return { content: nextContent, flow: nextFlow };
}
