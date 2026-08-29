<script lang="ts">
    import { highlight } from '@/lib/lecturn/shiki';

    let {
        value,
        lang,
        oninput,
        onblur,
        autofocus = false,
        class: className = '',
    }: {
        value: string;
        lang: string;
        oninput: (value: string) => void;
        onblur?: () => void;
        autofocus?: boolean;
        class?: string;
    } = $props();

    let highlightedHtml = $state('');
    let textareaEl = $state<HTMLTextAreaElement | null>(null);

    $effect(() => {
        void highlight(value || '// start typing…', lang).then((html) => {
            highlightedHtml = html;
        });
    });

    $effect(() => {
        if (autofocus && textareaEl) {
            textareaEl.focus();
        }
    });
</script>

<!-- Transparent textarea over a shiki-highlighted mirror: both layers share
     the same font metrics and padding so the caret lines up with the paint. -->
<div class="relative min-h-16 w-full overflow-hidden rounded-md {className}">
    <textarea
        bind:this={textareaEl}
        class="absolute inset-0 h-full w-full resize-none bg-transparent p-4 font-mono text-sm text-transparent caret-white outline-none"
        style="z-index: 1; color: transparent;"
        {value}
        oninput={(event) => oninput(event.currentTarget.value)}
        {onblur}
        spellcheck="false"
        autocomplete="off"
    ></textarea>
    <div
        class="pointer-events-none h-full select-none [&>pre]:m-0 [&>pre]:h-full [&>pre]:min-h-16 [&>pre]:overflow-auto [&>pre]:rounded-md [&>pre]:p-4 [&>pre]:font-mono [&>pre]:text-sm"
        style="z-index: 0;"
    >
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        {@html highlightedHtml}
    </div>
</div>
