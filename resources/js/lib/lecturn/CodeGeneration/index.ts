/**
 * Presentation code generation — public entry point.
 *
 * Architecture: an IoC container (CodegenContainer, built on lib/container.ts)
 * holds renderer plugins; the engine (PresentationToCode) resolves everything
 * it draws through it. Block types and slide layouts are both plugins — to
 * support a new one, register it via `container.use({...})`, no engine change.
 *
 * All relative imports carry .ts extensions so Node can run this natively via
 * type stripping (scripts/present.mjs).
 */
import type { FlowGraph, PresentationContent } from '@/types/generated';
import { CodegenContainer } from './Container.ts';
import { ParagraphRenderer, defaultBlockPlugins } from './plugins/blocks.ts';
import { defaultLayoutPlugins } from './plugins/layouts.ts';
import { PresentationToCode } from './PresentationToCode.ts';

export * from './contracts.ts';
export * from './support.ts';
export { CodegenContainer } from './Container.ts';
export { PresentationToCode } from './PresentationToCode.ts';
export * from './plugins/blocks.ts';
export * from './plugins/layouts.ts';

/** Container preloaded with Lecturn's built-in renderer plugins. */
export function createDefaultContainer(): CodegenContainer {
    return new CodegenContainer()
        .use(defaultBlockPlugins)
        .use(defaultLayoutPlugins)
        .useFallback(new ParagraphRenderer());
}

/**
 * Compile a Lecturn presentation document into an Animotion-compatible
 * Svelte component (single-file output).
 */
export function generatePresentationSvelte(
    rawContent: PresentationContent,
    rawFlow: FlowGraph | null = null,
    container: CodegenContainer = createDefaultContainer(),
): string {
    return new PresentationToCode(container).generate(rawContent, rawFlow);
}
