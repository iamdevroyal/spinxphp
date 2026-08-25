import { createApp } from 'vue'

// Every *.vue file under src/islands/ is auto-registered by filename via
// import.meta.glob, so a backend @island('Name', ...) call just works as
// soon as a matching frontend/src/islands/Name.vue exists — no manual
// registration step on the frontend side.
const islandModules = import.meta.glob('./islands/*.vue', { eager: true })

const islands = {}
for (const path in islandModules) {
  const name = path.split('/').pop().replace('.vue', '')
  islands[name] = islandModules[path].default
}

function hydrateIslands() {
  document.querySelectorAll('[data-spinx-island]').forEach((el) => {
    const name = el.getAttribute('data-spinx-island')
    const component = islands[name]

    if (!component) {
      console.warn(
        `[Spinx] No island component registered for "${name}". ` +
        `Add frontend/src/islands/${name}.vue.`
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

    createApp(component, props).mount(el)
  })
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', hydrateIslands)
} else {
  hydrateIslands()
}
