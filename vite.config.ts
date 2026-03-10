import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    outDir: 'assets',
    emptyOutDir: false,
    manifest: true,
    rollupOptions: {
      input: {
        main: 'src/main.tsx',
        frontend: 'src/frontend-auth-login/frontend.tsx',
        dashboard: 'src/frontend-dashboard/frontend-dashboard.tsx',
        'live-tournaments': 'src/frontend-live-tournaments/frontend-live-tournaments.tsx',
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) {
            return 'css/[name].css';
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
