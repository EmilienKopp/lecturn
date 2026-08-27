<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import FloatingReactions from '@/components/lecturn/FloatingReactions.svelte';
    import PresenterDock from '@/components/lecturn/PresenterDock.svelte';
    import Presenter from '@/components/lecturn/Presenter.svelte';
    import type { PresentationContent, TalkSettings } from '@/types/generated';

    let {
        presentation,
        viewerUrl,
    }: {
        presentation: {
            id: number;
            name: string;
            content: PresentationContent;
            talk_settings: TalkSettings;
            embed_token: string;
            updated_at: string | null;
        };
        viewerUrl: string;
    } = $props();

    let slideCount = $derived(presentation.content.slides.length);
</script>

<AppHead title={presentation.name} />

<div class="flex h-screen w-screen overflow-hidden bg-black">
    <!-- Slide column -->
    <div class="relative flex flex-1 flex-col items-center justify-center bg-black">
        <!-- 16:9 constrained slide box -->
        <div class="w-full" style="aspect-ratio: 16/9; max-height: 100vh;">
            <Presenter content={presentation.content} />
        </div>

        <FloatingReactions
            bind:this={floatingReactions}
            enabled={presentation.talk_settings.showReactions}
        />
    </div>

    <!-- Dock column -->
    <PresenterDock
        embedToken={presentation.embed_token}
        {viewerUrl}
        talkSettings={presentation.talk_settings}
        {slideCount}
    />
</div>
