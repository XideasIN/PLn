import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    fs: {
      strict: false,
      allow: ['..', '..']
    },
    host: 'localhost',
    port: 3000
  },
  base: './',
  resolve: {
    alias: {
      '@': '/src'
    }
  }
})
