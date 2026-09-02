<script lang="ts">
    import { Button } from '@/components/ui/button';
    import type { Variant } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogTitle,
        DialogDescription,
        DialogFooter,
    } from '@/components/ui/dialog';

    let deleteDialogOpen = $state(false);
    let title = $state('');
    let text = $state('');
    let onConfirm = $state(() => {});
    let variant = $state<Variant>('default');
    let action = $state('OK');

    export function confirm({
        title: confirmTitle,
        text: confirmText,
        onConfirm: callback,
        variant: confirmVariant,
        action: confirmAction,
    }: {
        title: string;
        text: string;
        onConfirm?: () => void;
        variant?: Variant;
        action?: string;
    }) {
        title = confirmTitle;
        text = confirmText;
        onConfirm = () => {
            callback?.();
            deleteDialogOpen = false;
        };
        variant = confirmVariant ?? 'default';
        action = confirmAction ?? 'OK';

        deleteDialogOpen = true;
    }
</script>

<Dialog bind:open={deleteDialogOpen}>
    <DialogContent class="space-y-5">
        <div class="space-y-3">
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>
                {text}
            </DialogDescription>
        </div>
        <DialogFooter>
            <Button
                variant="outline"
                onclick={() => (deleteDialogOpen = false)}
            >
                Cancel
            </Button>
            <Button
                {variant}
                onclick={onConfirm}
                data-test="slide-delete-confirm"
            >
                {action}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
