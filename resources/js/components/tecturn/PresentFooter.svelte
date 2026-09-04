<script lang="ts">
    import Github from 'lucide-svelte/icons/github';
    import Hash from 'lucide-svelte/icons/hash';
    import X from '@/components/icons/X.svelte';
    import type { FooterSettings } from '@/types/generated';

    let {
        footer,
        variant = 'overlay',
    }: {
        footer: FooterSettings;
        variant?: 'overlay' | 'dock';
    } = $props();

    const xUrl = $derived(
        footer.xHandle ? `https://x.com/${footer.xHandle}` : null,
    );
    const githubUrl = $derived(
        footer.githubHandle
            ? `https://github.com/${footer.githubHandle}`
            : null,
    );

    const hasContent = $derived(
        Boolean(footer.xHandle || footer.githubHandle || footer.hashtag),
    );

    // Transparent backgrounds fall through to whatever sits behind the footer.
    const backgroundStyle = $derived(
        footer.bgColor && footer.bgColor !== 'transparent'
            ? `background-color: ${footer.bgColor};`
            : '',
    );
</script>

{#if hasContent}
    {#if variant === 'dock'}
        <section class="rounded-lg bg-zinc-800 p-4">
            <div class="flex flex-col gap-2 text-xl">
                {#if xUrl}
                    <a
                        href={xUrl}
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-2 text-white hover:underline"
                    >
                        <X class="h-6 w-6 shrink-0" />@{footer.xHandle}
                    </a>
                {/if}
                {#if githubUrl}
                    <a
                        href={githubUrl}
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-2 text-white hover:underline"
                    >
                        <Github
                            class="h-6 w-6 shrink-0"
                        />@{footer.githubHandle}
                    </a>
                {/if}
                {#if footer.hashtag}
                    <span class="flex items-center gap-2 text-white">
                        <Hash class="h-6 w-6 shrink-0" />{footer.hashtag}
                    </span>
                {/if}
            </div>
        </section>
    {:else}
        <div
            class="pointer-events-auto absolute inset-x-0 bottom-0 z-[9998] w-full flex items-center justify-between gap-6 pb-4 px-16 text-3xl font-medium"
            style="{backgroundStyle} color: {footer.fontColor};"
            data-test="present-footer"
        >
            <div class="handles">
                {#if xUrl}
                    <a
                        href={xUrl}
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-1.5 hover:underline"
                    >
                        <X class="h-6 w-6 shrink-0" />@{footer.xHandle}
                    </a>
                {/if}
                {#if githubUrl}
                    <a
                        href={githubUrl}
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-1.5 hover:underline"
                    >
                        <Github
                            class="h-6 w-6 shrink-0"
                        />@{footer.githubHandle}
                    </a>
                {/if}
            </div>
            {#if footer.hashtag}
                <span class="flex items-center gap-1.5">
                    <Hash class="h-6 w-6 shrink-0" />{footer.hashtag}
                </span>
            {/if}
        </div>
    {/if}
{/if}

<style>
    .handles {
        display: flex;
        gap: 1.5rem; /* Match the gap used in the anchor tags */
    }
</style>
