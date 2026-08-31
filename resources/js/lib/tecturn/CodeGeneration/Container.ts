import { Container, Registry } from '../../container.ts';
import type {
    BlockRendererPlugin,
    CodegenPlugin,
    LayoutRendererPlugin,
} from './contracts.ts';

/**
 * Codegen facade over the generic IoC toolkit (lib/container.ts): a service
 * container plus two plugin registries the engine resolves renderers from.
 * Everything that renders is a plugin registered through `use()` — swap or
 * extend renderers by registering another plugin; last one for a key wins.
 */
export class CodegenContainer extends Container {
    readonly blockRenderers = new Registry<BlockRendererPlugin>(
        'block renderers',
    );
    readonly layoutRenderers = new Registry<LayoutRendererPlugin>(
        'layout renderers',
    );

    use(plugin: CodegenPlugin): this {
        for (const block of plugin.blocks ?? []) {
            this.blockRenderers.register(block.type, block);
        }

        for (const layout of plugin.layouts ?? []) {
            this.layoutRenderers.register(layout.layout, layout);
        }

        return this;
    }

    /** Renderer used for block types no plugin claims. */
    useFallback(renderer: BlockRendererPlugin): this {
        this.blockRenderers.fallback(renderer);

        return this;
    }

    blockRenderer(type: string): BlockRendererPlugin {
        return this.blockRenderers.resolve(type);
    }

    layoutRenderer(layout: string): LayoutRendererPlugin {
        return this.layoutRenderers.resolve(layout);
    }
}
