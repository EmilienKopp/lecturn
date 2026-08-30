/** Reads Laravel's XSRF-TOKEN cookie for CSRF-protected fetch requests. */
export function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Uploads an image file to a presentation endpoint and returns its public URL,
 * or null when the request fails.
 */
export async function uploadImage(
    url: string,
    file: File,
): Promise<string | null> {
    const form = new FormData();
    form.append('image', file);

    const response = await fetch(url, {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
        headers: {
            'X-XSRF-TOKEN': xsrfToken(),
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        return null;
    }

    const data = (await response.json()) as { url: string };

    return data.url;
}
