import type { Block, PresentationContent, Slide } from '@/types/generated';
import { layoutDefinitions } from '@/lib/lecturn/layouts';

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

    return parts.length > 0 ? ` style="${escapeAttribute(parts.join(' '))}"` : '';
};

const renderBlock = (block: Block, depth: number): string => {
    const pad = INDENT.repeat(depth);

    switch (block.type) {
        case 'code':
            return `${pad}<Code lang="${escapeAttribute(block.lang ?? 'text')}">${escapeHtml(block.content)}</Code>`;
        case 'image':
            return `${pad}<img src="${escapeAttribute(block.src ?? '')}" alt="${escapeAttribute(block.alt ?? '')}" />`;
        default:
            return `${pad}<p${blockStyleAttribute(block)}>${escapeHtml(block.content)}</p>`;
    }
};

const renderSlot = (slotName: string, blocks: Block[], depth: number): string => {
    const pad = INDENT.repeat(depth);
    const lines: string[] = [`${pad}<div class="slot-${slotName}">`];

    const staticBlocks = blocks.filter((block) => !block.transition);
    const transitionBlocks = blocks
        .filter((block) => block.transition)
        .sort((a, b) => (a.transition?.order ?? 0) - (b.transition?.order ?? 0));

    for (const block of staticBlocks) {
        lines.push(renderBlock(block, depth + 1));
    }

    for (const block of transitionBlocks) {
        lines.push(`${INDENT.repeat(depth + 1)}<Transition>`);
        lines.push(renderBlock(block, depth + 2));
        lines.push(`${INDENT.repeat(depth + 1)}</Transition>`);
    }

    lines.push(`${pad}</div>`);

    return lines.join('\n');
};

const renderSlide = (slide: Slide): string => {
    const backgroundStyle = slide.background
        ? ` style="background: ${escapeAttribute(slide.background)}"`
        : '';

    const lines: string[] = [
        `${INDENT}<Slide class="layout-${slide.layout}"${backgroundStyle}>`,
    ];

    for (const slotName of layoutDefinitions[slide.layout].slots) {
        const blocks = slide.slots[slotName];

        if (blocks && blocks.length > 0) {
            lines.push(renderSlot(slotName, blocks, 2));
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
    };

    const usedLayouts = [...new Set(content.slides.map((slide) => slide.layout))];

    return usedLayouts.map((layout) => `${INDENT}${cssByLayout[layout]}`).join('\n');
};

/**
 * Compile a Lecturn presentation document into an Animotion-compatible
 * Svelte component (single-file output).
 */
export function generatePresentationSvelte(content: PresentationContent): string {
    const usesCode = content.slides.some((slide) =>
        Object.values(slide.slots).some((blocks) =>
            blocks.some((block) => block.type === 'code'),
        ),
    );

    const usesTransition = content.slides.some((slide) =>
        Object.values(slide.slots).some((blocks) =>
            blocks.some((block) => block.transition),
        ),
    );

    const imports = [
        'Presentation',
        'Slide',
        ...(usesTransition ? ['Transition'] : []),
        ...(usesCode ? ['Code'] : []),
    ].join(', ');

    const slides = content.slides.map(renderSlide).join('\n\n');

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
