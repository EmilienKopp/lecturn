import type {
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
