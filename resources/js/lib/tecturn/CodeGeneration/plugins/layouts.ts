import type { Block, Slide } from '@/types/generated';
import { flattenFreeSteps, groupBlocksIntoSteps } from '../../flow-compiler.ts';
import { FREE_DEFAULTS } from '../../free-drag.ts';
import { layoutDefinitions } from '../../layouts.ts';
import type {
    CodegenPlugin,
    LayoutRendererPlugin,
    RenderContext,
} from '../contracts.ts';
import { INDENT, escapeAttribute } from '../support.ts';

const renderSlot = (
    slotName: string,
    blocks: Block[],
    depth: number,
    rc: RenderContext,
): string => {
    const pad = INDENT.repeat(depth);
    const lines: string[] = [`${pad}<div class="slot-${slotName}">`];
    const { staticBlocks, stepGroups } = groupBlocksIntoSteps(blocks, rc.steps);

    for (const block of staticBlocks) {
        lines.push(rc.renderBlock(block, depth + 1));
        lines.push(...rc.renderBlockActions(block, depth + 1));
    }

    // Blocks pinned to the same node reveal together: one <Transition> per
    // node, ordered by chain position (order maps to data-fragment-index,
    // which also keeps steps in sync across slots). A code block's <Action>
    // fragments nest inside its transition, mirroring the Animotion idiom.
    for (const group of stepGroups) {
        rc.use('Transition');
        lines.push(
            `${INDENT.repeat(depth + 1)}<Transition order={${group.order}}>`,
        );

        for (const block of group.blocks) {
            lines.push(rc.renderBlock(block, depth + 2));
            lines.push(...rc.renderBlockActions(block, depth + 2));
        }

        lines.push(`${INDENT.repeat(depth + 1)}</Transition>`);
    }

    lines.push(`${pad}</div>`);

    return lines.join('\n');
};

/**
 * A classic slot-grid layout: one `.slot-*` wrapper per slot defined in
 * layoutDefinitions, blocks grouped into <Transition> steps within each.
 */
export class SlotLayoutRenderer implements LayoutRendererPlugin {
    readonly layout: string;
    readonly css: string;

    constructor(layout: string, css: string) {
        this.layout = layout;
        this.css = css;
    }

    render(slide: Slide, rc: RenderContext): string[] {
        const definition = layoutDefinitions[slide.layout];

        if (!definition) {
            throw new Error(
                `Layout "${slide.layout}" has no slot definition in layouts.ts.`,
            );
        }

        const lines: string[] = [];

        for (const slotName of definition.slots) {
            const blocks = slide.slots[slotName];

            if (blocks && blocks.length > 0) {
                lines.push(renderSlot(slotName, blocks, 2, rc));
            }
        }

        return lines;
    }
}

const freeBlockStyle = (block: Block): string => {
    const style = block.style;
    const x = style.x ?? String(FREE_DEFAULTS.x);
    const y = style.y ?? String(FREE_DEFAULTS.y);
    const width = style.width ?? String(FREE_DEFAULTS.width);
    const parts = [`left: ${x}%;`, `top: ${y}%;`, `width: ${width}%;`];

    if (style.height != null) {
        parts.push(`height: ${style.height}%;`);
    }

    return parts.join(' ');
};

export class FreeLayoutRenderer implements LayoutRendererPlugin {
    readonly layout = 'free';

    // Reveal runs with disableLayout, so the stage must fit itself into the
    // slide area. Sizing the 16:9 stage to min(container width, container
    // height * 16/9) keeps it letterboxed and centered on any screen shape
    // instead of stretching or overflowing on very wide or short displays.
    readonly css =
        '.layout-free { position: relative; height: 100%; display: flex; align-items: center; justify-content: center; container-type: size; } .free-stage { position: relative; width: min(100cqw, calc(100cqh * 16 / 9)); aspect-ratio: 16 / 9; text-align: left; } .free-stage .free-block { position: absolute; }';

