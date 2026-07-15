# Lecturn — Requirements Document

> A WYSIWYG slide editor that compiles to [Animotion](https://animotion.dev) Svelte presentations.

---

## 1. Overview

Lecturn is a browser-based slide authoring tool. Users compose slides visually through a constrained layout system, and Lecturn exports a valid Animotion-compatible `.svelte` file (or set of files) as output. The editor stores its state as JSON; the Svelte output is a write-only compile target.

---

## 2. Goals

- Author Animotion presentations without writing Svelte by hand
- Keep the editor scope tight: content, layout, and transition sequencing only
- Produce clean, readable Svelte output that a developer can take over if needed
- Persist state as portable JSON (importable/exportable)

---

## 3. Non-Goals

- Not a general-purpose presentation tool (not a Keynote/PowerPoint replacement)
- No freeform drag-and-drop positioning (v1)
- No real-time collaborative editing
- No built-in Animotion runtime / preview (v1 — see §8)

---

## 4. Editor UI

### 4.1 Canvas

- Fixed 16:9 aspect ratio canvas, scaled to fit the viewport
- Canvas represents one slide at a time
- Slide list / navigator panel on the left (thumbnails or ordered list)
- Sidebar on the right for block-level controls (font, size, color, etc.)

### 4.2 Layout System

Each slide picks one layout. Layouts define named slots that the editor renders as outlined drop zones. Supported layouts:

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

Layouts are implemented as CSS Grid/Flex — no absolute positioning.

### 4.3 Blocks

Blocks are placed into layout slots. Each slot can contain one or more blocks stacked vertically. Supported block types:

| Type    | Implementation                                                             |
| ------- | -------------------------------------------------------------------------- |
| `text`  | `contenteditable="plaintext-only"` with toolbar for font/size/weight/color |
| `code`  | CodeMirror 6 with language selector and theme                              |
| `image` | File upload → stored as base64 or `/static/` asset reference               |

### 4.4 Transition Sequencing

- Any block can be assigned a transition (right-click → "Add transition")
- Transitions are ordered by a numeric index (1, 2, 3…) per slide
- Blocks with a transition get a numbered badge overlay in the editor
- Order can be reassigned via the right-click context menu
- Blocks without a transition render directly inside `<Slide>` (always visible)
- Blocks with a transition render wrapped in `<Transition>` in order

---

## 5. JSON Data Model

```json
{
    "version": "1.0",
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

## 6. Svelte Output (Codegen)

Lecturn compiles the JSON into an Animotion-compatible `.svelte` file.

### 6.1 Output Shape

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

### 6.2 Rules

- Blocks with `transition: null` render directly inside their slot div
- Blocks with a transition are sorted by `transition.order` and wrapped in `<Transition>`
- Layout slot divs use class names matching the layout ID (`slot-left`, `slot-right`, etc.)
- A companion `layouts.css` (or Tailwind classes) ships with the output to style the layout grids
- Output is a single `Presentation.svelte` file by default; one file per slide is a future option

---

## 7. Persistence

| Concern       | Approach                                          |
| ------------- | ------------------------------------------------- |
| Working state | `localStorage` during editing                     |
| Save / export | Download as `.json`                               |
| Compile       | "Export Svelte" button → downloads `.svelte` file |
| Import        | Load `.json` to resume editing                    |

---

## 8. Out of Scope for v1 (Future Considerations)

- Live Animotion preview (iframe embed of the compiled output)
- Freeform absolute-position layout ("canvas" mode)
- Custom layout builder
- Multiple themes / design tokens
- Per-slide transition animations (currently Animotion handles this at runtime)
- Cloud save / multi-user
- CLI (`lecturn build presentation.json`) to compile without opening the editor

---

## 9. Tech Stack

| Concern          | Choice                             |
| ---------------- | ---------------------------------- |
| Editor framework | Svelte 5                           |
| Rich text input  | `contenteditable="plaintext-only"` |
| Code editor      | CodeMirror 6                       |
| Styling          | Tailwind CSS                       |
| Codegen          | Plain JS template strings          |
| Build tool       | Vite                               |
| Output target    | Animotion (`@animotion/core`)      |
