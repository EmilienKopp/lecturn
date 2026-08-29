<script lang="ts">
    import ArrowDown from 'lucide-svelte/icons/arrow-down';
    import ArrowUp from 'lucide-svelte/icons/arrow-up';
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { untrack } from 'svelte';
    import CodeEditor from '@/components/lecturn/CodeEditor.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Label } from '@/components/ui/label';
    import { isValidHighlightLines } from '@/lib/lecturn/code-actions';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';

    let {
        editor,
        blockId,
        onClose,
    }: {
        editor: EditorState;
        blockId: string | null;
        onClose: () => void;
    } = $props();

    const block = $derived(blockId ? editor.blockById(blockId) : null);
    const pages = $derived(blockId ? editor.codeActionsForBlock(blockId) : []);

    // 'base' edits the block's own content; otherwise an action id.
    let selectedPage = $state<string>('base');
    let highlightDraft = $state('');

    const selectedAction = $derived(
        pages.find((page) => page.action.id === selectedPage) ?? null,
    );
    const highlightValid = $derived(
        highlightDraft.trim() === '' || isValidHighlightLines(highlightDraft),
    );

    // Reset to the base page whenever the modal targets another block, and
    // refill the highlight draft whenever another page is selected.
    $effect(() => {
        void blockId;
        selectedPage = 'base';
    });

    $effect(() => {
        void selectedPage;
        highlightDraft = untrack(
            () => selectedAction?.action.highlightLines ?? '',
        );
    });

    function selectPage(id: string): void {
        selectedPage = id;
    }

    function addPage(): void {
        if (!blockId) {
            return;
        }

        const actionId = editor.addCodeAction(blockId);

        if (actionId) {
            selectedPage = actionId;
        }
    }

    function removePage(actionId: string): void {
        if (!blockId) {
            return;
        }

        const index = pages.findIndex((page) => page.action.id === actionId);
        editor.removeCodeAction(blockId, actionId);

        if (selectedPage === actionId) {
            const neighbor = pages[index - 1] ?? null;
            selectedPage = neighbor ? neighbor.action.id : 'base';
        }
    }

    function commitHighlight(value: string): void {
        highlightDraft = value;

        if (!blockId || !selectedAction) {
            return;
        }

        const trimmed = value.trim();

        if (trimmed === '') {
            editor.updateCodeAction(blockId, selectedAction.action.id, {
                highlightLines: null,
            });
        } else if (isValidHighlightLines(trimmed)) {
            editor.updateCodeAction(blockId, selectedAction.action.id, {
                highlightLines: trimmed,
            });
        }
    }

    function handleOpenChange(open: boolean): void {
        if (!open) {
            onClose();
        }
    }
</script>

<Dialog open={block !== null} onOpenChange={handleOpenChange}>
    <DialogContent class="flex h-[80vh] flex-col sm:max-w-4xl">
        <DialogTitle>Code sequence</DialogTitle>
        <DialogDescription>
            Each page is a state the code morphs into during the presentation.
            Base is the code shown when the block appears.
        </DialogDescription>

        {#if block}
            <div class="flex min-h-0 flex-1 gap-4">
                <div
                    class="flex w-44 shrink-0 flex-col gap-1 overflow-y-auto"
                    data-test="sequence-page-list"
                >
                    <button
                        type="button"
                        class="rounded-md border px-2 py-1.5 text-left text-sm {selectedPage ===
                        'base'
                            ? 'border-primary bg-accent'
                            : 'hover:bg-accent'}"
                        onclick={() => selectPage('base')}
                        data-test="sequence-page-base"
                    >
                        Base
                    </button>

                    {#each pages as page (page.action.id)}
                        <div
                            class="group flex items-center gap-1 rounded-md border px-2 py-1 {selectedPage ===
                            page.action.id
                                ? 'border-primary bg-accent'
                                : 'hover:bg-accent'}"
                        >
                            <button
                                type="button"
                                class="min-w-0 flex-1 truncate text-left text-sm"
                                onclick={() => selectPage(page.action.id)}
                                data-test="sequence-page-{page.index + 1}"
                            >
                                {page.action.label ?? `Page ${page.index + 1}`}
                            </button>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                disabled={page.index === 0}
                                onclick={() =>
                                    blockId &&
                                    editor.moveCodeAction(
                                        blockId,
                                        page.action.id,
                                        'up',
                                    )}
                                title="Move up"
                            >
                                <ArrowUp class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                disabled={page.index === pages.length - 1}
                                onclick={() =>
                                    blockId &&
                                    editor.moveCodeAction(
                                        blockId,
                                        page.action.id,
                                        'down',
                                    )}
                                title="Move down"
                            >
                                <ArrowDown class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-destructive"
                                onclick={() => removePage(page.action.id)}
                                title="Delete page"
                                data-test="sequence-page-delete-{page.index +
                                    1}"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    {/each}

                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-1"
                        onclick={addPage}
                        data-test="sequence-add-page"
                    >
                        <Plus class="h-4 w-4" /> Add page
                    </Button>
                </div>

                <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-3">
                    {#key `${block.id}:${selectedPage}`}
                        <CodeEditor
                            class="min-h-0 flex-1 ring-1 ring-border ring-inset"
                            value={selectedAction
                                ? selectedAction.action.code
                                : block.content}
                            lang={block.lang ?? 'typescript'}
                            oninput={(value) => {
                                if (!blockId) {
                                    return;
                                }

                                if (selectedAction) {
                                    editor.updateCodeAction(
                                        blockId,
                                        selectedAction.action.id,
                                        { code: value },
                                    );
                                } else {
                                    editor.updateBlockContent(blockId, value);
                                }
                            }}
                        />
                    {/key}

                    {#if selectedAction}
                        <div class="flex gap-4">
                            <div class="flex-1 space-y-1">
                                <Label for="sequence-highlight" class="text-xs"
                                    >Highlight lines</Label
                                >
                                <input
                                    id="sequence-highlight"
                                    type="text"
                                    class="w-full rounded-md border bg-background px-2 py-1.5 font-mono text-sm {highlightValid
                                        ? ''
                                        : 'border-destructive'}"
                                    placeholder="e.g. 3,5-8 · * = all"
                                    value={highlightDraft}
                                    oninput={(event) =>
                                        commitHighlight(
                                            event.currentTarget.value,
                                        )}
                                    data-test="sequence-highlight-lines"
                                />
                                {#if !highlightValid}
                                    <p class="text-xs text-destructive">
                                        Use line numbers and ranges ("3,5-8") or
                                        "*" for all lines.
                                    </p>
                                {/if}
                            </div>
                            <div class="flex-1 space-y-1">
                                <Label for="sequence-label" class="text-xs"
                                    >Page label</Label
                                >
                                <input
                                    id="sequence-label"
                                    type="text"
                                    class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                                    placeholder="Page {selectedAction.index +
                                        1}"
                                    value={selectedAction.action.label ?? ''}
                                    onchange={(event) =>
                                        blockId &&
                                        editor.updateCodeAction(
                                            blockId,
                                            selectedAction.action.id,
                                            {
                                                label:
                                                    event.currentTarget.value.trim() ||
                                                    null,
                                            },
                                        )}
                                    data-test="sequence-page-label"
                                />
                            </div>
                        </div>
                    {:else}
                        <p class="text-xs text-muted-foreground">
                            Add pages to morph this code during the talk. Each
                            page becomes a step on the flow chart.
                        </p>
                    {/if}
                </div>
            </div>
        {/if}
    </DialogContent>
</Dialog>
