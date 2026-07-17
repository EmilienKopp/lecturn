<script lang="ts">
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { availableLayouts, layoutDefinitions } from '@/lib/lecturn/layouts';

    let { editor }: { editor: EditorState } = $props();

    const slide = $derived(editor.selectedSlide);
    const isCustomGrid = $derived(slide.layout === 'custom-grid');
    const gridRows = $derived((slide.config?.rows as number | undefined) ?? 3);
    const gridCols = $derived((slide.config?.cols as number | undefined) ?? 3);
</script>

<div class="space-y-2">
    <span class="text-xs font-medium text-muted-foreground">Layout</span>
    <div class="grid grid-cols-3 gap-2">
        {#each availableLayouts as layout (layout)}
            <button
                type="button"
                class="rounded border p-2 text-xs transition-colors hover:bg-accent {slide.layout === layout
                    ? 'border-primary bg-accent'
                    : ''}"
                onclick={() => editor.setLayout(layout)}
                data-test="layout-option-{layout}"
            >
                {layoutDefinitions[layout].label}
            </button>
        {/each}
    </div>

    {#if isCustomGrid}
        <div class="space-y-2 rounded-md border p-3">
            <span class="text-xs font-medium text-muted-foreground">Grid dimensions</span>
            <div class="flex items-center gap-3">
                <label class="flex flex-1 flex-col gap-1">
                    <span class="text-xs text-muted-foreground">Rows</span>
                    <input
                        type="number"
                        min="1"
                        max="12"
                        class="w-full rounded-md border bg-background px-2 py-1 text-sm"
                        value={gridRows}
                        onchange={(e) =>
                            editor.updateSlideConfig({
                                rows: Math.max(1, Math.min(12, parseInt(e.currentTarget.value, 10) || 3)),
                            })}
                        data-test="grid-rows-input"
                    />
                </label>
                <span class="mt-4 text-muted-foreground">×</span>
                <label class="flex flex-1 flex-col gap-1">
                    <span class="text-xs text-muted-foreground">Cols</span>
                    <input
                        type="number"
                        min="1"
                        max="12"
                        class="w-full rounded-md border bg-background px-2 py-1 text-sm"
                        value={gridCols}
                        onchange={(e) =>
                            editor.updateSlideConfig({
                                cols: Math.max(1, Math.min(12, parseInt(e.currentTarget.value, 10) || 3)),
                            })}
                        data-test="grid-cols-input"
                    />
                </label>
            </div>
            <p class="text-[11px] text-muted-foreground">
                Ctrl+click cells to select a region, then pick a block type.
            </p>
        </div>
    {/if}
</div>