    render(slide: Slide, rc: RenderContext): string[] {
        const blocks = slide.slots['main'] ?? [];

        if (blocks.length === 0) {
            return [];
        }

        const depth = 2;
        const pad = INDENT.repeat(depth);
        const inner = depth + 1;
        // A fixed 16:9 stage owns the coordinate space, matching the editor
        // exactly and staying immune to Reveal's content-centering of the
        // slide section.
        const lines: string[] = [`${pad}<div class="free-stage">`];

        // Each block gets its own absolutely-positioned div so Animotion's
        // <Transition> wrapper never sits between the block and its coordinates.
        for (const { block, order } of flattenFreeSteps(blocks, rc.steps)) {
            lines.push(
                `${INDENT.repeat(inner)}<div class="free-block" style="${escapeAttribute(freeBlockStyle(block))}">`,
            );

            if (order !== null) {
                rc.use('Transition');
                lines.push(
                    `${INDENT.repeat(inner + 1)}<Transition order={${order}}>`,
                );
                lines.push(rc.renderBlock(block, inner + 2));
                lines.push(...rc.renderBlockActions(block, inner + 2));
                lines.push(`${INDENT.repeat(inner + 1)}</Transition>`);
            } else {
                lines.push(rc.renderBlock(block, inner + 1));
                lines.push(...rc.renderBlockActions(block, inner + 1));
            }

            lines.push(`${INDENT.repeat(inner)}</div>`);
        }

        lines.push(`${pad}</div>`);

        return [lines.join('\n')];
    }
}

export class RichTextLayoutRenderer implements LayoutRendererPlugin {
    readonly layout = 'rich-text';

    readonly css =
        '.layout-rich-text { height: 100%; overflow-y: auto; padding: 2rem; } .layout-rich-text h1 { font-size: 2rem; font-weight: 700; } .layout-rich-text h2 { font-size: 1.5rem; font-weight: 600; } .layout-rich-text h3 { font-size: 1.25rem; font-weight: 600; } .layout-rich-text ul, .layout-rich-text ol { padding-left: 1.5rem; list-style: revert; } .layout-rich-text blockquote { border-left: 3px solid currentColor; padding-left: 1rem; } .layout-rich-text pre { background: #1e2030; color: #cdd6f4; border-radius: 0.375rem; padding: 0.75rem; } .layout-rich-text .box { border: 2px solid; border-radius: 0.375rem; padding: 1rem; }';

    render(slide: Slide, rc: RenderContext): string[] {
        return (slide.slots['main'] ?? []).map((block) =>
            rc.renderBlock(block, 2),
        );
    }
}

export const defaultLayoutPlugins: CodegenPlugin = {
    name: 'tecturn:layouts',
    layouts: [
        new SlotLayoutRenderer(
            'full',
            '.layout-full { display: grid; height: 100%; }',
        ),
        new SlotLayoutRenderer(
            'center',
            '.layout-center { display: flex; height: 100%; align-items: center; justify-content: center; }',
        ),
        new SlotLayoutRenderer(
            'top-main',
            '.layout-top-main { display: grid; height: 100%; grid-template-rows: auto 1fr; gap: 1rem; }',
        ),
        new SlotLayoutRenderer(
            'top-main-footer',
            '.layout-top-main-footer { display: grid; height: 100%; grid-template-rows: auto 1fr auto; gap: 1rem; }',
        ),
        new SlotLayoutRenderer(
            'left-right',
            '.layout-left-right { display: grid; height: 100%; grid-template-columns: 1fr 1fr; gap: 1.5rem; }',
        ),
        new SlotLayoutRenderer(
            'left-wide-right',
            '.layout-left-wide-right { display: grid; height: 100%; grid-template-columns: 1fr 2fr; gap: 1.5rem; }',
        ),
        new SlotLayoutRenderer(
            'grid-2x2',
            '.layout-grid-2x2 { display: grid; height: 100%; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 1rem; }',
        ),
        new SlotLayoutRenderer(
            'grid-2x3',
            '.layout-grid-2x3 { display: grid; height: 100%; grid-template-columns: repeat(3, 1fr); grid-template-rows: 1fr 1fr; gap: 1rem; }',
        ),
        new SlotLayoutRenderer(
            'custom-grid',
            '.layout-custom-grid { display: grid; height: 100%; gap: 0.25rem; }',
        ),
        new FreeLayoutRenderer(),
        new RichTextLayoutRenderer(),
    ],
};
