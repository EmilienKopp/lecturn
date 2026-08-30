import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import inertia from '@inertiajs/vite';
import laravel from 'laravel-vite-plugin';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';

const isSvelteCheck = process.argv.some((argument) =>
    argument.includes('svelte-check'),
);

if (isSvelteCheck) {
    process.env.LARAVEL_BYPASS_ENV_CHECK ??= '1';
}

export default defineConfig({
    optimizeDeps: {
        // The editor page pulls these in lazily (Inertia pages are
        // code-split), so without pre-bundling Vite discovers them at
        // runtime and forces a re-optimize + full reload mid-session —
        // an expensive esbuild spike that has taken WSL down.
        include: [
            'shiki',
            '@xyflow/svelte',
            '@editorjs/editorjs',
            '@editorjs/header',
            '@editorjs/list',
            '@editorjs/code',
            '@editorjs/quote',
            'laravel-echo',
            'pusher-js',
        ],
    },
    resolve: {
        alias: {
            // @animotion/core expects SvelteKit's $app/environment.
            '$app/environment': fileURLToPath(
                new URL(
                    './resources/js/lib/shims/app-environment.ts',
                    import.meta.url,
                ),
            ),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Bricolage Grotesque', {
                    weights: [500, 600, 700],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        svelte(),
        wayfinder({
            formVariants: true,
        }),
    ],

    // exclude from hmr
    server: {
        watch: {
            ignored: [
                '**/node_modules/**',
                '**/vendor/**',
                '**/public/**',
                '.demo/**',
            ],
        },
    },
});
