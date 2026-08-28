<script lang="ts">
    import { Presentation, Slide, Transition } from '@animotion/core';
    import '@animotion/core/theme';
    import {
        compileFlow,
        defaultFlowFromContent,
        flattenFreeSteps,
        groupBlocksIntoSteps,
        migrateLegacyTransitions,
        stepIndexBySlide,
    } from '@/lib/lecturn/flow-compiler';
    import { FREE_DEFAULTS } from '@/lib/lecturn/free-drag';
    import { layoutDefinitions } from '@/lib/lecturn/layouts';
    import type {
        Block,
        FlowGraph,
        PresentationContent,
        Slide as SlideData,
    } from '@/types/generated';

    let {
        content: rawContent,
        flow: rawFlow = null,
    }: {
        content: PresentationContent;
        flow?: FlowGraph | null;
    } = $props();

    // Same pipeline as codegen.ts, so live presenting and the Svelte export
    // reveal the exact same steps. Snapshots because the migration
    // structuredClones its inputs, which rejects $state proxies.
    const compiled = $derived.by(() => {
        const contentSnapshot = $state.snapshot(rawContent);
        const flowSnapshot = $state.snapshot(rawFlow);

        return migrateLegacyTransitions(
            contentSnapshot,
            flowSnapshot ?? defaultFlowFromContent(contentSnapshot),
        );
    });
    const content = $derived(compiled.content);
    const stepsBySlideId = $derived(
        stepIndexBySlide(compileFlow(compiled.flow, content)),
    );

    const blockStyle = (block: Block): string =>
        [
            block.style.fontSize ? `font-size: ${block.style.fontSize};` : '',
            block.style.fontWeight
                ? `font-weight: ${block.style.fontWeight};`
                : '',
            block.style.color ? `color: ${block.style.color};` : '',
        ]
            .filter(Boolean)
            .join(' ');

    const slotSteps = (slide: SlideData, slotName: string) =>
        groupBlocksIntoSteps(
            slide.slots[slotName] ?? [],
            stepsBySlideId.get(slide.id) ?? new Map(),
        );

    const freeSteps = (slide: SlideData) =>
        flattenFreeSteps(
            slide.slots['main'] ?? [],
            stepsBySlideId.get(slide.id) ?? new Map(),
        );

    const freeBlockStyle = (block: Block): string => {
        const parts = [
            `left: ${block.style.x ?? FREE_DEFAULTS.x}%;`,
            `top: ${block.style.y ?? FREE_DEFAULTS.y}%;`,
            `width: ${block.style.width ?? FREE_DEFAULTS.width}%;`,
        ];

        if (block.style.height !== null) {
            parts.push(`height: ${block.style.height}%;`);
        }

        return parts.join(' ');
    };
</script>

{#snippet blockView(block: Block)}
    {#if block.type === 'text'}
        <p style={blockStyle(block)}>
            {block.content}
        </p>
    {:else if block.type === 'code'}
        <pre><code>{block.content}</code></pre>
    {:else if block.type === 'image'}
        <img
            src={block.src ?? ''}
            alt={block.alt ?? ''}
            class="max-h-full max-w-full object-contain"
        />
    {/if}
{/snippet}

<div class="h-full w-full" data-test="presenter">
    <Presentation
        options={{
            hash: false,
            controls: true,
            progress: true,
        }}
    >
        {#each content.slides as slide (slide.id)}
            <Slide
                background={slide.background ??
                    (content.backgroundImage ? undefined : '#ffffff')}
                image={slide.background
                    ? undefined
                    : (content.backgroundImage ?? undefined)}
                class="h-full w-full"
            >
                {#if slide.layout === 'free'}
                    <div
                        class="relative mx-auto w-full"
                        style="aspect-ratio: 16 / 9; color: #1a1a1a; text-align: left;"
                    >
                        {#each freeSteps(slide) as { block, order } (block.id)}
                            <div
                                class="absolute"
                                style={freeBlockStyle(block)}
                            >
                                {#if order !== null}
                                    <Transition {order}>
                                        {@render blockView(block)}
                                    </Transition>
                                {:else}
                                    {@render blockView(block)}
                                {/if}
                            </div>
                        {/each}
                    </div>
                {:else}
                <div
                    class="{layoutDefinitions[slide.layout]
                        .containerClass} h-full p-12"
                    style="color: #1a1a1a"
                >
                    {#each layoutDefinitions[slide.layout].slots as slotName (slotName)}
                        {@const { staticBlocks, stepGroups } = slotSteps(
                            slide,
                            slotName,
                        )}
                        <div class="flex min-h-0 flex-col gap-4">
                            {#each staticBlocks as block (block.id)}
                                {@render blockView(block)}
                            {/each}
                            {#each stepGroups as group (group.order)}
                                <Transition order={group.order}>
                                    {#each group.blocks as block (block.id)}
                                        {@render blockView(block)}
                                    {/each}
                                </Transition>
                            {/each}
                        </div>
                    {/each}
                </div>
                {/if}
            </Slide>
        {/each}
    </Presentation>
</div>
