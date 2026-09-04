<script lang="ts">
    import Bold from 'lucide-svelte/icons/bold';
    import Italic from 'lucide-svelte/icons/italic';
    import { onMount } from 'svelte';
    import {
        applyColor,
        applyFontSize,
        toggleBold,
        toggleItalic,
    } from '@/lib/tecturn/inline-format';

    const fontSizes = ['1rem', '1.5rem', '2rem', '2.5rem', '3rem', '4rem'];

    let visible = $state(false);
    let top = $state(0);
    let left = $state(0);
    let color = $state('#000000');

    // Retained across control clicks that collapse the page selection, so an
    // action can restore the range before applying.
    let activeEditable: HTMLElement | null = null;
    let savedRange: Range | null = null;
    let interacting = false;

    /** The formattable block the selection lives in, if any. */
    function editableFromSelection(): HTMLElement | null {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
            return null;
        }

        let node: Node | null = selection.anchorNode;

        while (node && node !== document.body) {
            if (
                node instanceof HTMLElement &&
                node.hasAttribute('data-inline-format')
            ) {
                return node;
            }

            node = node.parentNode;
        }

        return null;
    }

    function syncFromSelection(): void {
        const editable = editableFromSelection();
        const selection = window.getSelection();

        if (editable && selection && selection.rangeCount > 0) {
            activeEditable = editable;
            savedRange = selection.getRangeAt(0).cloneRange();

            const rect = savedRange.getBoundingClientRect();
            top = rect.top - 44;
            left = rect.left + rect.width / 2;
            visible = true;
        } else if (!interacting) {
            visible = false;
        }
    }

    function restoreSelection(): void {
        if (!savedRange) {
            return;
        }

        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(savedRange);
    }

    /** Restore the selection, run the formatting op, then persist and reposition. */
    function run(op: () => void): void {
        activeEditable?.focus();
        restoreSelection();
        op();
        activeEditable?.dispatchEvent(
            new InputEvent('input', { bubbles: true }),
        );
        interacting = false;
        syncFromSelection();
    }

    onMount(() => {
        document.addEventListener('selectionchange', syncFromSelection);
        window.addEventListener('scroll', syncFromSelection, true);

        return () => {
            document.removeEventListener('selectionchange', syncFromSelection);
            window.removeEventListener('scroll', syncFromSelection, true);
        };
    });
</script>

{#if visible}
    <div
        role="toolbar"
        aria-label="Text formatting"
        tabindex="-1"
        class="fixed z-50 flex -translate-x-1/2 items-center gap-1 rounded-md border bg-popover px-1.5 py-1 text-popover-foreground shadow-lg"
        style="top: {top}px; left: {left}px"
        onpointerdown={() => (interacting = true)}
        data-test="inline-format-toolbar"
    >
        <button
            type="button"
            class="flex h-7 w-7 items-center justify-center rounded hover:bg-muted"
            onmousedown={(event) => event.preventDefault()}
            onclick={() => run(toggleBold)}
            title="Bold"
            data-test="inline-bold"
        >
            <Bold class="h-4 w-4" />
        </button>

        <button
            type="button"
            class="flex h-7 w-7 items-center justify-center rounded hover:bg-muted"
            onmousedown={(event) => event.preventDefault()}
            onclick={() => run(toggleItalic)}
            title="Italic"
            data-test="inline-italic"
        >
            <Italic class="h-4 w-4" />
        </button>

        <div class="mx-0.5 h-5 w-px bg-border"></div>

        <select
            class="h-7 rounded border bg-background px-1 text-xs"
            onchange={(event) => {
                const value = event.currentTarget.value;
                event.currentTarget.selectedIndex = 0;
                run(() => applyFontSize(value));
            }}
            title="Font size for selection"
            data-test="inline-font-size"
        >
            <option value="" disabled selected>Size</option>
            {#each fontSizes as size (size)}
                <option value={size}>{size}</option>
            {/each}
        </select>

        <label
            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded hover:bg-muted"
            title="Text color for selection"
        >
            <span
                class="h-4 w-4 rounded-full border border-border"
                style="background: {color}"
            ></span>
            <input
                type="color"
                class="sr-only"
                bind:value={color}
                oninput={() => run(() => applyColor(color))}
                data-test="inline-color"
            />
        </label>
    </div>
{/if}
