<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import Eye from 'lucide-svelte/icons/eye';
    import EyeOff from 'lucide-svelte/icons/eye-off';
    import SquarePen from 'lucide-svelte/icons/square-pen';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { toast } from 'svelte-sonner';
    import DeletePresentationBackgroundController from '@/actions/App/Http/Controllers/Presentations/DeletePresentationBackgroundController';
    import UploadPresentationBackgroundController from '@/actions/App/Http/Controllers/Presentations/UploadPresentationBackgroundController';
    import LayoutPicker from '@/components/lecturn/LayoutPicker.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { SUPPORTED_LANGUAGES } from '@/lib/lecturn/shiki';
    import { uploadImage, xsrfToken } from '@/lib/lecturn/uploads';

    let {
        editor,
        presentationId,
        onEditCodeSequence,
    }: {
        editor: EditorState;
        presentationId: number;
        onEditCodeSequence: (blockId: string) => void;
    } = $props();

    let uploadingBackground = $state(false);

    async function uploadBackgroundImage(event: Event) {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];
        const currentTeam = page.props.currentTeam;

        if (!file || !currentTeam) {
            return;
        }

        uploadingBackground = true;

        try {
            const url = await uploadImage(
                UploadPresentationBackgroundController({
                    current_team: currentTeam.slug,
                    presentation: presentationId,
                }).url,
                file,
            );

            if (url === null) {
                toast.error('Background image upload failed.');

                return;
            }

            editor.setBackgroundImage(url);
        } finally {
            uploadingBackground = false;
            input.value = '';
        }
    }

    async function removeBackgroundImage() {
        const currentTeam = page.props.currentTeam;

        if (!currentTeam) {
            return;
        }

        await fetch(
            DeletePresentationBackgroundController({
                current_team: currentTeam.slug,
                presentation: presentationId,
            }).url,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-XSRF-TOKEN': xsrfToken(),
                    Accept: 'application/json',
                },
            },
        );

        editor.setBackgroundImage(null);
    }

    const fontSizes = ['1rem', '1.5rem', '2rem', '2.5rem', '3rem', '4rem'];
    const fontWeights = ['normal', 'medium', 'semibold', 'bold'];

    const block = $derived(editor.selectedBlock);
    const transitions = $derived(
        editor.transitionsForSlide(editor.selectedSlide.id),
    );
    const pinnedTransition = $derived(
        transitions.find(
            (transition) => transition.nodeId === block?.transition?.nodeId,
        ) ?? null,
    );

    const renameTransition = (nodeId: string, value: string) => {
        if (!editor.setTransitionLabel(nodeId, value.trim() || null)) {
            toast.error('Another step on this slide already has that name.');
        }
    };

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

            {#if pinnedTransition}
                <div class="space-y-1">
                    <Label for="transition-label" class="text-xs"
                        >Step name</Label
                    >
                    <input
                        id="transition-label"
                        type="text"
                        class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                        placeholder="Step {pinnedTransition.index + 1}"
                        value={pinnedTransition.label ?? ''}
                        onchange={(event) =>
                            renameTransition(
                                pinnedTransition.nodeId,
                                event.currentTarget.value,
                            )}
                        data-test="inspector-transition-label"
                    />
                </div>
            {/if}
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

            <div class="space-y-1">
                <Label class="text-xs">Code sequence</Label>
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full"
                    onclick={() => onEditCodeSequence(block.id)}
                    data-test="inspector-edit-sequence"
                >
                    <SquarePen class="h-4 w-4" />
                    {(block.actions ?? []).length > 0
                        ? `Edit sequence (${(block.actions ?? []).length})`
                        : 'Add sequence'}
                </Button>
                <p class="text-[11px] text-muted-foreground">
                    Morph this code through pages during the talk, with optional
                    line highlights.
                </p>
            </div>
        {/if}

        {#if block.type === 'image'}
            <div class="space-y-1">
                <Label for="block-alt" class="text-xs">Alt text</Label>
                <input
                    id="block-alt"
                    type="text"
                    class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                    value={block.alt ?? ''}
                    oninput={(event) =>
                        editor.updateBlockAlt(
                            block.id,
                            event.currentTarget.value,
                        )}
                    data-test="inspector-alt"
                />
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
        <div class="space-y-1">
            <Label for="slide-title" class="text-xs">Slide title</Label>
            <input
                id="slide-title"
                type="text"
                class="h-8 w-full rounded-md border bg-background px-2 text-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                placeholder="Slide {editor.selectedSlideIndex + 1}"
                value={editor.selectedSlide.title ?? ''}
                onchange={(event) =>
                    editor.setSlideTitle(event.currentTarget.value)}
                data-test="inspector-slide-title"
            />
        </div>

        {#if editor.isEntrySlide(editor.selectedSlide.id)}
            <p class="text-xs text-muted-foreground">
                Entry slide (always shown).
            </p>
        {:else}
            {@const enabled = editor.isSlideEnabled(editor.selectedSlide.id)}
            <div class="space-y-1">
                <Button
                    variant={enabled ? 'outline' : 'default'}
                    size="sm"
                    class="w-full"
                    onclick={() =>
                        editor.toggleSlideEnabled(editor.selectedSlideIndex)}
                    data-test="inspector-toggle-slide"
                >
                    {#if enabled}
                        <EyeOff class="h-4 w-4" /> Disable slide
                    {:else}
                        <Eye class="h-4 w-4" /> Enable slide
                    {/if}
                </Button>
                {#if !enabled}
                    <p class="text-xs text-muted-foreground">
                        Hidden when presenting. Enabling re-links it into the
                        flow by its order.
                    </p>
                {/if}
            </div>
        {/if}

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
            <Button
                variant="outline"
                size="sm"
                class="w-full"
                onclick={() => editor.applyBackgroundToAllSlides()}
                data-test="apply-background-all"
            >
                Apply to all slides
            </Button>
        </div>

        <div class="space-y-1">
            <Label class="text-xs">Background image (all slides)</Label>
            {#if editor.backgroundImage}
                <div
                    class="h-16 w-full rounded-md border bg-cover bg-center"
                    style="background-image: url('{editor.backgroundImage}')"
                    data-test="inspector-background-image-preview"
                ></div>
            {/if}
            <label
                class="flex h-8 w-full cursor-pointer items-center justify-center rounded-md border text-xs hover:bg-accent {uploadingBackground
                    ? 'pointer-events-none opacity-60'
                    : ''}"
            >
                {uploadingBackground
                    ? 'Uploading…'
                    : editor.backgroundImage
                      ? 'Replace image'
                      : 'Upload image'}
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="hidden"
                    onchange={uploadBackgroundImage}
                    data-test="inspector-background-image-input"
                />
            </label>
            {#if editor.backgroundImage}
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full"
                    onclick={removeBackgroundImage}
                    data-test="remove-background-image"
                >
                    Remove image
                </Button>
            {/if}
            <p class="text-[11px] text-muted-foreground">
                Shows behind every slide that has no background color of its
                own.
            </p>
        </div>

        <p class="text-xs text-muted-foreground">
            Select a block on the canvas to edit its styles.
        </p>
    {/if}
</div>
