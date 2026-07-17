export function downloadBlob(filename: string, blob: Blob): void {
    const url = URL.createObjectURL(blob);

    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();

    URL.revokeObjectURL(url);
}

export function downloadFile(
    filename: string,
    contents: string,
    mime = 'text/plain',
): void {
    downloadBlob(filename, new Blob([contents], { type: mime }));
}

export function slugify(value: string): string {
    return (
        value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'presentation'
    );
}
