export function downloadFile(
    filename: string,
    contents: string,
    mime = 'text/plain',
): void {
    const blob = new Blob([contents], { type: mime });
    const url = URL.createObjectURL(blob);

    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();

    URL.revokeObjectURL(url);
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
