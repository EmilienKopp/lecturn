<script lang="ts">
    import Plus from 'lucide-svelte/icons/plus';
    import TextBlockView from '@/components/lecturn/TextBlockView.svelte';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';

    let {
        editor,
        slot: slotName,
    }: { editor: EditorState; slot: string } = $props();

    const blocks = $derived(editor.selectedSlide.slots[slotName] ?? []);
</script>

<div
    class="flex h-full flex-col gap-2 rounded border border-dashed border-muted-foreground/30 p-2"
    data-test="slot-{slotName}"
>
    {#each blocks as block (block.id)}
        {#if block.type === 'text'}
            <TextBlockView {editor} {block} />
        {/if}
    {/each}

    <button
        type="button"
        class="mt-auto flex items-center justify-center gap-1 rounded py-1 text-xs text-muted-foreground opacity-40 transition-opacity hover:bg-accent hover:opacity-100"
        onclick={() => editor.addTextBlock(slotName)}
        data-test="add-text-block-button"
    >
        <Plus class="h-3 w-3" /> Text
    </button>
</div>
