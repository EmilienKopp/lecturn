/**
 * Canonical font catalog, shared by every consumer so the set never drifts:
 *   - the editor inspector (the font-family dropdown),
 *   - the codegen renderers (which emit `font-family` and, for the standalone
 *     Svelte export, a Bunny `@import` so the file is self-contained),
 *   - scripts/present.mjs (which base64-inlines the used fonts' woff2 into the
 *     embedded web component so it renders offline, with no external calls).
 *
 * `block.style.fontFamily` stores a font's `label`. Renderers map that label to
 * its full CSS stack here; an unknown label is used verbatim as a fallback so
 * older documents never break.
 */
import type { PresentationContent } from '@/types/generated';

export type FontDefinition = {
    /** Stored on `block.style.fontFamily` and shown in the dropdown. */
    readonly label: string;
    /** Full `font-family` value, including web-safe fallbacks. */
    readonly stack: string;
    /** Bunny Fonts slug, used for the woff2 file names and the `@import` URL. */
    readonly slug: string;
    /** Weights vendored under resources/fonts/embed for offline inlining. */
    readonly weights: readonly number[];
};

/**
 * The curated deck fonts. Every entry must have its `latin` woff2 files
 * vendored (see scripts/fetch-embed-fonts.mjs) for the embed to inline them.
 */
export const FONTS: readonly FontDefinition[] = [
    {
        label: 'Instrument Sans',
        stack: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
        slug: 'instrument-sans',
        weights: [400, 500, 600, 700],
    },
    {
        label: 'Inter',
        stack: "'Inter', ui-sans-serif, system-ui, sans-serif",
        slug: 'inter',
        weights: [400, 500, 600, 700],
    },
    {
        label: 'Bricolage Grotesque',
        stack: "'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif",
        slug: 'bricolage-grotesque',
        weights: [400, 500, 600, 700],
    },
    {
        label: 'Anton',
        stack: "'Anton', Impact, ui-sans-serif, system-ui, sans-serif",
        slug: 'anton',
        weights: [400],
    },
    {
        label: 'Lora',
        stack: "'Lora', ui-serif, Georgia, Cambria, 'Times New Roman', serif",
        slug: 'lora',
        weights: [400, 500, 600, 700],
    },
    {
        label: 'JetBrains Mono',
        stack: "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace",
        slug: 'jetbrains-mono',
        weights: [400, 500, 600, 700],
    },
];

const FONTS_BY_LABEL = new Map(FONTS.map((font) => [font.label, font]));

/** Named CSS weights (the inspector's options) mapped to numeric weights. */
const WEIGHT_KEYWORDS: Record<string, number> = {
    normal: 400,
    medium: 500,
    semibold: 600,
    bold: 700,
};

export function fontByLabel(label: string): FontDefinition | undefined {
    return FONTS_BY_LABEL.get(label);
}

/**
 * The CSS `font-family` value for a stored label, or the label itself when it
 * isn't a catalog entry (so hand-authored or legacy values still apply).
 */
export function fontStack(label: string | null | undefined): string | null {
    if (!label) {
        return null;
    }

    return fontByLabel(label)?.stack ?? label;
}

/**
 * Resolve a `block.style.fontWeight` (a keyword like "bold" or a numeric
 * string) to the nearest available numeric weight for `font`.
 */
export function resolveWeight(
    font: FontDefinition,
    fontWeight: string | null | undefined,
): number {
    const requested =
        (fontWeight && WEIGHT_KEYWORDS[fontWeight]) ||
        (fontWeight ? Number.parseInt(fontWeight, 10) : NaN) ||
        400;

    let nearest = font.weights[0];

    for (const weight of font.weights) {
        if (Math.abs(weight - requested) < Math.abs(nearest - requested)) {
            nearest = weight;
        }
    }

    return nearest;
}

/** Every block in the deck, flattened across slides and slots. */
function allBlocks(content: PresentationContent) {
    return content.slides.flatMap((slide) => Object.values(slide.slots).flat());
}

/**
 * The catalog fonts actually referenced by the deck, each paired with the
 * numeric weights it's used at (deduped). Drives both the Bunny `@import` and
 * the embed's base64 inlining so only what's needed ships.
 */
export function usedFonts(
    content: PresentationContent,
): { font: FontDefinition; weights: number[] }[] {
    const weightsByLabel = new Map<string, Set<number>>();

    for (const block of allBlocks(content)) {
        const font = fontByLabel(block.style.fontFamily ?? '');

        if (!font) {
            continue;
        }

        const weights = weightsByLabel.get(font.label) ?? new Set<number>();
        weights.add(resolveWeight(font, block.style.fontWeight));
        weightsByLabel.set(font.label, weights);
    }

    return FONTS.filter((font) => weightsByLabel.has(font.label)).map(
        (font) => ({
            font,
            weights: [...weightsByLabel.get(font.label)!].sort((a, b) => a - b),
        }),
    );
}

/**
 * A Bunny Fonts stylesheet `@import` covering the deck's used fonts, or null
 * when the deck uses none. Portable (network-backed) font loading for the
 * standalone Svelte export; the embed inlines instead.
 */
export function bunnyImportCss(content: PresentationContent): string | null {
    const used = usedFonts(content);

    if (used.length === 0) {
        return null;
    }

    const families = used
        .map(({ font, weights }) => `${font.slug}:${weights.join(',')}`)
        .join('|');

    return `@import url('https://fonts.bunny.net/css?family=${families}');`;
}
