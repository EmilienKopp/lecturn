# Backend Presenter Export Infrastructure

## Context

The slide editor originally exported presentations only via a frontend pipeline: `codegen.ts → downloadFile()`. The backend Presenter port adds:

1. **"Export Web Component"** — PHP → Node.js subprocess → Vite build (`@sveltejs/vite-plugin-svelte` with `customElement: true`) → self-contained IIFE `.js` registering `<lecturn-presentation>`, embeddable in any site/Webflow
2. Foundation for a future **"Export PDF"** (PHP → Node → Playwright)
3. A clean `Presenter` interface so all export types share the same port

The frontend "Export Svelte" button is unchanged and stays client-side.

## Single Source of Truth

**The Svelte generation logic lives only in `resources/js/lib/lecturn/codegen.ts`.** There is no PHP port. Both consumers use the same module:

- The editor imports it directly for the client-side "Export Svelte" download.
- `scripts/present.mjs` imports it natively — Node ≥ 22.18 runs TypeScript via type stripping. To make this possible, `codegen.ts` imports `./layouts.ts` relatively (with extension) instead of via the `@/` alias, and `allowImportingTsExtensions` is enabled in `tsconfig.json`.

**No new npm dependencies.** The web-component bundle is produced by the already-installed Vite (programmatic `build()` API), which also compiles the `.svelte` components inside `@animotion/core` — something raw esbuild could not do without extra plugins.

## Architecture Fit

Export is a **read operation** (no mutation), so per ARCHITECTURE.md:

- No Action class — the controller reads via `PresentationReadModel::findForEditor()`
- `PresentationContent::fromArray($data['content'])` reconstructs the domain VO
- Authorization via route-bound `PresentationModel` + existing `PresentationPolicy`

The `app/Presentation/` namespace (distinct from `app/Domain/Presentation/`) hosts the Presenter port and implementations. It may use Laravel freely (it is infrastructure-adjacent: processes, paths).

## Components

| File | Purpose |
| --- | --- |
| `app/Presentation/Contracts/Presenter.php` | Port: `present(PresentationContent $content, string $name): PresenterOutput` |
| `app/Presentation/PresenterOutput.php` | Readonly DTO: `content`, `mimeType`, `filename` |
| `app/Presentation/ExportFormat.php` | Backed enum (`svelte`, `web-component`) with `mimeType()` / `extension()` |
| `app/Presentation/Presenters/NodePresenter.php` | Single implementation for both formats — pipes `{format, content}` JSON into `scripts/present.mjs` via `Symfony\Component\Process` |
| `app/Presentation/PresenterFactory.php` | `make(ExportFormat): Presenter` match expression |
| `app/Http/Requests/Presentations/ExportPresentationRequest.php` | Validates `format` (`Rule::enum`), exposes `exportFormat()` |
| `app/Http/Controllers/Presentations/ExportPresentationController.php` | Thin: authorize → ReadModel → Factory → `streamDownload()` |
| `scripts/present.mjs` | Node subprocess: stdin JSON → codegen.ts → (optionally) Vite custom-element build → stdout |
| Route | `GET /{current_team}/presentations/{presentation}/export?format=…` (`presentations.export`) |

## The Node Script

`scripts/present.mjs` reads `{ format, content }` from stdin and writes the artifact to stdout (errors → stderr, non-zero exit):

- **`svelte`**: runs `generatePresentationSvelte(content)` and prints the source.
- **`web-component`**: prepends `<svelte:options customElement="lecturn-presentation" />` to the generated source, writes it to a temp dir under `node_modules/.cache/`, and runs a programmatic Vite lib build (IIFE, `write: false`, `cssCodeSplit: false`). Extracted CSS is inlined via a small runtime `<style>` injector so the output is a single file. The `$app/environment` shim alias from `vite.config.ts` is replicated for Animotion's Transition component.

The whole web-component build takes **~1 second** (rolldown-powered Vite), so it runs synchronously in the request — no queue needed. Output is ~10 MB because Animotion's `Code` component pulls in the full Shiki highlighter; trimming that is a future optimization.

## Frontend

