import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
  plugins: [react({ jsxRuntime: 'classic' })],
  base: './',
  build: {
    outDir: resolve(__dirname, '../assets/react-dist'),
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: resolve(__dirname, 'index.html'),
      external: ['@wordpress/element'],
      output: {
        entryFileNames: 'omnigocrm.js',
        assetFileNames: 'omnigocrm.css',
        format: 'iife',
        name: 'OmniGoCRMApp',
        globals: {
          '@wordpress/element': 'wp.element'
        }
      }
    }
  }
});
