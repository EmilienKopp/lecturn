import { SvelteMap, SvelteSet } from 'svelte/reactivity';
import {
    codeActionsForBlock,
    defaultFlowFromContent,
    enabledSlideIds,
    migrateLegacyTransitions,
    transitionsForSlide,
} from '@/lib/tecturn/flow-compiler';
import { layoutDefinitions } from '@/lib/tecturn/layouts';
import type {
    Block,
    BlockStyle,
    CodeAction,
    FlowGraph,
    NodePosition,
    PresentationContent,
    Slide,
    SlideLayout,
} from '@/types/generated';

/** Editor-side mutable mirror of the generated (readonly) content types. */
type Mutable<T> = { -readonly [K in keyof T]: Mutable<T[K]> };
type MutableContent = Mutable<PresentationContent>;
type MutableSlide = Mutable<Slide>;
type MutableBlock = Mutable<Block>;
type MutableFlow = Mutable<FlowGraph>;
type MutableFlowNode = MutableFlow['nodes'][number];

export class EditorState {
    content = $state<MutableContent>() as MutableContent;
    flow = $state<MutableFlow>() as MutableFlow;
    selectedSlideIndex = $state(0);
    selectedBlockId = $state<string | null>(null);
    dirty = $state(false);

    constructor(content: PresentationContent, flow: FlowGraph | null = null) {
        // Inertia props arrive as $state proxies, which structuredClone
        // rejects; $state.snapshot produces a deep plain copy. Legacy
        // {order} block pins are migrated into chain nodes on the spot.
        const plainContent = $state.snapshot(content) as PresentationContent;
        const migrated = migrateLegacyTransitions(
            plainContent,
            $state.snapshot(
                flow ?? defaultFlowFromContent(plainContent),
            ) as FlowGraph,
        );

        this.content = migrated.content as MutableContent;
        this.flow = migrated.flow as MutableFlow;
        this.syncSlideNodes();
    }

    get selectedSlide(): MutableSlide {
        return this.content.slides[this.selectedSlideIndex];
    }

    get selectedBlock(): MutableBlock | null {
        if (this.selectedBlockId === null) {
            return null;
        }

        return this.findBlock(this.selectedBlockId);
    }

    addSlide(layout: SlideLayout = 'free'): void {
        const id = `slide-${crypto.randomUUID()}`;
        this.content.slides.push({
            id,
            layout,
            background: null,
            slots: {},
            config: null,
            title: null,
        });
        this.selectedSlideIndex = this.content.slides.length - 1;
        this.selectedBlockId = null;
        this.syncSlideNodes();

        // A wired deck gates playback on the nav chain, so a new slide has to
        // join it or it would be born disabled; splice it onto the tail by
        // order. Unwired decks stay implicit (every slide shown).
        if (this.hasNavEdges()) {
            this.enableSlide(id);
        }

        this.dirty = true;
    }

    removeSlide(index: number): void {
        if (this.content.slides.length <= 1) {
            return;
        }

        // Unlink the slide from the nav chain first so its neighbors bridge
        // (previous → next) instead of the tail falling off and disabling.
        if (this.hasNavEdges()) {
            this.disableSlide(this.content.slides[index].id);
        }

        this.content.slides.splice(index, 1);
        this.selectedSlideIndex = Math.min(
            this.selectedSlideIndex,
            this.content.slides.length - 1,
        );
        this.selectedBlockId = null;
        this.syncSlideNodes();
        this.dirty = true;
    }

    // ── Flow graph ──────────────────────────────────────────────────────

    /**
     * Reconciles slide nodes with content.slides: every slide gets exactly
     * one node; a slide node whose slide is gone is removed together with the
     * steps it owns and any touching edges. Transition nodes are otherwise
     * never created or destroyed here — they live only in the flow.
     */
    syncSlideNodes(): void {
        const slideIds = new SvelteSet(
            this.content.slides.map((slide) => slide.id),
        );
        const removed = new SvelteSet<string>();
        const removedSlideIds = new SvelteSet<string>();

        for (const node of this.flow.nodes) {
            if (
                node.type === 'slide' &&
                (!node.data.slideId || !slideIds.has(node.data.slideId))
            ) {
                removed.add(node.id);

                if (node.data.slideId) {
                    removedSlideIds.add(node.data.slideId);
                }
            }
        }

        for (const node of this.flow.nodes) {
            if (
                node.type === 'transition' &&
                node.data.slideId &&
                removedSlideIds.has(node.data.slideId)
            ) {
                removed.add(node.id);
            }
        }

        if (removed.size > 0) {
            this.flow.nodes = this.flow.nodes.filter(
                (node) => !removed.has(node.id),
            );
            this.flow.edges = this.flow.edges.filter(
                (edge) =>
                    !removed.has(edge.source) && !removed.has(edge.target),
            );
        }

        const represented = new SvelteSet(
            this.flow.nodes
                .filter((node) => node.type === 'slide')
                .map((node) => node.data.slideId),
        );
        let nextY =
            Math.max(0, ...this.flow.nodes.map((node) => node.position.y)) +
            160;

        for (const slide of this.content.slides) {
            if (!represented.has(slide.id)) {
                this.flow.nodes.push({
                    id: `node-${slide.id}`,
                    type: 'slide',
                    position: { x: 0, y: nextY },
                    data: { slideId: slide.id },
                });
                nextY += 160;
            }
        }

        this.reconcilePins();
        this.reconcileCodeActionNodes();
    }

