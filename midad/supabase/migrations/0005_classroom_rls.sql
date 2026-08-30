-- ============================================================
-- 0005 — سياسات الإدارة الصفّية
-- العزل بالمشترك، والرصد يكتبه معلّم الحصّة أو صاحب الحساب وحدهما.
-- ============================================================

alter table public.classes    enable row level security;
alter table public.students   enable row level security;
alter table public.periods    enable row level security;
alter table public.attendance enable row level security;

drop policy if exists classes_read on public.classes;
create policy classes_read on public.classes for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists classes_write on public.classes;
create policy classes_write on public.classes for insert
  with check (subscriber_id = app.subscriber_id() and app.can_write());
drop policy if exists classes_update on public.classes;
create policy classes_update on public.classes for update
  using (subscriber_id = app.subscriber_id() and app.can_write())
  with check (subscriber_id = app.subscriber_id());
drop policy if exists classes_delete on public.classes;
create policy classes_delete on public.classes for delete
  using (subscriber_id = app.subscriber_id() and app.is_owner());
drop policy if exists classes_admin on public.classes;
create policy classes_admin on public.classes for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists students_read on public.students;
create policy students_read on public.students for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists students_write on public.students;
create policy students_write on public.students for insert
  with check (subscriber_id = app.subscriber_id() and app.can_write());
drop policy if exists students_update on public.students;
create policy students_update on public.students for update
  using (subscriber_id = app.subscriber_id() and app.can_write())
  with check (subscriber_id = app.subscriber_id());
drop policy if exists students_delete on public.students;
create policy students_delete on public.students for delete
  using (subscriber_id = app.subscriber_id() and app.is_owner());
drop policy if exists students_admin on public.students;
create policy students_admin on public.students for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists periods_read on public.periods;
create policy periods_read on public.periods for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists periods_write on public.periods;
create policy periods_write on public.periods for insert
  with check (subscriber_id = app.subscriber_id() and app.can_write());
drop policy if exists periods_update on public.periods;
create policy periods_update on public.periods for update
  using (subscriber_id = app.subscriber_id() and app.can_write())
  with check (subscriber_id = app.subscriber_id());
drop policy if exists periods_delete on public.periods;
create policy periods_delete on public.periods for delete
  using (subscriber_id = app.subscriber_id() and (app.is_owner() or teacher_id = auth.uid()));
drop policy if exists periods_admin on public.periods;
create policy periods_admin on public.periods for all
  using (app.is_admin()) with check (app.is_admin());

drop policy if exists attendance_read on public.attendance;
create policy attendance_read on public.attendance for select
  using (subscriber_id = app.subscriber_id() or app.is_admin());
drop policy if exists attendance_write on public.attendance;
create policy attendance_write on public.attendance for insert
  with check (
    subscriber_id = app.subscriber_id() and app.can_write()
    and exists (
      select 1 from public.periods p
      where p.id = period_id and p.subscriber_id = app.subscriber_id()
        and (p.teacher_id = auth.uid() or app.is_owner())
    )
  );
drop policy if exists attendance_update on public.attendance;
create policy attendance_update on public.attendance for update
  using (
    subscriber_id = app.subscriber_id() and app.can_write()
    and exists (
      select 1 from public.periods p
      where p.id = period_id and (p.teacher_id = auth.uid() or app.is_owner())
    )
  )
  with check (subscriber_id = app.subscriber_id());
drop policy if exists attendance_delete on public.attendance;
create policy attendance_delete on public.attendance for delete
  using (subscriber_id = app.subscriber_id() and app.is_owner());
drop policy if exists attendance_admin on public.attendance;
create policy attendance_admin on public.attendance for all
  using (app.is_admin()) with check (app.is_admin());
