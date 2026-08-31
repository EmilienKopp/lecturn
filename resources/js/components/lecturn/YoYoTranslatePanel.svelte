<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { SvelteSet } from 'svelte/reactivity';
    import type { YoYoTranslateInfo } from '@/types/generated';

    let {
        yoyotranslate,
        routes,
    }: {
        yoyotranslate: YoYoTranslateInfo;
        routes: { start: string; stop: string };
    } = $props();

    // WebSocket state
    type SocketStatus = 'disconnected' | 'connecting' | 'live' | 'error';
    let socket: WebSocket | null = null;
    let socketStatus: SocketStatus = $state('disconnected');

    // Frames carry arrays of sub-word tokens tagged by language; concatenate
    // them into one running transcript per language.
    type CaptionToken = {
        text: string;
        language?: string;
        is_final?: boolean;
        translation_status?: string;
    };
    let transcripts = $state<Record<string, string>>({});
    let selectedLanguage = $state('');
    let languages = $derived(Object.keys(transcripts));

    // YoYoTranslate has no public API yet, so linking is manual: the presenter
    // creates an event at yoyotranslate.app and pastes its URL here.
    const startForm = useForm({ event_url: '' });

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

            socket.addEventListener('message', async (event) => {
                const raw =
                    event.data instanceof Blob
                        ? await event.data.text()
                        : event.data;

                if (!appendTokens(raw)) {
                    console.warn('[YoYoTranslate] unrecognized frame:', raw);
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

    /** Returns false when the frame doesn't match the expected token shape. */
    function appendTokens(raw: unknown): boolean {
        if (typeof raw !== 'string' || raw === '') {
            return false;
        }

        let tokens: CaptionToken[];

        try {
            const parsed = JSON.parse(raw) as {
                tokens?: unknown;
                data?: { tokens?: unknown };
            };
            // Frames arrive as {type: "translation", data: {tokens: [...]}}.
            const candidate = parsed?.data?.tokens ?? parsed?.tokens;

            if (!Array.isArray(candidate)) {
                return false;
            }

            tokens = candidate.filter(
                (token: unknown): token is CaptionToken =>
                    typeof (token as CaptionToken)?.text === 'string',
            );
        } catch {
            return false;
        }

        for (const token of tokens) {
            // Non-final tokens get re-sent once settled; skip them so text
            // isn't duplicated.
            if (token.is_final === false) {
                continue;
            }

            const language = token.language ?? 'unknown';
            transcripts[language] = (
                (transcripts[language] ?? '') + token.text
            ).slice(-1000);

            if (token.translation_status === 'translation') {
                translatedLanguages.add(language);
            }

            // Auto-select the first translated language to arrive (falling
            // back to the original) so captions show without any clicking.
            if (
                selectedLanguage === '' ||
                (translatedLanguages.has(language) &&
                    !translatedLanguages.has(selectedLanguage))
            ) {
                selectedLanguage = language;
            }
        }

        return true;
    }

    const translatedLanguages = new SvelteSet<string>();

    // Keep the newest caption text in view as the transcript grows.
    let captionBox = $state<HTMLDivElement>();
    $effect(() => {
        void transcripts[selectedLanguage];
        captionBox?.scrollTo({ top: captionBox.scrollHeight });
    });

    const statusLabel: Record<SocketStatus, string> = {
        disconnected: 'Disconnected',
        connecting: 'Connecting…',
        live: 'Live',
        error: 'Error',
    };

    const statusColor: Record<SocketStatus, string> = {
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
            <span
                class="inline-block h-2 w-2 rounded-full {statusColor[
                    socketStatus
                ]}"
            ></span>
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
                {stopForm.processing ? 'Unlinking…' : 'Unlink Translation'}
            </button>
        </form>

        <!-- Live captions -->
        {#if languages.length > 0}
            {#if languages.length > 1}
                <div class="mt-3 flex flex-wrap gap-1">
                    {#each languages as language (language)}
                        <button
                            type="button"
                            onclick={() => (selectedLanguage = language)}
                            class="rounded px-2 py-0.5 text-[11px] uppercase {selectedLanguage ===
                            language
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white/10 text-white/60 hover:bg-white/20'}"
                        >
                            {language}
                        </button>
                    {/each}
                </div>
            {/if}

            <div
                bind:this={captionBox}
                class="mt-3 max-h-28 overflow-y-auto rounded-lg bg-white/10 p-2 text-xs leading-relaxed"
            >
                <p>{transcripts[selectedLanguage] ?? ''}</p>
            </div>
        {:else if socketStatus === 'live'}
            <p class="mt-3 text-center text-xs text-white/50">
                Waiting for captions…
            </p>
        {/if}
    {:else}
        <!-- Link an event created in YoYoTranslate's own UI -->
        <form onsubmit={startSession} class="space-y-2">
            <label class="block text-xs text-white/70">
                Event URL
                <input
                    type="text"
                    bind:value={startForm.event_url}
                    placeholder="https://yoyotranslate.app/events/…"
                    class="mt-1 w-full rounded-lg bg-white/10 px-2 py-1 text-xs text-white placeholder:text-white/30"
                />
            </label>
            <p class="text-[11px] leading-snug text-white/40">
                Create an event on yoyotranslate.app, then paste its link here.
            </p>

            {#if startForm.errors.event_url}
                <p class="text-xs text-red-400">
                    {startForm.errors.event_url}
                </p>
            {/if}

            <button
                type="submit"
                disabled={startForm.processing}
                class="w-full rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium hover:bg-indigo-700 disabled:opacity-50"
            >
                {startForm.processing ? 'Linking…' : 'Link Translation'}
            </button>
        </form>
    {/if}
</div>
