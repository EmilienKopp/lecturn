import { SvelteSet } from 'svelte/reactivity';
import {
    defaultFlowFromContent,
    migrateLegacyTransitions,
} from '@/lib/lecturn/flow-compiler';
import { layoutDefinitions } from '@/lib/lecturn/layouts';
import type {
    Block,
    BlockStyle,
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

    addSlide(layout: SlideLayout = 'center'): void {
        this.content.slides.push({
            id: `slide-${crypto.randomUUID()}`,
            layout,
            background: null,
            slots: {},
            config: null,
        });
        this.selectedSlideIndex = this.content.slides.length - 1;
        this.selectedBlockId = null;
        this.syncSlideNodes();
        this.dirty = true;
    }

    removeSlide(index: number): void {
        if (this.content.slides.length <= 1) {
            return;
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
     * one node; nodes whose slide is gone are removed together with their
     * transition chain and touching edges. Transition nodes are otherwise
     * never created or destroyed here — they live only in the flow.
     */
    syncSlideNodes(): void {
        const slideIds = new SvelteSet(
            this.content.slides.map((slide) => slide.id),
        );
        const removed = new SvelteSet<string>();

        for (const node of this.flow.nodes) {
            if (
                node.type === 'slide' &&
                (!node.data.slideId || !slideIds.has(node.data.slideId))
            ) {
                removed.add(node.id);
                let cursor = this.chainTargetId(node.id);

                while (cursor && !removed.has(cursor)) {
                    removed.add(cursor);
                    cursor = this.chainTargetId(cursor);
                }
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
    }

    /**
     * A pin only means something while its node sits in the block's own
     * slide's chain; once the node is deleted or re-wired elsewhere the
     * block falls back to static.
     */
    private reconcilePins(): void {
        for (const slide of this.content.slides) {
            const chain = new SvelteSet(
                this.transitionsForSlide(slide.id).map(
                    (transition) => transition.nodeId,
                ),
            );

            for (const blocks of Object.values(slide.slots)) {
                for (const block of blocks) {
                    if (
                        block.transition?.nodeId &&
                        !chain.has(block.transition.nodeId)
                    ) {
                        block.transition = null;
                        this.dirty = true;
                    }
                }
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

        if (
            target.type === 'transition' &&
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

        // Labels double as step names in the pin picker, so merging the
        // target's chain segment must not introduce a duplicate label.
        if (target.type === 'transition') {
            const merged = [
                ...this.chainLabelsThrough(sourceId),
                ...this.chainLabelsFrom(targetId),
            ].filter((label): label is string => label !== null);

            if (new SvelteSet(merged).size !== merged.length) {
                return false;
            }
        }

        this.flow.edges.push({
            id: `edge-${crypto.randomUUID()}`,
            source: sourceId,
            target: targetId,
            label: null,
        });
        this.dirty = true;

        return true;
    }

    removeEdge(edgeId: string): void {
        const before = this.flow.edges.length;
        this.flow.edges = this.flow.edges.filter((edge) => edge.id !== edgeId);

        if (this.flow.edges.length !== before) {
            this.reconcilePins();
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

    /** Returns false when the label would collide within the node's chain. */
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
            this.chainLabelsThrough(nodeId, nodeId).includes(label)
        ) {
            return false;
        }

        node.data.label = label;
        this.dirty = true;

        return true;
    }

    // ── Block pinning ───────────────────────────────────────────────────

    /** The slide's transition chain in reveal order. */
    transitionsForSlide(
        slideId: string,
    ): { nodeId: string; label: string | null; index: number }[] {
        const slideNode = this.flow.nodes.find(
            (node) => node.type === 'slide' && node.data.slideId === slideId,
        );

        if (!slideNode) {
            return [];
        }

        return this.chainMemberIds(slideNode.id).map((nodeId, index) => ({
            nodeId,
            label: this.findFlowNode(nodeId)?.data.label ?? null,
            index,
        }));
    }

    /**
     * Creates a transition node appended to the slide's chain and returns its
     * id, or null when the label would collide within the chain.
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

        const chain = this.chainMemberIds(slideNode.id);

        if (
            label !== null &&
            label !== '' &&
            chain.some(
                (nodeId) => this.findFlowNode(nodeId)?.data.label === label,
            )
        ) {
            return null;
        }

        const tailId = chain.at(-1) ?? slideNode.id;
        const node: MutableFlowNode = {
            id: `node-${crypto.randomUUID()}`,
            type: 'transition',
            position: {
                x: slideNode.position.x + 260,
                y: slideNode.position.y + chain.length * 80,
            },
            data: { label },
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
     * "Step N" when it belongs to a slide's chain, generic otherwise.
     */
    transitionPlaceholder(nodeId: string): string {
        const anchorId = this.chainAnchorId(nodeId);

        if (this.findFlowNode(anchorId)?.type !== 'slide') {
            return 'Transition';
        }

        const index = this.chainMemberIds(anchorId).indexOf(nodeId);

        return index === -1 ? 'Transition' : `Step ${index + 1}`;
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

    /** Transition node ids downstream of the anchor, in chain order. */
    private chainMemberIds(anchorId: string): string[] {
        const members: string[] = [];
        const visited = new SvelteSet<string>([anchorId]);
        let cursor = this.chainTargetId(anchorId);

        while (cursor && !visited.has(cursor)) {
            members.push(cursor);
            visited.add(cursor);
            cursor = this.chainTargetId(cursor);
        }

        return members;
    }

    /** Walks incoming chain edges back to the chain's first node. */
    private chainAnchorId(nodeId: string): string {
        let cursor = nodeId;
        const visited = new SvelteSet<string>([nodeId]);

        for (;;) {
            const incoming = this.flow.edges.find(
                (edge) => edge.target === cursor,
            );

            if (!incoming || visited.has(incoming.source)) {
                return cursor;
            }

            cursor = incoming.source;
            visited.add(cursor);

            if (this.findFlowNode(cursor)?.type === 'slide') {
                return cursor;
            }
        }
    }

    /** Labels across the entire chain containing the node, minus excludeId. */
    private chainLabelsThrough(
        nodeId: string,
        excludeId: string | null = null,
    ): (string | null)[] {
        const anchor =
            this.findFlowNode(nodeId)?.type === 'slide'
                ? nodeId
                : this.chainAnchorId(nodeId);
        const members =
            this.findFlowNode(anchor)?.type === 'slide'
                ? this.chainMemberIds(anchor)
                : [anchor, ...this.chainMemberIds(anchor)];

        return members
            .filter((memberId) => memberId !== excludeId)
            .map((memberId) => this.findFlowNode(memberId)?.data.label ?? null)
            .filter((label) => label !== null && label !== '');
    }

    /** Labels of the node itself plus its downstream chain segment. */
    private chainLabelsFrom(nodeId: string): (string | null)[] {
        return [nodeId, ...this.chainMemberIds(nodeId)]
            .map((memberId) => this.findFlowNode(memberId)?.data.label ?? null)
            .filter((label) => label !== null && label !== '');
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

    addFreeBlock(
        x: string,
        y: string,
        type: 'text' | 'code' | 'box',
    ): void {
        const block = this.addBlock('main', type);
        block.style.x = x;
        block.style.y = y;
        block.style.width = '30';

        if (type === 'code') {
            block.lang = 'typescript';
        }
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
        };

        const slide = this.selectedSlide;
        slide.slots[slot] = [...(slide.slots[slot] ?? []), block];
        this.selectedBlockId = block.id;
        this.dirty = true;

        return block;
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
