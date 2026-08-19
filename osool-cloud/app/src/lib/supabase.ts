import { createClient } from '@supabase/supabase-js';

const url = import.meta.env.VITE_SUPABASE_URL;
const key = import.meta.env.VITE_SUPABASE_ANON_KEY;

// الفشل هنا صريحٌ عن قصد: مشروعٌ بلا مفاتيح يعطي شاشةً بيضاء وأخطاءً
// غامضة في الطرفية، فيضيع وقتٌ طويل قبل أن يُكتشف أن السبب ملفّ .env
// لم يُنسخ. الرسالة أوضح من الصمت.
if (!url || !key) {
  throw new Error(
    'مفاتيح Supabase غير موجودة. انسخ app/.env.example باسم .env.local واملأ القيمتين من لوحة Supabase ثم أعد تشغيل الخادم.',
  );
}

export const supabase = createClient(url, key, {
  auth: { persistSession: true, autoRefreshToken: true, detectSessionInUrl: false },
  global: { headers: { 'Accept-Language': 'ar' } },
});
