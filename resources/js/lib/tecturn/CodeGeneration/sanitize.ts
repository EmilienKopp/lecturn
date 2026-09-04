/**
 * Allowlist sanitizer for the inline-formatted HTML stored in a text/box
 * block's `content`. Text blocks used to hold plain text (escaped on export);
 * they now hold a small slice of HTML so a selection can carry its own color,
 * size, weight, or style.
 *
 * This is the single source of truth for what inline markup is allowed. The
 * editor calls it before persisting and the codegen calls it before emitting
 * the embed, so the same rules protect the stored data and the exported page.
 * It must stay DOM-free: the codegen runs it in a Node subprocess where there
 * is no `document` to parse against.
 *
 * Everything outside the allowlist is dropped. Disallowed tags lose their
 * markup but keep their text, so pasting rich HTML degrades to plain text
 * rather than smuggling script or style through.
 */

/** Tags that survive sanitizing. */
const ALLOWED_TAGS = new Set(['span', 'b', 'strong', 'i', 'em', 'br']);

/** Tags that never have a closing partner. */
const VOID_TAGS = new Set(['br']);

/** Style properties a `<span>` may keep, each with a value validator. */
const STYLE_VALIDATORS: Record<string, (value: string) => boolean> = {
    color: (v) =>
        /^#[0-9a-f]{3,8}$/i.test(v) ||
        /^rgba?\([\d.,\s%]+\)$/i.test(v) ||
        /^[a-z]+$/i.test(v),
    'font-size': (v) => /^\d*\.?\d+(px|rem|em|%|cqw|cqh)$/i.test(v),
    'font-weight': (v) => /^(normal|bold|lighter|bolder|[1-9]00)$/i.test(v),
    'font-style': (v) => /^(normal|italic|oblique)$/i.test(v),
};

/**
 * Pull the `style` attribute out of a start tag's body and keep only the
 * allowlisted declarations whose values pass validation. Anything with
 * `url(...)`, `expression`, extra properties, or a bad value is discarded.
 */
function sanitizeStyle(tagBody: string): string {
    const match =
        tagBody.match(/style\s*=\s*"([^"]*)"/i) ??
        tagBody.match(/style\s*=\s*'([^']*)'/i);

    if (!match) {
        return '';
    }

    const kept: string[] = [];

    for (const declaration of match[1].split(';')) {
        const colon = declaration.indexOf(':');

        if (colon === -1) {
            continue;
        }

        const property = declaration.slice(0, colon).trim().toLowerCase();
        const value = declaration.slice(colon + 1).trim();
        const validator = STYLE_VALIDATORS[property];

        if (validator && validator(value)) {
            kept.push(`${property}: ${value}`);
        }
    }

    return kept.join('; ');
}

/**
 * Normalize a single raw tag (`<...>`) to its allowlisted form, or `null` if
 * the tag is not allowed (the caller drops the markup but keeps inner text).
 */
function sanitizeTag(raw: string): string | null {
    const inner = raw.slice(1, -1).trim();
    const isClosing = inner.startsWith('/');
    const body = (isClosing ? inner.slice(1) : inner).replace(/\/$/, '').trim();
    const nameMatch = body.match(/^([a-z0-9]+)/i);

    if (!nameMatch) {
        return null;
    }

    const name = nameMatch[1].toLowerCase();

    if (!ALLOWED_TAGS.has(name)) {
        return null;
    }

    if (VOID_TAGS.has(name)) {
        return isClosing ? null : `<${name}>`;
    }

    if (isClosing) {
        return `</${name}>`;
    }

    if (name === 'span') {
        const style = sanitizeStyle(body);

        return style ? `<span style="${style}">` : '<span>';
    }

    return `<${name}>`;
}

/**
 * Return a sanitized copy of `html` containing only the allowlisted inline
 * markup. Text is preserved; stray `<`/`>` are escaped so they cannot form
 * new tags. Empty or falsy input yields an empty string.
 */
export function sanitizeInlineHtml(html: string): string {
    if (!html) {
        return '';
    }

    let out = '';
    let i = 0;

    while (i < html.length) {
        const char = html[i];

        if (char === '<') {
            const close = html.indexOf('>', i);

            if (close === -1) {
                out += '&lt;';
                i += 1;
                continue;
            }

            const tag = sanitizeTag(html.slice(i, close + 1));

            if (tag) {
                out += tag;
            }

            i = close + 1;
        } else if (char === '>') {
            out += '&gt;';
            i += 1;
        } else {
            out += char;
            i += 1;
        }
    }

    return out;
}
