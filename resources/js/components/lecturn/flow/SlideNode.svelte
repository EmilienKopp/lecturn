<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';
    import type { NodeProps } from '@xyflow/svelte';
    import Presentation from 'lucide-svelte/icons/presentation';

    let { data, selected }: NodeProps = $props();

    const index = $derived(data.index as number);
    const excerpt = $derived(data.excerpt as string);
    const onOpen = $derived(data.onOpen as () => void);
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
    class="w-48 rounded-lg border-2 bg-card p-3 shadow-sm transition-colors {selected
        ? 'border-primary'
        : 'border-border'}"
    ondblclick={onOpen}
    data-test="flow-slide-node"
>
    <Handle type="target" position={Position.Top} />

    <div
        class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
    >
        <Presentation class="h-3.5 w-3.5" />
        Slide {index + 1}
    </div>

    {#if excerpt}
        <p class="mt-1.5 line-clamp-2 text-sm">{excerpt}</p>
    {:else}
        <p class="mt-1.5 text-sm italic text-muted-foreground">Empty slide</p>
    {/if}

    <Handle type="source" position={Position.Bottom} />
</div>