    /**
     * A pin only means something while its step still belongs to the block's
     * slide. Steps are owned by `data.slideId`, not by edges, so deleting an
     * edge never unpins a block — only deleting the step node, or moving it to
     * another slide, drops the pin back to static.
     */
    private reconcilePins(): void {
        for (const slide of this.content.slides) {
            const owned = new SvelteSet(
                this.flow.nodes
                    .filter(
                        (node) =>
                            node.type === 'transition' &&
                            node.data.slideId === slide.id,
                    )
                    .map((node) => node.id),
            );

            for (const blocks of Object.values(slide.slots)) {
                for (const block of blocks) {
                    if (
                        block.transition?.nodeId &&
                        !owned.has(block.transition.nodeId)
                    ) {
                        block.transition = null;
                        this.dirty = true;
                    }
                }
            }
        }
    }

    /**
     * Code-action nodes and block action payloads are created and destroyed
     * together (addCodeAction/removeCodeAction), but content and flow save
     * separately, so a reload can surface a mismatch: a node whose block or
     * action is gone is dropped; an action whose node is gone gets a fresh
     * node appended to its block's chain.
     */
    private reconcileCodeActionNodes(): void {
        const actionOwners = new SvelteMap<
            string,
            { slideId: string; blockId: string }
        >();

        for (const slide of this.content.slides) {
            for (const blocks of Object.values(slide.slots)) {
                for (const block of blocks) {
                    for (const action of block.actions ?? []) {
                        actionOwners.set(action.id, {
                            slideId: slide.id,
                            blockId: block.id,
                        });
                    }
                }
            }
        }

        const removed = new SvelteSet<string>();
        const represented = new SvelteSet<string>();

        for (const node of this.flow.nodes) {
            if (node.type !== 'code-action') {
                continue;
            }

            const owner = node.data.actionId
                ? actionOwners.get(node.data.actionId)
                : undefined;

            if (!owner || owner.blockId !== node.data.blockId) {
                removed.add(node.id);
            } else if (node.data.actionId) {
                represented.add(node.data.actionId);
            }
        }

        if (removed.size > 0) {
            this.flow.nodes = this.flow.nodes.filter(
                (node) => !removed.has(node.id),
            );
            this.flow.edges = this.flow.edges.filter(
                (edge) =>
                    !removed.has(edge.source) && !removed.has(edge.target),
            );
            this.dirty = true;
        }

        for (const [actionId, owner] of actionOwners) {
            if (!represented.has(actionId)) {
                this.pushCodeActionNode(owner.slideId, owner.blockId, actionId);
                this.dirty = true;
            }
        }
    }

    addSlideNodeAt(position: NodePosition): void {
        this.addSlide();
        const slide = this.content.slides[this.content.slides.length - 1];
        const node = this.flow.nodes.find(
            (candidate) => candidate.data.slideId === slide.id,
        );

        if (node) {
            node.position = { ...position };
        }
    }

    addTransitionNode(position: NodePosition): void {
        this.flow.nodes.push({
            id: `node-${crypto.randomUUID()}`,
            type: 'transition',
            position: { ...position },
            data: { label: null },
        });
        this.dirty = true;
    }

    removeFlowNode(nodeId: string): void {
        const node = this.findFlowNode(nodeId);

        if (!node) {
            return;
        }

        if (node.type === 'slide') {
            const index = this.content.slides.findIndex(
                (slide) => slide.id === node.data.slideId,
            );

            if (index !== -1) {
                // Delegates to removeSlide, which honors the last-slide
                // guard and re-syncs nodes (removing the chain too).
                this.removeSlide(index);
            }

            return;
        }

        // Deleting a code-action node deletes the page itself — the node is
        // the page's handle, so the payload must not silently linger.
        if (node.type === 'code-action') {
            if (node.data.blockId && node.data.actionId) {
                this.removeCodeAction(node.data.blockId, node.data.actionId);
            }

            return;
        }

        this.flow.nodes = this.flow.nodes.filter(
            (candidate) => candidate.id !== nodeId,
        );
        this.flow.edges = this.flow.edges.filter(
            (edge) => edge.source !== nodeId && edge.target !== nodeId,
        );
        this.reconcilePins();
        this.dirty = true;
    }

