<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import LandingDeck from '@/components/LandingDeck.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard, login } from '@/routes';
    import type { Team } from '@/types';

    const auth = $derived(page.props.auth);
    const currentTeam = $derived(page.props.currentTeam as Team | null);
    const dashboardUrl = $derived(
        currentTeam ? dashboard(currentTeam.slug) : '/',
    );
</script>

<AppHead title="The stage for developer talks" />

<div
    class="house flex min-h-screen flex-col text-[hsl(40_30%_96%)] antialiased"
>
    <header
        class="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6"
    >
        <span
            class="font-display text-xl font-semibold tracking-tight select-none"
        >
            Tecturn<span class="text-[hsl(37_91%_55%)]">.</span>
        </span>
        <nav>
            {#if auth.user}
                <Link
                    href={toUrl(dashboardUrl)}
                    class="rounded-md border border-[hsl(34_9%_22%)] px-4 py-1.5 text-sm text-[hsl(40_20%_86%)] transition-colors hover:border-[hsl(37_40%_35%)] focus-visible:ring-2 focus-visible:ring-[hsl(37_91%_55%)] focus-visible:outline-none"
                >
                    Your decks
                </Link>
            {:else}
                <Link
                    href={toUrl(login())}
                    class="rounded-md border border-[hsl(34_9%_22%)] px-4 py-1.5 text-sm text-[hsl(40_20%_86%)] transition-colors hover:border-[hsl(37_40%_35%)] focus-visible:ring-2 focus-visible:ring-[hsl(37_91%_55%)] focus-visible:outline-none"
                >
                    Log in
                </Link>
            {/if}
        </nav>
    </header>

    <main class="flex grow flex-col items-center px-6">
        <!-- The hero: a real Animotion deck on a lit 16:9 stage. -->
        <div class="mt-4 w-full max-w-5xl">
            <div
                class="stage-canvas aspect-video w-full overflow-hidden rounded-xl bg-[#faf7f0] [container-type:size]"
            >
                <LandingDeck />
            </div>
            <p
                class="mt-4 hidden text-center font-mono text-xs text-[hsl(37_6%_55%)] sm:block"
            >
                click the stage, then
                <kbd
                    class="rounded border border-[hsl(34_9%_22%)] px-1.5 py-0.5"
                    >←</kbd
                >
                <kbd
                    class="rounded border border-[hsl(34_9%_22%)] px-1.5 py-0.5"
                    >→</kbd
                >
                to drive it
            </p>
        </div>

        <section class="mt-16 mb-16 max-w-xl text-center">
            <p class="text-lg leading-relaxed text-[hsl(40_15%_75%)]">
                Build slides out of blocks, wire their reveal order in a flow
                graph, and present with live translation and floating audience
                reactions. The deck above is the product doing its own pitch.
            </p>
            {#if auth.user}
                <Link
                    href={toUrl(dashboardUrl)}
                    class="mt-8 inline-block rounded-md bg-[hsl(37_91%_55%)] px-6 py-2.5 font-medium text-[hsl(36_45%_10%)] transition-colors hover:bg-[hsl(37_91%_62%)] focus-visible:ring-2 focus-visible:ring-[hsl(37_91%_55%)] focus-visible:ring-offset-2 focus-visible:ring-offset-[hsl(36_11%_7%)] focus-visible:outline-none"
                >
                    Back to your decks
                </Link>
            {:else}
                <Link
                    href={toUrl(login())}
                    class="mt-8 inline-block rounded-md bg-[hsl(37_91%_55%)] px-6 py-2.5 font-medium text-[hsl(36_45%_10%)] transition-colors hover:bg-[hsl(37_91%_62%)] focus-visible:ring-2 focus-visible:ring-[hsl(37_91%_55%)] focus-visible:ring-offset-2 focus-visible:ring-offset-[hsl(36_11%_7%)] focus-visible:outline-none"
                >
                    Take the stage
                </Link>
            {/if}
        </section>
    </main>

    <footer
        class="px-6 py-3 text-center font-mono text-xs text-[hsl(37_6%_42%)]"
    >
        Tecturn
    </footer>
</div>

<style>
    .house {
        /* Dark theater house with tungsten spill from above the stage. */
        background-color: hsl(36 11% 7%);
        background-image: radial-gradient(
            120% 60% at 50% -10%,
            hsl(37 60% 30% / 0.2),
            transparent 65%
        );
    }
</style>
