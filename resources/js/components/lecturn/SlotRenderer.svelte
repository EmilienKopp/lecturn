<script lang="ts">
    import Code2 from 'lucide-svelte/icons/code-2';
    import Plus from 'lucide-svelte/icons/plus';
    import Square from 'lucide-svelte/icons/square';
    import BlockPinMenu from '@/components/lecturn/BlockPinMenu.svelte';
    import BoxBlockView from '@/components/lecturn/BoxBlockView.svelte';
    import CodeBlockView from '@/components/lecturn/CodeBlockView.svelte';
    import TextBlockView from '@/components/lecturn/TextBlockView.svelte';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';

    let { editor, slot: slotName }: { editor: EditorState; slot: string } =
        $props();

    const blocks = $derived(editor.selectedSlide.slots[slotName] ?? []);
</script>

<div
    class="flex h-full flex-col gap-2 rounded border border-dashed border-current/25 p-2"
    data-test="slot-{slotName}"
>
    {#each blocks as block (block.id)}
        <BlockPinMenu {editor} {block}>
            {#if block.type === 'text'}
                <TextBlockView {editor} {block} />
            {:else if block.type === 'code'}
                <CodeBlockView {editor} {block} />
            {:else if block.type === 'box'}
                <BoxBlockView {editor} {block} />
            {/if}
        </BlockPinMenu>
    {/each}

    <div class="mt-auto flex items-center justify-center gap-1">
        <button
            type="button"
            class="flex items-center gap-1 rounded px-2 py-1 text-xs opacity-40 transition-opacity hover:bg-current/10 hover:opacity-100"
            onclick={() => editor.addTextBlock(slotName)}
            data-test="add-text-block-button"
            title="Add text block"
        >
            <Plus class="h-3 w-3" /> Text
        </button>
        <button
            type="button"
            class="flex items-center gap-1 rounded px-2 py-1 text-xs opacity-40 transition-opacity hover:bg-current/10 hover:opacity-100"
            onclick={() => editor.addCodeBlock(slotName)}
            data-test="add-code-block-button"
            title="Add code block"
        >
            <Code2 class="h-3 w-3" /> Code
        </button>
        <button
            type="button"
            class="flex items-center gap-1 rounded px-2 py-1 text-xs opacity-40 transition-opacity hover:bg-current/10 hover:opacity-100"
            onclick={() => editor.addBoxBlock(slotName)}
            data-test="add-box-block-button"
            title="Add bordered box"
        >
            <Square class="h-3 w-3" /> Box
        </button>
    </div>
</div>
