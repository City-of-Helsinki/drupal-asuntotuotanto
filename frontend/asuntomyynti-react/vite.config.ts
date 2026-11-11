/// <reference types="vitest" />
import react from '@vitejs/plugin-react-swc';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [react()],
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
    proxy: { '/fi': 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi' },
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
