<script lang="ts">
    import { sanitizeInlineHtml } from '@/lib/tecturn/CodeGeneration/sanitize';
    import type { EditorState } from '@/lib/tecturn/editor-state.svelte';
    import { fontStack } from '@/lib/tecturn/fonts';
    import { scaleFontSize } from '@/lib/tecturn/scaling';
    import type { Block } from '@/types/generated';

    let { editor, block }: { editor: EditorState; block: Block } = $props();

    const styleAttribute = $derived(
        [
            block.style.fontSize
                ? `font-size: ${scaleFontSize(block.style.fontSize)};`
                : '',
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
    // keystroke would reset the caret position. Content is inline HTML now, so
    // seed and save through the sanitizer to keep only allowlisted markup.
    const seedContent = (node: HTMLElement) => {
        node.innerHTML = sanitizeInlineHtml(block.content);
    };

    // Keep new lines as <br>; the default contenteditable Enter inserts a
    // <div> the sanitizer would strip, silently merging lines.
    const onKeydown = (event: KeyboardEvent) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.execCommand('insertLineBreak');
        }
    };
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
    contenteditable="true"
    data-inline-format
    use:seedContent
    class="min-h-[1.5em] cursor-text rounded px-1 outline-none focus:ring-2 focus:ring-primary/50 {editor.selectedBlockId ===
    block.id
        ? 'ring-2 ring-primary'
        : ''}"
    style={styleAttribute}
    onkeydown={onKeydown}
    oninput={(event) =>
        editor.updateBlockContent(
            block.id,
            sanitizeInlineHtml(event.currentTarget.innerHTML),
        )}
    onclick={(event) => {
        event.stopPropagation();
        editor.selectedBlockId = block.id;
    }}
    data-test="text-block"
></div>
