<script lang="ts">
    import Plus from 'lucide-svelte/icons/plus';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import { Button } from '@/components/ui/button';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { layoutDefinitions } from '@/lib/lecturn/layouts';

    let { editor }: { editor: EditorState } = $props();
</script>

<div class="flex h-full w-48 flex-col border-r">
    <div class="flex-1 space-y-2 overflow-y-auto p-3">
        {#each editor.content.slides as slide, index (slide.id)}
            <button
                type="button"
                class="group relative block w-full rounded-md border p-2 text-left text-sm transition-colors hover:bg-accent {index ===
                editor.selectedSlideIndex
                    ? 'border-primary bg-accent'
                    : ''}"
                onclick={() => editor.selectSlide(index)}
                data-test="slide-navigator-item"
            >
                <span class="font-medium">Slide {index + 1}</span>
                <span class="block text-xs text-muted-foreground">
                    {layoutDefinitions[slide.layout].label}
                </span>

                {#if editor.content.slides.length > 1}
                    <span
                        role="button"
                        tabindex="-1"
                        class="absolute top-1 right-1 hidden rounded p-1 text-muted-foreground group-hover:block hover:text-destructive"
                        onclick={(event) => {
                            event.stopPropagation();
                            editor.removeSlide(index);
                        }}
                        onkeydown={() => {}}
                        aria-label="Delete slide {index + 1}"
                        data-test="slide-delete-button"
                    >
                        <Trash2 class="h-3 w-3" />
                    </span>
                {/if}
            </button>
        {/each}
    </div>

    <div class="border-t p-3">
        <Button
            variant="outline"
            size="sm"
            class="w-full"
            onclick={() => editor.addSlide()}
            data-test="slide-add-button"
        >
            <Plus class="h-4 w-4" /> Add slide
        </Button>
    </div>
</div>
