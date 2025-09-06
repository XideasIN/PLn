import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Client Portal (Secured Access) - Port 3010
export default defineConfig({
  plugins: [react()],
  server: {
    fs: {
      strict: false,
      allow: ['..', '..']
    },
    host: 'localhost',
    port: 3010
  },
  base: './',
  resolve: {
    alias: {
      '@': '/src'
    }
  }
})