    /**
     * Two-lane rules: a slide may have one navigation edge (to a slide) and
     * one chain-start edge (to a transition); a transition may have one
     * outgoing edge, to a transition only; a transition accepts one incoming
     * edge. Returns false when the connection would break an invariant.
     */
    connect(sourceId: string, targetId: string): boolean {
        const source = this.findFlowNode(sourceId);
        const target = this.findFlowNode(targetId);

        if (!source || !target || sourceId === targetId) {
            return false;
        }

        if (source.type === 'transition' && target.type === 'slide') {
            return false;
        }

        // Code-action chains only order the pages of one code block, so both
        // ends must be code-action nodes of the same block.
        if (
            (source.type === 'code-action' || target.type === 'code-action') &&
            (source.type !== target.type ||
                source.data.blockId !== target.data.blockId)
        ) {
            return false;
        }

        if (
            target.type !== 'slide' &&
            this.flow.edges.some((edge) => edge.target === targetId)
        ) {
            return false;
        }

        const sameLane = this.flow.edges.some(
            (edge) =>
                edge.source === sourceId &&
                this.findFlowNode(edge.target)?.type === target.type,
        );

        if (sameLane) {
            return false;
        }

        // Wiring a step onto a slide (or another step) makes it, and the chain
        // hanging off it, belong to that slide. Labels double as step names, so
        // the move must not collide with a label the slide already owns.
        const ownerSlideId = source.data.slideId ?? null;

        if (target.type === 'transition' && ownerSlideId) {
            const moving = new SvelteSet(this.downstreamChainIds(targetId));
            const existing = this.flow.nodes
                .filter(
                    (node) =>
                        node.type === 'transition' &&
                        node.data.slideId === ownerSlideId &&
                        !moving.has(node.id),
                )
                .map((node) => node.data.label ?? null);
            const incoming = [...moving].map(
                (id) => this.findFlowNode(id)?.data.label ?? null,
            );
            const labels = [...existing, ...incoming].filter(
                (label): label is string => label !== null && label !== '',
            );

            if (new SvelteSet(labels).size !== labels.length) {
                return false;
            }
        }

        this.flow.edges.push({
            id: `edge-${crypto.randomUUID()}`,
            source: sourceId,
            target: targetId,
            label: null,
        });

        if (target.type === 'transition' && ownerSlideId) {
            this.assignSlideIdDownstream(targetId, ownerSlideId);
            this.reconcilePins();
        }

        this.dirty = true;

        return true;
    }

    removeEdge(edgeId: string): void {
        const before = this.flow.edges.length;
        this.flow.edges = this.flow.edges.filter((edge) => edge.id !== edgeId);

        // Edges only order steps; ownership lives on the node, so a removed
        // edge never changes which steps a slide has or unpins any block. The
        // now-unwired step simply reorders by its canvas position.
        if (this.flow.edges.length !== before) {
            this.dirty = true;
        }
    }

    moveNode(nodeId: string, position: NodePosition): void {
        const node = this.findFlowNode(nodeId);

        if (node) {
            node.position = { ...position };
            this.dirty = true;
        }
    }

    /** Returns false when the label collides with another step on the slide. */
    setTransitionLabel(nodeId: string, label: string | null): boolean {
        const node = this.findFlowNode(nodeId);

        if (!node || node.type !== 'transition') {
            return false;
        }

        if (node.data.label === label) {
            return true;
        }

        if (
            label !== null &&
            label !== '' &&
            node.data.slideId &&
            this.flow.nodes.some(
                (candidate) =>
                    candidate.type === 'transition' &&
                    candidate.id !== nodeId &&
                    candidate.data.slideId === node.data.slideId &&
                    candidate.data.label === label,
            )
        ) {
            return false;
        }

        node.data.label = label;
        this.dirty = true;

        return true;
    }

    // ── Slide enablement ────────────────────────────────────────────────

    /**
     * A slide is part of the show when it is the entry (first in order) or has
     * an incoming navigation edge. A deck with no nav edges is fully enabled
     * (see flow-compiler's enabledSlideIds), so this only starts gating once the
     * chain is in use.
     */
    isSlideEnabled(slideId: string): boolean {
        return enabledSlideIds(this.content, this.flow).has(slideId);
    }

    /** The first slide is the deck entry and can never be disabled. */
    isEntrySlide(slideId: string): boolean {
        return this.content.slides[0]?.id === slideId;
    }

    /** True once any slide→slide edge exists, i.e. the nav chain is in use. */
    private hasNavEdges(): boolean {
        return this.flow.edges.some(
            (edge) =>
                this.isSlideNode(edge.source) && this.isSlideNode(edge.target),
        );
    }

    private isSlideNode(nodeId: string): boolean {
        return this.findFlowNode(nodeId)?.type === 'slide';
    }

    private slideNodeFor(slideId: string): MutableFlowNode | null {
        return (
            this.flow.nodes.find(
                (node) =>
                    node.type === 'slide' && node.data.slideId === slideId,
            ) ?? null
        );
    }

