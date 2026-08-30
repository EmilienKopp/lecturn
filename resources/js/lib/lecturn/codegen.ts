/**
 * Back-compat entry point: generation now lives in ./CodeGeneration
 * (class-based, plugin-driven — see CodeGeneration/index.ts). Kept so the
 * editor and scripts/present.mjs import paths stay stable.
 */
export {
    createDefaultContainer,
    generatePresentationSvelte,
} from './CodeGeneration/index.ts';
