import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Identical contract to frontend/vite.config.js (the Vue default) — same
// fixed port convention, same build.outDir, same manifest output. This
// is what genuinely swappable means: Spinx\Templating\Vite doesn't care
// which framework produced the manifest, only that one exists at
// public/build/manifest.json with a src/main.{js,jsx} entry.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
  },
  build: {
    outDir: '../../public/build',
    manifest: true,
    rollupOptions: {
      input: 'src/main.jsx',
    },
  },
})
