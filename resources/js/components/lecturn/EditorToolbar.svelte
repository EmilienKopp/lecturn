<script lang="ts">
    import { router, page } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import Play from 'lucide-svelte/icons/play';
    import Save from 'lucide-svelte/icons/save';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { present, update } from '@/routes/presentations';

    let {
        editor,
        presentationId,
        name = $bindable(),
        onExport,
        onExportWebComponent,
    }: {
        editor: EditorState;
        presentationId: number;
        name: string;
        onExport: () => void;
        onExportWebComponent: () => Promise<void>;
    } = $props();

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
            { name, content: editor.content },
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

    <div class="ml-auto flex items-center gap-2">
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
