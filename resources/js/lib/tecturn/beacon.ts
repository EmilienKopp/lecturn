/**
 * Helpers for fire-and-forget requests that must survive page unload.
 *
 * navigator.sendBeacon can't set headers, so CSRF is carried as a `_token`
 * form field. Laravel's VerifyCsrfToken reads the plaintext token from the
 * XSRF-TOKEN cookie value, which equals csrf_token().
 */

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

type BeaconValue = string | number | boolean | Record<string, number>;

/**
 * Best-effort POST that keeps running after the page starts unloading. Nested
 * plain objects are flattened to `key[subKey]` so Laravel parses them as an
 * array. Falls back to fetch(keepalive) when sendBeacon is unavailable.
 */
export function beaconPost(url: string, fields: Record<string, BeaconValue> = {}): void {
    const body = new FormData();
    body.append('_token', csrfToken());

    for (const [key, value] of Object.entries(fields)) {
        if (value !== null && typeof value === 'object') {
            for (const [subKey, subValue] of Object.entries(value)) {
                body.append(`${key}[${subKey}]`, String(subValue));
            }
        } else {
            body.append(key, String(value));
        }
    }

    if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
        navigator.sendBeacon(url, body);

        return;
    }

    void fetch(url, { method: 'POST', body, credentials: 'same-origin', keepalive: true });
}
