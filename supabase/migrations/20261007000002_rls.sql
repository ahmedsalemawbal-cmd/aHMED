-- ميداني · RLS
-- كل جدول: owner_id = auth.uid().

do $$
declare t text;
begin
  foreach t in array array['profiles', 'activity_types', 'services', 'leads', 'visits', 'visit_media',
                           'assessments', 'lead_services', 'message_templates', 'messages', 'tasks',
                           'stage_history', 'quotes']
  loop
    execute format('alter table public.%I enable row level security', t);
    execute format('create policy %I on public.%I for select to authenticated using (owner_id = (select auth.uid()))',
                   t || '_select_own', t);
    if t not in ('stage_history', 'profiles') then
      execute format('create policy %I on public.%I for insert to authenticated with check (owner_id = (select auth.uid()))',
                     t || '_insert_own', t);
    end if;
    if t <> 'stage_history' then
      execute format('create policy %I on public.%I for update to authenticated using (owner_id = (select auth.uid())) with check (owner_id = (select auth.uid()))',
                     t || '_update_own', t);
    end if;
    if t not in ('stage_history', 'profiles') then
      execute format('create policy %I on public.%I for delete to authenticated using (owner_id = (select auth.uid()))',
                     t || '_delete_own', t);
    end if;
  end loop;
end;
$$;

-- anon بلا سياسات، فلا يرى أي صف.
-- stage_history بلا سياسة كتابة: يكتبه trigger فقط.
-- profiles بلا سياسة إنشاء أو حذف: يُنشأ تلقائياً عند أول دخول ولا يُحذف من الواجهة.

-- ---------------------------------------------------------------------------
-- Storage: bucket خاص لصور وفيديو الزيارات، المسار {owner_id}/{visit_id}/{file}
-- ---------------------------------------------------------------------------
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('visit-media', 'visit-media', false, 52428800,
        array['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'video/mp4', 'video/quicktime', 'video/webm', 'audio/webm', 'audio/mp4', 'audio/mpeg'])
on conflict (id) do nothing;

create policy visit_media_objects_select_own on storage.objects
  for select to authenticated
  using (bucket_id = 'visit-media' and (storage.foldername(name))[1] = (select auth.uid())::text);

create policy visit_media_objects_insert_own on storage.objects
  for insert to authenticated
  with check (bucket_id = 'visit-media' and (storage.foldername(name))[1] = (select auth.uid())::text);

create policy visit_media_objects_update_own on storage.objects
  for update to authenticated
  using (bucket_id = 'visit-media' and (storage.foldername(name))[1] = (select auth.uid())::text)
  with check (bucket_id = 'visit-media' and (storage.foldername(name))[1] = (select auth.uid())::text);

create policy visit_media_objects_delete_own on storage.objects
  for delete to authenticated
  using (bucket_id = 'visit-media' and (storage.foldername(name))[1] = (select auth.uid())::text);
