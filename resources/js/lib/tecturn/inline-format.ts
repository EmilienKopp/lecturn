/**
 * Browser-only helpers that apply inline formatting to the current selection
 * inside a text/box block's contenteditable. The floating toolbar calls these;
 * the block's own `oninput` handler then persists the sanitized result.
 *
 * Bold, italic, and color go through `execCommand` with CSS styling on, which
 * handles the range splitting/merging and toggling that hand-rolled DOM
 * surgery gets wrong. `execCommand` is deprecated but still works everywhere
 * and, with `styleWithCSS`, emits `<span style>` markup that matches the
 * sanitizer allowlist. Font size has no CSS-value `execCommand`, so it wraps
 * the range in a styled span directly.
 */

import { scaleFontSize } from './scaling';

let cssStylingEnabled = false;

/** Make execCommand emit inline styles (`<span style>`) instead of tags. */
function ensureCssStyling(): void {
    if (cssStylingEnabled) {
        return;
    }

    try {
        document.execCommand('styleWithCSS', false, 'true');
        cssStylingEnabled = true;
    } catch {
        // Some browsers reject the toggle; the tag output still sanitizes fine.
    }
}

function hasRangeSelection(): boolean {
    const selection = window.getSelection();

    return !!selection && selection.rangeCount > 0 && !selection.isCollapsed;
}

export function toggleBold(): void {
    ensureCssStyling();
    document.execCommand('bold');
}

export function toggleItalic(): void {
    ensureCssStyling();
    document.execCommand('italic');
}

export function applyColor(color: string): void {
    if (!hasRangeSelection()) {
        return;
    }

    ensureCssStyling();
    document.execCommand('foreColor', false, color);
}

/**
 * Wrap the current selection in a `<span>` carrying the given font size.
 * `surroundContents` handles the clean case; a partial selection across nodes
 * falls back to extract-and-reinsert.
 */
export function applyFontSize(size: string): void {
    const selection = window.getSelection();

    if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
        return;
    }

    const range = selection.getRangeAt(0);
    const span = document.createElement('span');
    // Store the stage-relative size so the span scales with the stage the same
    // way block-level text does, in the editor, presenter, and export.
    span.style.fontSize = scaleFontSize(size) ?? size;

    try {
        range.surroundContents(span);
    } catch {
        span.appendChild(range.extractContents());
        range.insertNode(span);
    }

    selection.removeAllRanges();
    const reselected = document.createRange();
    reselected.selectNodeContents(span);
    selection.addRange(reselected);
}
