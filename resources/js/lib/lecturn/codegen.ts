import type {
    Block,
    FlowGraph,
    PresentationContent,
    Slide,
} from '@/types/generated';

// Relative + extensioned so Node can run this file natively (scripts/present.mjs).
import {
    compileFlow,
    defaultFlowFromContent,
    groupBlocksIntoSteps,
    migrateLegacyTransitions,
    stepIndexBySlide,
    type StepIndex,
} from './flow-compiler.ts';
import { layoutDefinitions } from './layouts.ts';

interface EditorJsBlock {
    type: string;
    data: Record<string, unknown>;
}

interface EditorJsOutput {
    blocks: EditorJsBlock[];
}

const INDENT = '    ';

const escapeHtml = (value: string): string =>
    value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');

const escapeAttribute = (value: string): string =>
    escapeHtml(value).replaceAll('"', '&quot;');

const blockStyleAttribute = (block: Block): string => {
    const parts = [
        block.style.fontSize ? `font-size: ${block.style.fontSize};` : '',
        block.style.fontWeight ? `font-weight: ${block.style.fontWeight};` : '',
        block.style.color ? `color: ${block.style.color};` : '',
    ].filter(Boolean);

    return parts.length > 0
        ? ` style="${escapeAttribute(parts.join(' '))}"`
        : '';
};

const renderEditorJsBlock = (
    ejsBlock: EditorJsBlock,
    depth: number,
): string => {
    const pad = INDENT.repeat(depth);
    const { type, data } = ejsBlock;

    switch (type) {
        case 'header': {
            const level = (data.level as number) ?? 2;

            return `${pad}<h${level}>${data.text as string}</h${level}>`;
        }
        case 'list': {
            const tag = (data.style as string) === 'ordered' ? 'ol' : 'ul';
            const items = (data.items as string[])
                .map((item) => `${pad}${INDENT}<li>${item}</li>`)
                .join('\n');

            return `${pad}<${tag}>\n${items}\n${pad}</${tag}>`;
        }
        case 'code':
            return `${pad}<pre><code>${escapeHtml(data.code as string)}</code></pre>`;
        case 'quote':
            return `${pad}<blockquote>${data.text as string}</blockquote>`;
        default:
            return `${pad}<p>${(data.text as string) ?? ''}</p>`;
    }
};

const renderRichtextBlock = (block: Block, depth: number): string => {
    const pad = INDENT.repeat(depth);

    if (!block.content) {
        return `${pad}<div class="richtext"></div>`;
    }

    let output: EditorJsOutput;

    try {
        output = JSON.parse(block.content) as EditorJsOutput;
    } catch {
        return `${pad}<div class="richtext">${escapeHtml(block.content)}</div>`;
    }

    const inner = output.blocks
        .map((b) => renderEditorJsBlock(b, depth + 1))
        .join('\n');

    return `${pad}<div class="richtext">\n${inner}\n${pad}</div>`;
};

const renderBlock = (block: Block, depth: number): string => {
    const pad = INDENT.repeat(depth);

    switch (block.type) {
        case 'richtext':
            return renderRichtextBlock(block, depth);
        case 'code':
            return `${pad}<Code lang="${escapeAttribute(block.lang ?? 'text')}">${escapeHtml(block.content)}</Code>`;
        case 'image':
            return `${pad}<img src="${escapeAttribute(block.src ?? '')}" alt="${escapeAttribute(block.alt ?? '')}" />`;
        case 'box':
            return `${pad}<div class="box" style="${escapeAttribute([block.style.borderColor ? `border: 2px solid ${block.style.borderColor};` : '', block.style.backgroundColor ? `background: ${block.style.backgroundColor};` : ''].filter(Boolean).join(' '))}">${escapeHtml(block.content)}</div>`;
        default:
            return `${pad}<p${blockStyleAttribute(block)}>${escapeHtml(block.content)}</p>`;
    }
};

const renderSlot = (
    slotName: string,
    blocks: Block[],
    depth: number,
    steps: StepIndex,
): string => {
    const pad = INDENT.repeat(depth);
    const lines: string[] = [`${pad}<div class="slot-${slotName}">`];
    const { staticBlocks, stepGroups } = groupBlocksIntoSteps(blocks, steps);

    for (const block of staticBlocks) {
        lines.push(renderBlock(block, depth + 1));
    }

    // Blocks pinned to the same node reveal together: one <Transition> per
    // node, ordered by chain position (order maps to data-fragment-index,
    // which also keeps steps in sync across slots).
    for (const group of stepGroups) {
        lines.push(
            `${INDENT.repeat(depth + 1)}<Transition order={${group.order}}>`,
        );

        for (const block of group.blocks) {
            lines.push(renderBlock(block, depth + 2));
        }

        lines.push(`${INDENT.repeat(depth + 1)}</Transition>`);
    }

    lines.push(`${pad}</div>`);

    return lines.join('\n');
};

