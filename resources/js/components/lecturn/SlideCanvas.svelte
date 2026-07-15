<script lang="ts">
    import SlotRenderer from '@/components/lecturn/SlotRenderer.svelte';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { layoutDefinitions } from '@/lib/lecturn/layouts';

    let { editor }: { editor: EditorState } = $props();

    const slide = $derived(editor.selectedSlide);
    const definition = $derived(layoutDefinitions[slide.layout]);
</script>

<!-- svelte-ignore a11y_no_static_element_interactions, a11y_click_events_have_key_events -->
<div
    class="flex flex-1 items-center justify-center overflow-hidden bg-muted/40 p-8"
    onclick={() => (editor.selectedBlockId = null)}
>
    <div
        class="aspect-video w-full max-w-5xl rounded-md border shadow-sm"
        style="background: {slide.background ?? '#ffffff'}"
        data-test="slide-canvas"
    >
        <div class="{definition.containerClass} p-8">
            {#each definition.slots as slotName (slotName)}
                <SlotRenderer {editor} slot={slotName} />
            {/each}
        </div>
    </div>
</div>
