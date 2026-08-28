import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let instance: Echo<'reverb'> | null = null;

/**
 * Lazily create the Echo connection so only pages that subscribe to
 * broadcast channels (e.g. the presenter screen) open a websocket.
 */
export function getEcho(): Echo<'reverb'> {
    if (!instance) {
        (window as typeof window & { Pusher: typeof Pusher }).Pusher = Pusher;

        instance = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS:
                (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    return instance;
}
