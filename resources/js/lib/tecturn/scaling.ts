/**
 * Font-size scaling for slide text.
 *
 * Block sizes are authored and stored in absolute units (`2rem`, etc.) but the
 * slide stage is rendered at wildly different pixel sizes: ~1000px wide in the
 * editor, up to full-screen when presenting or embedded. An absolute size that
 * looks right in the editor renders proportionally tiny on a big stage, and
 * since text is left-anchored it also appears to drift left. So text is sized
 * in container-query width units (`cqw`) instead, resolved against the stage,
 * so it keeps the same proportion at any stage size.
 *
 * This mirrors the model the code block already uses (`1.4cqw` == 14px on a
 * ~1000px stage). Keeping the same reference width means text and code scale
 * together. Pure math, no DOM: the codegen runs it in a Node subprocess.
 */

/** Stage width the authored sizes are calibrated against. 14px -> 1.4cqw. */
export const REFERENCE_STAGE_WIDTH_PX = 1000;

/** Root font size the `rem`/`em` presets resolve against. */
const REM_BASE_PX = 16;

/**
 * Convert an absolute font size (`rem`/`em`/`px`) to the stage-relative `cqw`
 * equivalent. Returns `null` for a null/empty input so callers omit the
 * declaration entirely, and passes through values already in another unit
 * (e.g. `cqw`, `%`) unchanged.
 */
export function scaleFontSize(fontSize: string | null): string | null {
    if (!fontSize) {
        return null;
    }

    const match = fontSize.trim().match(/^(\d*\.?\d+)(rem|em|px)$/i);

    if (!match) {
        return fontSize.trim();
    }

    const value = parseFloat(match[1]);
    const px = match[2].toLowerCase() === 'px' ? value : value * REM_BASE_PX;
    const cqw = (px / REFERENCE_STAGE_WIDTH_PX) * 100;

    return `${Math.round(cqw * 1000) / 1000}cqw`;
}
