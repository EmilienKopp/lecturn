<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';
    import type { NodeProps } from '@xyflow/svelte';
    import Presentation from 'lucide-svelte/icons/presentation';

    let { data, selected }: NodeProps = $props();

    const index = $derived(data.index as number);
    const title = $derived((data.title as string | null) ?? null);
    const enabled = $derived((data.enabled as boolean | undefined) ?? true);
    const excerpt = $derived(data.excerpt as string);
    const onOpen = $derived(data.onOpen as () => void);
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
    class="w-48 rounded-lg border-2 bg-card p-3 shadow-sm transition-colors {selected
        ? 'border-primary'
        : 'border-border'} {enabled ? '' : 'border-dashed opacity-45'}"
    ondblclick={onOpen}
    data-test="flow-slide-node"
    data-disabled={!enabled}
>
    <Handle type="target" position={Position.Top} />

    <div
        class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
    >
        <Presentation class="h-3.5 w-3.5" />
        {title ?? `Slide ${index + 1}`}
    </div>

    {#if excerpt}
        <p class="mt-1.5 line-clamp-2 text-sm">{excerpt}</p>
    {:else}
        <p class="mt-1.5 text-sm italic text-muted-foreground">Empty slide</p>
    {/if}

    <Handle type="source" position={Position.Bottom} />
</div>
