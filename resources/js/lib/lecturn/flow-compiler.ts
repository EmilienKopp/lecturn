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
    /** The slide's flattened reveal sequence: transitions and code actions. */
    steps: SlideStep[];
    /** Target slide id of the navigation edge; null means implicit next slide. */
    nextSlideId: string | null;
};

export type CompiledDeck = {
    slides: CompiledSlide[];
};

/**
 * Orders a set of chain nodes (all of one lane): chain edges between owned
 * nodes impose explicit order where present; unwired nodes fall back to their
 * canvas position (top-to-bottom, then left-to-right). Nodes trapped in a
 * cycle have no head; they surface anyway, by position.
 */
function orderChainNodes(owned: FlowNode[], edges: FlowEdge[]): FlowNode[] {
    if (owned.length === 0) {
        return [];
    }

    const ownedById = new Map(owned.map((node) => [node.id, node]));
    const nextOf = new Map<string, string>();
    const hasIncoming = new Set<string>();

    for (const edge of edges) {
        if (ownedById.has(edge.source) && ownedById.has(edge.target)) {
            nextOf.set(edge.source, edge.target);
            hasIncoming.add(edge.target);
        }
    }

    const byPosition = (a: FlowNode, b: FlowNode): number =>
        a.position.y - b.position.y ||
        a.position.x - b.position.x ||
        a.id.localeCompare(b.id);

    const ordered: FlowNode[] = [];
    const visited = new Set<string>();

    // A chain head is a node no other owned node points at; its whole chain
    // follows it. Heads (and singletons) are laid out by canvas position.
    const heads = owned
        .filter((node) => !hasIncoming.has(node.id))
        .sort(byPosition);

    for (const head of heads) {
        let cursor: FlowNode | undefined = head;

        while (cursor && !visited.has(cursor.id)) {
            visited.add(cursor.id);
            ordered.push(cursor);
            const nextId = nextOf.get(cursor.id);
            cursor = nextId ? ownedById.get(nextId) : undefined;
        }
    }

    for (const node of [...owned].sort(byPosition)) {
        if (!visited.has(node.id)) {
            visited.add(node.id);
            ordered.push(node);
        }
    }

    return ordered;
}

/**
 * A slide's reveal steps in order. A transition node belongs to a slide
 * through its stable `data.slideId`, never through edge reachability, so a step
 * keeps its identity (id, label, block pins) no matter what happens to edges.
 * Chain edges (transition→transition) impose explicit order where present;
 * steps left unwired fall back to their canvas position. This is the single
 * source of truth for step order, shared by the editor and the codegen.
 */
export function transitionsForSlide(
    flow: FlowGraph,
    slideId: string,
): CompiledTransition[] {
    const owned = flow.nodes.filter(
        (node) => node.type === 'transition' && node.data.slideId === slideId,
    );

    return orderChainNodes(owned, flow.edges).map((node) => ({
        nodeId: node.id,
        label: node.data.label ?? null,
    }));
}

export type CompiledCodeAction = {
    nodeId: string;
    actionId: string;
};

/**
 * A code block's action pages in play order. Code-action nodes belong to
 * their block through `data.blockId`; chain edges (code-action→code-action)
 * order the pages, unwired ones fall back to canvas position — the same
 * rules as a slide's transitions.
 */
export function codeActionsForBlock(
    flow: FlowGraph,
    blockId: string,
): CompiledCodeAction[] {
    const owned = flow.nodes.filter(
        (node) => node.type === 'code-action' && node.data.blockId === blockId,
    );

    return orderChainNodes(owned, flow.edges).flatMap((node) =>
        node.data.actionId
            ? [{ nodeId: node.id, actionId: node.data.actionId }]
            : [],
    );
}

export type SlideStep =
    | {
          kind: 'transition';
          order: number;
          nodeId: string;
          label: string | null;
      }
    | {
          kind: 'code-action';
          order: number;
          nodeId: string;
          blockId: string;
          actionId: string;
      };

/**
 * The slide's whole reveal sequence, flattened to the fragment orders
 * Animotion plays: transitions in chain order, each immediately followed by
 * the action pages of the code blocks pinned to it (nesting follows the
 * block's pin), then the action pages of unpinned code blocks. Every step
 * gets a 1-based order shared by <Transition order> and <Action order>.
 */
