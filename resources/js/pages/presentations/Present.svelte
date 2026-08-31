<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import FloatingReactions from '@/components/tecturn/FloatingReactions.svelte';
    import Presenter from '@/components/tecturn/Presenter.svelte';
    import PresenterDock from '@/components/tecturn/PresenterDock.svelte';
    import YoYoTranslatePanel from '@/components/tecturn/YoYoTranslatePanel.svelte';
    import { getEcho } from '@/lib/echo';
    import type {
        FlowGraph,
        PresentationContent,
        TalkSettings,
        YoYoTranslateInfo,
    } from '@/types/generated';

    let {
        presentation,
        viewerUrl,
        translationRoutes,
    }: {
        presentation: {
            id: number;
            name: string;
            content: PresentationContent;
            talk_settings: TalkSettings;
            flow: FlowGraph | null;
            embed_token: string;
            updated_at: string | null;
            yoyotranslate: YoYoTranslateInfo;
        };
        viewerUrl: string;
        translationRoutes: { start: string; stop: string };
    } = $props();

    let slideCount = $derived(presentation.content.slides.length);

    // Session-only override: starts from the saved setting, toggled from the
    // dock without persisting.
    let showReactions = $state(presentation.talk_settings.showReactions);

    let floatingReactions = $state<FloatingReactions>();
    let recentReactions = $state<{ id: number; emoji: string }[]>([]);
    let reactionCounter = 0;

    $effect(() => {
        const channelName = `presentation.${presentation.embed_token}`;

        getEcho()
            .channel(channelName)
            .listen('.reaction.sent', (event: { emoji: string }) => {
                floatingReactions?.spawnReaction(event.emoji);
                recentReactions = [
                    ...recentReactions,
                    { id: ++reactionCounter, emoji: event.emoji },
                ].slice(-30);
            });

        return () => getEcho().leave(channelName);
    });
</script>

<AppHead title={presentation.name} />

<div class="flex h-screen w-screen overflow-hidden bg-black">
    <!-- Slide column -->
    <div
        class="relative flex flex-1 flex-col items-center justify-center overflow-hidden bg-black [container-type:size]"
    >
        <!-- The slide box is the largest 16:9 that fits the column on both
             axes, centered, so wide screens letterbox with black around it
             instead of the slide stretching to fill. -->
        <div
            style="width: min(100cqw, calc(100cqh * 16 / 9)); aspect-ratio: 16 / 9;"
        >
            <Presenter
                content={presentation.content}
                flow={presentation.flow}
            />
        </div>

        <FloatingReactions
            bind:this={floatingReactions}
            enabled={showReactions}
        />
    </div>

    <!-- Dock column -->
    {#if presentation.talk_settings.showDock}
        <PresenterDock
            {viewerUrl}
            talkSettings={presentation.talk_settings}
            {slideCount}
            {recentReactions}
            bind:showReactions
        />
    {/if}
</div>

<YoYoTranslatePanel
    yoyotranslate={presentation.yoyotranslate}
    routes={translationRoutes}
/>
