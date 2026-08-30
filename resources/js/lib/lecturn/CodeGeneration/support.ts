export const INDENT_SIZE = 4;

/**
 * Indentation for the generated Svelte source. Plain spaces — this indents
 * code, not rendered markup, so HTML entities would corrupt the output.
 */
export const INDENT = ' '.repeat(INDENT_SIZE);

export interface EditorJsBlock {
    type: string;
    data: Record<string, unknown>;
}

export interface EditorJsOutput {
    blocks: EditorJsBlock[];
}

export const escapeHtml = (value: string): string =>
    value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');

export const escapeAttribute = (value: string): string =>
    escapeHtml(value).replaceAll('"', '&quot;');

// Animotion's <Code> takes the source via a `code` prop, so it lands in a JS
// template literal in the generated file, not as HTML text. Escape only what
// would break the literal, leaving the code itself verbatim for the highlighter.
export const escapeTemplateLiteral = (value: string): string =>
    value
        .replaceAll('\\', '\\\\')
        .replaceAll('`', '\\`')
        .replaceAll('${', '\\${');
