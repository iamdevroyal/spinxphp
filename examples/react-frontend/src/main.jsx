import { createRoot } from 'react-dom/client'
import { createElement } from 'react'

// Same contract as frontend/src/main.js (the Vue default): scan for
// [data-spinx-island], look up a matching component by filename, mount
// it with data-spinx-props as props. Nothing about @island or the
// backend's DirectiveCompiler changed to make this work — the hydration
// hook is plain HTML/JSON, not a Vue-specific format. Swapping frontend/
// for this directory (and adjusting spinx.json's "frontend" key) is the
// entire migration cost.
const islandModules = import.meta.glob('./islands/*.jsx', { eager: true })

const islands = {}
for (const path in islandModules) {
  const name = path.split('/').pop().replace('.jsx', '')
  islands[name] = islandModules[path].default
}

function hydrateIslands() {
  document.querySelectorAll('[data-spinx-island]').forEach((el) => {
    const name = el.getAttribute('data-spinx-island')
    const Component = islands[name]

    if (!Component) {
      console.warn(
        `[Spinx] No island component registered for "${name}". ` +
        `Add examples/react-frontend/src/islands/${name}.jsx.`
      )
      return
    }

    let props = {}
    const rawProps = el.getAttribute('data-spinx-props')
    if (rawProps) {
      try {
        props = JSON.parse(rawProps)
      } catch (err) {
        console.error(`[Spinx] Failed to parse props for island "${name}":`, err)
      }
    }

    createRoot(el).render(createElement(Component, props))
  })
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', hydrateIslands)
} else {
  hydrateIslands()
}
