<script lang="ts">
    import { useHttp } from '@inertiajs/svelte';
    import SendReactionController from '@/actions/App/Http/Controllers/Presentations/SendReactionController';
    import AppHead from '@/components/AppHead.svelte';
    import FloatingReactions from '@/components/tecturn/FloatingReactions.svelte';

    let {
        presentationName,
        embedToken,
    }: {
        presentationName: string;
        embedToken: string;
    } = $props();

    const EMOJIS = ['👏', '❤️', '😂', '🤯', '🙌', '🔥'] as const;

    const http = useHttp({
        emoji: '',
    });

    let floatingReactions = $state<FloatingReactions>();
    let flaring = $state<string | null>(null);
    let lastSentAt = 0;

    function sendReaction(emoji: string): void {
        const now = performance.now();

        if (now - lastSentAt < 250) {
            return;
        }

        lastSentAt = now;

        floatingReactions?.spawnReaction(emoji);
        flaring = emoji;
        setTimeout(() => {
            if (flaring === emoji) {
                flaring = null;
            }
        }, 400);

        http.emoji = emoji;
        http.post(SendReactionController.url({ presentation: embedToken }));
    }
</script>

<AppHead title={presentationName} />

<!-- The guest's own reactions float up their screen, mirroring the hall. -->
<div class="pointer-events-none absolute inset-0 z-20" aria-hidden="true">
    <FloatingReactions bind:this={floatingReactions} enabled />
</div>

<main
    class="relative z-10 flex flex-1 flex-col items-center px-6 pt-[18dvh] pb-[max(2.5rem,env(safe-area-inset-bottom))]"
>
    <header class="flex flex-col items-center text-center">
        <p
            class="flex items-center gap-2 font-mono text-[11px] font-semibold tracking-[0.3em] text-[hsl(37_6%_55%)] uppercase"
        >
            <span
                class="live-dot h-2 w-2 rounded-full bg-[hsl(37_91%_55%)]"
                aria-hidden="true"
            ></span>
            Live
        </p>
        <p class="mt-6 text-sm text-[hsl(37_6%_55%)]">
            You're in the audience of
        </p>
        <h1 class="font-display mt-2 max-w-md text-3xl font-bold text-balance">
            {presentationName}
        </h1>
    </header>

    <div class="flex-1"></div>

    <p class="mb-6 text-sm text-[hsl(37_6%_55%)]">
        Tap a key — it lands on the big screen
    </p>

    <div class="grid grid-cols-3 gap-4">
        {#each EMOJIS as emoji (emoji)}
            <button
                type="button"
                class="footlight-key flex h-20 w-20 items-center justify-center rounded-full text-4xl select-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[hsl(37_91%_55%)]"
                class:is-flaring={flaring === emoji}
                onclick={() => sendReaction(emoji)}
                aria-label="React with {emoji}"
            >
                {emoji}
            </button>
        {/each}
    </div>
</main>

<style>
    .footlight-key {
        background-color: hsl(34 10% 15%);
        box-shadow:
            inset 0 1px 0 hsl(40 20% 92% / 0.06),
            0 0 0 1px hsl(34 9% 21%),
            0 8px 24px -12px hsl(36 45% 4% / 0.8);
        transition:
            transform 120ms ease,
            box-shadow 200ms ease;
    }

    .footlight-key:active {
        transform: scale(0.92);
    }

    .footlight-key.is-flaring {
        box-shadow:
            inset 0 1px 0 hsl(40 20% 92% / 0.06),
            0 0 0 1.5px hsl(37 91% 55%),
            0 0 28px hsl(37 91% 55% / 0.35);
    }

    @keyframes live-pulse {
        0%,
        100% {
            opacity: 1;
        }
        50% {
            opacity: 0.35;
        }
    }

    .live-dot {
        animation: live-pulse 2.4s ease-in-out infinite;
    }

    @media (prefers-reduced-motion: reduce) {
        .live-dot {
            animation: none;
        }

        .footlight-key,
        .footlight-key:active {
            transition: none;
            transform: none;
        }
    }
</style>
