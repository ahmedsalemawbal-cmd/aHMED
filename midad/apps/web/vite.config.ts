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
        /* محرّك التحرير حزمةٌ مستقلّة: من يفتح اللوح أو المكتبة لا يدفع
           ثمن ProseMirror، ويُحمَّل عند فتح مستندٍ فقط. */
        manualChunks(id) {
          /* لا قطعةَ يدويّةً للمحرّر: كانت تجمع تيبتاب وبروزميرور في قطعةٍ
             واحدة، فيدخل منها رمزٌ واحدٌ في مسار البدء فتُسحب القطعةُ كلُّها
             (٤٥٢ك) في أوّل فتحة — وفي صفحةٍ لا محرّر فيها.
             والمُجمّع يقسم على حدود الاستيراد المؤجَّل وحده أدقَّ منّا. */
          if (id.includes('node_modules/@supabase')) return 'supabase'
          if (id.includes('node_modules/react-router')) return 'router'
        },
      },
    },
  },
})
