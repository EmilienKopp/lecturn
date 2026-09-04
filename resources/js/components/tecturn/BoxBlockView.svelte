<script lang="ts">
    import { sanitizeInlineHtml } from '@/lib/tecturn/CodeGeneration/sanitize';
    import type { EditorState } from '@/lib/tecturn/editor-state.svelte';
    import { fontStack } from '@/lib/tecturn/fonts';
    import { scaleFontSize } from '@/lib/tecturn/scaling';
    import type { Block } from '@/types/generated';

    let { editor, block }: { editor: EditorState; block: Block } = $props();

    let el = $state<HTMLDivElement | null>(null);

    // Seed the contenteditable once via an action; re-rendering its children on
    // every keystroke would reset the caret. Content is inline HTML now, so
    // seed and save through the sanitizer to keep only allowlisted markup
    // (colored/sized spans, bold, italic).
    const seedContent = (node: HTMLElement) => {
        node.innerHTML = sanitizeInlineHtml(block.content);
    };

    function onInput() {
        if (el) {
            editor.updateBlockContent(
                block.id,
                sanitizeInlineHtml(el.innerHTML),
            );
        }
    }

    // Keep new lines as <br>; the default Enter inserts a <div> the sanitizer
    // would strip, silently merging lines.
    function onKeydown(event: KeyboardEvent) {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.execCommand('insertLineBreak');
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
        'inherit'}; font-size: {scaleFontSize(block.style.fontSize) ??
        ''}; font-weight: {block.style.fontWeight ??
        ''}; font-family: {fontStack(block.style.fontFamily) ?? 'inherit'};"
    onclick={(e) => {
        e.stopPropagation();
        editor.selectedBlockId = block.id;
    }}
    data-test="box-block-{block.id}"
>
    <div
        bind:this={el}
        use:seedContent
        contenteditable="true"
        data-inline-format
        class="min-h-8 w-full outline-none"
        onkeydown={onKeydown}
        oninput={onInput}
    ></div>
</div>
