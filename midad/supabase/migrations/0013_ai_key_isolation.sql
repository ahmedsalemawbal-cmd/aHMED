-- ══ عزلُ المفتاح — إصلاحان كشفهما الفحص ══
--
-- ① السياساتُ تُجمع بـ«أو» لا بـ«و».
--
-- كُتبت سياسةُ قراءةٍ تستثني `ai_secret`، وبقيت `settings_admin` القديمة
-- وهي `for all using (app.is_admin())` — فظلّت تبيح القراءة. والسياستان
-- تُجمعان، فما منعته الأولى أباحته الثانية.
--
--     لا تُقيّد سياسةٌ ما أباحته أخرى.
--
-- فتُفصل الكتابة عن القراءة: للمالك أن يكتب كلّ شيءٍ ويقرأ كلّ شيءٍ إلّا
-- السرّ. والخادم وحده (service_role) يتجاوز الحماية كلَّها فيقرؤه.

drop policy if exists settings_admin on public.platform_settings;

create policy settings_write_insert on public.platform_settings
  for insert with check (app.is_admin());
create policy settings_write_update on public.platform_settings
  for update using (app.is_admin()) with check (app.is_admin());
create policy settings_write_delete on public.platform_settings
  for delete using (app.is_admin());

drop policy if exists settings_public_read on public.platform_settings;
create policy settings_public_read on public.platform_settings for select using (
  key = any (array['general', 'payment_public', 'trial'])
  or (app.is_admin() and key <> 'ai_secret')
);

-- ② والدمج لا الاستبدال.
--
-- في `ai` إعداداتٌ قائمة (`enabled`, `monthly_cap_calls`) وكتابةُ كائنٍ
-- جديدٍ مكانها تمحو ما لا تعرفه الدالّة. والدمج يُبقيه.
--
--     ما لا تعرفه لا تمحُه.

create or replace function public.set_ai_key(p_provider text, p_model text, p_key text)
returns jsonb language plpgsql security definer set search_path to 'public', 'app' as $$
declare hint text; patch jsonb;
begin
  if not app.is_admin() then raise exception 'للمالك وحده'; end if;
  if p_provider not in ('anthropic', 'openai') then
    raise exception 'مزوّدٌ غير معروف: %', p_provider;
  end if;

  if p_key is null or length(trim(p_key)) = 0 then
    delete from public.platform_settings where key = 'ai_secret';
    patch := jsonb_build_object('provider', p_provider, 'model', p_model,
                                'configured', false, 'hint', '');
  else
    -- آخرُ أربعةِ أحرفٍ وحدها: تكفي ليُميّز المالك أيَّ مفتاحٍ وضع، ولا
    -- تكفي أحدًا ليستعمله.
    hint := right(trim(p_key), 4);
    insert into public.platform_settings (key, value)
    values ('ai_secret', jsonb_build_object('api_key', trim(p_key)))
    on conflict (key) do update set value = excluded.value, updated_at = now();
    patch := jsonb_build_object('provider', p_provider, 'model', p_model,
                                'configured', true, 'hint', hint);
  end if;

  insert into public.platform_settings (key, value)
  values ('ai', patch)
  on conflict (key) do update
    set value = public.platform_settings.value || patch, updated_at = now();

  return patch;
end $$;

revoke all on function public.set_ai_key(text, text, text) from public;
grant execute on function public.set_ai_key(text, text, text) to authenticated;
