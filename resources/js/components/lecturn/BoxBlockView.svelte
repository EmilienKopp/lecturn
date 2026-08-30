<script lang="ts">
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import type { Block } from '@/types/generated';

    let { editor, block }: { editor: EditorState; block: Block } = $props();

    let el = $state<HTMLDivElement | null>(null);
    let seeded = false;

    $effect(() => {
        if (el && !seeded) {
            el.textContent = block.content;
            seeded = true;
        }
    });

    function onInput() {
        if (el) {
            editor.updateBlockContent(block.id, el.textContent ?? '');
        }
    }
</script>

<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
<div
    class="w-full rounded-md border-2 p-4 text-sm outline-none ring-offset-1 focus-within:ring-1 focus-within:ring-primary {editor.selectedBlockId ===
    block.id
        ? 'ring-1 ring-primary'
        : ''}"
    style="border-color: {block.style.borderColor ??
        'hsl(var(--border))'}; background-color: {block.style.backgroundColor ??
        'transparent'}; color: {block.style.color ??
        'inherit'}; font-size: {block.style.fontSize ?? ''}; font-weight: {block
        .style.fontWeight ?? ''};"
    onclick={(e) => {
        e.stopPropagation();
        editor.selectedBlockId = block.id;
    }}
    data-test="box-block-{block.id}"
>
    <div
        bind:this={el}
        contenteditable="plaintext-only"
        class="min-h-8 w-full outline-none"
        oninput={onInput}
    ></div>
</div>