    private navOutEdge(nodeId: string): MutableFlow['edges'][number] | null {
        return (
            this.flow.edges.find(
                (edge) =>
                    edge.source === nodeId && this.isSlideNode(edge.target),
            ) ?? null
        );
    }

    private navInEdges(nodeId: string): MutableFlow['edges'] {
        return this.flow.edges.filter(
            (edge) => edge.target === nodeId && this.isSlideNode(edge.source),
        );
    }

    private pushNavEdge(sourceId: string, targetId: string): void {
        this.flow.edges.push({
            id: `edge-${crypto.randomUUID()}`,
            source: sourceId,
            target: targetId,
            label: null,
        });
    }

    /**
     * Turns the implicit "every slide in order" deck into explicit slide→slide
     * edges. Only runs on an unwired deck, so it never clobbers author edges;
     * it's the bridge from legacy content order to the nav-chain model.
     */
    private materializeChain(): void {
        if (this.hasNavEdges()) {
            return;
        }

        for (let i = 0; i < this.content.slides.length - 1; i++) {
            const from = this.slideNodeFor(this.content.slides[i].id);
            const to = this.slideNodeFor(this.content.slides[i + 1].id);

            if (from && to) {
                this.pushNavEdge(from.id, to.id);
            }
        }
    }

    /** Enables or disables the slide at an index; the entry slide is a no-op. */
    toggleSlideEnabled(index: number): void {
        const slide = this.content.slides[index];

        if (!slide || this.isEntrySlide(slide.id)) {
            return;
        }

        // Disabling means dropping out of the chain, which only exists once
        // it's explicit — materialize the implied order first.
        this.materializeChain();

        if (this.isSlideEnabled(slide.id)) {
            this.disableSlide(slide.id);
        } else {
            this.enableSlide(slide.id);
        }

        this.dirty = true;
    }

    /** Unlinks a slide from the nav chain, bridging its neighbors. */
    private disableSlide(slideId: string): void {
        const node = this.slideNodeFor(slideId);

        if (!node) {
            return;
        }

        const navIn = this.navInEdges(node.id);
        const navOut = this.navOutEdge(node.id);
        const nextTargetId = navOut?.target ?? null;
        const removed = new SvelteSet(
            [...navIn, ...(navOut ? [navOut] : [])].map((edge) => edge.id),
        );

        // Reconnect whatever pointed here to whatever this pointed at, so the
        // chain stays continuous and nothing downstream disables by accident.
        if (nextTargetId) {
            for (const edge of navIn) {
                if (edge.source === nextTargetId) {
                    continue;
                }

                const bridged = this.flow.edges.some(
                    (candidate) =>
                        !removed.has(candidate.id) &&
                        candidate.source === edge.source &&
                        candidate.target === nextTargetId,
                );

                if (!bridged) {
                    this.pushNavEdge(edge.source, nextTargetId);
                }
            }
        }

        this.flow.edges = this.flow.edges.filter(
            (edge) => !removed.has(edge.id),
        );
    }

    /**
     * Splices a slide back into the chain by content order: its nearest enabled
     * predecessor points to it, and it inherits that predecessor's old target
     * (the next slide) — so order alone rebuilds the edges.
     */
    private enableSlide(slideId: string): void {
        const index = this.content.slides.findIndex(
            (slide) => slide.id === slideId,
        );

        if (index <= 0) {
            return;
        }

        const enabled = enabledSlideIds(this.content, this.flow);
        let predecessor: string | null = null;

        for (let i = index - 1; i >= 0; i--) {
            if (enabled.has(this.content.slides[i].id)) {
                predecessor = this.content.slides[i].id;
                break;
            }
        }

        const predecessorNode = predecessor
            ? this.slideNodeFor(predecessor)
            : null;
        const node = this.slideNodeFor(slideId);

        if (!predecessorNode || !node) {
            return;
        }

        const inherited = this.navOutEdge(predecessorNode.id);
        const inheritedTargetId = inherited?.target ?? null;
        const removed = new SvelteSet(
            [inherited, this.navOutEdge(node.id)]
                .filter(
                    (edge): edge is NonNullable<typeof edge> => edge != null,
                )
                .map((edge) => edge.id),
        );

        this.flow.edges = this.flow.edges.filter(
            (edge) => !removed.has(edge.id),
        );
        this.pushNavEdge(predecessorNode.id, node.id);

        if (inheritedTargetId && inheritedTargetId !== node.id) {
            this.pushNavEdge(node.id, inheritedTargetId);
        }
    }

    // ── Block pinning ───────────────────────────────────────────────────

    /** The slide's reveal steps in order (see flow-compiler transitionsForSlide). */
    transitionsForSlide(
        slideId: string,
    ): { nodeId: string; label: string | null; index: number }[] {
        return transitionsForSlide(this.flow, slideId).map(
            (transition, index) => ({
                nodeId: transition.nodeId,
                label: transition.label,
                index,
            }),
        );
    }

