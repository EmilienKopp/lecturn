import type {
    Block,
    FlowGraph,
    PresentationContent,
    Slide,
} from '@/types/generated';
import type { CodeActionCue, StepIndex } from '../flow-compiler.ts';
import {
    codeActionCues,
    compileFlow,
    defaultFlowFromContent,
    enabledSlideIds,
    migrateLegacyTransitions,
    stepIndexBySlide,
} from '../flow-compiler.ts';
import type { CodegenContainer } from './Container.ts';
import type { BlockRendererPlugin, RenderContext } from './contracts.ts';
import { ANIMOTION_IMPORT_ORDER } from './contracts.ts';
import { INDENT, escapeAttribute } from './support.ts';

/**
 * The engine: compiles a Tecturn presentation document into an
 * Animotion-compatible Svelte component (single-file output). Owns the flow
 * compilation, the <Slide> shells and the file skeleton (script imports,
 * styles); every piece of markup inside a slide comes from a plugin resolved
 * through the container.
 */
export class PresentationToCode {
    private container: CodegenContainer;

    constructor(container: CodegenContainer) {
        this.container = container;
    }

    generate(
        rawContent: PresentationContent,
        rawFlow: FlowGraph | null = null,
    ): string {
        const { content, flow } = migrateLegacyTransitions(
            rawContent,
            rawFlow ?? defaultFlowFromContent(rawContent),
        );

        const deck = compileFlow(flow, content);
        const stepsBySlideId = stepIndexBySlide(deck);

        // Disabled slides (no incoming nav edge, not the entry) are dropped
        // from the built deck entirely — they never reach the audience.
        const enabled = enabledSlideIds(content, flow);
        const shownSlides = content.slides.filter((slide) =>
            enabled.has(slide.id),
        );

        // Cues resolved per shown slide; block ids are globally unique, so both
        // maps flatten across the deck. Ref names are stable (code_0, code_1, …)
        // in first-appearance order.
        const refNameByBlockId = new Map<string, string>();
        const cuesByBlockId = new Map<string, CodeActionCue[]>();

        for (const compiledSlide of deck.slides) {
            if (!enabled.has(compiledSlide.slide.id)) {
                continue;
            }

            for (const cue of codeActionCues(
                compiledSlide.slide,
                compiledSlide.steps,
            )) {
                if (!refNameByBlockId.has(cue.blockId)) {
                    refNameByBlockId.set(
                        cue.blockId,
                        `code_${refNameByBlockId.size}`,
                    );
                }

                cuesByBlockId.set(cue.blockId, [
                    ...(cuesByBlockId.get(cue.blockId) ?? []),
                    cue,
                ]);
            }
        }

        // Usage is tracked while rendering: plugins declare the Animotion
        // components their markup needs via rc.use(), and every block plugin
        // that rendered gets to contribute <style> lines afterwards.
        const usedComponents = new Set<string>(['Presentation', 'Slide']);
        const usedBlockRenderers = new Set<BlockRendererPlugin>();

        const contextFor = (steps: StepIndex): RenderContext => {
            const rc: RenderContext = {
                steps,
                refNameByBlockId,
                cuesByBlockId,
                use: (component: string) => {
                    usedComponents.add(component);
                },
                renderBlock: (block: Block, depth: number) => {
                    const renderer = this.container.blockRenderer(block.type);
                    usedBlockRenderers.add(renderer);

                    return renderer.render(block, depth, rc);
                },
                renderBlockActions: (block: Block, depth: number) =>
                    this.container
                        .blockRenderer(block.type)
                        .actions?.(block, depth, rc) ?? [],
            };

            return rc;
        };

        const slides = shownSlides
            .map((slide) =>
                this.renderSlide(
                    slide,
                    contextFor(stepsBySlideId.get(slide.id) ?? new Map()),
                    content.backgroundImage ?? null,
                ),
            )
            .join('\n\n');

        const imports = [
            ...ANIMOTION_IMPORT_ORDER.filter((component) =>
                usedComponents.has(component),
            ),
            ...[...usedComponents].filter(
                (component) => !ANIMOTION_IMPORT_ORDER.includes(component),
            ),
        ].join(', ');

        const refDeclarations =
            cuesByBlockId.size > 0
                ? `\n\n${[...refNameByBlockId.values()]
                      .map((name) => `${INDENT}let ${name} = $state();`)
                      .join('\n')}`
                : '';

        const styles = [
            ...this.layoutCss(content),
            ...[...usedBlockRenderers].flatMap((renderer) => {
                const css = renderer.css?.();

                return css ? [css] : [];
            }),
        ].join('\n');

        return `<script>
${INDENT}import { ${imports} } from '@animotion/core';${refDeclarations}
</script>

<Presentation>
${slides}
</Presentation>

<style>
${styles}
</style>
`;
    }

    private renderSlide(
        slide: Slide,
        rc: RenderContext,
        backgroundImage: string | null,
    ): string {
        // A slide's own color wins; otherwise the deck-wide image is the backdrop.
        let backgroundAttr = '';

        if (slide.background) {
            backgroundAttr = ` style="background: ${escapeAttribute(slide.background)}"`;
        } else if (backgroundImage) {
            backgroundAttr = ` image="${escapeAttribute(backgroundImage)}"`;
        }

        const lines: string[] = [
            `${INDENT}<Slide class="layout-${slide.layout}"${backgroundAttr}>`,
            ...this.container.layoutRenderer(slide.layout).render(slide, rc),
            `${INDENT}</Slide>`,
        ];

        return lines.join('\n');
    }

    private layoutCss(content: PresentationContent): string[] {
        const usedLayouts = [
            ...new Set(content.slides.map((slide) => slide.layout)),
        ];

        if (usedLayouts.length === 0) {
            return [];
        }

        // Slides default to a white background, but the surrounding Animotion
        // theme is dark; pin a base ink color so text never inherits it.
        const baseInk = `${INDENT}${usedLayouts.map((layout) => `.layout-${layout}`).join(', ')} { color: #1a1a1a; }`;

        return [
            baseInk,
            ...usedLayouts.map(
                (layout) =>
                    `${INDENT}${this.container.layoutRenderer(layout).css}`,
            ),
        ];
    }
}
