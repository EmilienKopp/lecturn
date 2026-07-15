<script lang="ts">
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { availableLayouts, layoutDefinitions } from '@/lib/lecturn/layouts';

    let { editor }: { editor: EditorState } = $props();
</script>

<div class="space-y-1">
    <span class="text-xs font-medium text-muted-foreground">Layout</span>
    <div class="grid grid-cols-3 gap-2">
        {#each availableLayouts as layout (layout)}
            <button
                type="button"
                class="rounded border p-2 text-xs transition-colors hover:bg-accent {editor
                    .selectedSlide.layout === layout
                    ? 'border-primary bg-accent'
                    : ''}"
                onclick={() => editor.setLayout(layout)}
                data-test="layout-option-{layout}"
            >
                {layoutDefinitions[layout].label}
            </button>
        {/each}
    </div>
</div>
