import type {
    Block,
    BlockStyle,
    PresentationContent,
    Slide,
    SlideLayout,
} from '@/types/generated';
import { layoutDefinitions } from '@/lib/lecturn/layouts';

/** Editor-side mutable mirror of the generated (readonly) content types. */
type Mutable<T> = { -readonly [K in keyof T]: Mutable<T[K]> };
type MutableContent = Mutable<PresentationContent>;
type MutableSlide = Mutable<Slide>;
type MutableBlock = Mutable<Block>;

export class EditorState {
    content = $state<MutableContent>() as MutableContent;
    selectedSlideIndex = $state(0);
    selectedBlockId = $state<string | null>(null);
    dirty = $state(false);

    constructor(content: PresentationContent) {
        // Inertia props arrive as $state proxies, which structuredClone
        // rejects; $state.snapshot produces a deep plain copy.
        this.content = $state.snapshot(content) as MutableContent;
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
        this.dirty = true;
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
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            slide.config = { rows: 3, cols: 3 } as any;
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
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        this.selectedSlide.config = { ...(this.selectedSlide.config ?? {}), ...config } as any;
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
                const block = blocks.find((candidate) => candidate.id === blockId);

                if (block) {
                    return block;
                }
            }
        }

        return null;
    }
}
