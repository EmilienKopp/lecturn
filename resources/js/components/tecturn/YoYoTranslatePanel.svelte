<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import ArrowDownToLine from 'lucide-svelte/icons/arrow-down-to-line';
    import ArrowUpToLine from 'lucide-svelte/icons/arrow-up-to-line';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import Unlink from 'lucide-svelte/icons/unlink';
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
    let reconnectNonce = $state(0);

    // Frames carry arrays of sub-word tokens tagged by language; concatenate
    // them into one running transcript per language.
    type CaptionToken = {
        text: string;
        language?: string;
        is_final?: boolean;
        translation_status?: string;
    };
    let transcripts = $state<Record<string, string>>({});
    let languages = $derived(Object.keys(transcripts));

    // Up to two languages shown at once; two get a two-column layout.
    let selectedLanguages = $state<string[]>([]);
    let dockedTop = $state(false);

    function toggleLanguage(language: string) {
        if (selectedLanguages.includes(language)) {
            selectedLanguages = selectedLanguages.filter(
                (selected) => selected !== language,
            );
        } else {
            selectedLanguages = [...selectedLanguages, language].slice(-2);
        }
    }

    // YoYoTranslate has no public API yet, so linking is manual: the presenter
    // creates an event at yoyotranslate.app and pastes its URL here.
    const startForm = useForm({ event_url: '' });

    function startSession(e: SubmitEvent) {
        e.preventDefault();
        startForm.post(routes.start);
    }

    const stopForm = useForm({});

    function unlinkSession() {
        stopForm.delete(routes.stop);
    }

    // Open / close WebSocket whenever the session becomes active. Bumping
    // reconnectNonce re-runs the effect, tearing down and reopening the socket.
    $effect(() => {
        void reconnectNonce;

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

            autoSelect(language);
        }

        return true;
    }

    /**
     * Auto-select the first translated language to arrive (falling back to
     * the original) so captions show without any clicking.
     */
    function autoSelect(language: string) {
        if (selectedLanguages.includes(language)) {
            return;
        }

        if (selectedLanguages.length === 0) {
            selectedLanguages = [language];

            return;
        }

        const hasTranslatedSelection = selectedLanguages.some((selected) =>
            translatedLanguages.has(selected),
        );

        if (translatedLanguages.has(language) && !hasTranslatedSelection) {
            selectedLanguages = [...selectedLanguages, language].slice(-2);
        }
    }

    const translatedLanguages = new SvelteSet<string>();

    // Keep the newest caption text in view as the transcripts grow.
    const captionBoxes: Record<string, HTMLDivElement | undefined> = {};
    $effect(() => {
        for (const language of selectedLanguages) {
            void transcripts[language];
        }

        for (const box of Object.values(captionBoxes)) {
            box?.scrollTo({ top: box.scrollHeight });
        }
    });

    const statusColor: Record<SocketStatus, string> = {
        disconnected: 'bg-gray-400',
        connecting: 'bg-yellow-400 animate-pulse',
        live: 'bg-green-500',
        error: 'bg-red-500',
    };
</script>

{#if yoyotranslate.active}
    <!-- Subtitle bar: rendered in the slide column's vertical stack, so it
         claims its own space instead of overlaying the slides. order-first
         moves it above the slide area when docked to the top. -->
    <div
        class="w-full bg-zinc-950/80 px-6 pb-4 pt-1.5 {dockedTop
            ? 'order-first'
            : 'order-last'}"
        data-test="yoyotranslate-panel"
    >
        <!-- Control cluster, top right -->
        <div class="flex items-center justify-end gap-2">
            {#if languages.length > 1}
                <div class="flex gap-1">
                    {#each languages as language (language)}
                        <button
                            type="button"
                            onclick={() => toggleLanguage(language)}
                            class="rounded px-1.5 text-[10px] uppercase tracking-wide transition-colors {selectedLanguages.includes(
                                language,
                            )
                                ? 'bg-white/20 text-white/90'
                                : 'text-white/35 hover:text-white/70'}"
                        >
                            {language}
                        </button>
                    {/each}
                </div>
            {/if}

            <span
                class="flex items-center gap-1 text-[10px] uppercase tracking-wide text-white/40"
            >
                <span
                    class="inline-block h-1.5 w-1.5 rounded-full {statusColor[
                        socketStatus
                    ]}"
                ></span>
                {socketStatus}
            </span>

            <button
                type="button"
                onclick={() => (dockedTop = !dockedTop)}
                title={dockedTop ? 'Dock to bottom' : 'Dock to top'}
                class="text-white/35 transition-colors hover:text-white/80"
            >
                {#if dockedTop}
                    <ArrowDownToLine class="h-3 w-3" />
                {:else}
                    <ArrowUpToLine class="h-3 w-3" />
                {/if}
            </button>

            <button
                type="button"
                onclick={() => reconnectNonce++}
                title="Reconnect"
                class="text-white/35 transition-colors hover:text-white/80"
            >
                <RefreshCw class="h-3 w-3" />
            </button>

            <button
                type="button"
                onclick={unlinkSession}
                disabled={stopForm.processing}
                title="Unlink translation"
                class="text-white/35 transition-colors hover:text-red-400 disabled:opacity-50"
            >
                <Unlink class="h-3 w-3" />
            </button>
        </div>

        <!-- Captions -->
        {#if selectedLanguages.length > 0}
            <div
                class="mt-2 grid gap-8 {selectedLanguages.length === 2
                    ? 'grid-cols-2'
                    : 'grid-cols-1'}"
            >
                {#each selectedLanguages as language (language)}
                    <div
                        bind:this={captionBoxes[language]}
                        class="max-h-28 overflow-y-auto text-lg leading-relaxed text-white/90"
                    >
                        <p>{transcripts[language] ?? ''}</p>
                    </div>
                {/each}
            </div>
        {:else}
            <p class="my-4 text-center text-sm text-white/40">
                Waiting for captions…
            </p>
        {/if}
    </div>
{:else}
    <!-- Link an event created in YoYoTranslate's own UI -->
    <div
        class="fixed bottom-4 right-4 z-50 w-80 rounded-xl border border-white/10 bg-black/70 p-4 text-white shadow-2xl backdrop-blur-md"
        data-test="yoyotranslate-panel"
    >
        <div class="mb-3 flex items-center justify-between">
            <span class="text-sm font-semibold">YoYoTranslate</span>
        </div>

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
    </div>
{/if}
