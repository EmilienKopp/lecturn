<script module lang="ts">
    import { dashboard } from '@/routes';
    import type { Team } from '@/types';

    export const layout = (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    });
</script>

<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import Presentation from 'lucide-svelte/icons/presentation';
    import Radio from 'lucide-svelte/icons/radio';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import PendingInvitationsModal from '@/components/PendingInvitationsModal.svelte';
    import { Button } from '@/components/ui/button';
    import { edit, index, present } from '@/routes/presentations';
    import type { DashboardInvitation } from '@/types';

    type Engagement = {
        total_sessions: number;
        total_reactions: number;
        total_viewers: number;
        avg_reactions_per_session: number;
        top_emoji: string | null;
    };

    type SessionRow = {
        id: number;
        presentation_id: number;
        presentation_name: string;
        started_at: string;
        ended_at: string | null;
        duration_seconds: number;
        is_live: boolean;
        viewer_count: number;
        reaction_total: number;
        reaction_counts: Record<string, number>;
        top_emoji: string | null;
    };

    type DeckRow = {
        id: number;
        name: string;
        slide_count: number;
        updated_at: string | null;
    };

    let {
        pendingInvitations = [],
        engagement,
        recentSessions = [],
        recentDecks = [],
    }: {
        pendingInvitations?: DashboardInvitation[];
        engagement: Engagement;
        recentSessions?: SessionRow[];
        recentDecks?: DeckRow[];
    } = $props();

    const teamSlug = $derived(page.props.currentTeam?.slug ?? '');

    const stats = $derived([
        { label: 'Talks given', value: engagement.total_sessions },
        { label: 'People reached', value: engagement.total_viewers },
        { label: 'Reactions', value: engagement.total_reactions },
        { label: 'Avg per talk', value: engagement.avg_reactions_per_session },
    ]);

    const numberFormatter = new Intl.NumberFormat();

    const formatDuration = (seconds: number): string => {
        if (seconds < 60) {
            return `${seconds}s`;
        }

        const m = Math.floor(seconds / 60);
        const h = Math.floor(m / 60);

        return h > 0 ? `${h}h ${m % 60}m` : `${m}m`;
    };

    const formatWhen = (value: string): string => {
        const then = new Date(value).getTime();
        const diff = Date.now() - then;
        const minutes = Math.round(diff / 60000);

        if (minutes < 1) {
            return 'just now';
        }
        if (minutes < 60) {
            return `${minutes}m ago`;
        }

        const hours = Math.round(minutes / 60);
        if (hours < 24) {
            return `${hours}h ago`;
        }

        const days = Math.round(hours / 24);
        if (days < 7) {
            return `${days}d ago`;
        }

        return new Date(value).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        });
    };

    // Reaction chips, biggest tally first.
    const sortedReactions = (counts: Record<string, number>) =>
        Object.entries(counts).sort(([, a], [, b]) => b - a);

    const openDeck = (id: number) =>
        router.visit(edit({ current_team: teamSlug, presentation: id }).url);

    const presentDeck = (id: number) =>
        router.visit(present({ current_team: teamSlug, presentation: id }).url);

    const hasHistory = $derived(recentSessions.length > 0);
</script>

<AppHead title="Dashboard" />

