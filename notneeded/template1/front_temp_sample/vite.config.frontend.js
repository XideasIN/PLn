import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Frontend (Public Interface) - Port 3000
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