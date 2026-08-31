import type { Block, Slide } from '@/types/generated';
import type { CodeActionCue, StepIndex } from '../flow-compiler.ts';

/**
 * Canonical order for @animotion/core imports in the generated <script>.
 * Components requested by plugins but absent here append in usage order.
 */
export const ANIMOTION_IMPORT_ORDER: string[] = [
    'Presentation',
    'Slide',
    'Transition',
    'Code',
    'Action',
];

/**
 * Per-slide rendering services handed to every plugin. Plugins never reach
 * into the engine or the container directly — everything they need to
 * cooperate (step orders, code-action wiring, import tracking, dispatching
 * nested blocks) flows through this context.
 */
export interface RenderContext {
    /** Reveal-step orders for the current slide, keyed by flow node id. */
    readonly steps: StepIndex;
    /** `bind:this` ref names for code blocks with action pages, deck-wide. */
    readonly refNameByBlockId: ReadonlyMap<string, string>;
    /** Resolved do/undo page pairs per code block, deck-wide. */
    readonly cuesByBlockId: ReadonlyMap<string, CodeActionCue[]>;
    /** Record that the emitted markup needs an @animotion/core component. */
    use(component: string): void;
    /** Dispatch a block through the registered block renderer plugins. */
    renderBlock(block: Block, depth: number): string;
    /** The block's trailing <Action> fragment lines, if its plugin emits any. */
    renderBlockActions(block: Block, depth: number): string[];
}

/**
 * Renders one block type to Svelte source. Registered in the container by
 * `type`; one plugin per type, last registration wins.
 */
export interface BlockRendererPlugin {
    /** The `Block.type` this plugin renders. */
    readonly type: string;
    render(block: Block, depth: number, rc: RenderContext): string;
    /** <Action> fragment lines appended after the block's markup. */
    actions?(block: Block, depth: number, rc: RenderContext): string[];
    /** Extra <style> lines, emitted once when the plugin rendered anything. */
    css?(): string | null;
}

/**
 * Renders the body of a \<Slide\> for one layout. The engine owns the <Slide>
 * shell (class, background); the plugin owns everything inside it.
 */
export interface LayoutRendererPlugin {
    /** The `Slide.layout` this plugin renders. */
    readonly layout: string;
    /** Inner lines of the <Slide> body, indented at depth 2. */
    render(slide: Slide, rc: RenderContext): string[];
    /** The layout's <style> rule line, emitted when a slide uses the layout. */
    readonly css: string;
}

/** A bundle of renderers registered together via `CodegenContainer.use()`. */
export interface CodegenPlugin {
    readonly name: string;
    readonly blocks?: BlockRendererPlugin[];
    readonly layouts?: LayoutRendererPlugin[];
}
