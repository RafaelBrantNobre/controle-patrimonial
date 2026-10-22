import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // Porta fixa, para o endereço do README valer em qualquer máquina.
    port: 5173,
    strictPort: true,
  },
})
