<script lang="ts">
    import { Presentation, Slide } from '@animotion/core';
    import '@animotion/core/theme';
    import { layoutDefinitions } from '@/lib/lecturn/layouts';
    import type {
        Block,
        PresentationContent,
        Slide as SlideData,
    } from '@/types/generated';

    let { content }: { content: PresentationContent } = $props();

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

    const slotBlocks = (slide: SlideData, slotName: string): Block[] =>
        slide.slots[slotName] ?? [];
</script>

<div class="fixed inset-0 bg-black" data-test="presenter">
    <Presentation
        options={{
            hash: false,
            controls: true,
            progress: true,
        }}
    >
        {#each content.slides as slide (slide.id)}
            <Slide
                background={slide.background ?? '#ffffff'}
                class="h-full w-full"
            >
                <div
                    class="{layoutDefinitions[slide.layout]
                        .containerClass} h-full p-12"
                >
                    {#each layoutDefinitions[slide.layout].slots as slotName (slotName)}
                        <div class="flex min-h-0 flex-col gap-4">
                            {#each slotBlocks(slide, slotName) as block (block.id)}
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
                            {/each}
                        </div>
                    {/each}
                </div>
            </Slide>
        {/each}
    </Presentation>
</div>
