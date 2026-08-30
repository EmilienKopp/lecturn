<script lang="ts">
    import Check from 'lucide-svelte/icons/check';
    import Plus from 'lucide-svelte/icons/plus';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import X from 'lucide-svelte/icons/x';
    import type { Snippet } from 'svelte';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import type { Block } from '@/types/generated';

    let {
        editor,
        block,
        children,
    }: {
        editor: EditorState;
        block: Block;
        children: Snippet;
    } = $props();

    let open = $state(false);
    let position = $state({ x: 0, y: 0 });

    const transitions = $derived(
        editor.transitionsForSlide(editor.selectedSlide.id),
    );
    const pinnedNodeId = $derived(block.transition?.nodeId ?? null);
    const pinnedStep = $derived(
        transitions.find((transition) => transition.nodeId === pinnedNodeId) ??
            null,
    );

    function openMenu(event: MouseEvent): void {
        event.preventDefault();
        event.stopPropagation();
        position = { x: event.clientX, y: event.clientY };
        open = true;
    }

    function pinTo(nodeId: string | null): void {
        editor.pinBlock(block.id, nodeId);
        open = false;
    }

    function addStep(): void {
        const nodeId = editor.appendTransitionToSlide(editor.selectedSlide.id);

        if (nodeId) {
            editor.pinBlock(block.id, nodeId);
        }

        open = false;
    }

    $effect(() => {
        if (!open) {
            return;
        }

        const close = (event: PointerEvent) => {
            const target = event.target as HTMLElement | null;

            if (!target?.closest('[data-block-pin-menu]')) {
                open = false;
            }
        };
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                open = false;
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
<div class="relative" oncontextmenu={openMenu}>
    {@render children()}

    {#if pinnedStep}
        <span
            class="pointer-events-none absolute -top-2 -left-2 z-10 flex items-center gap-1 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground shadow"
            data-test="block-pin-badge-{block.id}"
        >
            <Sparkles class="h-2.5 w-2.5" />
            {editor.transitionDisplayName(pinnedStep)}
        </span>
    {/if}
</div>

{#if open}
    <div
        class="fixed z-50 min-w-44 rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
        style="top: {position.y}px; left: {position.x}px;"
        role="menu"
        tabindex="-1"
        data-block-pin-menu
        data-test="block-pin-menu"
    >
        <p class="px-2 py-1 text-xs text-muted-foreground">Set as transition</p>

        {#each transitions as transition (transition.nodeId)}
            <button
                type="button"
                class="flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 hover:bg-accent hover:text-accent-foreground"
                onclick={() => pinTo(transition.nodeId)}
                role="menuitem"
                data-test="block-pin-option-{transition.index}"
            >
                <span class="flex h-3.5 w-3.5 items-center justify-center">
                    {#if transition.nodeId === pinnedNodeId}
                        <Check class="h-3.5 w-3.5" />
                    {/if}
                </span>
                {editor.transitionDisplayName(transition)}
            </button>
        {/each}

        <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 hover:bg-accent hover:text-accent-foreground"
            onclick={addStep}
            role="menuitem"
            data-test="block-pin-add"
        >
            <Plus class="h-3.5 w-3.5" /> Add new step
        </button>

        {#if pinnedNodeId}
            <button
                type="button"
                class="flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                onclick={() => pinTo(null)}
                role="menuitem"
                data-test="block-pin-unpin"
            >
                <X class="h-3.5 w-3.5" /> Unpin
            </button>
        {/if}
    </div>
{/if}
