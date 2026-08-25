import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Dev server port is fixed (not auto-incremented on conflict) because
// storage/frontend/hot — read by Spinx\Templating\Vite — is written with
// this exact URL by `spinx serve`. If Vite silently picked a different
// port on conflict, the @vite directive would point the browser at the
// wrong place with no obvious error.
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
  },
  build: {
    outDir: '../public/build',
    manifest: true,
    rollupOptions: {
      input: 'src/main.js',
    },
  },
})
