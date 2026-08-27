<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { useHttp } from '@inertiajs/svelte';

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

    let lastSent = $state<string | null>(null);

    async function sendReaction(emoji: string): Promise<void> {
        http.emoji = emoji;
        lastSent = emoji;

        await http.post(`/present/${embedToken}/reactions`, {
            onFinish: () => {
                setTimeout(() => {
                    if (lastSent === emoji) lastSent = null;
                }, 800);
            },
        });
    }
</script>

<AppHead title={presentationName} />

<div
    class="flex min-h-screen flex-col items-center justify-center gap-10 bg-zinc-950 px-6 text-white"
>
    <div class="text-center">
        <p class="text-sm font-medium uppercase tracking-widest text-zinc-500">
            You're watching
        </p>
        <h1 class="mt-1 text-2xl font-bold">{presentationName}</h1>
    </div>

    <p class="text-zinc-400">Tap an emoji to react live</p>

    <div class="grid grid-cols-3 gap-4">
        {#each EMOJIS as emoji}
            <button
                type="button"
                class="flex h-20 w-20 items-center justify-center rounded-2xl bg-zinc-800 text-4xl shadow transition active:scale-95 {lastSent === emoji ? 'bg-zinc-600 scale-95' : 'hover:bg-zinc-700'}"
                onclick={() => sendReaction(emoji)}
                disabled={http.processing}
            >
                {emoji}
            </button>
        {/each}
    </div>

    {#if lastSent}
        <p class="text-sm text-zinc-400">Sent {lastSent}</p>
    {/if}
</div>
