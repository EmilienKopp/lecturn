<script lang="ts">
    import CodeEditor from '@/components/tecturn/CodeEditor.svelte';
    import type { EditorState } from '@/lib/tecturn/editor-state.svelte';
    import { highlight } from '@/lib/tecturn/shiki';
    import type { Block } from '@/types/generated';

    let { editor, block }: { editor: EditorState; block: Block } = $props();

    let isEditing = $state(false);
    let highlightedHtml = $state('');

    async function renderHighlight() {
        highlightedHtml = await highlight(
            block.content || '// start typing…',
            block.lang ?? 'typescript',
        );
    }

    $effect(() => {
        void renderHighlight();
    });

    function startEditing() {
        isEditing = true;
        editor.selectedBlockId = block.id;
    }

    function stopEditing() {
        isEditing = false;
        void renderHighlight();
    }
</script>

<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
<div
    class="relative min-h-16 w-full overflow-hidden rounded-md text-sm ring-1 ring-inset {editor.selectedBlockId ===
    block.id
        ? 'ring-primary'
        : 'ring-border'}"
    onclick={(e) => {
        e.stopPropagation();
        startEditing();
    }}
    data-test="code-block-{block.id}"
>
    {#if isEditing}
        <CodeEditor
            value={block.content}
            lang={block.lang ?? 'typescript'}
            oninput={(value) => editor.updateBlockContent(block.id, value)}
            onblur={stopEditing}
            autofocus
        />
    {:else}
        <div
            class="[&>pre]:m-0 [&>pre]:min-h-16 [&>pre]:overflow-auto [&>pre]:rounded-md [&>pre]:p-4 [&>pre]:font-mono [&>pre]:text-sm"
        >
            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
            {@html highlightedHtml}
        </div>
    {/if}

    {#if block.lang}
        <span
            class="pointer-events-none absolute right-2 top-2 rounded bg-black/40 px-1.5 py-0.5 font-mono text-[10px] text-white/70"
        >
            {block.lang}
        </span>
    {/if}
</div>
