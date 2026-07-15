<script lang="ts">
    import { router, page } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import Save from 'lucide-svelte/icons/save';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import type { EditorState } from '@/lib/lecturn/editor-state.svelte';
    import { update } from '@/routes/presentations';

    let {
        editor,
        presentationId,
        name = $bindable(),
        onExport,
    }: {
        editor: EditorState;
        presentationId: number;
        name: string;
        onExport: () => void;
    } = $props();

    let saving = $state(false);

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
        <Button
            variant="outline"
            size="sm"
            onclick={onExport}
            data-test="editor-export-button"
        >
            <Download class="h-4 w-4" /> Export Svelte
        </Button>

        <Button
            size="sm"
            disabled={!editor.dirty || saving}
            onclick={save}
            data-test="editor-save-button"
        >
            <Save class="h-4 w-4" /> {saving ? 'Saving…' : 'Save'}
        </Button>
    </div>
</div>
