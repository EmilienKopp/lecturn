# Lecturn — Requirements Document

> A web-native presentation platform. Author slides in the browser, present from your account, and ship to your own website as a self-contained embeddable component.

---

## 1. Overview

Lecturn is a browser-based slide authoring and hosting platform. Users compose presentations visually through a constrained layout system, present them directly from lecturn.io, and optionally export or embed them anywhere on the web — including custom websites, Webflow, Framer, or plain HTML pages.

The Animotion/Svelte rendering engine powers the output but is an invisible implementation detail. Users never write code.

The core value proposition is **ownership and portability**: your presentations live where you want them, look like the web, and don't require a Google or Microsoft account to exist.

---

## 2. Goals

- Author presentations in a good-enough visual editor (not competing with Google Slides on features — competing on output quality and ownership)
- MINIMALISM is a design principle: no unnecessary UI, no unnecessary features, no unnecessary complexity
- The slides you design are also meant to be MINIMALISTIC (no giant walls of text or images) -> that's part of the value proposition: A tool that does minimalism well, not a tool that does everything poorly
- Host and present directly from lecturn.io with no export required
- Export as a self-contained embeddable web component (script tag, works anywhere)
- Store all state as portable JSON (import/export at any time)
- Ship compiled output via CDN — no iframe, no external dependency at embed time

---

## 3. Non-Goals

- Not a Keynote/PowerPoint feature-for-feature replacement
- No freeform drag-and-drop positioning (v1)
- No real-time collaborative editing (v1)
- No video/audio embedding (v1)
- Not targeting non-technical users — primary audience is developers, technical educators, and dev-adjacent creators
- No "power user" feature additions that compromise minimalism as a design principle

---

## 4. Product Tiers

| Tier       | Description                                                                     |
| ---------- | ------------------------------------------------------------------------------- |
| **Free**   | Author and present from lecturn.io. Presentations live at `lecturn.io/p/{slug}` |
| **Pro**    | Embed script export, custom slug, presentations hosted on your own domain       |
| **Studio** | Multiple presentations, client delivery, white-label embed (agency use)         |

The free tier is a fully functional presenting tool — the upgrade reason is portability, which is philosophically aligned with why the target user chose Lecturn over Google Slides in the first place.

---

## 5. Editor UI

### 5.1 Canvas

- Fixed 16:9 aspect ratio canvas, scaled to fit the viewport
- Canvas shows one slide at a time
- Slide navigator panel on the left (ordered list or thumbnails)
- Sidebar on the right for block-level style controls (font, size, color, etc.)

### 5.2 Layout System

Each slide picks one layout. Layouts define named slots rendered as outlined zones in the editor. No absolute positioning — layouts own all sizing and placement.

| ID                | Description                                      |
| ----------------- | ------------------------------------------------ |
| `full`            | Single full-bleed slot                           |
| `center`          | Vertically and horizontally centered single slot |
| `top-main`        | Header + main content                            |
| `top-main-footer` | Header + main content + footer                   |
| `left-right`      | Two equal columns                                |
| `left-wide-right` | Narrow left (1/3) + wide right (2/3)             |
| `grid-2x2`        | Four equal cells                                 |
| `grid-2x3`        | Six equal cells                                  |

Layouts are implemented as CSS Grid/Flex.

### 5.3 Blocks

Blocks are placed into layout slots. Each slot can contain one or more blocks stacked vertically.

| Type    | Implementation                                                             |
| ------- | -------------------------------------------------------------------------- |
| `text`  | `contenteditable="plaintext-only"` with toolbar for font/size/weight/color |
| `code`  | CodeMirror 6 with language selector and theme                              |
| `image` | File upload → stored as base64 or CDN asset reference                      |

### 5.4 Transition Sequencing

- Any block can be assigned a transition step (right-click → "Add transition")
- Transitions are ordered by a numeric index (1, 2, 3…) per slide
- Blocks with a transition get a numbered badge overlay in the editor
- Order can be reassigned via the right-click context menu
- Blocks without a transition are always visible on the slide
- Blocks with a transition are revealed in order during presentation

---

## 6. Presenting

- Presentations are hosted at `lecturn.io/p/{slug}` on the free tier
- Fullscreen presentation mode with keyboard navigation (arrow keys, spacebar)
- Transition steps advance on keypress, matching Animotion's default behaviour
- Shareable link — no account required to view

---

## 7. JSON Data Model

The canonical source of truth. All editor state is serialised to and from this format.

