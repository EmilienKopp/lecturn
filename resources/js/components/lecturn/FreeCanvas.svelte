<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import UploadPresentationImageController from '@/actions/App/Http/Controllers/Presentations/UploadPresentationImageController';
    import BlockPinMenu from '@/components/lecturn/BlockPinMenu.svelte';
    import BoxBlockView from '@/components/lecturn/BoxBlockView.svelte';
    import CodeBlockView from '@/components/lecturn/CodeBlockView.svelte';
    import TextBlockView from '@/components/lecturn/TextBlockView.svelte';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import {
        clampPercent,
        FREE_DEFAULTS,
        round2,
        startPointerDrag,
    } from '@/lib/lecturn/free-drag';
    import { uploadImage } from '@/lib/lecturn/uploads';

    let {
        editor,
        presentationId,
    }: { editor: EditorState; presentationId: number } = $props();

    const slide = $derived(editor.selectedSlide);
    const blocks = $derived(slide.slots['main'] ?? []);

    let canvasEl = $state<HTMLDivElement | null>(null);
    let imageInput = $state<HTMLInputElement | null>(null);
    let pendingImagePosition = $state<{ x: string; y: string } | null>(null);
    let uploadingImage = $state(false);
    let popoverVisible = $state(false);
    let popover = $state<{ top: number; left: number; x: number; y: number }>({
        top: 0,
        left: 0,
        x: 0,
        y: 0,
    });

    function num(value: string | null, fallback: number): number {
        if (value === null) {
            return fallback;
        }

        const parsed = parseFloat(value);

        return Number.isNaN(parsed) ? fallback : parsed;
    }

    function openPopover(event: MouseEvent) {
        if (!canvasEl) {
            return;
        }

        const rect = canvasEl.getBoundingClientRect();
        const x = clampPercent(
            ((event.clientX - rect.left) / rect.width) * 100,
            0,
            95,
        );
        const y = clampPercent(
            ((event.clientY - rect.top) / rect.height) * 100,
            0,
            95,
        );
        popover = {
            top: event.clientY - rect.top,
            left: event.clientX - rect.left,
            x,
            y,
        };
        popoverVisible = true;
    }

    function createBlock(type: 'text' | 'code' | 'box') {
        editor.addFreeBlock(
            String(round2(popover.x)),
            String(round2(popover.y)),
            type,
        );
        popoverVisible = false;
    }

    function insertImage() {
        pendingImagePosition = {
            x: String(round2(popover.x)),
            y: String(round2(popover.y)),
        };
        popoverVisible = false;
        imageInput?.click();
    }

    async function onImageSelected(event: Event) {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];
        const currentTeam = page.props.currentTeam;
        const position = pendingImagePosition;

        input.value = '';

        if (!file || !currentTeam || !position) {
            return;
        }

        uploadingImage = true;

        try {
            const url = await uploadImage(
                UploadPresentationImageController({
                    current_team: currentTeam.slug,
                    presentation: presentationId,
                }).url,
                file,
            );

            if (url === null) {
                toast.error('Image upload failed.');

                return;
            }

            editor.addFreeImageBlock(position.x, position.y, url);
        } finally {
            uploadingImage = false;
            pendingImagePosition = null;
        }
    }

    function clearSelection() {
        popoverVisible = false;
        editor.selectedBlockId = null;
    }

    function startMove(event: PointerEvent, block: (typeof blocks)[number]) {
        if (!canvasEl) {
            return;
        }

        const startX = num(block.style.x, FREE_DEFAULTS.x);
        const startY = num(block.style.y, FREE_DEFAULTS.y);
        const width = num(block.style.width, FREE_DEFAULTS.width);

        startPointerDrag(event, {
            container: canvasEl,
            onMove: (dx, dy) => {
                editor.updateBlockStyle(block.id, {
                    x: String(
                        round2(clampPercent(startX + dx, 0, 100 - width)),
                    ),
                    y: String(round2(clampPercent(startY + dy, 0, 95))),
                });
            },
        });
    }

    function startResize(
        event: PointerEvent,
        block: (typeof blocks)[number],
        wrapper: HTMLElement,
    ) {
        if (!canvasEl) {
            return;
        }

        const startWidth = num(block.style.width, FREE_DEFAULTS.width);
        const rect = canvasEl.getBoundingClientRect();
        // Auto-height blocks have no stored height; seed from the rendered box.
        const startHeight =
            block.style.height !== null
                ? num(block.style.height, 20)
                : (wrapper.getBoundingClientRect().height / rect.height) * 100;
        const x = num(block.style.x, FREE_DEFAULTS.x);
        const y = num(block.style.y, FREE_DEFAULTS.y);

        startPointerDrag(event, {
            container: canvasEl,
            onMove: (dx, dy) => {
                editor.updateBlockStyle(block.id, {
                    width: String(
                        round2(clampPercent(startWidth + dx, 5, 100 - x)),
                    ),
                    height: String(
                        round2(clampPercent(startHeight + dy, 5, 100 - y)),
                    ),
                });
            },
        });
    }

    function resetHeight(block: (typeof blocks)[number]) {
        editor.updateBlockStyle(block.id, { height: null });
    }
