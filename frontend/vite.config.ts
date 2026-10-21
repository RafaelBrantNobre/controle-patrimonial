import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Porta fixa, para o endereço do README valer em qualquer máquina.
    port: 5173,
    strictPort: true,
  },
})
