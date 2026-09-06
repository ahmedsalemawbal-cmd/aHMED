import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

/**
 * ══ إعدادُ البناء ══
 *
 * و`base` هنا `'/'` لا `'./'` كما في مِداد — والفرقُ مقصود:
 * مِداد تطبيقٌ خلف تسجيل دخولٍ يُوجَّه بـ`HashRouter`، فتكفيه المساراتُ
 * النسبيّة. وأصولُ البناء **موقعٌ تجاريٌّ تُهمّه الأرشفة**، فمسارُه حقيقيّ
 * (`/fire-resistant-doors/` لا `/#/…`) — والمسارُ الحقيقيُّ العميقُ يكسر
 * المساراتِ النسبيّة: صفحةٌ في عمقِ مجلّدين تطلب `assets/app.js` فتُصيب
 * `/doors/assets/app.js` ولا شيءَ هناك.
 *
 *     ما يُوجَّه بمسارٍ حقيقيٍّ يُحمَّل بمسارٍ مطلق.
 */
/*
 * والأساسُ من البيئة لا مكتوبًا: الموقعُ الحيُّ على جذر نطاقه (`/`)،
 * والمعاينةُ على GitHub Pages تحت مسارِ المستودع (`/aHMED/`). وأساسٌ
 * واحدٌ مكتوبٌ يجعل إحداهما تعمل والأخرى تطلب أصولَها من موضعٍ لا شيءَ
 * فيه.
 */
export default defineConfig({
  plugins: [react()],
  base: process.env.OSOUL_BASE || '/',
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    sourcemap: false,
    rollupOptions: {
      output: {
        entryFileNames: 'assets/app.[hash].js',
        chunkFileNames: 'assets/[name].[hash].js',
        assetFileNames: 'assets/[name].[hash][extname]',
        /**
         * قطعتان يدويًّا لا أكثر — والباقي يقسّمه رولّاب وحدَه.
         *
         * ودرسُ مِداد المكتوبُ في `vite.config.ts` عندها: قطعةٌ يدويّةٌ لمكتبةٍ
         * ثقيلةٍ تُسحَب **كاملةً** في صفحةٍ لا تستعملها. فلا تُقطَع إلّا ما
         * تستعمله كلُّ صفحةٍ فعلًا.
         */
        manualChunks: {
          supabase: ['@supabase/supabase-js'],
          router: ['react-router-dom'],
        },
      },
    },
  },
  server: { port: 4190, strictPort: true, host: '127.0.0.1' },
  preview: { port: 4191, strictPort: true, host: '127.0.0.1' },
})
