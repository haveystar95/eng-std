import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// The dev server proxies /admin/api to a running backend2 when VITE_API_BASE is
// left as the same-origin default. Point VITE_API_BASE at a full URL (ngrok or
// http://localhost:8001/admin/api) to hit a real backend instead of the mocks.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5174,
    proxy: {
      '/admin/api': {
        target: 'http://localhost:8001',
        changeOrigin: true,
      },
    },
  },
})
