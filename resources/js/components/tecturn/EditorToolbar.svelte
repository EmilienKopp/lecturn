<script lang="ts">
    import { router, page } from '@inertiajs/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import CodeXml from 'lucide-svelte/icons/code-xml';
    import Download from 'lucide-svelte/icons/download';
    import Heart from 'lucide-svelte/icons/heart';
    import Languages from 'lucide-svelte/icons/languages';
    import LayoutPanelLeft from 'lucide-svelte/icons/layout-panel-left';
    import PanelRight from 'lucide-svelte/icons/panel-right';
    import Play from 'lucide-svelte/icons/play';
    import QrCode from 'lucide-svelte/icons/qr-code';
    import Save from 'lucide-svelte/icons/save';
    import Settings2 from 'lucide-svelte/icons/settings-2';
    import Workflow from 'lucide-svelte/icons/workflow';
    import { toast } from 'svelte-sonner';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Input } from '@/components/ui/input';
    import { promise } from '@/lib/support/async';
    import { ms } from '@/lib/support/time';
    import type { EditorState } from '@/lib/tecturn/editor-state.svelte';
    import { present, update } from '@/routes/presentations';
    import type { TalkSettings } from '@/types/generated';
    import Checkbox from '../ui/checkbox/Checkbox.svelte';
    import Label from '../ui/label/Label.svelte';

    let {
        editor,
        presentationId,
        talkSettings,
        name = $bindable(),
        view = $bindable(),
        onExport,
        onExportWebComponent,
        embedSnippet,
        viewerUrl,
    }: {
        editor: EditorState;
        presentationId: number;
        talkSettings: TalkSettings;
        name: string;
        view: 'slides' | 'flow';
        onExport: () => void;
        onExportWebComponent: () => Promise<void>;
        embedSnippet: string;
        viewerUrl: string;
    } = $props();

    let showReactions = $state(talkSettings.showReactions);
    let showDock = $state(talkSettings.showDock);
    let showTranslation = $state(talkSettings.showTranslation);
    let savingTalkSettings = $state(false);
    let autoSave = $state(talkSettings.autoSave ?? false);
    let saving = $state(promise());

    const AUTO_SAVE_INTERVAL = ms(10_000);

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
            {
                talk_settings: {
                    ...talkSettings,
                    showReactions,
                    showDock,
                    showTranslation,
                    autoSave,
                },
            },
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

    const toggleTranslation = () =>
        persistTalkSettings(
            () => (showTranslation = !showTranslation),
            () => (showTranslation = !showTranslation),
        );

    const toggleAutoSave = () => {
        persistTalkSettings(
            () => (autoSave = !autoSave),
            () => (autoSave = !autoSave),
        );
    };

    const copyEmbedSnippet = async () => {
        await navigator.clipboard.writeText(embedSnippet);
        toast.success('Embed code copied to clipboard.');
    };

    const copyViewerUrl = async () => {
        await navigator.clipboard.writeText(viewerUrl);
        toast.success('Reaction URL copied to clipboard.');
    };

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

    const save = async () => {
        const currentTeam = page.props.currentTeam;

        if (!currentTeam) {
            return;
        }

        saving = new Promise<void>((resolve) => {
            router.put(
                update({
                    current_team: currentTeam.slug,
                    presentation: presentationId,
                }).url,
                {
                    name,
                    content: editor.content,
                    flow: $state.snapshot(editor.flow),
                    autoSave: autoSave,
                },
                {
                    preserveState: true,
                    onSuccess: () => {
                        editor.dirty = false;
                    },
                    onFinish: () => {
                        resolve();
                    },
                },
            );
        });
    };

    setInterval(() => {
        if (autoSave && editor.dirty) {
            save();
        }
    }, AUTO_SAVE_INTERVAL.milliseconds());
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

    <div
        class="text-sm flex items-center gap-1 justify-center"
        title="Toggle auto save (every {AUTO_SAVE_INTERVAL.seconds()}s)"
        class:text-muted-foreground={!autoSave}
    >
        <Checkbox
            id="auto-save-toggle"
            size="sm"
            class="text-muted-foreground"
            data-test="editor-toggle-auto-save"
            onclick={toggleAutoSave}
            checked={autoSave}
        />
        <Label for="auto-save-toggle">Auto Save</Label>
    </div>

    {#snippet toggleRow(
        label: string,
        Icon: typeof Heart,
        checked: boolean,
        toggle: () => void,
        testId: string,
    )}
        <button
            type="button"
            role="menuitemcheckbox"
            aria-checked={checked}
            class="flex w-full cursor-pointer select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground"
            disabled={savingTalkSettings}
            onclick={toggle}
            data-test={testId}
        >
            <Icon class="h-4 w-4" />
            {label}
            <span
                class="ml-auto text-xs {checked
                    ? 'font-medium text-primary'
                    : 'text-muted-foreground'}"
            >
                {checked ? 'On' : 'Off'}
            </span>
        </button>
    {/snippet}

    <div class="ml-auto flex items-center gap-2">
        <Button
            variant="ghost"
            size="sm"
            class="text-muted-foreground"
            onclick={copyViewerUrl}
            title="Copy the URL the audience uses to react"
            data-test="editor-copy-viewer-url"
        >
            <QrCode class="h-4 w-4" /> Reaction URL
        </Button>

        {#if presentUrl}
            <Button
                size="sm"
                class="bg-emerald-600 text-white shadow hover:bg-emerald-500"
                asChild
            >
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

        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {#snippet children(props)}
                    <Button
                        {...props}
                        variant="outline"
                        size="sm"
                        data-test="editor-settings-menu"
                    >
                        <Settings2 class="h-4 w-4" /> Settings
                        <ChevronDown class="h-3.5 w-3.5 opacity-60" />
                    </Button>
                {/snippet}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={4} class="w-56">
                {@render toggleRow(
                    'Reactions',
                    Heart,
                    showReactions,
                    toggleReactions,
                    'editor-reactions-toggle',
                )}
                {@render toggleRow(
                    'Dock',
                    PanelRight,
                    showDock,
                    toggleDock,
                    'editor-dock-toggle',
                )}
                {@render toggleRow(
                    'Live Translation',
                    Languages,
                    showTranslation,
                    toggleTranslation,
                    'editor-translation-toggle',
                )}
            </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {#snippet children(props)}
                    <Button
                        {...props}
                        variant="outline"
                        size="sm"
                        data-test="editor-export-menu"
                    >
                        <Download class="h-4 w-4" /> Export
                        <ChevronDown class="h-3.5 w-3.5 opacity-60" />
                    </Button>
                {/snippet}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={4} class="w-60">
                <DropdownMenuItem asChild>
                    {#snippet children(props)}
                        <button
                            type="button"
                            class={props.class}
                            onclick={() => {
                                props.onClick?.();
                                onExport();
                            }}
                            data-test="editor-export-button"
                        >
                            <Download class="mr-2 h-4 w-4" /> Export Svelte
                        </button>
                    {/snippet}
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    {#snippet children(props)}
                        <button
                            type="button"
                            class={props.class}
                            disabled={exportingWebComponent}
                            onclick={() => {
                                props.onClick?.();
                                exportWebComponent();
                            }}
                            data-test="editor-export-web-component-button"
                        >
                            <Download class="mr-2 h-4 w-4" />
                            {exportingWebComponent
                                ? 'Exporting…'
                                : 'Export Web Component'}
                        </button>
                    {/snippet}
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    {#snippet children(props)}
                        <button
                            type="button"
                            class={props.class}
                            onclick={() => {
                                props.onClick?.();
                                copyEmbedSnippet();
                            }}
                            data-test="editor-copy-embed-button"
                        >
                            <CodeXml class="mr-2 h-4 w-4" /> Copy Embed
                        </button>
                    {/snippet}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <Button
            size="sm"
            disabled={!editor.dirty}
            onclick={save}
            data-test="editor-save-button"
        >
            <Save class="h-4 w-4" />
            {#key saving}
                {#await saving}
                    Saving…
                {:then}
                    Save
                {/await}
            {/key}
        </Button>
    </div>
</div>
