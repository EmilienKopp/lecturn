<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import ClipboardPaste from 'lucide-svelte/icons/clipboard-paste';
    import Plus from 'lucide-svelte/icons/plus';
    import Presentation from 'lucide-svelte/icons/presentation';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Upload from 'lucide-svelte/icons/upload';
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
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { destroy, edit, importJson, store } from '@/routes/presentations';

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
    let importing = $state(false);
    let importError = $state<string | null>(null);
    let importInput = $state<HTMLInputElement | null>(null);
    let pasteDialogOpen = $state(false);
    let pastedJson = $state('');

    const teamSlug = $derived(page.props.currentTeam?.slug ?? '');

    const triggerFileImport = () => {
        importError = null;
        importInput?.click();
    };

    const submitImport = (
        payload: { file: File } | { json: string },
        onDone?: () => void,
    ) => {
        importing = true;

        router.post(importJson(teamSlug).url, payload, {
            forceFormData: true,
            onError: (errors) => {
                importError =
                    errors.file ?? errors.json ?? 'Could not import that JSON.';
            },
            onFinish: () => {
                importing = false;
                onDone?.();
            },
        });
    };

    const importFromFile = (event: Event) => {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];

        if (!file) {
            return;
        }

        importError = null;
        submitImport({ file }, () => {
            input.value = '';
        });
    };

    const importFromPaste = () => {
        if (pastedJson.trim() === '') {
            return;
        }

        importError = null;
        submitImport({ json: pastedJson }, () => {
            if (!importError) {
                pasteDialogOpen = false;
                pastedJson = '';
            }
        });
    };

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

        <div class="flex items-center gap-2">
            <input
                bind:this={importInput}
                type="file"
                accept="application/json,.json"
                class="hidden"
                onchange={importFromFile}
                data-test="import-presentation-input"
            />
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    {#snippet children(props)}
                        <Button
                            variant="outline"
                            onclick={props.onclick}
                            aria-expanded={props['aria-expanded']}
                            data-state={props['data-state']}
                            disabled={importing}
                            data-test="import-presentation-button"
                        >
                            <Upload class="h-4 w-4" />
                            {importing ? 'Importing…' : 'Import'}
                            <ChevronDown class="ml-1 h-4 w-4 opacity-50" />
                        </Button>
                    {/snippet}
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    <DropdownMenuItem asChild>
                        {#snippet children(props)}
                            <button
                                type="button"
                                class={props.class}
                                onclick={() => {
                                    props.onClick?.();
                                    triggerFileImport();
                                }}
                                data-test="import-upload-option"
                            >
                                <Upload class="h-4 w-4" />
                                Upload file
                            </button>
                        {/snippet}
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        {#snippet children(props)}
                            <button
                                type="button"
                                class={props.class}
                                onclick={() => {
                                    props.onClick?.();
                                    importError = null;
                                    pasteDialogOpen = true;
                                }}
                                data-test="import-paste-option"
                            >
                                <ClipboardPaste class="h-4 w-4" />
                                Paste JSON
                            </button>
                        {/snippet}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

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
    </div>

    {#if importError && !pasteDialogOpen}
        <p
            class="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            data-test="import-presentation-error"
        >
            {importError}
        </p>
    {/if}

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

<Dialog bind:open={pasteDialogOpen}>
    <DialogContent>
        <div class="space-y-4">
            <div class="space-y-3">
                <DialogTitle>Paste presentation JSON</DialogTitle>
                <DialogDescription>
                    Paste an exported presentation's JSON below. It will be
                    imported as a new presentation.
                </DialogDescription>
            </div>

            <textarea
                bind:value={pastedJson}
                rows={12}
                spellcheck={false}
                placeholder={'{\n  "name": "My deck",\n  "content": { "version": "1.0", "slides": [] }\n}'}
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-xs shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                data-test="import-paste-textarea"
            ></textarea>

            {#if importError}
                <p
                    class="text-sm text-destructive"
                    data-test="import-paste-error"
                >
                    {importError}
                </p>
            {/if}
        </div>
        <DialogFooter>
            <Button variant="outline" onclick={() => (pasteDialogOpen = false)}>
                Cancel
            </Button>
            <Button
                onclick={importFromPaste}
                disabled={importing || pastedJson.trim() === ''}
                data-test="import-paste-submit"
            >
                {importing ? 'Importing…' : 'Import'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

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
