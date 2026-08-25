import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Custom plugin: Redirects root visits on Vite dev server (localhost:5173)
// to the backend Spinx application (localhost:8080)
function spinxRedirectPlugin() {
  return {
    name: 'spinx-backend-redirect',
    configureServer(server) {
      server.middlewares.use((req, res, next) => {
        if (req.url === '/' || req.url === '/index.html') {
          res.writeHead(302, { Location: 'http://localhost:8080' })
          res.end()
          return
        }
        next()
      })
    },
  }
}

// Dev server port is fixed (not auto-incremented on conflict) because
// storage/frontend/hot — read by Spinx\Templating\Vite — is written with
// this exact URL by `spinx serve`.
export default defineConfig({
  plugins: [vue(), spinxRedirectPlugin()],
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
