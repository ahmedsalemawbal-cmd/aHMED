import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// `base: './'` عن قصد: المخرجات مسارات نسبيّة، فيعمل الموقع من جذر
// النطاق ومن مجلّد فرعي معًا — وهو الفرق بين رفعٍ ينجح وشاشةٍ بيضاء.
export default defineConfig({
  plugins: [react()],
  base: './',
  server: { port: 5173, host: true },
  build: { outDir: 'dist', sourcemap: false },
});