</script>

<!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
<div
    bind:this={canvasEl}
    class="relative h-full w-full"
    onclick={clearSelection}
    ondblclick={openPopover}
    data-test="free-canvas"
>
    {#if blocks.length === 0}
        <div
            class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm opacity-40"
        >
            Double-click to add a block
        </div>
    {/if}

    {#each blocks as block (block.id)}
        {@const selected = editor.selectedBlockId === block.id}
        {@const height = block.style.height}
        <div
            class="free-block absolute {selected
                ? 'z-10 ring-2 ring-primary'
                : ''}"
            style="left: {num(block.style.x, FREE_DEFAULTS.x)}%; top: {num(
                block.style.y,
                FREE_DEFAULTS.y,
            )}%; width: {num(
                block.style.width,
                FREE_DEFAULTS.width,
            )}%;{height !== null ? ` height: ${num(height, 20)}%;` : ''}"
            onclick={(e) => {
                e.stopPropagation();
                editor.selectedBlockId = block.id;
            }}
        >
            {#if selected}
                <!-- Drag handle: move from the top bar so text editing below stays intact. -->
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div
                    class="absolute -top-4 left-0 flex h-4 w-full cursor-move items-center justify-center rounded-t bg-primary/80"
                    onpointerdown={(e) => startMove(e, block)}
                    title="Drag to move"
                >
                    <div
                        class="h-0.5 w-6 rounded bg-primary-foreground/70"
                    ></div>
                </div>
            {/if}

            <div class="h-full w-full">
                <BlockPinMenu {editor} {block}>
                    {#if block.type === 'text'}
                        <TextBlockView {editor} {block} />
                    {:else if block.type === 'code'}
                        <CodeBlockView {editor} {block} />
                    {:else if block.type === 'box'}
                        <BoxBlockView {editor} {block} />
                    {:else if block.type === 'image'}
                        <img
                            src={block.src ?? ''}
                            alt={block.alt ?? ''}
                            class="h-full w-full object-contain"
                            draggable="false"
                        />
                    {/if}
                </BlockPinMenu>
            </div>

            {#if selected}
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div
                    class="absolute -right-1.5 -bottom-1.5 h-3 w-3 cursor-nwse-resize rounded-full border border-primary-foreground bg-primary"
                    onpointerdown={(e) =>
                        startResize(e, block, e.currentTarget.parentElement!)}
                    ondblclick={(e) => {
                        e.stopPropagation();
                        resetHeight(block);
                    }}
                    title="Drag to resize · double-click to reset height"
                ></div>
            {/if}
        </div>
    {/each}

    {#if popoverVisible}
        <div
            class="absolute z-50 flex gap-1 rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
            style="top: {popover.top}px; left: {popover.left}px;"
            onclick={(e) => e.stopPropagation()}
        >
            <button
                type="button"
                class="rounded px-2 py-1 text-xs hover:bg-accent"
                onclick={() => createBlock('text')}>Text</button
            >
            <button
                type="button"
                class="rounded px-2 py-1 text-xs hover:bg-accent"
                onclick={() => createBlock('code')}>Code</button
            >
            <button
                type="button"
                class="rounded px-2 py-1 font-mono text-xs hover:bg-accent"
                onclick={() => createBlock('box')}>Box</button
            >
            <button
                type="button"
                class="rounded px-2 py-1 text-xs hover:bg-accent"
                onclick={insertImage}>Image</button
            >
        </div>
    {/if}

    {#if uploadingImage}
        <div
            class="pointer-events-none absolute inset-0 flex items-center justify-center bg-background/50 text-sm"
        >
            Uploading image…
        </div>
    {/if}

    <input
        bind:this={imageInput}
        type="file"
        accept="image/jpeg,image/png,image/webp,image/gif"
        class="hidden"
        onchange={onImageSelected}
        data-test="free-image-input"
    />
</div>
