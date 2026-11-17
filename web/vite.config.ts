/// <reference types="vitest/config" />
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
  test: {
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}'],
      // Ponto de entrada (main.tsx) e utilitários de teste não contam
      exclude: ['src/main.tsx', 'src/vite-env.d.ts', 'src/test/**', 'src/**/*.test.{ts,tsx}'],
      reporter: ['text-summary', 'html'],
      reportsDirectory: './coverage',
      thresholds: { lines: 85, statements: 85 },
    },
  },
});
