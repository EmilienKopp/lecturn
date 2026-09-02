/**
 * Presentation export subprocess, invoked by App\Presentation\Presenters\NodePresenter.
 *
 * Reads `{ format, content, flow }` as JSON from stdin and writes the export artifact
 * to stdout. The Svelte source generation lives exclusively in
 * resources/js/lib/tecturn/codegen.ts (shared with the frontend editor) — this
 * script only orchestrates around it.
 *
 * Formats:
 *   - "svelte":         the generated Animotion Svelte source, as-is.
 *   - "web-component":  the source compiled as a custom element
 *                       (<tecturn-presentation>) and bundled by Vite into a
 *                       single self-contained IIFE script (Animotion inlined,
 *                       CSS injected at runtime).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

async function readStdin() {
    let data = '';

    for await (const chunk of process.stdin) {
        data += chunk;
    }

    return data;
}

/**
 * Base64 `@font-face` rules for the fonts a deck uses, read from the vendored
 * woff2 (resources/fonts/embed, see scripts/fetch-embed-fonts.mjs). Inlining
 * keeps the embedded component self-contained — it renders with no network.
 */
function embedFontFaceCss(content, usedFonts) {
    const rules = [];

    for (const { font, weights } of usedFonts(content)) {
        for (const weight of weights) {
            const file = path.join(
                root,
                'resources/fonts/embed',
                `${font.slug}-${weight}.woff2`,
            );

            if (!fs.existsSync(file)) {
                continue;
            }

            const base64 = fs.readFileSync(file).toString('base64');
            rules.push(
                `@font-face{font-family:'${font.label}';font-style:normal;` +
                    `font-weight:${weight};font-display:swap;` +
                    `src:url(data:font/woff2;base64,${base64}) format('woff2');}`,
            );
        }
    }

    return rules.join('\n');
}

async function buildWebComponent(source, tag, fontFaceCss = '') {
    const { build } = await import('vite');
    const { svelte } = await import('@sveltejs/vite-plugin-svelte');

    // The entry must live inside the project so `@animotion/core` resolves.
    const cacheDir = path.join(root, 'node_modules', '.cache');
    fs.mkdirSync(cacheDir, { recursive: true });
    const tmpDir = fs.mkdtempSync(path.join(cacheDir, 'tecturn-export-'));
    const entry = path.join(tmpDir, 'Presentation.svelte');

    try {
        // shadow: 'none' is required — Animotion's Presentation initializes
        // Reveal.js against document.querySelector('.reveal'), which cannot
        // see inside a shadow root (nor can the CSS injected into <head>).
        fs.writeFileSync(
            entry,
            `<svelte:options customElement={{ tag: ${JSON.stringify(tag)}, shadow: 'none' }} />\n${source}`,
        );

        const result = await build({
            configFile: false,
            root,
            logLevel: 'error',
            resolve: {
                alias: {
                    '$app/environment': path.join(
                        root,
                        'resources/js/lib/shims/app-environment.ts',
                    ),
                },
            },
            plugins: [svelte({ compilerOptions: { customElement: true } })],
            build: {
                write: false,
                cssCodeSplit: false,
                lib: {
                    entry,
                    formats: ['iife'],
                    name: 'TecturnPresentation',
                    fileName: () => 'presentation.js',
                    cssFileName: 'presentation',
                },
            },
        });

        const outputs = (Array.isArray(result) ? result : [result]).flatMap(
            (r) => r.output,
        );
        const js = outputs.find((o) => o.type === 'chunk').code;
        const css = outputs
            .filter((o) => o.type === 'asset' && o.fileName.endsWith('.css'))
            .map((o) => o.source)
            .join('\n');

        // Fonts first so their @font-face rules are defined before the deck's
        // styles reference them.
        const allCss = [fontFaceCss, css].filter(Boolean).join('\n');

        const cssInjector = allCss
            ? `(function(){var s=document.createElement('style');s.textContent=${JSON.stringify(allCss)};document.head.appendChild(s);})();\n`
            : '';

        return cssInjector + js;
    } finally {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    }
}

try {
    const { format, content, flow, tag } = JSON.parse(await readStdin());

    // Node 22.18+ runs TypeScript directly via type stripping.
    const { generatePresentationSvelte } =
        await import('../resources/js/lib/tecturn/codegen.ts');
    const { usedFonts } = await import(
        '../resources/js/lib/tecturn/fonts.ts'
    );

    switch (format) {
        case 'svelte':
            // Self-contained source: fonts pulled from Bunny via @import.
            process.stdout.write(
                generatePresentationSvelte(content, flow ?? null),
            );
            break;
        case 'web-component':
            // No @import; the used fonts are base64-inlined instead so the
            // embed renders offline.
            process.stdout.write(
                await buildWebComponent(
                    generatePresentationSvelte(content, flow ?? null, {
                        fonts: 'none',
                    }),
                    tag ?? 'tecturn-presentation',
                    embedFontFaceCss(content, usedFonts),
                ),
            );
            break;
        default:
            throw new Error(`Unknown export format: ${format}`);
    }
} catch (error) {
    process.stderr.write(String(error?.stack ?? error));
    process.exit(1);
}
