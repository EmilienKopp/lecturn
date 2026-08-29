/**
 * Presentation export subprocess, invoked by App\Presentation\Presenters\NodePresenter.
 *
 * Reads `{ format, content, flow }` as JSON from stdin and writes the export artifact
 * to stdout. The Svelte source generation lives exclusively in
 * resources/js/lib/lecturn/codegen.ts (shared with the frontend editor) — this
 * script only orchestrates around it.
 *
 * Formats:
 *   - "svelte":         the generated Animotion Svelte source, as-is.
 *   - "web-component":  the source compiled as a custom element
 *                       (<lecturn-presentation>) and bundled by Vite into a
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

async function buildWebComponent(source, tag) {
    const { build } = await import('vite');
    const { svelte } = await import('@sveltejs/vite-plugin-svelte');

    // The entry must live inside the project so `@animotion/core` resolves.
    const cacheDir = path.join(root, 'node_modules', '.cache');
    fs.mkdirSync(cacheDir, { recursive: true });
    const tmpDir = fs.mkdtempSync(path.join(cacheDir, 'lecturn-export-'));
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
                    name: 'LecturnPresentation',
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

        const cssInjector = css
            ? `(function(){var s=document.createElement('style');s.textContent=${JSON.stringify(css)};document.head.appendChild(s);})();\n`
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
        await import('../resources/js/lib/lecturn/codegen.ts');
    const source = generatePresentationSvelte(content, flow ?? null);

    switch (format) {
        case 'svelte':
            process.stdout.write(source);
            break;
        case 'web-component':
            process.stdout.write(
                await buildWebComponent(source, tag ?? 'lecturn-presentation'),
            );
            break;
        default:
            throw new Error(`Unknown export format: ${format}`);
    }
} catch (error) {
    process.stderr.write(String(error?.stack ?? error));
    process.exit(1);
}
