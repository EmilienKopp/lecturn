<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Presenter from '@/components/lecturn/Presenter.svelte';
    import YoYoTranslatePanel from '@/components/lecturn/YoYoTranslatePanel.svelte';
    import type { PresentationContent, YoYoTranslateInfo } from '@/types/generated';

    let {
        presentation,
        translationRoutes,
    }: {
        presentation: {
            id: number;
            name: string;
            content: PresentationContent;
            updated_at: string | null;
            yoyotranslate: YoYoTranslateInfo;
        };
        translationRoutes: { start: string; stop: string };
    } = $props();
</script>

<AppHead title={presentation.name} />

<Presenter content={presentation.content} />

<YoYoTranslatePanel
    yoyotranslate={presentation.yoyotranslate}
    routes={translationRoutes}
/>
