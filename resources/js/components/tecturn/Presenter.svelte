<script lang="ts">
    import {
        Action,
        Code,
        Presentation,
        Slide,
        Transition,
    } from '@animotion/core';
    import '@animotion/core/theme';
    import {
        codeActionCues,
        compileFlow,
        defaultFlowFromContent,
        enabledSlideIds,
        flattenFreeSteps,
        groupBlocksIntoSteps,
        migrateLegacyTransitions,
        stepIndexBySlide,
    } from '@/lib/tecturn/flow-compiler';
    import { FREE_DEFAULTS } from '@/lib/tecturn/free-drag';
    import { layoutDefinitions } from '@/lib/tecturn/layouts';
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
    const deck = $derived(compileFlow(compiled.flow, content));
    const stepsBySlideId = $derived(stepIndexBySlide(deck));
    // Code-action cues per slide: do/undo page pairs resolved by the same
    // compiler the codegen uses, so live playback matches the export.
    const cuesBySlideId = $derived(
        new Map(
            deck.slides.map((compiledSlide) => [
                compiledSlide.slide.id,
                codeActionCues(compiledSlide.slide, compiledSlide.steps),
            ]),
        ),
    );

    // Instances of the Animotion Code components, keyed by block id, so
    // Action fragments can morph them. Plain object on purpose — refs are
    // imperative handles, not render state.
    const codeRefs: Record<string, ReturnType<typeof Code> | undefined> = {};

    const cuesFor = (slide: SlideData, blockId: string) =>
        (cuesBySlideId.get(slide.id) ?? []).filter(
            (cue) => cue.blockId === blockId,
        );
    // Disabled slides never reach the audience, mirroring the exported deck.
    const shownSlides = $derived(
        (() => {
            const enabled = enabledSlideIds(content, compiled.flow);

            return content.slides.filter((slide) => enabled.has(slide.id));
        })(),
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

        if (block.style.height != null) {
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
        <!-- Animotion's theme ships `.reveal pre { font-size: 0.55em }` and its
             shiki CSS adds no padding or radius, so an unstyled block renders
             as tiny raw monospace. The stage is a size container, so 1.4cqw
             reproduces the editor's proportion (14px on a ~1010px stage) at any
             screen size; the ! beats the theme rule. -->
        <div
            class="[&_pre]:m-0 [&_pre]:overflow-auto [&_pre]:rounded-lg [&_pre]:bg-[#24292e] [&_pre]:p-4 [&_pre]:text-[1.4cqw]! [&_pre]:leading-relaxed"
        >
            <!-- autoIndent={false}: Animotion's indent() dedents by the
                 smallest indent among *indented* lines, ignoring zero-indent
                 ones, so top-level snippets lose their first indent level on
                 every render/update. Our code is stored verbatim; render it
                 verbatim. -->
            <Code
                bind:this={codeRefs[block.id]}
                code={block.content}
                lang={block.lang ?? 'text'}
                theme="github-dark"
                autoIndent={false}
            />
        </div>
    {:else if block.type === 'image'}
        <img
            src={block.src ?? ''}
            alt={block.alt ?? ''}
            class="max-h-full max-w-full object-contain"
        />
    {/if}
{/snippet}

{#snippet blockActions(slide: SlideData, block: Block)}
    {#each cuesFor(slide, block.id) as cue (cue.order)}
        <Action
            order={cue.order}
            do={async () => {
                const ref = codeRefs[block.id];

                if (ref) {
                    await ref.update`${cue.show.code}`;
                    void ref.selectLines`${cue.show.highlightLines ?? '*'}`;
                }
            }}
            undo={async () => {
                const ref = codeRefs[block.id];

                if (ref) {
                    await ref.update`${cue.back.code}`;
                    void ref.selectLines`${cue.back.highlightLines ?? '*'}`;
                }
            }}
        />
    {/each}
{/snippet}

<div class="h-full w-full" data-test="presenter">
    <Presentation
        options={{
            hash: false,
            controls: true,
            progress: true,
        }}
    >
        {#each shownSlides as slide (slide.id)}
            <Slide
                background={slide.background ??
                    (content.backgroundImage ? undefined : '#ffffff')}
                image={slide.background
                    ? undefined
                    : (content.backgroundImage ?? undefined)}
                class="h-full w-full"
            >
                {#if slide.layout === 'free'}
                    <!-- Fit the 16:9 stage inside the slide area (letterbox)
                         so it never stretches or overflows on odd screen sizes. -->
                    <div
                        class="flex h-full w-full items-center justify-center [container-type:size]"
                    >
                        <div
                            class="relative"
                            style="width: min(100cqw, calc(100cqh * 16 / 9)); aspect-ratio: 16 / 9; color: #1a1a1a; text-align: left;"
                        >
                            {#each freeSteps(slide) as { block, order } (block.id)}
                                <div
                                    class="absolute"
                                    style={freeBlockStyle(block)}
                                >
                                    {#if order !== null}
                                        <Transition {order}>
                                            {@render blockView(block)}
                                            {@render blockActions(slide, block)}
                                        </Transition>
                                    {:else}
                                        {@render blockView(block)}
                                        {@render blockActions(slide, block)}
                                    {/if}
                                </div>
                            {/each}
                        </div>
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
                                    {@render blockActions(slide, block)}
                                {/each}
                                {#each stepGroups as group (group.order)}
                                    <Transition order={group.order}>
                                        {#each group.blocks as block (block.id)}
                                            {@render blockView(block)}
                                            {@render blockActions(slide, block)}
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
