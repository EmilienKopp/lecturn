<script lang="ts">
    import Check from 'lucide-svelte/icons/check';
    import Copy from 'lucide-svelte/icons/copy';
    import type { TalkSettings } from '@/types/generated';

    let {
        viewerUrl,
        talkSettings,
        slideCount = 0,
        currentSlide = 0,
        recentReactions = [],
        showReactions = $bindable(false),
    }: {
        viewerUrl: string;
        talkSettings: TalkSettings;
        slideCount?: number;
        currentSlide?: number;
        recentReactions?: { id: number; emoji: string }[];
        showReactions?: boolean;
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
            ? formatTime(
                  Math.max(
                      0,
                      talkSettings.durationMinutes * 60 - elapsedSeconds,
                  ),
              )
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

    let copiedViewerUrl = $state(false);

    const copyViewerUrl = async () => {
        await navigator.clipboard.writeText(viewerUrl);
        copiedViewerUrl = true;
        setTimeout(() => (copiedViewerUrl = false), 2000);
    };
</script>

<aside
    class="flex h-full w-72 flex-col gap-4 overflow-y-auto bg-zinc-900 p-4 text-white"
    data-test="presenter-dock"
>
    <!-- Timer -->
    <section class="rounded-lg bg-zinc-800 p-4">
        <p
            class="mb-1 text-xs font-semibold uppercase tracking-wider text-zinc-400"
        >
            {talkSettings.timerMode === 'countdown' ? 'Time left' : 'Elapsed'}
        </p>
        <p
            class="font-mono text-4xl font-bold tabular-nums {isOverTime
                ? 'text-red-400'
                : 'text-white'}"
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
            <img
                src={qrUrl}
                alt="QR code for {viewerUrl}"
                width="180"
                height="180"
            />
        </div>
        <button
            type="button"
            class="mt-2 flex w-full items-center justify-center gap-1 rounded px-1 py-0.5 text-[10px] text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-600"
            onclick={copyViewerUrl}
            title="Copy reaction URL"
            data-test="dock-copy-viewer-url"
        >
            {#if copiedViewerUrl}
                <Check class="h-3 w-3 shrink-0 text-emerald-500" /> Copied!
            {:else}
                <Copy class="h-3 w-3 shrink-0" />
                <span class="truncate">{viewerUrl}</span>
            {/if}
        </button>
    </section>

    <!-- Reactions -->
    <section class="rounded-lg bg-zinc-800 p-4">
        <div class="flex items-center justify-between">
            <p
                class="text-xs font-semibold uppercase tracking-wider text-zinc-400"
            >
                Reactions
            </p>
            <button
                type="button"
                role="switch"
                aria-checked={showReactions}
                aria-label="Show audience reactions on screen"
                class="relative h-5 w-9 rounded-full transition-colors {showReactions
                    ? 'bg-amber-500'
                    : 'bg-zinc-600'}"
                onclick={() => (showReactions = !showReactions)}
                data-test="dock-reactions-toggle"
            >
                <span
                    class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white transition-transform {showReactions
                        ? 'translate-x-4'
                        : ''}"
                ></span>
            </button>
        </div>
    </section>
</aside>
