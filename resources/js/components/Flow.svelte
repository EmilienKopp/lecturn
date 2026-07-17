<script>
    import {
        SvelteFlow,
        Controls,
        MiniMap,
        Background,
        BackgroundVariant,
    } from '@xyflow/svelte';
    import '@xyflow/svelte/dist/style.css';

    let nodes = $state.raw([
        { id: '1', position: { x: 0, y: 0 }, data: { label: '1' } },
        { id: '2', position: { x: 0, y: 100 }, data: { label: '2' } },
        { id: '3', position: { x: 0, y: 200 }, data: { label: '3' } },
    ]);

    let edges = $state.raw([
        { id: 'e1-2', source: '1', target: '2' },
        { id: 'e2-3', source: '2', target: '3', label: 'edge' },
    ]);
</script>

<div style:width="100%" style:height="100%">
    <SvelteFlow bind:nodes bind:edges fitView colorMode="system">
        <Controls />
        <MiniMap />
        <Background variant={BackgroundVariant.Dots} gap={12} size={1} />
    </SvelteFlow>
</div>
