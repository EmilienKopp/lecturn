<script lang="ts">
    import { untrack } from 'svelte';
    import EditorJS, { type OutputData } from '@editorjs/editorjs';
    // @ts-expect-error — no bundled types
    import Header from '@editorjs/header';
    // @ts-expect-error — no bundled types
    import List from '@editorjs/list';
    // @ts-expect-error — no bundled types
    import CodeTool from '@editorjs/code';
    // @ts-expect-error — no bundled types
    import Quote from '@editorjs/quote';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import type { Block } from '@/types/generated';

    let { editor, block }: { editor: EditorState; block: Block } = $props();

    let holderEl = $state<HTMLDivElement | null>(null);

    function parseContent(raw: string): OutputData | undefined {
        if (!raw) return undefined;
        try {
            return JSON.parse(raw) as OutputData;
        } catch {
            return undefined;
        }
    }

    $effect(() => {
        if (!holderEl) return;

        const instance = new EditorJS({
            holder: holderEl,
            data: untrack(() => parseContent(block.content)),
            autofocus: false,
            placeholder: 'Start writing…',
            tools: {
                header: {
                    class: Header,
                    config: { levels: [1, 2, 3, 4], defaultLevel: 2 },
                },
                list: {
                    class: List,
                    inlineToolbar: true,
                },
                code: CodeTool,
                quote: {
                    class: Quote,
                    inlineToolbar: true,
                },
            },
            onChange: async (api) => {
                const data = await api.saver.save();
                editor.updateBlockContent(block.id, JSON.stringify(data));
            },
        });

        return () => {
            instance.isReady.then(() => instance.destroy()).catch(() => {});
        };
    });
</script>

<div
    bind:this={holderEl}
    class="editorjs-holder h-full w-full px-8 py-4"
    data-test="richtext-block-{block.id}"
></div>

<style>
    /* Make EditorJS feel native to the slide canvas */
    .editorjs-holder :global(.codex-editor) {
        height: 100%;
    }
    .editorjs-holder :global(.codex-editor__redactor) {
        padding-bottom: 2rem !important;
    }
    .editorjs-holder :global(.ce-block__content) {
        max-width: 100%;
    }
    .editorjs-holder :global(.ce-toolbar__content) {
        max-width: 100%;
    }
    .editorjs-holder :global(.cdx-block) {
        padding: 0.25rem 0;
    }

    /* Ensure the "+" and drag-handle toolbars are always reachable */
    .editorjs-holder :global(.ce-toolbar) {
        display: block !important;
    }
    .editorjs-holder :global(.ce-toolbar__plus),
    .editorjs-holder :global(.ce-toolbar__settings-btn) {
        display: flex !important;
        opacity: 0;
        transition: opacity 0.1s;
    }
    .editorjs-holder :global(.ce-toolbar--opened .ce-toolbar__plus),
    .editorjs-holder :global(.ce-toolbar--opened .ce-toolbar__settings-btn) {
        opacity: 1;
    }

    .editorjs-holder :global(h1.ce-header) {
        font-size: 2rem;
        font-weight: 700;
    }
    .editorjs-holder :global(h2.ce-header) {
        font-size: 1.5rem;
        font-weight: 600;
    }
    .editorjs-holder :global(h3.ce-header) {
        font-size: 1.25rem;
        font-weight: 600;
    }
    .editorjs-holder :global(h4.ce-header) {
        font-size: 1rem;
        font-weight: 600;
    }
    .editorjs-holder :global(.cdx-quote) {
        border-left: 3px solid currentColor;
        padding-left: 1rem;
    }
    .editorjs-holder :global(.ce-code__textarea) {
        font-family: monospace;
        font-size: 0.875rem;
        background: hsl(220 14% 10%);
        color: hsl(210 40% 96%);
        border-radius: 0.375rem;
        padding: 0.75rem;
    }
</style>
