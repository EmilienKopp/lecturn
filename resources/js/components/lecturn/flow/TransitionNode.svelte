<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';
    import type { NodeProps } from '@xyflow/svelte';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Sparkles from 'lucide-svelte/icons/sparkles';

    let { id, data, selected }: NodeProps = $props();

    const label = $derived((data.label as string | null) ?? '');
    const placeholder = $derived(
        (data.placeholder as string | undefined) ?? 'Transition',
    );
    const onLabelChange = $derived(
        data.onLabelChange as (nodeId: string, label: string | null) => void,
    );

    let editing = $state(false);
    let menuOpen = $state(false);
    let menuPosition = $state({ x: 0, y: 0 });
    let input = $state<HTMLInputElement>();

    // Focus and select the field as soon as it appears so a rename is a
    // type-over, matching the block-pin menu's immediacy.
    $effect(() => {
        if (editing && input) {
            input.focus();
            input.select();
        }
    });

    function openMenu(event: MouseEvent): void {
        event.preventDefault();
        event.stopPropagation();
        menuPosition = { x: event.clientX, y: event.clientY };
        menuOpen = true;
    }

    function startRename(): void {
        menuOpen = false;
        editing = true;
    }

    function commit(value: string): void {
        onLabelChange(id, value.trim() || null);
        editing = false;
    }

    $effect(() => {
        if (!menuOpen) {
            return;
        }

        const close = (event: PointerEvent) => {
            const target = event.target as HTMLElement | null;

            if (!target?.closest('[data-transition-menu]')) {
                menuOpen = false;
            }
        };
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                menuOpen = false;
            }
        };

        document.addEventListener('pointerdown', close);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('pointerdown', close);
            document.removeEventListener('keydown', closeOnEscape);
        };
    });
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
    class="flex items-center gap-1.5 rounded-full border-2 bg-card py-1 pr-3 pl-2.5 shadow-sm transition-colors {selected
        ? 'border-primary'
        : 'border-dashed border-border'}"
    oncontextmenu={openMenu}
    ondblclick={startRename}
    data-test="flow-transition-node"
>
    <Handle type="target" position={Position.Top} />

    <Sparkles class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />

    {#if editing}
        <input
            bind:this={input}
            class="nodrag w-28 bg-transparent text-xs outline-none placeholder:italic"
            {placeholder}
            value={label}
            onblur={(event) => commit(event.currentTarget.value)}
            onkeydown={(event) => {
                if (event.key === 'Enter') {
                    event.currentTarget.blur();
                } else if (event.key === 'Escape') {
                    editing = false;
                }
            }}
        />
    {:else if label}
        <span class="w-28 truncate text-xs">{label}</span>
    {:else}
        <span class="w-28 truncate text-xs text-muted-foreground italic"
            >{placeholder}</span
        >
    {/if}

    <Handle type="source" position={Position.Bottom} />
</div>

{#if menuOpen}
    <div
        class="fixed z-50 min-w-36 rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
        style="top: {menuPosition.y}px; left: {menuPosition.x}px;"
        role="menu"
        tabindex="-1"
        data-transition-menu
        data-test="transition-context-menu"
    >
        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 hover:bg-accent hover:text-accent-foreground"
            onclick={startRename}
            role="menuitem"
            data-test="transition-rename"
        >
            <Pencil class="h-3.5 w-3.5" /> Rename
        </button>
    </div>
{/if}
