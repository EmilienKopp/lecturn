<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Presenter from '@/components/lecturn/Presenter.svelte';
    import type { PresentationContent } from '@/types/generated';

    let {
        presentation,
    }: {
        presentation: {
            id: number;
            name: string;
            content: PresentationContent;
            updated_at: string | null;
        };
    } = $props();
</script>

<AppHead title={presentation.name} />

<Presenter content={presentation.content} />
