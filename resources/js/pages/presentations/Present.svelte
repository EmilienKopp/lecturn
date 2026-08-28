<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import FloatingReactions from '@/components/lecturn/FloatingReactions.svelte';
    import Presenter from '@/components/lecturn/Presenter.svelte';
    import PresenterDock from '@/components/lecturn/PresenterDock.svelte';
    import { getEcho } from '@/lib/echo';
    import type {
        FlowGraph,
        PresentationContent,
        TalkSettings,
    } from '@/types/generated';

    let {
        presentation,
        viewerUrl,
    }: {
        presentation: {
            id: number;
            name: string;
            content: PresentationContent;
            talk_settings: TalkSettings;
            flow: FlowGraph | null;
            embed_token: string;
            updated_at: string | null;
        };
        viewerUrl: string;
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
        class="relative flex flex-1 flex-col items-center justify-center bg-black"
    >
        <!-- 16:9 constrained slide box -->
        <div class="w-full" style="aspect-ratio: 16/9; max-height: 100vh;">
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
