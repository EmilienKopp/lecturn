<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import EditorToolbar from '@/components/lecturn/EditorToolbar.svelte';
    import FlowCanvas from '@/components/lecturn/flow/FlowCanvas.svelte';
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
    import type {
        FlowGraph,
        PresentationContent,
        TalkSettings,
    } from '@/types/generated';

    let {
        presentation,
        embed,
    }: {
        presentation: {
            id: number;
            name: string;
            content: PresentationContent;
            talk_settings: TalkSettings;
            flow: FlowGraph | null;
            updated_at: string | null;
        };
        embed: {
            url: string;
            tag: string;
        };
    } = $props();

    // The element must be block-level with a real height — Reveal.js sizes
    // itself to 100% of its container.
    const embedSnippet = `<script src="${embed.url}"><\/script>\n<${embed.tag} style="display: block; width: 100%; aspect-ratio: 16 / 9;"></${embed.tag}>`;

    const editor = new EditorState(presentation.content, presentation.flow);
    let name = $state(presentation.name);
    let view = $state<'slides' | 'flow'>('slides');

    const openSlide = (slideIndex: number) => {
        if (slideIndex !== -1) {
            editor.selectSlide(slideIndex);
        }

        view = 'slides';
    };

    const exportSvelte = () => {
        // Snapshots: codegen structuredClones its inputs, which rejects
        // $state proxies.
        downloadFile(
            `${slugify(name)}.svelte`,
            generatePresentationSvelte(
                $state.snapshot(editor.content),
                $state.snapshot(editor.flow),
            ),
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
        talkSettings={presentation.talk_settings}
        bind:name
        bind:view
        onExport={exportSvelte}
        onExportWebComponent={exportWebComponent}
        {embedSnippet}
    />

    {#if view === 'flow'}
        <div class="min-h-0 flex-1">
            <FlowCanvas {editor} onOpenSlide={openSlide} />
        </div>
    {:else}
        <div class="flex min-h-0 flex-1">
            <SlideNavigator {editor} />
            <SlideCanvas {editor} />
            <InspectorPanel {editor} />
        </div>
    {/if}
</div>