export function slideStepSequence(flow: FlowGraph, slide: Slide): SlideStep[] {
    const blocks = Object.values(slide.slots).flat();
    const codeBlocks = blocks.filter(
        (block) => block.type === 'code' && (block.actions ?? []).length > 0,
    );

    const actionsOf = (block: Block): SlideStep[] => {
        const actionIds = new Set((block.actions ?? []).map((a) => a.id));

        return codeActionsForBlock(flow, block.id)
            .filter((action) => actionIds.has(action.actionId))
            .map((action) => ({
                kind: 'code-action' as const,
                order: 0,
                nodeId: action.nodeId,
                blockId: block.id,
                actionId: action.actionId,
            }));
    };

    const steps: SlideStep[] = [];
    const sequenced = new Set<string>();

    for (const transition of transitionsForSlide(flow, slide.id)) {
        steps.push({
            kind: 'transition',
            order: 0,
            nodeId: transition.nodeId,
            label: transition.label,
        });

        for (const block of codeBlocks) {
            if (block.transition?.nodeId === transition.nodeId) {
                steps.push(...actionsOf(block));
                sequenced.add(block.id);
            }
        }
    }

    // Unpinned code blocks are visible from the start; their pages play after
    // every reveal (pin the block to a transition to interleave earlier).
    // Blocks pinned to a node outside the slide's chain render static, so
    // their pages land here too instead of silently vanishing.
    for (const block of codeBlocks) {
        if (!sequenced.has(block.id)) {
            steps.push(...actionsOf(block));
        }
    }

    return steps.map((step, index) => ({ ...step, order: index + 1 }));
}

/**
 * The slides that are part of the show. A navigation edge is a slide→slide
 * link; a slide is enabled when it is the entry (first in content order) or has
 * at least one incoming navigation edge. As a backward-compatibility rule, a
 * deck with no navigation edges at all is treated as fully enabled — that keeps
 * legacy and freshly-created decks (which wire nothing) playing every slide
 * until the author starts using the nav chain. Shared by the editor, the
 * Presenter, and the codegen so all three agree on what is shown.
 */
export function enabledSlideIds(
    content: PresentationContent,
    flow: FlowGraph,
): Set<string> {
    const slideNodeIds = new Set(
        flow.nodes
            .filter((node) => node.type === 'slide')
            .map((node) => node.id),
    );
    const nodesById = new Map(flow.nodes.map((node) => [node.id, node]));
    const navEdges = flow.edges.filter(
        (edge) =>
            slideNodeIds.has(edge.source) && slideNodeIds.has(edge.target),
    );

    if (navEdges.length === 0) {
        return new Set(content.slides.map((slide) => slide.id));
    }

    const withIncoming = new Set<string>();

    for (const edge of navEdges) {
        const targetSlideId = nodesById.get(edge.target)?.data.slideId;

        if (targetSlideId) {
            withIncoming.add(targetSlideId);
        }
    }

    const enabled = new Set<string>();

    content.slides.forEach((slide, index) => {
        if (index === 0 || withIncoming.has(slide.id)) {
            enabled.add(slide.id);
        }
    });

    return enabled;
}

/**
 * Walks the flow graph into the shape Animotion renders: for each slide (in
 * content order), its ordered reveal steps and the navigation edge target for
 * future branch-aware playback.
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

    const navTarget = (sourceId: string): string | null => {
        const navEdge = flow.edges.find(
            (edge) =>
                edge.source === sourceId &&
                nodesById.get(edge.target)?.type === 'slide',
        );
        const target = navEdge ? nodesById.get(navEdge.target) : null;

        return target?.data.slideId ?? null;
    };

    const slides = content.slides.map((slide): CompiledSlide => {
        const node = nodesBySlideId.get(slide.id) ?? null;

        return {
            slide,
            nodeId: node?.id ?? null,
            transitions: transitionsForSlide(flow, slide.id),
            steps: slideStepSequence(flow, slide),
            nextSlideId: node ? navTarget(node.id) : null,
        };
    });

    return { slides };
}

/** Fragment order (1-based reveal step) per transition node id. */
export type StepIndex = Map<string, number>;

