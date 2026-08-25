# Templating

## Directives

`.spinx.html` templates compile to plain PHP via
`Spinx\Templating\DirectiveCompiler`. Full syntax:

| Directive | Compiles to |
|---|---|
| `{{ $expr }}` | Escaped output (`htmlspecialchars`) |
| `{!! $expr !!}` | Raw, unescaped output |
| `{{-- comment --}}` | Stripped entirely |
| `@if(...)` / `@elseif(...)` / `@else` / `@endif` | `<?php if/elseif/else/endif ?>` |
| `@foreach(...)` / `@endforeach` | `<?php foreach/endforeach ?>` |
| `@include('view', [...])` | Renders a partial — data is explicit, never inherited from the parent scope |
| `@island('Name', [...])` | Emits a `data-spinx-island` hydration hook (see below) |
| `@vite` | Dev-mode HMR script tags, or production manifest-based asset tags |

Conditions can nest freely — `@if(($a && $b) || $c)` — and quoted
strings inside directive arguments can safely contain commas or
parentheses. Both are handled by a balanced-paren/quote-aware scanner
rather than a naive regex; see `DirectiveCompiler`'s own docblock and
tests for the details, including one real bug this caught and fixed
during development (a literal `{{ }}` in prose text was corrupting
distant unrelated content — fixed by making the echo regexes unable to
skip past their own closing delimiter).

**Caveat if you write documentation that shows the `{{ }}` or `{!! !!}`
syntax literally** (like this file, before rendering): escape it as
`&#123;&#123; &#125;&#125;` if it ever ends up inside a `.spinx.html`
file, or it will be parsed as an (empty, invalid) directive. This file
is markdown, not `.spinx.html`, so it's safe as written.

Templates are compiled once and cached (`Spinx\Templating\TemplateCache`)
keyed by source mtime — never re-parsed per request on a persistent
process.

## `@island` — the frontend-agnostic mechanism

```html
@island('ExampleIsland', ['message' => $message])
```

compiles to:

```html
<div data-spinx-island="ExampleIsland" data-spinx-props="{&quot;message&quot;:...}"></div>
```

That's it — plain HTML with a `data-spinx-island` attribute and a
JSON-encoded `data-spinx-props` attribute. Nothing about this is
Vue-specific. Any frontend's bootstrap script just needs to scan for
`[data-spinx-island]` elements and mount the matching component. This is
the actual mechanism behind "frontend-agnostic, Vue-first" — not a claim
that's asserted in the spec and left unproven, but one with three working
implementations in this repo.

## Reference implementation 1: Vue (the default)

`frontend/src/main.js` scans for `[data-spinx-island]`, looks up a
matching `.vue` file in `frontend/src/islands/` by name, and mounts it
with `createApp(Component, props).mount(el)`. See `Health/Welcome` at
`/` for the working example.

## Reference implementation 2: React

`examples/react-frontend/` is the same contract, a different framework —
`react-dom/client`'s `createRoot(el).render(createElement(Component, props))`
instead of Vue's `createApp`. This was actually built and verified, not
just described:

```bash
cd examples/react-frontend
npm install   # real install, 63 packages
npm run build # real Vite build — produced a working manifest + hashed bundle
```

To use React instead of Vue in your own project: replace `frontend/`
with a copy of `examples/react-frontend/`, keep the same `data-spinx-island`
contract, and add `.jsx` files to `src/islands/`. (This build never got
as far as wiring a `spinx.json`-driven auto-switch between the two — see
[docs/README.md](README.md#known-gaps) — so today the swap is manual: point
`spinx serve`/`spinx build` at whichever directory you're using.)

## Reference implementation 3: raw HTML (no frontend framework)

The `Todo` module (`/todos`) uses zero `@island` calls — just
`@if`/`@foreach`/`{{ }}` and plain HTML forms with `method="POST"`
submissions. This proves the floor: Spinx works as a complete,
interactive web framework with no JavaScript framework at all. Compare
its `module.php` and `Infrastructure/Http/Views/index.spinx.html`
directly against `Health`'s to see the same directive compiler producing
either style depending on what a given page actually needs.

## `@vite` and the dev/production asset pipeline

In dev mode (`spinx serve`), a marker file at `storage/frontend/hot`
(written by the CLI, containing the Vite dev server's URL) tells `@vite`
to emit script tags pointing directly at Vite for full HMR. In
production, it reads `public/build/.vite/manifest.json` — note the
`.vite/` subdirectory; this is a real detail confirmed by actually
running `vite build` during development (Vite 5 changed this path from
earlier versions, and the framework's own code had assumed the old
location until this was caught).
