<script lang="ts">
    import GridCanvas from '@/components/lecturn/GridCanvas.svelte';
    import RichTextEditor from '@/components/lecturn/RichTextEditor.svelte';
    import SlotRenderer from '@/components/lecturn/SlotRenderer.svelte';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { layoutDefinitions } from '@/lib/lecturn/layouts';

    let { editor }: { editor: EditorState } = $props();

    const slide = $derived(editor.selectedSlide);
    const definition = $derived(layoutDefinitions[slide.layout]);
    const isCustomGrid = $derived(slide.layout === 'custom-grid');
    const isRichText = $derived(slide.layout === 'rich-text');

    const richtextBlock = $derived(
        isRichText ? (slide.slots['main']?.[0] ?? null) : null,
    );
</script>

<!-- svelte-ignore a11y_no_static_element_interactions, a11y_click_events_have_key_events -->
<div
    class="flex flex-1 items-center justify-center bg-muted/40 p-8 {isRichText
        ? 'overflow-visible'
        : 'overflow-hidden'}"
    onclick={() => (editor.selectedBlockId = null)}
>
    <div
        class="stage-canvas aspect-video w-full max-w-5xl rounded-md {isRichText
            ? 'overflow-visible'
            : ''}"
        style="background: {slide.background ?? '#ffffff'}; color: #1a1a1a"
        data-test="slide-canvas"
    >
        {#if isCustomGrid}
            <div class="h-full w-full p-8">
                <GridCanvas {editor} />
            </div>
        {:else if isRichText}
            {#key slide.id}
                {#if richtextBlock}
                    <RichTextEditor {editor} block={richtextBlock} />
                {:else}
                    <div class="flex h-full items-center justify-center">
                        <button
                            type="button"
                            class="text-sm opacity-60 hover:opacity-100"
                            onclick={(e) => {
                                e.stopPropagation();
                                editor.addRichtextBlock('main');
                            }}
                        >
                            + Initialize editor
                        </button>
                    </div>
                {/if}
            {/key}
        {:else}
            <div class="{definition.containerClass} p-8">
                {#each definition.slots as slotName (slotName)}
                    <SlotRenderer {editor} slot={slotName} />
                {/each}
            </div>
        {/if}
    </div>
</div>