```json
{
    "version": "1.0",
    "meta": {
        "title": "My Presentation",
        "slug": "my-presentation"
    },
    "slides": [
        {
            "id": "slide-1",
            "layout": "left-right",
            "background": "#0f0f0f",
            "slots": {
                "left": [
                    {
                        "id": "block-1",
                        "type": "text",
                        "content": "Hello world",
                        "style": {
                            "fontSize": "2.5rem",
                            "fontWeight": "bold",
                            "color": "#ffffff"
                        },
                        "transition": null
                    }
                ],
                "right": [
                    {
                        "id": "block-2",
                        "type": "code",
                        "lang": "php",
                        "content": "echo 'hello';",
                        "transition": { "order": 1 }
                    },
                    {
                        "id": "block-3",
                        "type": "image",
                        "src": "/assets/photo.jpg",
                        "alt": "A photo",
                        "transition": { "order": 2 }
                    }
                ]
            }
        }
    ]
}
```

---

## 8. Compilation Pipeline

Lecturn's backend compiles JSON into a Svelte component, then builds it into a self-contained web component bundle using the Svelte compiler's custom element output.

```
Lecturn JSON
  → Svelte codegen (server-side)
  → Svelte compiler (customElement: true)
  → Self-contained JS bundle
  → CDN-hosted at lecturn.io/embed/{id}.js
```

### 8.1 Embed Output

```html
<!-- Drop anywhere: Webflow, Framer, plain HTML, WordPress, etc. -->
<script src="https://lecturn.io/embed/abc123.js"></script>
<lecturn-presentation id="abc123" />
```

- No iframe — a real web component
- Fully self-contained: styles, fonts, and logic bundled into the JS file
- Works in any HTML context with no build step required on the consumer's side

### 8.2 Svelte Intermediate (Animotion)

The intermediate Svelte representation uses Animotion components:

```svelte
<script>
    import { Presentation, Slide, Transition, Code } from '@animotion/core';
</script>

<Presentation>
    <Slide class="layout-left-right" style="background: #0f0f0f">
        <div class="slot-left">
            <p style="font-size: 2.5rem; font-weight: bold; color: #ffffff;">
                Hello world
            </p>
        </div>
        <div class="slot-right">
            <Transition>
                <Code lang="php">echo 'hello';</Code>
            </Transition>
            <Transition>
                <img src="/assets/photo.jpg" alt="A photo" />
            </Transition>
        </div>
    </Slide>
</Presentation>
```

### 8.3 Codegen Rules

- Blocks with `transition: null` render directly inside their slot div
- Blocks with a transition are sorted by `transition.order` and wrapped in `<Transition>`
- Layout slot divs use class names matching the slot name (`slot-left`, `slot-right`, etc.)
- Scoped styles for layout grids are compiled into the bundle

---

## 9. Persistence & Storage

| Concern         | Approach                                          |
| --------------- | ------------------------------------------------- |
| Working state   | `localStorage` during editing                     |
| Cloud save      | Server-side per user account (authenticated)      |
| JSON export     | Download `.json` at any time                      |
| JSON import     | Resume editing from any `.json` file              |
| Compiled bundle | Generated server-side on publish, served from CDN |

---

## 10. Tech Stack

| Concern              | Choice                                              |
| -------------------- | --------------------------------------------------- |
| Editor frontend      | Svelte 5                                            |
| Rich text input      | `contenteditable="plaintext-only"`                  |
| Code editor          | CodeMirror 6                                        |
| Styling              | Tailwind CSS                                        |
| Codegen              | Plain JS template strings                           |
| Compiler             | Svelte compiler (`customElement: true`)             |
| Presentation runtime | Animotion (`@animotion/core`)                       |
| Build tool           | Vite                                                |
| Backend              | TBD (Laravel is a natural fit given author's stack) |
| Asset storage        | TBD (S3-compatible)                                 |
| Bundle CDN           | TBD (Cloudflare R2 / CloudFront)                    |

---

## 11. Future Considerations (Post-v1)

- CLI: `lecturn build presentation.json` for local compile without the editor
- Freeform "canvas" layout mode with absolute positioning
- Custom layout builder
- Theme system / design tokens
- Per-slide background images or videos
- Password-protected presentations
- Analytics (view counts, slide drop-off)
- Exportable PDF / static HTML snapshot
- **[v2] Svelte Flow transition editor**: a node graph view where slides and blocks are nodes, and edges define transition sequence and animation type. Replaces/supplements the right-click order assignment with a visual wiring interface. The current JSON transition model (order, future animation) is designed to support this without schema changes.
