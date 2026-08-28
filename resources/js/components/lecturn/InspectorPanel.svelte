<script lang="ts">
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import LayoutPicker from '@/components/lecturn/LayoutPicker.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { SUPPORTED_LANGUAGES } from '@/lib/lecturn/shiki';

    let { editor }: { editor: EditorState } = $props();

    const fontSizes = ['1rem', '1.5rem', '2rem', '2.5rem', '3rem', '4rem'];
    const fontWeights = ['normal', 'medium', 'semibold', 'bold'];

    const block = $derived(editor.selectedBlock);
    const transitions = $derived(
        editor.transitionsForSlide(editor.selectedSlide.id),
    );

    const setTransition = (blockId: string, value: string) => {
        if (value === '__new__') {
            const nodeId = editor.appendTransitionToSlide(
                editor.selectedSlide.id,
            );

            if (nodeId) {
                editor.pinBlock(blockId, nodeId);
            }

            return;
        }

        editor.pinBlock(blockId, value || null);
    };
</script>

<div class="flex h-full w-64 flex-col gap-6 overflow-y-auto border-l p-4">
    {#if block}
        {#if block.type !== 'richtext'}
            <div class="space-y-1">
                <Label for="block-transition" class="text-xs">Transition</Label>
                <select
                    id="block-transition"
                    class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                    value={block.transition?.nodeId ?? ''}
                    onchange={(event) =>
                        setTransition(block.id, event.currentTarget.value)}
                    data-test="inspector-transition"
                >
                    <option value="">Static (always visible)</option>
                    {#each transitions as transition (transition.nodeId)}
                        <option value={transition.nodeId}>
                            {editor.transitionDisplayName(transition)}
                        </option>
                    {/each}
                    <option value="__new__">+ New step</option>
                </select>
            </div>
        {/if}

        {#if block.type === 'code'}
            <div class="space-y-1">
                <Label for="block-lang" class="text-xs">Language</Label>
                <select
                    id="block-lang"
                    class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                    value={block.lang ?? 'typescript'}
                    onchange={(e) =>
                        editor.updateBlockLang(block.id, e.currentTarget.value)}
                    data-test="inspector-lang"
                >
                    {#each SUPPORTED_LANGUAGES as lang (lang)}
                        <option value={lang}>{lang}</option>
                    {/each}
                </select>
            </div>
        {/if}

        {#if block.type === 'text' || block.type === 'box'}
            <div class="space-y-1">
                <Label for="block-font-size" class="text-xs">Font size</Label>
                <select
                    id="block-font-size"
                    class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                    value={block.style.fontSize ?? ''}
                    onchange={(event) =>
                        editor.updateBlockStyle(block.id, {
                            fontSize: event.currentTarget.value || null,
                        })}
                    data-test="inspector-font-size"
                >
                    <option value="">Default</option>
                    {#each fontSizes as size (size)}
                        <option value={size}>{size}</option>
                    {/each}
                </select>
            </div>

            <div class="space-y-1">
                <Label for="block-font-weight" class="text-xs"
                    >Font weight</Label
                >
                <select
                    id="block-font-weight"
                    class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                    value={block.style.fontWeight ?? ''}
                    onchange={(event) =>
                        editor.updateBlockStyle(block.id, {
                            fontWeight: event.currentTarget.value || null,
                        })}
                    data-test="inspector-font-weight"
                >
                    <option value="">Default</option>
                    {#each fontWeights as weight (weight)}
                        <option value={weight}>{weight}</option>
                    {/each}
                </select>
            </div>

            <div class="space-y-1">
                <Label for="block-color" class="text-xs">Text color</Label>
                <input
                    id="block-color"
                    type="color"
                    class="h-8 w-full cursor-pointer rounded-md border"
                    value={block.style.color ?? '#000000'}
                    oninput={(event) =>
                        editor.updateBlockStyle(block.id, {
                            color: event.currentTarget.value,
                        })}
                    data-test="inspector-color"
                />
            </div>
        {/if}

        {#if block.type === 'box'}
            <div class="space-y-1">
                <Label for="block-border-color" class="text-xs"
                    >Border color</Label
                >
                <input
                    id="block-border-color"
                    type="color"
                    class="h-8 w-full cursor-pointer rounded-md border"
                    value={block.style.borderColor ?? '#e2e8f0'}
                    oninput={(event) =>
                        editor.updateBlockStyle(block.id, {
                            borderColor: event.currentTarget.value,
                        })}
                    data-test="inspector-border-color"
                />
            </div>

            <div class="space-y-1">
                <Label for="block-bg-color" class="text-xs">Background</Label>
                <input
                    id="block-bg-color"
                    type="color"
                    class="h-8 w-full cursor-pointer rounded-md border"
                    value={block.style.backgroundColor ?? '#ffffff'}
                    oninput={(event) =>
                        editor.updateBlockStyle(block.id, {
                            backgroundColor: event.currentTarget.value,
                        })}
                    data-test="inspector-bg-color"
                />
            </div>
        {/if}

        <Button
            variant="destructive"
            size="sm"
            onclick={() => editor.removeBlock(block.id)}
            data-test="inspector-delete-block"
        >
            <Trash2 class="h-4 w-4" /> Delete block
        </Button>
    {:else}
        <LayoutPicker {editor} />

        <div class="space-y-1">
            <Label for="slide-background" class="text-xs">Background</Label>
            <input
                id="slide-background"
                type="color"
                class="h-8 w-full cursor-pointer rounded-md border"
                value={editor.selectedSlide.background ?? '#ffffff'}
                oninput={(event) =>
                    editor.setBackground(event.currentTarget.value)}
                data-test="inspector-background"
            />
        </div>

        <p class="text-xs text-muted-foreground">
            Select a block on the canvas to edit its styles.
        </p>
    {/if}
</div>
