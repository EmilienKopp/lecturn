<script lang="ts">
    import { Handle, Position } from '@xyflow/svelte';
    import type { NodeProps } from '@xyflow/svelte';
    import Sparkles from 'lucide-svelte/icons/sparkles';

    let { id, data, selected }: NodeProps = $props();

    const label = $derived((data.label as string | null) ?? '');
    const onLabelChange = $derived(
        data.onLabelChange as (nodeId: string, label: string | null) => void,
    );
</script>

<div
    class="flex items-center gap-1.5 rounded-full border-2 bg-card py-1 pl-2.5 pr-3 shadow-sm transition-colors {selected
        ? 'border-primary'
        : 'border-dashed border-border'}"
    data-test="flow-transition-node"
>
    <Handle type="target" position={Position.Top} />

    <Sparkles class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />

    <input
        class="nodrag w-28 bg-transparent text-xs outline-none placeholder:italic"
        placeholder="Transition"
        value={label}
        onchange={(event) =>
            onLabelChange(id, event.currentTarget.value.trim() || null)}
    />

    <Handle type="source" position={Position.Bottom} />
</div>