    /**
     * Creates a step owned by the slide, wired onto the end of its current
     * order, and returns its id — or null when the label would collide with a
     * step the slide already owns.
     */
    appendTransitionToSlide(
        slideId: string,
        label: string | null = null,
    ): string | null {
        const slideNode = this.flow.nodes.find(
            (node) => node.type === 'slide' && node.data.slideId === slideId,
        );

        if (!slideNode) {
            return null;
        }

        const steps = this.transitionsForSlide(slideId);

        if (
            label !== null &&
            label !== '' &&
            steps.some((step) => step.label === label)
        ) {
            return null;
        }

        const tailId = steps.at(-1)?.nodeId ?? slideNode.id;
        const tailNode = this.findFlowNode(tailId) ?? slideNode;
        const node: MutableFlowNode = {
            id: `node-${crypto.randomUUID()}`,
            type: 'transition',
            // Sits below the current tail so the position fallback keeps the
            // same order once an edge is removed.
            position: {
                x: slideNode.position.x + 260,
                y: tailNode.position.y + 80,
            },
            data: { label, slideId },
        };

        this.flow.nodes.push(node);
        this.flow.edges.push({
            id: `edge-${crypto.randomUUID()}`,
            source: tailId,
            target: node.id,
            label: null,
        });
        this.dirty = true;

        return node.id;
    }

    /** Pins a block to a transition in its slide's chain; null unpins. */
    pinBlock(blockId: string, nodeId: string | null): boolean {
        const slide = this.slideContainingBlock(blockId);
        const block = this.findBlock(blockId);

        if (!slide || !block) {
            return false;
        }

        if (nodeId === null) {
            if (block.transition !== null) {
                block.transition = null;
                this.dirty = true;
            }

            return true;
        }

        const inChain = this.transitionsForSlide(slide.id).some(
            (transition) => transition.nodeId === nodeId,
        );

        if (!inChain) {
            return false;
        }

        if (block.transition?.nodeId !== nodeId) {
            block.transition = { nodeId, order: null };
            this.dirty = true;
        }

        return true;
    }

    /** Display name for a chain step: its label, or its 1-based position. */
    transitionDisplayName(transition: {
        label: string | null;
        index: number;
    }): string {
        return transition.label ?? `Step ${transition.index + 1}`;
    }

    /**
     * Fallback name for an unlabeled transition node in the flow view:
     * "Step N" when it belongs to a slide, generic otherwise.
     */
    transitionPlaceholder(nodeId: string): string {
        const node = this.findFlowNode(nodeId);

        if (!node || node.type !== 'transition' || !node.data.slideId) {
            return 'Transition';
        }

        const index = this.transitionsForSlide(node.data.slideId).findIndex(
            (step) => step.nodeId === nodeId,
        );

        return index === -1 ? 'Transition' : `Step ${index + 1}`;
    }

    // ── Code actions ────────────────────────────────────────────────────

    /**
     * The block's action pages in play order (chain edges, canvas-position
     * fallback), joined with their payloads. Nodes whose payload is gone are
     * skipped; reconciliation removes them on the next sync.
     */
    codeActionsForBlock(
        blockId: string,
    ): { nodeId: string; action: Mutable<CodeAction>; index: number }[] {
        const block = this.findBlock(blockId);

        if (!block) {
            return [];
        }

        const actionsById = new SvelteMap(
            (block.actions ?? []).map((action) => [action.id, action]),
        );
        const pages: {
            nodeId: string;
            action: Mutable<CodeAction>;
            index: number;
        }[] = [];

        for (const entry of codeActionsForBlock(this.flow, blockId)) {
            const action = actionsById.get(entry.actionId);

            if (action) {
                pages.push({
                    nodeId: entry.nodeId,
                    action,
                    index: pages.length,
                });
            }
        }

        return pages;
    }

    /**
     * Appends a new page to the block's sequence: the payload lands on the
     * block, its ordering node joins the flow chained after the last page
     * (anchored under the block's pinned transition, or its slide node).
     * Returns the new action id, or null when the block cannot hold pages.
     */
    addCodeAction(blockId: string): string | null {
        const slide = this.slideContainingBlock(blockId);
        const block = this.findBlock(blockId);

        if (!slide || !block || block.type !== 'code') {
            return null;
        }

        const lastPage = this.codeActionsForBlock(blockId).at(-1);
        const action: Mutable<CodeAction> = {
            id: `action-${crypto.randomUUID()}`,
            code: lastPage?.action.code ?? block.content,
            highlightLines: null,
            label: null,
        };

        block.actions = [...(block.actions ?? []), action];
        this.pushCodeActionNode(slide.id, blockId, action.id);
        this.dirty = true;

        return action.id;
    }

