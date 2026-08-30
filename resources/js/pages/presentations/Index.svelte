<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Presentation from 'lucide-svelte/icons/presentation';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { destroy, edit, store } from '@/routes/presentations';

    type PresentationListItem = {
        id: number;
        name: string;
        slide_count: number;
        updated_at: string | null;
    };

    let {
        presentations,
    }: {
        presentations: PresentationListItem[];
    } = $props();

    let createDialogOpen = $state(false);
    let newName = $state('');
    let creating = $state(false);
    let deleteDialogOpen = $state(false);
    let presentationDeleting = $state<PresentationListItem | null>(null);

    const teamSlug = $derived(page.props.currentTeam?.slug ?? '');

    const createPresentation = (event: SubmitEvent) => {
        event.preventDefault();

        creating = true;

        router.post(
            store(teamSlug).url,
            { name: newName },
            {
                onFinish: () => {
                    creating = false;
                    createDialogOpen = false;
                    newName = '';
                },
            },
        );
    };

    const confirmDelete = () => {
        if (!presentationDeleting) {
            return;
        }

        router.delete(
            destroy({
                current_team: teamSlug,
                presentation: presentationDeleting.id,
            }).url,
            {
                onFinish: () => {
                    deleteDialogOpen = false;
                    presentationDeleting = null;
                },
            },
        );
    };

    const openEditor = (presentation: PresentationListItem) => {
        router.visit(
            edit({ current_team: teamSlug, presentation: presentation.id }).url,
        );
    };

    const formatUpdatedAt = (value: string | null): string =>
        value ? new Date(value).toLocaleString() : '';
</script>

<AppHead title="Presentations" />

<div class="flex flex-col space-y-6 p-6">
    <div class="flex items-center justify-between">
        <Heading
            variant="small"
            title="Presentations"
            description="Author Animotion presentations for your team"
        />

        <Dialog bind:open={createDialogOpen}>
            <DialogTrigger asChild>
                {#snippet children(props)}
                    <Button
                        onclick={(event: MouseEvent) => {
                            if (typeof props.onClick === 'function') {
                                props.onClick(event);
                            }
                        }}
                        data-test="new-presentation-button"
                    >
                        <Plus class="h-4 w-4" /> New presentation
                    </Button>
                {/snippet}
            </DialogTrigger>
            <DialogContent>
                <form onsubmit={createPresentation} class="space-y-4">
                    <div class="space-y-3">
                        <DialogTitle>New presentation</DialogTitle>
                        <DialogDescription>
                            Give your presentation a name. You can rename it
                            later.
                        </DialogDescription>
                    </div>

                    <div class="space-y-2">
                        <Label for="new-presentation-name">Name</Label>
                        <Input
                            id="new-presentation-name"
                            bind:value={newName}
                            required
                            maxlength={255}
                            data-test="new-presentation-name"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={creating || newName.trim() === ''}
                            data-test="new-presentation-submit"
                        >
                            {creating ? 'Creating…' : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {#each presentations as presentation (presentation.id)}
            <!-- svelte-ignore a11y_no_static_element_interactions, a11y_click_events_have_key_events -->
            <div
                class="group relative cursor-pointer"
                onclick={() => openEditor(presentation)}
                data-test="presentation-card"
            >
                <Card class="transition-shadow group-hover:shadow-md">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Presentation
                                class="h-4 w-4 text-muted-foreground"
                            />
                            {presentation.name}
                        </CardTitle>
                        <CardDescription>
                            {presentation.slide_count}
                            {presentation.slide_count === 1
                                ? 'slide'
                                : 'slides'}
                            · updated {formatUpdatedAt(presentation.updated_at)}
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Button
                    variant="ghost"
                    size="sm"
                    class="absolute top-2 right-2 hidden text-muted-foreground group-hover:flex hover:text-destructive"
                    onclick={(event: MouseEvent) => {
                        event.stopPropagation();
                        presentationDeleting = presentation;
                        deleteDialogOpen = true;
                    }}
                    data-test="presentation-delete-button"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>
        {/each}
    </div>

    {#if presentations.length === 0}
        <p class="py-12 text-center text-muted-foreground">
            No presentations yet. Create your first one to get started.
        </p>
    {/if}
</div>

<Dialog bind:open={deleteDialogOpen}>
    <DialogContent>
        <div class="space-y-3">
            <DialogTitle>Delete presentation</DialogTitle>
            <DialogDescription>
                Delete "{presentationDeleting?.name}"? This cannot be undone.
            </DialogDescription>
        </div>
        <DialogFooter>
            <Button
                variant="outline"
                onclick={() => (deleteDialogOpen = false)}
            >
                Cancel
            </Button>
            <Button
                variant="destructive"
                onclick={confirmDelete}
                data-test="presentation-delete-confirm"
            >
                Delete
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
