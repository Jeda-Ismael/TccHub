import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// No Docker, API_PROXY_TARGET aponta para o container da API (http://php)
// e as chamadas a /api passam pelo Vite, sem CORS nem certificado.
const apiProxyTarget = process.env.API_PROXY_TARGET;

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: apiProxyTarget ? { '/api': { target: apiProxyTarget, changeOrigin: true } } : undefined,
  },
});
