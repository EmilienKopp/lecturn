/**
 * Shim for SvelteKit's `$app/environment`, required by @animotion/core's
 * Transition component. This app is Vite + Inertia, not SvelteKit, so the
 * module is aliased here (see vite.config.ts and tsconfig.json).
 */
export const browser = typeof window !== 'undefined';
export const dev = import.meta.env.DEV;
export const building = false;
