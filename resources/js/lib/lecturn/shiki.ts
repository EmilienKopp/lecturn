import { createHighlighter } from 'shiki';
import type { Highlighter, BundledLanguage, BundledTheme } from 'shiki';

export const SUPPORTED_LANGUAGES: BundledLanguage[] = [
    'typescript',
    'javascript',
    'php',
    'python',
    'bash',
    'css',
    'html',
    'json',
    'svelte',
    'sql',
];

let highlighterPromise: Promise<Highlighter> | null = null;

export function getHighlighter(): Promise<Highlighter> {
    if (!highlighterPromise) {
        highlighterPromise = createHighlighter({
            themes: ['github-dark', 'github-light'] as BundledTheme[],
            langs: SUPPORTED_LANGUAGES,
        });
    }

    return highlighterPromise;
}

export async function highlight(
    code: string,
    lang: BundledLanguage | string,
    theme: BundledTheme = 'github-dark',
): Promise<string> {
    const hl = await getHighlighter();
    const safeLang = SUPPORTED_LANGUAGES.includes(lang as BundledLanguage)
        ? lang
        : 'typescript';

    return hl.codeToHtml(code, { lang: safeLang, theme });
}
