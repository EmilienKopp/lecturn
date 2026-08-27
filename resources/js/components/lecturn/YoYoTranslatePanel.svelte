<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import type { YoYoTranslateInfo } from '@/types/generated';

    let {
        yoyotranslate,
        routes,
    }: {
        yoyotranslate: YoYoTranslateInfo;
        routes: { start: string; stop: string };
    } = $props();

    // WebSocket state
    let socket: WebSocket | null = null;
    let socketStatus: 'disconnected' | 'connecting' | 'live' | 'error' = $state(
        'disconnected',
    );
    let captions: string[] = $state([]);

    // Form for starting a session (needs a source language)
    const startForm = useForm({ source_language: 'en' });

    function startSession(e: SubmitEvent) {
        e.preventDefault();
        startForm.post(routes.start);
    }

    const stopForm = useForm({});

    function stopSession(e: SubmitEvent) {
        e.preventDefault();
        stopForm.delete(routes.stop);
    }

    // Open / close WebSocket whenever the session becomes active
    $effect(() => {
        if (yoyotranslate.active && yoyotranslate.websocket_url) {
            socketStatus = 'connecting';
            socket = new WebSocket(yoyotranslate.websocket_url);

            socket.addEventListener('open', () => {
                socketStatus = 'live';
            });

            socket.addEventListener('message', (event) => {
                const text =
                    typeof event.data === 'string' ? event.data : '';
                if (text) {
                    captions = [...captions.slice(-49), text];
                }
            });

            socket.addEventListener('error', () => {
                socketStatus = 'error';
            });

            socket.addEventListener('close', () => {
                if (socketStatus !== 'error') {
                    socketStatus = 'disconnected';
                }
                socket = null;
            });

            return () => {
                socket?.close();
                socket = null;
                socketStatus = 'disconnected';
            };
        }
    });

    const statusLabel: Record<typeof socketStatus, string> = {
        disconnected: 'Disconnected',
        connecting: 'Connecting…',
        live: 'Live',
        error: 'Error',
    };

    const statusColor: Record<typeof socketStatus, string> = {
        disconnected: 'bg-gray-400',
        connecting: 'bg-yellow-400 animate-pulse',
        live: 'bg-green-500',
        error: 'bg-red-500',
    };
</script>

<div
    class="fixed bottom-4 right-4 z-50 w-80 rounded-xl border border-white/10 bg-black/70 p-4 text-white shadow-2xl backdrop-blur-md"
    data-test="yoyotranslate-panel"
>
    <div class="mb-3 flex items-center justify-between">
        <span class="text-sm font-semibold">YoYoTranslate</span>
        <span class="flex items-center gap-1.5 text-xs">
            <span class="inline-block h-2 w-2 rounded-full {statusColor[socketStatus]}"></span>
            {statusLabel[socketStatus]}
        </span>
    </div>

    {#if yoyotranslate.active}
        <!-- Active session controls -->
        <form onsubmit={stopSession}>
            <button
                type="submit"
                disabled={stopForm.processing}
                class="w-full rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium hover:bg-red-700 disabled:opacity-50"
            >
                {stopForm.processing ? 'Stopping…' : 'Stop Translation'}
            </button>
        </form>

        <!-- Live captions -->
        {#if captions.length > 0}
            <div class="mt-3 max-h-28 overflow-y-auto rounded-lg bg-white/10 p-2 text-xs leading-relaxed">
                {#each captions as caption, i (i)}
                    <p>{caption}</p>
                {/each}
            </div>
        {:else if socketStatus === 'live'}
            <p class="mt-3 text-center text-xs text-white/50">Waiting for captions…</p>
        {/if}
    {:else}
        <!-- Start session form -->
        <form onsubmit={startSession} class="space-y-2">
            <label class="block text-xs text-white/70">
                Source language
                <select
                    bind:value={startForm.source_language}
                    class="mt-1 w-full rounded-lg bg-white/10 px-2 py-1 text-xs text-white"
                >
                    <option value="en">English</option>
                    <option value="fr">French</option>
                    <option value="es">Spanish</option>
                    <option value="de">German</option>
                    <option value="pt">Portuguese</option>
                    <option value="ja">Japanese</option>
                    <option value="zh">Chinese</option>
                </select>
            </label>

            {#if startForm.errors.source_language}
                <p class="text-xs text-red-400">{startForm.errors.source_language}</p>
            {/if}

            <button
                type="submit"
                disabled={startForm.processing}
                class="w-full rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium hover:bg-indigo-700 disabled:opacity-50"
            >
                {startForm.processing ? 'Starting…' : 'Start Translation'}
            </button>
        </form>
    {/if}
</div>
