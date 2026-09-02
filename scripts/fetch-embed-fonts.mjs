/**
 * Vendors the `latin` woff2 files for the catalog fonts (resources/js/lib/
 * tecturn/fonts.ts) into resources/fonts/embed. scripts/present.mjs reads these
 * at export time to base64-inline the fonts a deck uses into the embedded web
 * component, so the embed renders offline with no calls to Bunny.
 *
 * Run after changing the FONTS catalog:  node scripts/fetch-embed-fonts.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outDir = path.join(root, 'resources/fonts/embed');

const { FONTS } = await import('../resources/js/lib/tecturn/fonts.ts');

/**
 * Parse a Bunny stylesheet into `{ weight -> latin woff2 url }`. Bunny emits one
 * @font-face per subset; we keep only the `latin` subset to stay small.
 */
function latinWoff2ByWeight(css) {
    const byWeight = {};

    for (const block of css.split('@font-face').slice(1)) {
        const weight = block.match(/font-weight:\s*(\d+)/)?.[1];
        const url = block.match(/url\((https:\/\/[^)]+?\.woff2)\)/)?.[1];

        if (!weight || !url) {
            continue;
        }

        // `<slug>-<subset>-<weight>-<style>.woff2` — take plain `latin`, not
        // `latin-ext`/`greek`/`cyrillic`.
        if (/-latin-\d+-/.test(url)) {
            byWeight[weight] = url;
        }
    }

    return byWeight;
}

fs.mkdirSync(outDir, { recursive: true });

let written = 0;

for (const font of FONTS) {
    const css = await fetch(
        `https://fonts.bunny.net/css?family=${font.slug}:${font.weights.join(',')}`,
        { headers: { 'User-Agent': 'Mozilla/5.0' } },
    ).then((response) => response.text());

    const urls = latinWoff2ByWeight(css);

    for (const weight of font.weights) {
        const url = urls[String(weight)];

        if (!url) {
            throw new Error(
                `No latin woff2 for ${font.slug}:${weight} (Bunny response changed?)`,
            );
        }

        const bytes = Buffer.from(
            await fetch(url).then((response) => response.arrayBuffer()),
        );
        fs.writeFileSync(
            path.join(outDir, `${font.slug}-${weight}.woff2`),
            bytes,
        );
        written += 1;
        process.stdout.write(
            `${font.slug}-${weight}.woff2 (${Math.round(bytes.length / 1024)}kb)\n`,
        );
    }
}

process.stdout.write(`\nVendored ${written} woff2 file(s) into ${outDir}\n`);
