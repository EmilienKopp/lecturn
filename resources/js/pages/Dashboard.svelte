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
    import AppHead from '@/components/AppHead.svelte';
    import PendingInvitationsModal from '@/components/PendingInvitationsModal.svelte';
    import PlaceholderPattern from '@/components/PlaceholderPattern.svelte';
    import type { DashboardInvitation } from '@/types';
    import Flow from '@/components/Flow.svelte';

    let {
        pendingInvitations = [],
    }: {
        pendingInvitations?: DashboardInvitation[];
    } = $props();
</script>

<AppHead title="Dashboard" />

{#if pendingInvitations.length > 0}
    <PendingInvitationsModal invitations={pendingInvitations} />
{/if}

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <Flow />
</div>