/**
 * Reveal order per transition node id, keyed by slide id. Orders come from
 * the flattened step sequence, so a transition followed by code-action pages
 * leaves gaps — that keeps <Transition order> and <Action order> fragments
 * interleaved correctly.
 */
export function stepIndexBySlide(deck: CompiledDeck): Map<string, StepIndex> {
    return new Map(
        deck.slides.map((compiled) => [
            compiled.slide.id,
            new Map(
                compiled.steps
                    .filter((step) => step.kind === 'transition')
                    .map((step) => [step.nodeId, step.order]),
            ),
        ]),
    );
}

/** One page state of a code block: the code shown and the highlighted lines. */
export type CodePage = {
    code: string;
    highlightLines: string | null;
};

/**
 * A playable code action: what the block morphs to when the fragment fires
 * (`show`) and what it morphs back to when it rewinds (`back` — the previous
 * page, or the block's base content before the first action).
 */
export type CodeActionCue = {
    order: number;
    blockId: string;
    show: CodePage;
    back: CodePage;
};

/**
 * Resolves a slide's code-action steps against its block payloads into
 * do/undo page pairs, in play order. Shared by the Presenter and the codegen
 * so live playback and the Svelte export cannot drift.
 */
export function codeActionCues(
    slide: Slide,
    steps: SlideStep[],
): CodeActionCue[] {
    const blocks = Object.values(slide.slots).flat();
    const blockById = new Map(blocks.map((block) => [block.id, block]));
    const previousPageByBlock = new Map<string, CodePage>();
    const cues: CodeActionCue[] = [];

    for (const step of steps) {
        if (step.kind !== 'code-action') {
            continue;
        }

        const block = blockById.get(step.blockId);
        const action = (block?.actions ?? []).find(
            (candidate) => candidate.id === step.actionId,
        );

        if (!block || !action) {
            continue;
        }

        const back = previousPageByBlock.get(block.id) ?? {
            code: block.content,
            highlightLines: null,
        };
        const show: CodePage = {
            code: action.code,
            highlightLines: action.highlightLines ?? null,
        };

        cues.push({ order: step.order, blockId: block.id, show, back });
        previousPageByBlock.set(block.id, show);
    }

    return cues;
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

export type FreeStep = {
    block: Block;
    /** Reveal step, or null when the block is always visible. */
    order: number | null;
};

/**
 * Free layout positions each block absolutely, so blocks reveal individually
 * rather than in shared step containers. This keeps content order (paint order)
 * while resolving each block's reveal step via the same step index. Blocks
 * pinned to the same node share an order and reveal together.
 */
export function flattenFreeSteps(
    blocks: Block[],
    steps: StepIndex,
): FreeStep[] {
    return blocks.map((block) => ({
        block,
        order:
            block.transition?.nodeId != null
                ? (steps.get(block.transition.nodeId) ?? null)
                : null,
    }));
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

    // Content stored before code actions existed has no `actions` key on its
    // blocks; normalise here (the shared entry point of editor, Presenter and
    // codegen) so every downstream consumer can rely on the array existing.
    for (const slide of nextContent.slides) {
        for (const blocks of Object.values(slide.slots)) {
            for (const block of blocks) {
                block.actions ??= [];
            }
        }
    }

    const nodesById = new Map(nextFlow.nodes.map((node) => [node.id, node]));
    const chainTargetBySource = new Map<string, string>();

    for (const edge of nextFlow.edges) {
        if (nodesById.get(edge.target)?.type === 'transition') {
            chainTargetBySource.set(edge.source, edge.target);
        }
    }

    // Ownership backfill: transition nodes built in the flow view before
    // `data.slideId` existed carry no owner. Whatever a slide's edge-chain
    // reaches today is claimed by that slide, so old graphs keep their steps
    // after the identity model change.
    for (const node of nextFlow.nodes) {
        if (node.type !== 'slide' || !node.data.slideId) {
            continue;
        }

        let cursor = chainTargetBySource.get(node.id);
        const seen = new Set<string>();

        while (cursor && !seen.has(cursor)) {
            seen.add(cursor);
            const transition = nodesById.get(cursor);

            if (
                transition?.type === 'transition' &&
                transition.data.slideId == null
            ) {
                transition.data.slideId = node.data.slideId;
            }

            cursor = chainTargetBySource.get(cursor);
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
                data: { label: null, slideId: slide.id },
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
