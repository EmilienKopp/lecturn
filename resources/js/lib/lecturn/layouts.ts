import type { SlideLayout } from '@/types/generated';

export type LayoutDefinition = {
    label: string;
    slots: string[];
    /** Tailwind classes for the slide-level grid/flex container. */
    containerClass: string;
    /** Optional per-slot classes (keyed by slot name). */
    slotClass?: Record<string, string>;
};

export const layoutDefinitions: Record<SlideLayout, LayoutDefinition> = {
    // full: {
    //     label: 'Full',
    //     slots: ['main'],
    //     containerClass: 'grid h-full grid-cols-1',
    // },
    // center: {
    //     label: 'Center',
    //     slots: ['main'],
    //     containerClass: 'flex h-full items-center justify-center',
    // },
    // 'top-main': {
    //     label: 'Top + Main',
    //     slots: ['top', 'main'],
    //     containerClass: 'grid h-full grid-rows-[auto_1fr] gap-4',
    // },
    // 'top-main-footer': {
    //     label: 'Top + Main + Footer',
    //     slots: ['top', 'main', 'footer'],
    //     containerClass: 'grid h-full grid-rows-[auto_1fr_auto] gap-4',
    // },
    // 'left-right': {
    //     label: 'Two Columns',
    //     slots: ['left', 'right'],
    //     containerClass: 'grid h-full grid-cols-2 gap-6',
    // },
    // 'left-wide-right': {
    //     label: 'Narrow Left + Wide Right',
    //     slots: ['left', 'right'],
    //     containerClass: 'grid h-full grid-cols-[1fr_2fr] gap-6',
    // },
    // 'grid-2x2': {
    //     label: 'Grid 2×2',
    //     slots: ['a', 'b', 'c', 'd'],
    //     containerClass: 'grid h-full grid-cols-2 grid-rows-2 gap-4',
    // },
    // 'grid-2x3': {
    //     label: 'Grid 2×3',
    //     slots: ['a', 'b', 'c', 'd', 'e', 'f'],
    //     containerClass: 'grid h-full grid-cols-3 grid-rows-2 gap-4',
    // },
    // 'custom-grid': {
    //     label: 'Free Grid',
    //     slots: ['main'],
    //     containerClass: '',
    // },
    // 'rich-text': {
    //     label: 'Rich Text',
    //     slots: ['main'],
    //     containerClass: 'h-full overflow-y-auto',
    // },
    free: {
        label: 'Free',
        slots: ['main'],
        containerClass: 'relative h-full',
    },
};

export const availableLayouts: SlideLayout[] = [
    // 'full',
    // 'center',
    // 'top-main',
    // 'left-right',
    // 'left-wide-right',
    // 'grid-2x2',
    // 'custom-grid',
    // 'rich-text',
    'free',
];
