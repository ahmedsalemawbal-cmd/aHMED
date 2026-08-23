import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  // مسارات نسبية: تعمل من دالّة الحافّة ومن GitHub Pages معًا
  base: './',
  plugins: [react()],
  build: {
    target: 'es2020',
    assetsInlineLimit: 4096,
    rollupOptions: {
      output: {
        // بصمةٌ في الاسم: تحديثُ النشر لا يترك متصفّحًا على نسخةٍ قديمة
        entryFileNames: 'assets/app.[hash].js',
        chunkFileNames: 'assets/[name].[hash].js',
        assetFileNames: 'assets/[name].[hash][extname]',
        manualChunks: undefined,
      },
    },
  },
})