const renderSlide = (slide: Slide, steps: StepIndex): string => {
    const backgroundStyle = slide.background
        ? ` style="background: ${escapeAttribute(slide.background)}"`
        : '';

    const lines: string[] = [
        `${INDENT}<Slide class="layout-${slide.layout}"${backgroundStyle}>`,
    ];

    if (slide.layout === 'rich-text') {
        const blocks = slide.slots['main'] ?? [];

        for (const block of blocks) {
            lines.push(renderBlock(block, 2));
        }
    } else {
        for (const slotName of layoutDefinitions[slide.layout].slots) {
            const blocks = slide.slots[slotName];

            if (blocks && blocks.length > 0) {
                lines.push(renderSlot(slotName, blocks, 2, steps));
            }
        }
    }

    lines.push(`${INDENT}</Slide>`);

    return lines.join('\n');
};

const layoutCss = (content: PresentationContent): string => {
    const cssByLayout: Record<string, string> = {
        full: '.layout-full { display: grid; height: 100%; }',
        center: '.layout-center { display: flex; height: 100%; align-items: center; justify-content: center; }',
        'top-main':
            '.layout-top-main { display: grid; height: 100%; grid-template-rows: auto 1fr; gap: 1rem; }',
        'top-main-footer':
            '.layout-top-main-footer { display: grid; height: 100%; grid-template-rows: auto 1fr auto; gap: 1rem; }',
        'left-right':
            '.layout-left-right { display: grid; height: 100%; grid-template-columns: 1fr 1fr; gap: 1.5rem; }',
        'left-wide-right':
            '.layout-left-wide-right { display: grid; height: 100%; grid-template-columns: 1fr 2fr; gap: 1.5rem; }',
        'grid-2x2':
            '.layout-grid-2x2 { display: grid; height: 100%; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 1rem; }',
        'grid-2x3':
            '.layout-grid-2x3 { display: grid; height: 100%; grid-template-columns: repeat(3, 1fr); grid-template-rows: 1fr 1fr; gap: 1rem; }',
        'custom-grid':
            '.layout-custom-grid { display: grid; height: 100%; gap: 0.25rem; }',
        'rich-text':
            '.layout-rich-text { height: 100%; overflow-y: auto; padding: 2rem; } .layout-rich-text h1 { font-size: 2rem; font-weight: 700; } .layout-rich-text h2 { font-size: 1.5rem; font-weight: 600; } .layout-rich-text h3 { font-size: 1.25rem; font-weight: 600; } .layout-rich-text ul, .layout-rich-text ol { padding-left: 1.5rem; list-style: revert; } .layout-rich-text blockquote { border-left: 3px solid currentColor; padding-left: 1rem; } .layout-rich-text pre { background: #1e2030; color: #cdd6f4; border-radius: 0.375rem; padding: 0.75rem; } .layout-rich-text .box { border: 2px solid; border-radius: 0.375rem; padding: 1rem; }',
    };

    const usedLayouts = [
        ...new Set(content.slides.map((slide) => slide.layout)),
    ];

    // Slides default to a white background, but the surrounding Animotion
    // theme is dark; pin a base ink color so text never inherits it.
    const baseInk =
        usedLayouts.length > 0
            ? [
                  `${INDENT}${usedLayouts.map((layout) => `.layout-${layout}`).join(', ')} { color: #1a1a1a; }`,
              ]
            : [];

    return [
        ...baseInk,
        ...usedLayouts.map((layout) => `${INDENT}${cssByLayout[layout]}`),
    ].join('\n');
};

/**
 * Compile a Lecturn presentation document into an Animotion-compatible
 * Svelte component (single-file output).
 */
export function generatePresentationSvelte(
    rawContent: PresentationContent,
    rawFlow: FlowGraph | null = null,
): string {
    const { content, flow } = migrateLegacyTransitions(
        rawContent,
        rawFlow ?? defaultFlowFromContent(rawContent),
    );

    const deck = compileFlow(flow, content);
    const stepsBySlideId = stepIndexBySlide(deck);

    const usesCode = content.slides.some((slide) =>
        Object.values(slide.slots).some((blocks) =>
            blocks.some((block) => block.type === 'code'),
        ),
    );

    const usesTransition = content.slides.some((slide) =>
        Object.values(slide.slots).some((blocks) =>
            blocks.some(
                (block) =>
                    block.transition?.nodeId != null &&
                    stepsBySlideId.get(slide.id)?.has(block.transition.nodeId),
            ),
        ),
    );

    const imports = [
        'Presentation',
        'Slide',
        ...(usesTransition ? ['Transition'] : []),
        ...(usesCode ? ['Code'] : []),
    ].join(', ');

    const slides = content.slides
        .map((slide) =>
            renderSlide(slide, stepsBySlideId.get(slide.id) ?? new Map()),
        )
        .join('\n\n');

    return `<script>
${INDENT}import { ${imports} } from '@animotion/core';
</script>

<Presentation>
${slides}
</Presentation>

<style>
${layoutCss(content)}
</style>
`;
}
