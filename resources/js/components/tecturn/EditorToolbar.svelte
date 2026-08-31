<script lang="ts">
    import { router, page } from '@inertiajs/svelte';
    import CodeXml from 'lucide-svelte/icons/code-xml';
    import Download from 'lucide-svelte/icons/download';
    import Heart from 'lucide-svelte/icons/heart';
    import LayoutPanelLeft from 'lucide-svelte/icons/layout-panel-left';
    import PanelRight from 'lucide-svelte/icons/panel-right';
    import Play from 'lucide-svelte/icons/play';
    import Save from 'lucide-svelte/icons/save';
    import Workflow from 'lucide-svelte/icons/workflow';
    import { toast } from 'svelte-sonner';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import type { EditorState } from '@/lib/tecturn/editor-state.svelte';
    import { present, update } from '@/routes/presentations';
    import type { TalkSettings } from '@/types/generated';

    let {
        editor,
        presentationId,
        talkSettings,
        name = $bindable(),
        view = $bindable(),
        onExport,
        onExportWebComponent,
        embedSnippet,
    }: {
        editor: EditorState;
        presentationId: number;
        talkSettings: TalkSettings;
        name: string;
        view: 'slides' | 'flow';
        onExport: () => void;
        onExportWebComponent: () => Promise<void>;
        embedSnippet: string;
    } = $props();

    let showReactions = $state(talkSettings.showReactions);
    let showDock = $state(talkSettings.showDock);
    let savingTalkSettings = $state(false);

    const persistTalkSettings = (
        apply: () => void,
        rollback: () => void,
    ): void => {
        const currentTeam = page.props.currentTeam;

        if (!currentTeam || savingTalkSettings) {
            return;
        }

        apply();
        savingTalkSettings = true;

        router.put(
            update({
                current_team: currentTeam.slug,
                presentation: presentationId,
            }).url,
            { talk_settings: { ...talkSettings, showReactions, showDock } },
            {
                preserveState: true,
                onError: rollback,
                onFinish: () => {
                    savingTalkSettings = false;
                },
            },
        );
    };

    const toggleReactions = () =>
        persistTalkSettings(
            () => (showReactions = !showReactions),
            () => (showReactions = !showReactions),
        );

    const toggleDock = () =>
        persistTalkSettings(
            () => (showDock = !showDock),
            () => (showDock = !showDock),
        );

    const copyEmbedSnippet = async () => {
        await navigator.clipboard.writeText(embedSnippet);
        toast.success('Embed code copied to clipboard.');
    };

    let saving = $state(false);
    let exportingWebComponent = $state(false);

    const exportWebComponent = async () => {
        exportingWebComponent = true;

        try {
            await onExportWebComponent();
        } finally {
            exportingWebComponent = false;
        }
    };

    const presentUrl = $derived(
        page.props.currentTeam
            ? present({
                  current_team: page.props.currentTeam.slug,
                  presentation: presentationId,
              }).url
            : null,
    );

    const save = () => {
        const currentTeam = page.props.currentTeam;

        if (!currentTeam) {
            return;
        }

        saving = true;

        router.put(
            update({
                current_team: currentTeam.slug,
                presentation: presentationId,
            }).url,
            {
                name,
                content: editor.content,
                flow: $state.snapshot(editor.flow),
            },
            {
                preserveState: true,
                onSuccess: () => {
                    editor.dirty = false;
                },
                onFinish: () => {
                    saving = false;
                },
            },
        );
    };
</script>

<div class="flex items-center gap-3 border-b px-4 py-2">
    <Input
        bind:value={name}
        class="max-w-xs font-medium"
        oninput={() => (editor.dirty = true)}
        data-test="editor-presentation-name"
    />

    <div class="flex items-center rounded-md border p-0.5">
        <Button
            variant={view === 'slides' ? 'secondary' : 'ghost'}
            size="sm"
            onclick={() => (view = 'slides')}
            aria-pressed={view === 'slides'}
            data-test="editor-view-slides"
        >
            <LayoutPanelLeft class="h-4 w-4" /> Slides
        </Button>
        <Button
            variant={view === 'flow' ? 'secondary' : 'ghost'}
            size="sm"
            onclick={() => {
                editor.syncSlideNodes();
                view = 'flow';
            }}
            aria-pressed={view === 'flow'}
            data-test="editor-view-flow"
        >
            <Workflow class="h-4 w-4" /> Flow
        </Button>
    </div>

    <div class="ml-auto flex items-center gap-2">
        <Button
            variant={showReactions ? 'default' : 'outline'}
            size="sm"
            onclick={toggleReactions}
            disabled={savingTalkSettings}
            aria-pressed={showReactions}
            data-test="editor-reactions-toggle"
        >
            <Heart class="h-4 w-4" />
            Reactions {showReactions ? 'on' : 'off'}
        </Button>

        <Button
            variant={showDock ? 'default' : 'outline'}
            size="sm"
            onclick={toggleDock}
            disabled={savingTalkSettings}
            aria-pressed={showDock}
            data-test="editor-dock-toggle"
        >
            <PanelRight class="h-4 w-4" />
            Dock {showDock ? 'on' : 'off'}
        </Button>

        {#if presentUrl}
            <Button variant="outline" size="sm" asChild>
                {#snippet children(props)}
                    <a
                        {...props}
                        href={presentUrl}
                        target="_blank"
                        rel="noopener"
                        data-test="editor-present-link"
                    >
                        <Play class="h-4 w-4" /> Present
                    </a>
                {/snippet}
            </Button>
        {/if}

        <Button
            variant="outline"
            size="sm"
            onclick={onExport}
            data-test="editor-export-button"
        >
            <Download class="h-4 w-4" /> Export Svelte
        </Button>

        <Button
            variant="outline"
            size="sm"
            disabled={exportingWebComponent}
            onclick={exportWebComponent}
            data-test="editor-export-web-component-button"
        >
            <Download class="h-4 w-4" />
            {exportingWebComponent ? 'Exporting…' : 'Export Web Component'}
        </Button>

        <Button
            variant="outline"
            size="sm"
            onclick={copyEmbedSnippet}
            data-test="editor-copy-embed-button"
        >
            <CodeXml class="h-4 w-4" /> Copy Embed
        </Button>

        <Button
            size="sm"
            disabled={!editor.dirty || saving}
            onclick={save}
            data-test="editor-save-button"
        >
            <Save class="h-4 w-4" />
            {saving ? 'Saving…' : 'Save'}
        </Button>
    </div>
</div>
