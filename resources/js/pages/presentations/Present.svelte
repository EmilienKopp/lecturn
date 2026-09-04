<script lang="ts">
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FloatingReactions from '@/components/tecturn/FloatingReactions.svelte';
    import Presenter from '@/components/tecturn/Presenter.svelte';
    import PresenterDock from '@/components/tecturn/PresenterDock.svelte';
    import PresentFooter from '@/components/tecturn/PresentFooter.svelte';
    import YoYoTranslatePanel from '@/components/tecturn/YoYoTranslatePanel.svelte';
    import { getEcho } from '@/lib/echo';
    import { beaconPost } from '@/lib/tecturn/beacon';
    import type {
        FlowGraph,
        PresentationContent,
        TalkSettings,
        YoYoTranslateInfo,
    } from '@/types/generated';

    let {
        presentation,
        viewerUrl,
        sessionRoutes,
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
        sessionRoutes: { start: string; close: string };
        translationRoutes: { start: string; stop: string };
    } = $props();

    let slideCount = $derived(presentation.content.slides.length);

    // Session-only override: starts from the saved setting, toggled from the
    // dock without persisting.
    let showReactions = $state(presentation.talk_settings.showReactions);

    let floatingReactions = $state<FloatingReactions>();
    let recentReactions = $state<{ id: number; emoji: string }[]>([]);
    let reactionCounter = 0;

    // Live stats shown in the dock, fed by the presentation broadcast channel.
    let viewerCount = $state(0);
    let reactionTotal = $state(0);

    $effect(() => {
        const channelName = `presentation.${presentation.embed_token}`;

        getEcho()
            .channel(channelName)
            .listen('.reaction.sent', (event: { emoji: string }) => {
                floatingReactions?.spawnReaction(event.emoji);
                reactionTotal += 1;
                recentReactions = [
                    ...recentReactions,
                    { id: ++reactionCounter, emoji: event.emoji },
                ].slice(-30);
            })
            .listen('.viewer.presence', (event: { count: number }) => {
                viewerCount = event.count;
            });

        return () => getEcho().leave(channelName);
    });

    // A live session opens while the presenter is on this page and closes when
    // they leave, so reactions and viewers are attributed to a real talk.
    onMount(() => {
        beaconPost(sessionRoutes.start);

        const close = (): void => beaconPost(sessionRoutes.close);

        window.addEventListener('pagehide', close);

        return () => {
            window.removeEventListener('pagehide', close);
            close();
        };
    });
</script>

<AppHead title={presentation.name} />

<div class="flex h-screen w-screen overflow-hidden bg-black">
    <!-- Slide column: a vertical stack so the caption bar docks above or
         below the slide area, shrinking it instead of overlapping it. The
         presenter dock keeps its full-height column to the right. -->
    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
        <div
            class="relative flex min-h-0 flex-1 flex-col items-center justify-center overflow-hidden bg-black [container-type:size]"
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

            {#if presentation.talk_settings.footer.enabled && !presentation.talk_settings.footer.showInDock}
                <PresentFooter
                    footer={presentation.talk_settings.footer}
                    variant="overlay"
                />
            {/if}
        </div>

        {#if presentation.talk_settings.showTranslation}
            <YoYoTranslatePanel
                yoyotranslate={presentation.yoyotranslate}
                routes={translationRoutes}
            />
        {/if}
    </div>

    <!-- Dock column -->
    {#if presentation.talk_settings.showDock}
        <PresenterDock
            {viewerUrl}
            talkSettings={presentation.talk_settings}
            {slideCount}
            {recentReactions}
            {viewerCount}
            {reactionTotal}
            bind:showReactions
        />
    {/if}
</div>
