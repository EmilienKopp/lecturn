<script lang="ts">
    import type { Snippet } from 'svelte';

    let { children }: { children?: Snippet } = $props();
</script>

<!--
    Guest shell for audience-facing pages. Always the darkened house,
    regardless of the app's light/dark theme: a warm charcoal backdrop
    with a faint tungsten spill from the stage above.
-->
<div
    class="guest-house relative flex min-h-dvh flex-col overflow-hidden text-[hsl(40_30%_96%)] antialiased"
>
    {@render children?.()}
</div>

<style>
    .guest-house {
        background-color: hsl(36 11% 7%);
        background-image: radial-gradient(
            120% 60% at 50% -10%,
            hsl(37 60% 30% / 0.18),
            transparent 65%
        );
    }
</style>
