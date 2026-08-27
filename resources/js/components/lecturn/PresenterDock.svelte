<script lang="ts">
    import type { TalkSettings } from '@/types/generated';

    let {
        embedToken,
        viewerUrl,
        talkSettings,
        slideCount = 0,
        currentSlide = 0,
    }: {
        embedToken: string;
        viewerUrl: string;
        talkSettings: TalkSettings;
        slideCount?: number;
        currentSlide?: number;
    } = $props();

    // --- Timer ---
    let elapsedSeconds = $state(0);
    let startedAt = $state(Date.now());

    $effect(() => {
        startedAt = Date.now();
        elapsedSeconds = 0;

        const interval = setInterval(() => {
            elapsedSeconds = Math.floor((Date.now() - startedAt) / 1000);
        }, 1000);

        return () => clearInterval(interval);
    });

    const formatTime = (seconds: number): string => {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) {
            return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }

        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };

    const displayTime = $derived(
        talkSettings.timerMode === 'countdown' && talkSettings.durationMinutes
            ? formatTime(Math.max(0, talkSettings.durationMinutes * 60 - elapsedSeconds))
            : formatTime(elapsedSeconds),
    );

    const isOverTime = $derived(
        talkSettings.timerMode === 'countdown' &&
            talkSettings.durationMinutes !== null &&
            talkSettings.durationMinutes !== undefined &&
            elapsedSeconds >= talkSettings.durationMinutes * 60,
    );

    // --- QR Code ---
    const qrUrl = $derived(
        `https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=4&data=${encodeURIComponent(viewerUrl)}`,
    );

    // --- Recent reactions ---
    let recentReactions = $state<{ id: number; emoji: string }[]>([]);
</script>

<aside
    class="flex h-full w-72 flex-col gap-4 overflow-y-auto bg-zinc-900 p-4 text-white"
    data-test="presenter-dock"
>
    <!-- Timer -->
    <section class="rounded-lg bg-zinc-800 p-4">
        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-zinc-400">
            {talkSettings.timerMode === 'countdown' ? 'Time left' : 'Elapsed'}
        </p>
        <p
            class="font-mono text-4xl font-bold tabular-nums {isOverTime ? 'text-red-400' : 'text-white'}"
        >
            {displayTime}
        </p>
        {#if slideCount > 0}
            <p class="mt-2 text-xs text-zinc-400">
                Slide {currentSlide + 1} / {slideCount}
            </p>
        {/if}
    </section>

    <!-- QR Code -->
    <section class="rounded-lg bg-white p-3">
        <p class="mb-2 text-center text-xs font-semibold text-zinc-700">
            Scan to react
        </p>
        <div class="flex justify-center">
            <img src={qrUrl} alt="QR code for {viewerUrl}" width="180" height="180" />
        </div>
        <p class="mt-2 truncate text-center text-[10px] text-zinc-400">
            {viewerUrl}
        </p>
    </section>

    <!-- Recent reactions -->
    {#if recentReactions.length > 0}
        <section class="rounded-lg bg-zinc-800 p-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                Reactions
            </p>
            <div class="flex flex-wrap gap-1">
                {#each recentReactions.slice(-20) as reaction (reaction.id)}
                    <span class="text-xl">{reaction.emoji}</span>
                {/each}
            </div>
        </section>
    {/if}
</aside>