{#if pendingInvitations.length > 0}
    <PendingInvitationsModal invitations={pendingInvitations} />
{/if}

<div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-6">
    <div class="flex items-end justify-between gap-4">
        <Heading
            variant="small"
            title="Dashboard"
            description="How your talks landed with the room"
        />
        <Button variant="outline" onclick={() => router.visit(index(teamSlug).url)}>
            All presentations
        </Button>
    </div>

    <!-- Engagement strip. The loudest reaction is the signature: the room's own
         voice, sized up, instead of another number tile. -->
    <section
        class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-3 lg:grid-cols-5"
    >
        {#each stats as stat (stat.label)}
            <div class="bg-card p-5">
                <p
                    class="font-mono text-3xl font-bold tabular-nums text-foreground"
                >
                    {numberFormatter.format(stat.value)}
                </p>
                <p
                    class="mt-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                >
                    {stat.label}
                </p>
            </div>
        {/each}

        <div
            class="flex flex-col justify-between bg-card p-5"
            data-test="loudest-reaction"
        >
            <span class="text-3xl leading-none" aria-hidden="true">
                {engagement.top_emoji ?? '—'}
            </span>
            <p
                class="mt-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
            >
                Loudest reaction
            </p>
        </div>
    </section>

    <div class="grid gap-8 lg:grid-cols-[1.6fr_1fr]">
        <!-- Recent talks feed -->
        <section class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-foreground">Recent talks</h2>

            {#if hasHistory}
                <ul class="flex flex-col gap-2">
                    {#each recentSessions as session (session.id)}
                        <li
                            class="rounded-xl border border-border bg-card p-4"
                            data-test="session-row"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p
                                        class="truncate font-display font-semibold text-foreground"
                                    >
                                        {session.presentation_name}
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {formatWhen(session.started_at)} · {formatDuration(
                                            session.duration_seconds,
                                        )} · {session.viewer_count}
                                        {session.viewer_count === 1
                                            ? 'viewer'
                                            : 'viewers'}
                                    </p>
                                </div>

                                {#if session.is_live}
                                    <span
                                        class="flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-semibold text-emerald-500"
                                    >
                                        <Radio class="h-3 w-3" /> Live
                                    </span>
                                {:else}
                                    <span
                                        class="shrink-0 font-mono text-sm tabular-nums text-muted-foreground"
                                    >
                                        {session.reaction_total} ⚡
                                    </span>
                                {/if}
                            </div>

                            {#if session.reaction_total > 0}
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    {#each sortedReactions(session.reaction_counts) as [emoji, count] (emoji)}
                                        <span
                                            class="flex items-center gap-1 rounded-md bg-accent px-2 py-0.5 text-sm"
                                        >
                                            <span aria-hidden="true">{emoji}</span>
                                            <span
                                                class="font-mono text-xs tabular-nums text-muted-foreground"
                                                >{count}</span
                                            >
                                        </span>
                                    {/each}
                                </div>
                            {/if}
                        </li>
                    {/each}
                </ul>
            {:else}
                <div
                    class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border bg-card px-6 py-14 text-center"
                >
                    <Radio class="h-6 w-6 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">
                        No talks yet. Present a deck and the room's reactions land
                        here.
                    </p>
                    {#if recentDecks.length > 0}
                        <Button onclick={() => presentDeck(recentDecks[0].id)}>
                            Present “{recentDecks[0].name}”
                        </Button>
                    {/if}
                </div>
            {/if}
        </section>

        <!-- Jump back into a deck -->
        <section class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold text-foreground">Your decks</h2>

            {#if recentDecks.length > 0}
                <ul class="flex flex-col gap-2">
                    {#each recentDecks as deck (deck.id)}
                        <li
                            class="group flex items-center justify-between gap-2 rounded-xl border border-border bg-card p-3"
                        >
                            <!-- svelte-ignore a11y_no_static_element_interactions, a11y_click_events_have_key_events -->
                            <div
                                class="flex min-w-0 flex-1 cursor-pointer items-center gap-3"
                                onclick={() => openDeck(deck.id)}
                            >
                                <Presentation
                                    class="h-4 w-4 shrink-0 text-muted-foreground"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-medium text-foreground"
                                    >
                                        {deck.name}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {deck.slide_count}
                                        {deck.slide_count === 1
                                            ? 'slide'
                                            : 'slides'}
                                    </p>
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onclick={() => presentDeck(deck.id)}
                            >
                                Present
                            </Button>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p
                    class="rounded-xl border border-dashed border-border bg-card px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    No decks yet.
                    <a class="text-primary" href={index(teamSlug).url}>
                        Create one
                    </a>
                    to get started.
                </p>
            {/if}
        </section>
    </div>
</div>
