import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: '../media/js',
    emptyOutDir: false,
    rollupOptions: {
      input: './src/main.js',
      output: {
        format: 'iife',
        name: 'AiBoostAdmin',
        entryFileNames: 'admin-vue.js',
        assetFileNames: (info) =>
          info.name && info.name.endsWith('.css') ? 'admin-vue.css' : '[name].[ext]',
      },
    },
  },
})