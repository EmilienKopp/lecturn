# Backend Presenter Export Infrastructure

## Context

The slide editor currently exports presentations via a purely frontend pipeline: `codegen.ts → downloadFile()`. This document describes the backend Presenter port that adds:

1. **"Export Web Component"** — PHP → Node.js subprocess → `svelte/compiler` (`customElement: true`) → esbuild bundle → self-contained `.js` for embedding in any site/Webflow
2. Foundation for a future **"Export PDF"** (PHP → Node → Playwright)
3. A clean `Presenter` interface so all export types share the same port

The existing frontend "Export Svelte" is unchanged.

---

## Architecture Fit

Export is a **read operation** (no mutation), so:
- No Action class — controller reads directly from `PresentationReadModel`
- Uses `PresentationContent::fromArray($data['content'])` to reconstruct the VO from the ReadModel array
- `PresentationPolicy` already exists — authorize via route-bound `PresentationModel`

The `app/Presentation/` namespace (separate from `app/Domain/Presentation/`) is the home for the Presenter interface and its implementations.

---

## New Files

| File | Purpose |
|------|---------|
| `app/Presentation/PresenterOutput.php` | Readonly DTO: `content`, `mimeType`, `filename` |
| `app/Presentation/ExportFormat.php` | Backed enum: `SvelteSource = 'svelte'`, `WebComponent = 'web-component'` |
| `app/Presentation/Contracts/Presenter.php` | **Update** stub → `present(PresentationContent, string $name): PresenterOutput` |
| `app/Presentation/Presenters/SvelteSourcePresenter.php` | PHP port of `codegen.ts`'s `generatePresentationSvelte()` |
| `app/Presentation/Presenters/WebComponentPresenter.php` | Spawns `node scripts/present.mjs` via `Symfony\Component\Process` |
| `app/Presentation/PresenterFactory.php` | `make(ExportFormat): Presenter` — match expression, no interface (YAGNI) |
| `app/Http/Requests/Presentations/ExportPresentationRequest.php` | Validates `format` via `Rule::enum(ExportFormat::class)` |
| `app/Http/Controllers/Presentations/ExportPresentationController.php` | Thin controller: ReadModel → Factory → `response()->streamDownload()` |
| `scripts/present.mjs` | Node subprocess: stdin JSON → Svelte compile + esbuild bundle → stdout JS |

## Modified Files

| File | Change |
|------|--------|
| `routes/web.php` | Add `GET presentations/{presentation}/export` → `ExportPresentationController` |
| `resources/js/pages/presentations/Editor.svelte` | Add `exportWebComponent()` using `fetch` + blob download |
| `resources/js/components/lecturn/EditorToolbar.svelte` | Add "Export Web Component" button |

---

## Key Implementation Details

### `Presenter` interface update
```php
interface Presenter {
    public function present(PresentationContent $content, string $name): PresenterOutput;
}
```
Both implementations need `$name` for the output filename.

### `SvelteSourcePresenter`
- PHP port of `codegen.ts` — same rendering logic
- Use `SlideLayout::slots()` (already on the domain VO) for slot names
- Private `slugify(string): string` method mirroring the TS utility
- Returns `PresenterOutput('text/plain', '{slug}.svelte')`

### `WebComponentPresenter`
```php
$process = new Process(['node', base_path('scripts/present.mjs')]);
$process->setInput(json_encode(['format' => 'web-component', 'content' => $content->toArray(), 'name' => $name]));
$process->setTimeout(30)->run();
if (!$process->isSuccessful()) throw new \RuntimeException($process->getErrorOutput());
return new PresenterOutput($process->getOutput(), 'application/javascript', "{$slug}.js");
```

### `scripts/present.mjs`
1. Read stdin → parse JSON
2. Generate Svelte source (duplicate of `codegen.ts` logic in plain JS — do NOT import TS directly)
3. `compile(source, { customElement: true, name: 'LecturnPresentation', generate: 'client' })`
4. `esbuild.build({ bundle: true })` to inline `@animotion/core`
5. Write bundle to stdout

`svelte/compiler` is CommonJS; bridge it in `.mjs` with:
```js
import { createRequire } from 'module';
const { compile } = createRequire(import.meta.url)('svelte/compiler');
```

### Controller
```php
public function __invoke(ExportPresentationRequest $request, Team $current_team, PresentationModel $presentation): StreamedResponse
{
    Gate::authorize('view', $presentation);
    $data = $this->presentations->findForEditor($presentation->id);
    $content = PresentationContent::fromArray($data['content']);
    $output = $this->factory->make(ExportFormat::from($request->validated('format')))->present($content, $data['name']);
    return response()->streamDownload(fn() => print($output->content), $output->filename, ['Content-Type' => $output->mimeType]);
}
```

### Frontend download
`fetch` (acceptable per ARCHITECTURE.md — returns non-page data) + `URL.createObjectURL(blob)`. Add a `downloadBlob(filename, blob)` helper to `download.ts`.

---

## Dependency Change Required

**`esbuild` is not installed.** The `scripts/present.mjs` bundling step requires it.

Recommended: `npm install --save-dev esbuild` — pure binary, no transitive deps, ~10 MB.

---

## Implementation Order

Steps 1–9 are pure PHP and testable before touching Node.

1. `PresenterOutput.php`
2. `ExportFormat.php`
3. `Presenter.php` (update interface)
4. `SvelteSourcePresenter.php` + unit test
5. `WebComponentPresenter.php` (PHP side)
6. `PresenterFactory.php`
7. `ExportPresentationRequest.php`
8. `ExportPresentationController.php`
9. `routes/web.php` — add route
10. *(Approval gate)* `npm install --save-dev esbuild`
11. `scripts/present.mjs`
12. `php artisan wayfinder:generate`
13. Frontend: `Editor.svelte` + `EditorToolbar.svelte`
14. Tests: `ExportPresentationTest.php`

---

## Adding More Export Formats Later

To add `PdfPresenter`:
1. Add `Pdf = 'pdf'` to `ExportFormat`
2. Implement `PdfPresenter` (PHP → Node → Playwright)
3. Add a case to `PresenterFactory::make()`
4. Add a button to `EditorToolbar`

No other files change. The port is open.
