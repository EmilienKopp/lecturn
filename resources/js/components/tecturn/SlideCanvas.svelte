<script lang="ts">
    import FreeCanvas from '@/components/tecturn/FreeCanvas.svelte';
    import GridCanvas from '@/components/tecturn/GridCanvas.svelte';
    import RichTextEditor from '@/components/tecturn/RichTextEditor.svelte';
    import SlotRenderer from '@/components/tecturn/SlotRenderer.svelte';
    import type { EditorState } from '@/lib/tecturn/editor-state.svelte';
    import { layoutDefinitions } from '@/lib/tecturn/layouts';

    let {
        editor,
        presentationId,
    }: { editor: EditorState; presentationId: number } = $props();

    const slide = $derived(editor.selectedSlide);
    const definition = $derived(layoutDefinitions[slide.layout]);
    const isCustomGrid = $derived(slide.layout === 'custom-grid');
    const isRichText = $derived(slide.layout === 'rich-text');
    const isFree = $derived(slide.layout === 'free');

    // A slide's own color wins; otherwise the deck-wide background image shows.
    const stageBackground = $derived(
        slide.background ??
            (editor.backgroundImage
                ? `url('${editor.backgroundImage}') center / cover no-repeat`
                : '#ffffff'),
    );

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
        style="background: {stageBackground}; color: #1a1a1a"
        data-test="slide-canvas"
    >
        {#if isCustomGrid}
            <div class="h-full w-full p-8">
                <GridCanvas {editor} />
            </div>
        {:else if isFree}
            <FreeCanvas {editor} {presentationId} />
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
