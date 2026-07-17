<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import EditorToolbar from '@/components/lecturn/EditorToolbar.svelte';
    import InspectorPanel from '@/components/lecturn/InspectorPanel.svelte';
    import SlideCanvas from '@/components/lecturn/SlideCanvas.svelte';
    import SlideNavigator from '@/components/lecturn/SlideNavigator.svelte';
    import { generatePresentationSvelte } from '@/lib/lecturn/codegen';
    import {
        downloadBlob,
        downloadFile,
        slugify,
    } from '@/lib/lecturn/download';
    import { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { exportMethod } from '@/routes/presentations';
    import type { PresentationContent } from '@/types/generated';
    import { page } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';

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

    const exportWebComponent = async () => {
        const currentTeam = page.props.currentTeam;

        if (!currentTeam) {
            return;
        }

        const url = exportMethod(
            {
                current_team: currentTeam.slug,
                presentation: presentation.id,
            },
            { query: { format: 'web-component' } },
        ).url;

        const response = await fetch(url);

        if (!response.ok) {
            toast.error('Web component export failed.');
            return;
        }

        downloadBlob(`${slugify(name)}.js`, await response.blob());
    };
</script>

<AppHead title={name} />

<div class="flex h-[calc(100vh-4rem)] flex-col">
    <EditorToolbar
        {editor}
        presentationId={presentation.id}
        bind:name
        onExport={exportSvelte}
        onExportWebComponent={exportWebComponent}
    />

    <div class="flex min-h-0 flex-1">
        <SlideNavigator {editor} />
        <SlideCanvas {editor} />
        <InspectorPanel {editor} />
    </div>
</div>
