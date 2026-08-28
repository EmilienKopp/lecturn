<script lang="ts">
    import CodeBlockView from '@/components/lecturn/CodeBlockView.svelte';
    import BoxBlockView from '@/components/lecturn/BoxBlockView.svelte';
    import TextBlockView from '@/components/lecturn/TextBlockView.svelte';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';

    let { editor }: { editor: EditorState } = $props();

    const slide = $derived(editor.selectedSlide);
    const rows = $derived((slide.config?.rows as number | undefined) ?? 3);
    const cols = $derived((slide.config?.cols as number | undefined) ?? 3);
    const blocks = $derived(slide.slots['main'] ?? []);

    // Cell selection state
    let selectedCells = $state<Set<string>>(new Set());
    let popoverVisible = $state(false);
    let popoverRect = $state<{ top: number; left: number } | null>(null);
    let gridEl = $state<HTMLDivElement | null>(null);

    function cellKey(r: number, c: number) {
        return `${r},${c}`;
    }

    function isCellOccupied(r: number, c: number): boolean {
        return blocks.some((b) => {
            const col = b.style.gridColumn;
            const row = b.style.gridRow;
            if (!col || !row) return false;
            const [cs, cspan] = parseGridProp(col);
            const [rs, rspan] = parseGridProp(row);
            return r >= rs && r < rs + rspan && c >= cs && c < cs + cspan;
        });
    }

    function parseGridProp(val: string): [number, number] {
        const parts = val.split('/').map((s) => s.trim());
        const start = parseInt(parts[0], 10);
        const spanPart = parts[1] ?? '';
        const span = spanPart.startsWith('span')
            ? parseInt(spanPart.replace('span', '').trim(), 10)
            : 1;
        return [start, span];
    }

    function toggleCell(event: MouseEvent, r: number, c: number) {
        if (!event.ctrlKey && !event.metaKey) {
            // Plain click selects a placed block if one exists at this cell, otherwise clears
            selectedCells = new Set();
            popoverVisible = false;
            return;
        }

        event.preventDefault();

        const key = cellKey(r, c);
        const next = new Set(selectedCells);

        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }

        selectedCells = next;

        if (next.size > 0 && isRectangular(next)) {
            showPopover();
        } else {
            popoverVisible = false;
        }
    }

    function isRectangular(cells: Set<string>): boolean {
        if (cells.size === 0) return false;
        const parsed = [...cells].map((k) => {
            const [r, c] = k.split(',').map(Number);
            return { r, c };
        });
        const minR = Math.min(...parsed.map((p) => p.r));
        const maxR = Math.max(...parsed.map((p) => p.r));
        const minC = Math.min(...parsed.map((p) => p.c));
        const maxC = Math.max(...parsed.map((p) => p.c));
        const expected = (maxR - minR + 1) * (maxC - minC + 1);
        return cells.size === expected;
    }

    function selectionBounds(): {
        minR: number;
        maxR: number;
        minC: number;
        maxC: number;
    } {
        const parsed = [...selectedCells].map((k) => {
            const [r, c] = k.split(',').map(Number);
            return { r, c };
        });
        return {
            minR: Math.min(...parsed.map((p) => p.r)),
            maxR: Math.max(...parsed.map((p) => p.r)),
            minC: Math.min(...parsed.map((p) => p.c)),
            maxC: Math.max(...parsed.map((p) => p.c)),
        };
    }

    function showPopover() {
        if (!gridEl) return;
        const gridRect = gridEl.getBoundingClientRect();
        const bounds = selectionBounds();
        // Position near the bottom-right of the selection
        const cellW = gridRect.width / cols;
        const cellH = gridRect.height / rows;
        popoverRect = {
            top: bounds.maxR * cellH + 8,
            left: (bounds.maxC + 1) * cellW - 120,
        };
        popoverVisible = true;
    }

    function createBlock(type: 'text' | 'code' | 'box') {
        const { minR, maxR, minC, maxC } = selectionBounds();
        const gridColumn = `${minC + 1} / span ${maxC - minC + 1}`;
        const gridRow = `${minR + 1} / span ${maxR - minR + 1}`;
        editor.addGridBlock('main', gridColumn, gridRow, type);
        selectedCells = new Set();
        popoverVisible = false;
    }

    function clearSelection() {
        selectedCells = new Set();
        popoverVisible = false;
    }

    const cellNumbers = $derived(
        Array.from({ length: rows }, (_, r) =>
            Array.from({ length: cols }, (_, c) => ({ r, c })),
        ).flat(),
    );
</script>

<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
<div
    bind:this={gridEl}
    class="relative h-full w-full p-2"
    style="display: grid; grid-template-columns: repeat({cols}, 1fr); grid-template-rows: repeat({rows}, 1fr); gap: 4px;"
    onclick={clearSelection}
    data-test="grid-canvas"
>
    <!-- Ghost cells -->
    {#each cellNumbers as { r, c } (cellKey(r, c))}
        {@const key = cellKey(r, c)}
        {@const occupied = isCellOccupied(r, c)}
        {@const selected = selectedCells.has(key)}
        <div
            class="rounded transition-colors {occupied
                ? 'pointer-events-none opacity-0'
                : selected
                  ? 'border-2 border-primary bg-primary/20'
                  : 'border border-dashed border-muted-foreground/30 hover:border-muted-foreground/60 hover:bg-muted/30'}"
            onclick={(e) => {
                e.stopPropagation();
                toggleCell(e, r, c);
            }}
            style="grid-column: {c + 1}; grid-row: {r + 1};"
            data-test="grid-cell-{r}-{c}"
            title="Ctrl+click to select"
        ></div>
    {/each}

    <!-- Placed blocks -->
    {#each blocks as block (block.id)}
        <div
            class="overflow-hidden"
            style="grid-column: {block.style.gridColumn ??
                'auto'}; grid-row: {block.style.gridRow ?? 'auto'};"
            onclick={(e) => {
                e.stopPropagation();
                editor.selectedBlockId = block.id;
            }}
        >
            {#if block.type === 'text'}
                <TextBlockView {editor} {block} />
            {:else if block.type === 'code'}
                <CodeBlockView {editor} {block} />
            {:else if block.type === 'box'}
                <BoxBlockView {editor} {block} />
            {/if}
        </div>
    {/each}

    <!-- Block type popover -->
    {#if popoverVisible && popoverRect}
        <div
            class="absolute z-50 flex gap-1 rounded-md border bg-popover p-1 shadow-md"
            style="top: {popoverRect.top}px; left: {popoverRect.left}px;"
            onclick={(e) => e.stopPropagation()}
        >
            <button
                type="button"
                class="rounded px-2 py-1 text-xs hover:bg-accent"
                onclick={() => createBlock('text')}>Text</button
            >
            <button
                type="button"
                class="rounded px-2 py-1 text-xs hover:bg-accent"
                onclick={() => createBlock('code')}>Code</button
            >
            <button
                type="button"
                class="rounded px-2 py-1 font-mono text-xs hover:bg-accent"
                onclick={() => createBlock('box')}>Box</button
            >
        </div>
    {/if}
</div>
