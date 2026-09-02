<script lang="ts">
    import type { EditorState } from '@/lib/tecturn/editor-state.svelte';
    import { fontStack } from '@/lib/tecturn/fonts';
    import type { Block } from '@/types/generated';

    let { editor, block }: { editor: EditorState; block: Block } = $props();

    const styleAttribute = $derived(
        [
            block.style.fontSize ? `font-size: ${block.style.fontSize};` : '',
            block.style.fontWeight
                ? `font-weight: ${block.style.fontWeight};`
                : '',
            fontStack(block.style.fontFamily)
                ? `font-family: ${fontStack(block.style.fontFamily)};`
                : '',
            block.style.color ? `color: ${block.style.color};` : '',
        ]
            .filter(Boolean)
            .join(' '),
    );

    // Seed the contenteditable once; re-rendering its children on every
    // keystroke would reset the caret position.
    const seedContent = (node: HTMLElement) => {
        node.textContent = block.content;
    };
</script>

<!-- svelte-ignore a11y_no_static_element_interactions, a11y_click_events_have_key_events -->
<div
    contenteditable="plaintext-only"
    use:seedContent
    class="min-h-[1.5em] cursor-text rounded px-1 outline-none focus:ring-2 focus:ring-primary/50 {editor.selectedBlockId ===
    block.id
        ? 'ring-2 ring-primary'
        : ''}"
    style={styleAttribute}
    oninput={(event) =>
        editor.updateBlockContent(
            block.id,
            event.currentTarget.textContent ?? '',
        )}
    onclick={(event) => {
        event.stopPropagation();
        editor.selectedBlockId = block.id;
    }}
    data-test="text-block"
></div>