    updateCodeAction(
        blockId: string,
        actionId: string,
        patch: Partial<Pick<CodeAction, 'code' | 'highlightLines' | 'label'>>,
    ): void {
        const block = this.findBlock(blockId);
        const action = (block?.actions ?? []).find(
            (candidate) => candidate.id === actionId,
        );

        if (!action) {
            return;
        }

        for (const [key, value] of Object.entries(patch) as [
            keyof typeof patch,
            string | null,
        ][]) {
            if (action[key] !== value) {
                action[key] = value as never;
                this.dirty = true;
            }
        }
    }

    /** Removes a page and its node, bridging the chain around the gap. */
    removeCodeAction(blockId: string, actionId: string): void {
        const block = this.findBlock(blockId);
        const index = (block?.actions ?? []).findIndex(
            (candidate) => candidate.id === actionId,
        );

        if (block && index !== -1) {
            block.actions.splice(index, 1);
            this.dirty = true;
        }

        const node = this.flow.nodes.find(
            (candidate) =>
                candidate.type === 'code-action' &&
                candidate.data.actionId === actionId,
        );

        if (!node) {
            return;
        }

        const incoming = this.flow.edges.find(
            (edge) => edge.target === node.id,
        );
        const outgoing = this.flow.edges.find(
            (edge) => edge.source === node.id,
        );

        this.flow.nodes = this.flow.nodes.filter(
            (candidate) => candidate.id !== node.id,
        );
        this.flow.edges = this.flow.edges.filter(
            (edge) => edge.source !== node.id && edge.target !== node.id,
        );

        if (incoming && outgoing) {
            this.flow.edges.push({
                id: `edge-${crypto.randomUUID()}`,
                source: incoming.source,
                target: outgoing.target,
                label: null,
            });
        }

        this.dirty = true;
    }

    /** Swaps a page with its neighbor by rewiring (and repositioning) the chain. */
    moveCodeAction(
        blockId: string,
        actionId: string,
        direction: 'up' | 'down',
    ): boolean {
        const pages = this.codeActionsForBlock(blockId);
        const index = pages.findIndex((page) => page.action.id === actionId);
        const targetIndex = index + (direction === 'up' ? -1 : 1);

        if (index === -1 || targetIndex < 0 || targetIndex >= pages.length) {
            return false;
        }

        const ordered = pages.map((page) => page.nodeId);
        [ordered[index], ordered[targetIndex]] = [
            ordered[targetIndex],
            ordered[index],
        ];

        // Swap canvas positions too, so the chart mirrors the new order even
        // once an edge is removed (position is the unwired fallback order).
        const a = this.findFlowNode(pages[index].nodeId);
        const b = this.findFlowNode(pages[targetIndex].nodeId);

        if (a && b) {
            [a.position, b.position] = [b.position, a.position];
        }

        const owned = new SvelteSet(
            this.flow.nodes
                .filter(
                    (node) =>
                        node.type === 'code-action' &&
                        node.data.blockId === blockId,
                )
                .map((node) => node.id),
        );

        this.flow.edges = this.flow.edges.filter(
            (edge) => !(owned.has(edge.source) && owned.has(edge.target)),
        );

        for (let i = 0; i < ordered.length - 1; i++) {
            this.flow.edges.push({
                id: `edge-${crypto.randomUUID()}`,
                source: ordered[i],
                target: ordered[i + 1],
                label: null,
            });
        }

        this.dirty = true;

        return true;
    }

    /** Fallback name for a code-action node in the flow view: "Page N". */
    codeActionPlaceholder(nodeId: string): string {
        const node = this.findFlowNode(nodeId);

        if (!node || node.type !== 'code-action' || !node.data.blockId) {
            return 'Code action';
        }

        const index = this.codeActionsForBlock(node.data.blockId).findIndex(
            (page) => page.nodeId === nodeId,
        );

        return index === -1 ? 'Code action' : `Page ${index + 1}`;
    }

    /** The node a block's action chain visually hangs from on the flow chart. */
    codeActionAnchorNodeId(blockId: string): string | null {
        const slide = this.slideContainingBlock(blockId);
        const block = this.findBlock(blockId);

        if (!slide || !block) {
            return null;
        }

        const pinned = block.transition?.nodeId
            ? this.findFlowNode(block.transition.nodeId)
            : null;

        return (pinned ?? this.slideNodeFor(slide.id))?.id ?? null;
    }

    private pushCodeActionNode(
        slideId: string,
        blockId: string,
        actionId: string,
    ): void {
        const tail = this.codeActionsForBlock(blockId).at(-1);
        const tailNode = tail ? this.findFlowNode(tail.nodeId) : null;
        const anchorId = this.codeActionAnchorNodeId(blockId);
        const anchorNode = anchorId ? this.findFlowNode(anchorId) : null;
        const base = tailNode ?? anchorNode;
        const position = base
            ? {
                  x: base.position.x + (tailNode ? 0 : 260),
                  y: base.position.y + 80,
              }
            : { x: 0, y: 0 };
        const node: MutableFlowNode = {
            id: `node-${crypto.randomUUID()}`,
            type: 'code-action',
            position,
            data: { slideId, blockId, actionId },
        };

        this.flow.nodes.push(node);

        if (tail) {
            this.flow.edges.push({
                id: `edge-${crypto.randomUUID()}`,
                source: tail.nodeId,
                target: node.id,
                label: null,
            });
        }
    }

