<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import EditorToolbar from '@/components/lecturn/EditorToolbar.svelte';
    import InspectorPanel from '@/components/lecturn/InspectorPanel.svelte';
    import SlideCanvas from '@/components/lecturn/SlideCanvas.svelte';
    import SlideNavigator from '@/components/lecturn/SlideNavigator.svelte';
    import { generatePresentationSvelte } from '@/lib/lecturn/codegen';
    import { downloadFile, slugify } from '@/lib/lecturn/download';
    import { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import type { PresentationContent } from '@/types/generated';

    let {
        presentation,
    }: {
        presentation: {
            id: number;
            name: string;
            content: PresentationContent;
            updated_at: string | null;
        };
    } = $props();

    const editor = new EditorState(presentation.content);
    let name = $state(presentation.name);

    const exportSvelte = () => {
        downloadFile(
            `${slugify(name)}.svelte`,
            generatePresentationSvelte(editor.content),
        );
    };
</script>

<AppHead title={name} />

<div class="flex h-[calc(100vh-4rem)] flex-col">
    <EditorToolbar
        {editor}
        presentationId={presentation.id}
        bind:name
        onExport={exportSvelte}
    />

    <div class="flex min-h-0 flex-1">
        <SlideNavigator {editor} />
        <SlideCanvas {editor} />
        <InspectorPanel {editor} />
    </div>
</div>
