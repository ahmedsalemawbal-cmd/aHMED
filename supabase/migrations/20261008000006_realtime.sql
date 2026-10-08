-- ميداني · Realtime: العميل المسجّل من الجوال يظهر فوراً في الداشبورد.
-- Realtime يطبّق RLS نفسها، فلا يصل للمستخدم إلا ما يملكه.
alter publication supabase_realtime add table public.leads, public.tasks, public.messages, public.visits, public.quotes;
