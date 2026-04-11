import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  // Use relative asset URLs so bundles work correctly when served from plugin paths.
  base: './',
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@landing': path.resolve(__dirname, './src/frontend-landing/app'),
    },
  },
  build: {
    outDir: 'assets',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: 'src/main.tsx',
        frontend: 'src/frontend-auth-login/frontend.tsx',
        dashboard: 'src/frontend-dashboard/frontend-dashboard.tsx',
        landing: 'src/frontend-landing/frontend-landing.tsx',
        'landing-shell': 'src/frontend-landing-shell/frontend-landing-shell.tsx',
        'live-tournaments': 'src/frontend-live-tournaments/frontend-live-tournaments.tsx',
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) {
            return 'css/[name]-[hash][extname]';
          }
          return 'assets/[name][extname]';
        },
      },
    },
  },
  server: {
    port: 5173,
    origin: 'http://localhost:5173',
    cors: true,
    hmr: {
      host: 'localhost',
    },
  },
});
