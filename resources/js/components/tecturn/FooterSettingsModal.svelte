<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import type { FooterSettings } from '@/types/generated';
    import PresentFooter from './PresentFooter.svelte';

    let {
        footer,
        open = $bindable(false),
        onSave,
    }: {
        footer: FooterSettings;
        open?: boolean;
        onSave: (next: FooterSettings) => void;
    } = $props();

    const userDefaults = $derived(page.props.auth?.user);

    // Staged edits — only committed to the presentation on Save.
    let enabled = $state(false);
    let xHandle = $state('');
    let githubHandle = $state('');
    let hashtag = $state('');
    let bgColor = $state('#000000');
    let transparent = $state(true);
    let fontColor = $state('#ffffff');
    let showInDock = $state(false);

    const stripLeading = (value: string | null | undefined): string =>
        (value ?? '').replace(/^[@#]/, '').trim();

    // Seed the form from the saved footer value first, then the user's profile
    // handle as a default when the footer field is empty.
    function seed() {
        enabled = footer.enabled;
        xHandle =
            stripLeading(footer.xHandle) ||
            stripLeading(userDefaults?.social_x_handle);
        githubHandle =
            stripLeading(footer.githubHandle) ||
            stripLeading(userDefaults?.social_github_handle);
        hashtag = stripLeading(footer.hashtag);
        transparent = !footer.bgColor || footer.bgColor === 'transparent';
        bgColor = transparent ? '#000000' : footer.bgColor;
        fontColor = footer.fontColor || '#ffffff';
        showInDock = footer.showInDock;
    }

    // Re-seed whenever the modal transitions to open. The editor opens it by
    // setting `open` directly (bind:open), which bypasses onOpenChange, so we
    // watch the flag itself rather than relying on the Dialog callback.
    let wasOpen = false;

    $effect(() => {
        if (open && !wasOpen) {
            seed();
        }

        wasOpen = open;
    });

    function handleOpenChange(value: boolean) {
        open = value;
    }

    const preview = $derived<FooterSettings>({
        enabled,
        xHandle: stripLeading(xHandle) || null,
        githubHandle: stripLeading(githubHandle) || null,
        hashtag: stripLeading(hashtag) || null,
        bgColor: transparent ? 'transparent' : bgColor,
        fontColor,
        showInDock,
    });

    function save() {
        onSave(preview);
        open = false;
    }
</script>

<Dialog {open} onOpenChange={handleOpenChange}>
    <DialogContent class="sm:max-w-lg">
        <div class="space-y-3">
            <DialogTitle>Footer</DialogTitle>
            <DialogDescription>
                Shown across every slide while presenting.
            </DialogDescription>
        </div>

        <div class="grid gap-4">
            <label class="flex items-center gap-2 text-sm" for="footer-enabled">
                <Checkbox
                    id="footer-enabled"
                    bind:checked={enabled}
                    data-test="footer-enabled"
                />
                Show footer
            </label>

            <div class="grid gap-2">
                <Label for="footer-x">X handle</Label>
                <Input
                    id="footer-x"
                    bind:value={xHandle}
                    placeholder={stripLeading(userDefaults?.social_x_handle) ||
                        'yourhandle'}
                    autocomplete="off"
                    data-test="footer-x-handle"
                />
            </div>

            <div class="grid gap-2">
                <Label for="footer-github">GitHub handle</Label>
                <Input
                    id="footer-github"
                    bind:value={githubHandle}
                    placeholder={stripLeading(
                        userDefaults?.social_github_handle,
                    ) || 'yourhandle'}
                    autocomplete="off"
                    data-test="footer-github-handle"
                />
            </div>

            <div class="grid gap-2">
                <Label for="footer-hashtag">Event hashtag</Label>
                <Input
                    id="footer-hashtag"
                    bind:value={hashtag}
                    placeholder="myconf2026"
                    autocomplete="off"
                    data-test="footer-hashtag"
                />
            </div>

            <div class="flex items-center gap-6">
                <div class="grid gap-2">
                    <Label for="footer-bg">Background</Label>
                    <div class="flex items-center gap-2">
                        <input
                            id="footer-bg"
                            type="color"
                            bind:value={bgColor}
                            disabled={transparent}
                            class="h-9 w-12 cursor-pointer rounded border bg-transparent disabled:opacity-40"
                        />
                        <label
                            class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            for="footer-transparent"
                        >
                            <Checkbox
                                id="footer-transparent"
                                bind:checked={transparent}
                                data-test="footer-transparent"
                            />
                            Transparent
                        </label>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="footer-font">Font color</Label>
                    <input
                        id="footer-font"
                        type="color"
                        bind:value={fontColor}
                        class="h-9 w-12 cursor-pointer rounded border bg-transparent"
                    />
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm" for="footer-in-dock">
                <Checkbox
                    id="footer-in-dock"
                    bind:checked={showInDock}
                    data-test="footer-in-dock"
                />
                <span>
                    Show in dock
                    <span class="block text-xs text-muted-foreground">
                        Only visible when the dock is turned on.
                    </span>
                </span>
            </label>

            <div class="grid gap-2">
                <span class="text-xs font-medium text-muted-foreground"
                    >Preview</span
                >
                <div
                    class="relative flex h-16 items-end overflow-hidden rounded border bg-[repeating-conic-gradient(#e5e7eb_0_25%,#f9fafb_0_50%)] bg-[length:16px_16px]"
                >
                    <PresentFooter footer={preview} variant="overlay" />
                </div>
            </div>
        </div>

        <DialogFooter class="gap-2">
            <Button variant="secondary" onclick={() => (open = false)}
                >Cancel</Button
            >
            <Button onclick={save} data-test="footer-save">Save</Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
