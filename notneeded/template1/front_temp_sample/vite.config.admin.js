import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Admin Console (Secured Access) - Port 3020
export default defineConfig({
  plugins: [react()],
  server: {
    fs: {
      strict: false,
      allow: ['..', '..']
    },
    host: 'localhost',
    port: 3020
  },
  base: './',
  resolve: {
    alias: {
      '@': '/src'
    }
  }
})