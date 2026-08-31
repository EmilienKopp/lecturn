/**
 * Animotion selectLines syntax: line numbers and ranges ("3", "3,5-8") or
 * "*" for all lines. Must stay in sync with the PHP CodeAction value object
 * (app/Domain/Presentation/ValueObjects/CodeAction.php), which enforces the
 * same pattern on save.
 */
export const HIGHLIGHT_LINES_PATTERN = /^(\*|\d+(-\d+)?(,\d+(-\d+)?)*)$/;

export const isValidHighlightLines = (value: string): boolean =>
    HIGHLIGHT_LINES_PATTERN.test(value.trim());