    slideForNode(nodeId: string): MutableSlide | null {
        const node = this.findFlowNode(nodeId);

        if (!node || node.type !== 'slide') {
            return null;
        }

        return (
            this.content.slides.find(
                (slide) => slide.id === node.data.slideId,
            ) ?? null
        );
    }

    private findFlowNode(nodeId: string): MutableFlowNode | null {
        return (
            this.flow.nodes.find((candidate) => candidate.id === nodeId) ?? null
        );
    }

    private chainTargetId(sourceId: string): string | null {
        const edge = this.flow.edges.find(
            (candidate) =>
                candidate.source === sourceId &&
                this.findFlowNode(candidate.target)?.type === 'transition',
        );

        return edge?.target ?? null;
    }

    /** The node itself plus every transition reachable by following chain edges. */
    private downstreamChainIds(startId: string): string[] {
        const ids: string[] = [];
        const visited = new SvelteSet<string>();
        let cursor: string | null = startId;

        while (cursor && !visited.has(cursor)) {
            visited.add(cursor);
            ids.push(cursor);
            cursor = this.chainTargetId(cursor);
        }

        return ids;
    }

    /** Claims a step and its downstream chain for a slide. */
    private assignSlideIdDownstream(startId: string, slideId: string): void {
        for (const id of this.downstreamChainIds(startId)) {
            const node = this.findFlowNode(id);

            if (node && node.type === 'transition') {
                node.data.slideId = slideId;
            }
        }
    }

    private slideContainingBlock(blockId: string): MutableSlide | null {
        for (const slide of this.content.slides) {
            for (const blocks of Object.values(slide.slots)) {
                if (blocks.some((block) => block.id === blockId)) {
                    return slide;
                }
            }
        }

        return null;
    }

    selectSlide(index: number): void {
        this.selectedSlideIndex = index;
        this.selectedBlockId = null;
    }

    setLayout(layout: SlideLayout): void {
        const slide = this.selectedSlide;

        if (slide.layout === layout) {
            return;
        }

        // Blocks living in slots the new layout doesn't define are merged
        // into the new layout's first slot so no content is lost.
        const newSlots = layoutDefinitions[layout].slots;
        const orphaned: MutableBlock[] = [];
        const remapped: Record<string, MutableBlock[]> = {};

        for (const [slotName, blocks] of Object.entries(slide.slots)) {
            if (newSlots.includes(slotName)) {
                remapped[slotName] = blocks;
            } else {
                orphaned.push(...blocks);
            }
        }

        if (orphaned.length > 0) {
            const firstSlot = newSlots[0];
            remapped[firstSlot] = [...(remapped[firstSlot] ?? []), ...orphaned];
        }

        slide.layout = layout;
        slide.slots = remapped;

        if (layout === 'custom-grid' && slide.config === null) {
            slide.config = { rows: 3, cols: 3 } as any;
        }

        // Free layout positions blocks absolutely; cascade any block arriving
        // without coordinates so incoming content doesn't stack invisibly.
        if (layout === 'free') {
            let cascade = 0;

            for (const block of slide.slots['main'] ?? []) {
                if (block.style.x === null) {
                    block.style.x = '10';
                    block.style.y = String(10 + cascade * 12);
                    block.style.width ??= '30';
                    cascade += 1;
                }
            }
        }

        // Rich-text layout owns the entire slide; replace content with a single richtext block.
        if (layout === 'rich-text') {
            slide.slots = { main: [this.buildRichtextBlock()] };
            this.selectedBlockId = null;
        }

        this.dirty = true;
    }

    setBackground(background: string | null): void {
        this.selectedSlide.background = background;
        this.dirty = true;
    }

    /** Names the current slide; an empty title falls back to "Slide N". */
    setSlideTitle(title: string | null): void {
        const next = title && title.trim() !== '' ? title.trim() : null;

        if (this.selectedSlide.title === next) {
            return;
        }

        this.selectedSlide.title = next;
        this.dirty = true;
    }

    get backgroundImage(): string | null {
        return this.content.backgroundImage ?? null;
    }

    /** Deck-wide background image URL; shown behind slides without their own color. */
    setBackgroundImage(url: string | null): void {
        this.content.backgroundImage = url;
        this.dirty = true;
    }

    /** Applies the current slide's background to every slide in the deck. */
    applyBackgroundToAllSlides(): void {
        const background = this.selectedSlide.background;

        for (const slide of this.content.slides) {
            slide.background = background;
        }

        this.dirty = true;
    }

    updateSlideConfig(config: Record<string, unknown>): void {
        this.selectedSlide.config = {
            ...(this.selectedSlide.config ?? {}),
            ...config,
        } as any;
        this.dirty = true;
    }

