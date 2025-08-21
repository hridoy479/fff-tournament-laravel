import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
  plugins: [
    laravel({
      input: 'resources/js/app.ts',
      refresh: true,
    }),
    vue({
      include: [/\.vue$/], // Explicitly include .vue files
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  server: {
    hmr: {
      host: 'localhost',
    },
  },
  // Added for debugging
  build: {
    sourcemap: true,
  },
  optimizeDeps: {
    include: [
      'vue',
      '@inertiajs/vue3',
    ],
  },
});