/// <reference types="vitest" />
import basicSsl from '@vitejs/plugin-basic-ssl';
import react from '@vitejs/plugin-react-swc';
import { defineConfig } from 'vite';

const backendOrigin = process.env.VITE_BACKEND_ORIGIN || 'https://asuntotuotanto.docker.so';
const drupalOrigin = process.env.VITE_DEV_ORIGIN || 'https://asuntotuotanto.docker.so';

export default defineConfig({
  plugins: [react(), ...(process.env.VITE_HTTPS === 'true' ? [basicSsl()] : [])],
  build: {
    outDir: 'build',
    cssCodeSplit: false,
    rollupOptions: {
      output: {
        format: 'iife',
        name: 'AsuReactApp',
        entryFileNames: 'assets/js/main.[hash].chunk.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) {
            return 'assets/css/main.[hash].css';
          }
          return 'assets/[name].[hash].[ext]';
        },
      },
    },
  },
  server: {
    host: '0.0.0.0',
    cors: { origin: drupalOrigin },
    proxy: {
      '/fi': { target: backendOrigin, changeOrigin: true, secure: false },
      '/sv': { target: backendOrigin, changeOrigin: true, secure: false },
      '/en': { target: backendOrigin, changeOrigin: true, secure: false },
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/setupTests.ts',
  },
  // For CRA variables compatibility. Remove when all variables are migrated from REACT_APP_ to VITE_ prefix.
  define: Object.fromEntries(
    Object.entries(process.env)
      .filter(([key]) => key.startsWith('REACT_APP_'))
      .map(([key, value]) => [`import.meta.env.${key.replace(/^REACT_APP_/, 'VITE_')}`, JSON.stringify(value)])
  ),
});
