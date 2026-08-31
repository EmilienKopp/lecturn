import type { Block } from '@/types/generated';
import type { CodePage } from '../../flow-compiler.ts';
import type {
    BlockRendererPlugin,
    CodegenPlugin,
    RenderContext,
} from '../contracts.ts';
import type { EditorJsBlock, EditorJsOutput } from '../support.ts';
import {
    INDENT,
    escapeAttribute,
    escapeHtml,
    escapeTemplateLiteral,
} from '../support.ts';

export class RichtextRenderer implements BlockRendererPlugin {
    readonly type = 'richtext';

    render(block: Block, depth: number): string {
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
            .map((b) => this.renderEditorJsBlock(b, depth + 1))
            .join('\n');

        return `${pad}<div class="richtext">\n${inner}\n${pad}</div>`;
    }

    private renderEditorJsBlock(
        ejsBlock: EditorJsBlock,
        depth: number,
    ): string {
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
    }
}

// Animotion's theme ships `.reveal pre { font-size: 0.55em }` and its shiki CSS
// adds no padding or radius, so an unstyled block renders as tiny raw monospace.
// 1.4cqw reproduces the editor's proportion (14px on a ~1010px stage) against
// the free stage's size container; !important beats the theme rule. :global()
// because the <pre> lives inside the Code component, out of scoped-CSS reach.
// The dark background is a fallback for when the theme doesn't inline one.
const CODE_CSS = `${INDENT}:global(pre.shiki-magic-move-container) { margin: 0; padding: 1rem; border-radius: 0.5rem; background: #24292e; font-size: 1.4cqw !important; line-height: 1.6; overflow: auto; }`;

export class CodeRenderer implements BlockRendererPlugin {
    readonly type = 'code';

    render(block: Block, depth: number, rc: RenderContext): string {
        rc.use('Code');
        const pad = INDENT.repeat(depth);
        const ref = rc.refNameByBlockId.get(block.id);

        // autoIndent={false}: Animotion's indent() dedents by the smallest
        // indent among *indented* lines, ignoring zero-indent lines — a
        // snippet with top-level lines loses its whole first indent level.
        // Tecturn stores code verbatim and the literal adds no wrapper
        // indentation, so the dedent service is pure damage here.
        return `${pad}<Code ${ref ? `bind:this={${ref}} ` : ''}code={\`${escapeTemplateLiteral(block.content)}\`} lang="${escapeAttribute(block.lang ?? 'text')}" theme="github-dark" autoIndent={false} />`;
    }

    /**
     * The block's <Action> fragments, one per page. Both callbacks morph the
     * block and (re)apply the line highlight; `*` doubles as the reset (select
     * all = no dimming), matching the Animotion idiom.
     */
    actions(block: Block, depth: number, rc: RenderContext): string[] {
        const cues = rc.cuesByBlockId.get(block.id) ?? [];
        const ref = rc.refNameByBlockId.get(block.id);

        if (!ref || cues.length === 0) {
            return [];
        }

        rc.use('Action');
        const pad = INDENT.repeat(depth);
        const fire = (page: CodePage): string =>
            `async () => { await ${ref}.update\`${escapeTemplateLiteral(page.code)}\`; ${ref}.selectLines\`${page.highlightLines ?? '*'}\`; }`;

        return cues.map(
            (cue) =>
                `${pad}<Action order={${cue.order}} do={${fire(cue.show)}} undo={${fire(cue.back)}} />`,
        );
    }

    css(): string {
        return CODE_CSS;
    }
}

export class ImageRenderer implements BlockRendererPlugin {
    readonly type = 'image';

    render(block: Block, depth: number): string {
        const pad = INDENT.repeat(depth);

        // Inline style, not a class: the embed injects its CSS globally into
        // the host page (shadow: 'none'), so a bare `img {}` rule would leak
        // onto the host's own images. Mirrors Presenter.svelte's
        // `max-h-full max-w-full object-contain`.
        return `${pad}<img src="${escapeAttribute(block.src ?? '')}" alt="${escapeAttribute(block.alt ?? '')}" style="max-width: 100%; max-height: 100%; object-fit: contain;" />`;
    }
}

export class BoxRenderer implements BlockRendererPlugin {
    readonly type = 'box';

    render(block: Block, depth: number): string {
        const pad = INDENT.repeat(depth);
        const style = [
            block.style.borderColor
                ? `border: 2px solid ${block.style.borderColor};`
                : '',
            block.style.backgroundColor
                ? `background: ${block.style.backgroundColor};`
                : '',
        ]
            .filter(Boolean)
            .join(' ');

        return `${pad}<div class="box" style="${escapeAttribute(style)}">${escapeHtml(block.content)}</div>`;
    }
}

/** Fallback for plain text blocks and any type no plugin claims. */
export class ParagraphRenderer implements BlockRendererPlugin {
    readonly type = 'text';

    render(block: Block, depth: number): string {
        const pad = INDENT.repeat(depth);

        return `${pad}<p${this.styleAttribute(block)}>${escapeHtml(block.content)}</p>`;
    }

    private styleAttribute(block: Block): string {
        const parts = [
            block.style.fontSize ? `font-size: ${block.style.fontSize};` : '',
            block.style.fontWeight
                ? `font-weight: ${block.style.fontWeight};`
                : '',
            block.style.color ? `color: ${block.style.color};` : '',
        ].filter(Boolean);

        return parts.length > 0
            ? ` style="${escapeAttribute(parts.join(' '))}"`
            : '';
    }
}

export const defaultBlockPlugins: CodegenPlugin = {
    name: 'tecturn:blocks',
    blocks: [
        new RichtextRenderer(),
        new CodeRenderer(),
        new ImageRenderer(),
        new BoxRenderer(),
        new ParagraphRenderer(),
    ],
};