    addRichtextBlock(slot: string): void {
        const block = this.buildRichtextBlock();
        const slide = this.selectedSlide;
        slide.slots[slot] = [block];
        this.selectedBlockId = null;
        this.dirty = true;
    }

    addTextBlock(slot: string): void {
        this.addBlock(slot, 'text');
    }

    addCodeBlock(slot: string): void {
        const block = this.addBlock(slot, 'code');
        block.lang = 'typescript';
    }

    addBoxBlock(slot: string): void {
        this.addBlock(slot, 'box');
    }

    addGridBlock(
        slot: string,
        gridColumn: string,
        gridRow: string,
        type: 'text' | 'code' | 'box',
    ): void {
        const block = this.addBlock(slot, type);
        block.style.gridColumn = gridColumn;
        block.style.gridRow = gridRow;

        if (type === 'code') {
            block.lang = 'typescript';
        }
    }

    addFreeBlock(x: string, y: string, type: 'text' | 'code' | 'box'): void {
        const block = this.addBlock('main', type);
        block.style.x = x;
        block.style.y = y;
        block.style.width = '30';

        if (type === 'code') {
            block.lang = 'typescript';
        }
    }

    addFreeImageBlock(x: string, y: string, src: string): void {
        const block = this.addBlock('main', 'image');
        block.style.x = x;
        block.style.y = y;
        block.style.width = '30';
        block.style.height = '25';
        block.src = src;
    }

    updateBlockContent(blockId: string, content: string): void {
        const block = this.findBlock(blockId);

        if (block && block.content !== content) {
            block.content = content;
            this.dirty = true;
        }
    }

    updateBlockStyle(blockId: string, style: Partial<BlockStyle>): void {
        const block = this.findBlock(blockId);

        if (block) {
            block.style = { ...block.style, ...style } as MutableBlock['style'];
            this.dirty = true;
        }
    }

    updateBlockLang(blockId: string, lang: string | null): void {
        const block = this.findBlock(blockId);

        if (block) {
            block.lang = lang;
            this.dirty = true;
        }
    }

    updateBlockAlt(blockId: string, alt: string): void {
        const block = this.findBlock(blockId);

        if (block) {
            block.alt = alt;
            this.dirty = true;
        }
    }

    removeBlock(blockId: string): void {
        for (const slide of this.content.slides) {
            for (const [slotName, blocks] of Object.entries(slide.slots)) {
                const index = blocks.findIndex((block) => block.id === blockId);

                if (index !== -1) {
                    blocks.splice(index, 1);

                    if (blocks.length === 0) {
                        delete slide.slots[slotName];
                    }

                    if (this.selectedBlockId === blockId) {
                        this.selectedBlockId = null;
                    }

                    // A removed code block takes its action pages' nodes
                    // (and their edges) with it.
                    this.reconcileCodeActionNodes();
                    this.dirty = true;

                    return;
                }
            }
        }
    }

    private buildRichtextBlock(): MutableBlock {
        return {
            id: `block-${crypto.randomUUID()}`,
            type: 'richtext',
            content: '',
            style: {
                fontSize: null,
                fontWeight: null,
                color: null,
                borderColor: null,
                backgroundColor: null,
                gridColumn: null,
                gridRow: null,
                x: null,
                y: null,
                width: null,
                height: null,
            },
            transition: null,
            lang: null,
            src: null,
            alt: null,
            actions: [],
        };
    }

    private addBlock(slot: string, type: string): MutableBlock {
        const block: MutableBlock = {
            id: `block-${crypto.randomUUID()}`,
            type,
            content: '',
            style: {
                fontSize: null,
                fontWeight: null,
                color: null,
                borderColor: null,
                backgroundColor: null,
                gridColumn: null,
                gridRow: null,
                x: null,
                y: null,
                width: null,
                height: null,
            },
            transition: null,
            lang: null,
            src: null,
            alt: null,
            actions: [],
        };

        const slide = this.selectedSlide;

        // An empty slots map round-trips through the backend as a JSON [] and
        // arrives here as an array; assigning a named slot key to it would be
        // dropped by JSON.stringify on save. Normalise to a plain object first.
        if (Array.isArray(slide.slots)) {
            slide.slots = {};
        }

        slide.slots[slot] = [...(slide.slots[slot] ?? []), block];
        this.selectedBlockId = block.id;
        this.dirty = true;

        return block;
    }

    /** Public lookup for surfaces that address a block by id (e.g. the sequence modal). */
    blockById(blockId: string): MutableBlock | null {
        return this.findBlock(blockId);
    }

    private findBlock(blockId: string): MutableBlock | null {
        for (const slide of this.content.slides) {
            for (const blocks of Object.values(slide.slots)) {
                const block = blocks.find(
                    (candidate) => candidate.id === blockId,
                );

                if (block) {
                    return block;
                }
            }
        }

        return null;
    }
}