- `EditorToolbar.svelte`: "Export Web Component" button with a pending state (`Exporting…`).
- `Editor.svelte`: `exportWebComponent()` fetches the export route (Wayfinder's `exportMethod` — Wayfinder renames the reserved word `export`) with `?format=web-component`, then triggers a browser download via `downloadBlob()` (new helper in `download.ts`; `downloadFile()` now delegates to it).
- Raw `fetch` is acceptable here per ARCHITECTURE.md: the endpoint returns non-page data.

## Deployment Note

The export route requires `node` on the server and installed `node_modules` (including devDependencies — Vite and the Svelte plugin are dev deps). If production installs with `--omit=dev`, either move `vite`, `@sveltejs/vite-plugin-svelte`, `svelte`, and `@animotion/core` to `dependencies` or keep dev deps installed on the web server.

## CDN-Style Embed Endpoint

`GET /embed/presentations/{embed_token}.js` (`presentations.embed`) serves the web-component build directly for `<script src>` embedding on external sites:

```html
<script src="https://lecturn.example/embed/presentations/{token}.js"></script>
<lecturn-deck-{first-8-of-token}
    style="display: block; width: 100%; aspect-ratio: 16 / 9"
></lecturn-deck-{first-8-of-token}>
```

Two embed-specific compile details (both handled by the "Copy Embed" snippet):

- The element compiles with **`shadow: 'none'`** (light DOM). Animotion's `Presentation` initializes Reveal.js via `document.querySelector('.reveal')`, which cannot see into a shadow root — and the reveal/theme CSS injected into `<head>` couldn't style it either. Light DOM makes both work; the tradeoff is no style encapsulation from the host page.
- The element **must be block-level with a real height** (Reveal sizes to 100% of its container); custom elements default to `display: inline` with zero height.

**Access model** — the route is public (external pages can't send session cookies) and keyed by `embed_token`, a random 32-char string on the presentations table (generated in `PresentationModel::booted()` on create; backfilled by migration). The URL is a capability: private until shared, revocable by rotating the token. Throttled at 60 req/min.

**Caching & regeneration** (`app/Presentation/EmbedCache.php`):

- **Lazy generation**: first request materializes `storage/app/embeds/{token}.js` (write-to-temp + rename, so concurrent readers never see a partial file); later requests serve from disk with `Cache-Control: public, max-age=300`.
- **Save refresh**: `UpdatePresentation` dispatches `PresentationContentReplaced` (only when content changed, not on rename); the `RefreshPresentationEmbed` listener regenerates the file **only if it already exists** — decks nobody embeds never pay the build cost. The listener runs synchronously (~1s added to save); make it `ShouldQueue` once a queue worker exists.

**Per-presentation custom element tag** — embeds use `lecturn-deck-{lowercased first 8 chars of token}` instead of the shared `lecturn-presentation` tag, so two decks on one page don't collide on `customElements.define()`. The tag is passed to `scripts/present.mjs` via the optional `tag` field (plumbed through `NodePresenter` / `PresenterFactory::make($format, $customElementTag)`).

**View note** — `embed_token` was added to `presentations_view` (the `.sql` file), and the add-column migration is deliberately timestamped *before* the view-creation migration so fresh databases (tests) replay in a valid order.

## Adding More Export Formats Later (e.g. PDF)

1. Add `Pdf = 'pdf'` to `ExportFormat` with its mime/extension
2. Implement a `PdfPresenter` (likely: reuse the Node script to produce HTML, then Playwright → PDF)
3. Add the case to `PresenterFactory::make()`
4. Add a toolbar button

Nothing else changes — the port is open.

## Verification

- `php artisan test --compact tests/Feature/Presentations/ExportPresentationTest.php` — both formats end-to-end (real Node subprocess), invalid format 422, cross-team 404
- `php artisan test --compact tests/Feature/Presentations/EmbedPresentationTest.php` — guest access, lazy generation, cached serving, save-refresh (only when file exists), unknown token 404, token generation
- Manual: editor toolbar → "Export Web Component" → downloaded `.js` defines `<lecturn-presentation>` when loaded via `<script src>`
