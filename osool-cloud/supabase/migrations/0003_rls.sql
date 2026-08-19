-- ═══════════════════════════════════════════════════════════════════
--  أمان مستوى الصفّ (RLS)
--
--  في النظام القديم كان الحارس في PHP: `current_user_can()` قبل كل
--  استعلام — ٨٤ موضعًا. ونسيان واحد منها يفتح البيانات كلّها.
--
--  هنا الحارس **في القاعدة نفسها**: لا يمرّ صفّ إلى أحد إلا بسياسة
--  تسمح به، مهما كان الطريق — واجهة، أو تطبيق جوّال، أو استدعاء
--  مباشر لواجهة Supabase. القاعدة تمنع، فلا يبقى الأمان رهن انتباه
--  من يكتب الشاشة التالية.
--
--  ثلاث طبقات:
--   ١) `deny by default` — تُفعَّل RLS على كل جدول، فيصير الأصل المنع.
--   ٢) سياسة عامة تُشتقّ من الصلاحيات لجداول الإدارة.
--   ٣) سياسات خاصة للحسّاس: الأسرة · الصحة · العهدة · سجلّ التدقيق.
-- ═══════════════════════════════════════════════════════════════════

-- ── (١) المنع هو الأصل ──────────────────────────────────────────────
--
-- كل جدول يبدأ مقفلًا. الجدول الذي يُضاف غدًا ولا يُكتب له سياسة
-- يكون مقفلًا لا مفتوحًا — وهذا هو الفرق بين نظام يصمد ونظام يتسرّب.

do $$
declare t record;
begin
    for t in
        select tablename from pg_tables
         where schemaname = 'public' and tablename like 'sch\_%'
    loop
        execute format('alter table public.%I enable row level security', t.tablename);
        execute format('alter table public.%I force row level security', t.tablename);
    end loop;
end $$;

-- ── (٢) الجداول الإدارية: تُقرأ وتُكتب بالصلاحية ────────────────────

do $$
declare
    m record;
    map constant text[][] := array[
        -- [الجدول, صلاحية القراءة, صلاحية الكتابة]
        ['students',            'sch_view_students',   'sch_manage_students'],
        ['classes',             'sch_view_students',   'sch_manage_classes'],
        ['enrollments',         'sch_view_students',   'sch_manage_students'],
        ['guardians',           'sch_view_students',   'sch_manage_guardians'],
        ['guardian_student',    'sch_view_students',   'sch_manage_guardians'],
        ['attendance',          'sch_view_students',   'sch_manage_attendance'],
        ['staff_attendance',    'sch_manage_staff',    'sch_manage_staff'],
        ['subjects',            'sch_view_students',   'sch_manage_subjects'],
        ['timetable',           'sch_view_students',   'sch_build_timetable'],
        ['exams',               'sch_view_students',   'sch_manage_exams'],
        ['exam_results',        'sch_view_students',   'sch_manage_grades'],
        ['homework',            'sch_view_students',   'sch_manage_homework'],
        ['homework_subs',       'sch_view_students',   'sch_manage_homework'],
        ['content',             'sch_view_students',   'sch_manage_content'],
        ['certificates',        'sch_view_students',   'sch_manage_students'],
        ['invoices',            'sch_manage_finance',  'sch_manage_finance'],
        ['payments',            'sch_manage_finance',  'sch_manage_finance'],
        ['fee_plans',           'sch_manage_finance',  'sch_manage_finance'],
        ['accounts',            'sch_manage_accounting','sch_manage_accounting'],
        ['journal_entries',     'sch_manage_accounting','sch_manage_accounting'],
        ['journal_lines',       'sch_manage_accounting','sch_manage_accounting'],
        ['payroll',             'sch_manage_hr',       'sch_manage_hr'],
        ['contracts',           'sch_manage_hr',       'sch_manage_hr'],
        ['staff_leaves',        'sch_manage_hr',       'sch_decide_leaves'],
        ['employees',           'sch_manage_staff',    'sch_manage_staff'],
        ['routes',              'sch_view_students',   'sch_manage_transport'],
        ['stops',               'sch_view_students',   'sch_manage_transport'],
        ['buses',               'sch_view_students',   'sch_manage_transport'],
        ['subscriptions',       'sch_view_students',   'sch_manage_transport'],
        ['assets',              'sch_manage_assets',   'sch_manage_assets'],
        ['inventory_items',     'sch_manage_assets',   'sch_manage_assets'],
        ['visitors',            'sch_manage_services', 'sch_manage_services'],
        ['incidents',           'sch_manage_services', 'sch_manage_services'],
        ['kg_logs',             'sch_view_students',   'sch_manage_kg'],
        ['alerts',              'sch_manage_alerts',   'sch_manage_alerts'],
        ['settings',            'sch_manage_settings', 'sch_manage_settings']
    ];
begin
    for m in
        select map[i][1] as tbl, map[i][2] as r_cap, map[i][3] as w_cap
          from generate_subscripts(map, 1) as i
    loop
        if not exists (select 1 from pg_tables
                        where schemaname='public' and tablename = 'sch_' || m.tbl) then
            continue;   -- الخريطة أوسع من المخطّط عمدًا: تحتمل جداول تُضاف لاحقًا
        end if;

        execute format(
            'create policy %I on public.%I for select using (public.sch_has(%L))',
            m.tbl || '_read', 'sch_' || m.tbl, m.r_cap);

        execute format(
            'create policy %I on public.%I for insert with check (public.sch_has(%L))',
            m.tbl || '_add', 'sch_' || m.tbl, m.w_cap);

        execute format(
            'create policy %I on public.%I for update using (public.sch_has(%L)) with check (public.sch_has(%L))',
            m.tbl || '_edit', 'sch_' || m.tbl, m.w_cap, m.w_cap);

        execute format(
            'create policy %I on public.%I for delete using (public.sch_has(%L))',
            m.tbl || '_drop', 'sch_' || m.tbl, m.w_cap);
    end loop;
end $$;

-- ── (٣) الحسّاس: سياسات مكتوبة بيدها ────────────────────────────────

-- ▸ المستخدم يقرأ صفّه، والإدارة تقرأ الجميع
create policy users_self on public.sch_users
    for select using (auth_id = auth.uid() or public.sch_has('sch_manage_staff'));

create policy users_admin_write on public.sch_users
    for all using (public.sch_has('sch_manage_staff'))
        with check (public.sch_has('sch_manage_staff'));

create policy roles_read on public.sch_roles for select using (true);
create policy caps_read  on public.sch_role_caps for select using (true);

create policy usercaps_read on public.sch_user_caps
    for select using (user_id = public.sch_uid() or public.sch_has('sch_manage_staff'));

create policy usercaps_write on public.sch_user_caps
    for all using (public.sch_has('sch_manage_staff'))
        with check (public.sch_has('sch_manage_staff'));

-- ▸ وليّ الأمر يرى أبناءه — ولا يرى طفلًا واحدًا سواهم
create policy students_family on public.sch_students
    for select using (id in (select public.sch_my_children()));

create policy students_self on public.sch_students
    for select using (user_id = public.sch_uid());

create policy attendance_family on public.sch_attendance
    for select using (student_id in (select public.sch_my_children())
                   or student_id = public.sch_my_student());

create policy invoices_family on public.sch_invoices
    for select using (student_id in (select public.sch_my_children()));

-- الدفعة لا تحمل الطالب — تحمل الفاتورة. فيمرّ الإذن من حيث تمرّ العلاقة.
create policy payments_family on public.sch_payments
    for select using (invoice_id in (select id from public.sch_invoices));

create policy certificates_family on public.sch_certificates
    for select using (student_id in (select public.sch_my_children())
                   or student_id = public.sch_my_student());

create policy kg_family on public.sch_kg_logs
    for select using (student_id in (select public.sch_my_children()));

-- ▸ الطالب يرى ما يخصّه هو
create policy homework_own on public.sch_homework_subs
    for select using (student_id = public.sch_my_student());

create policy homework_own_write on public.sch_homework_subs
    for insert with check (student_id = public.sch_my_student() and public.sch_has('sch_learn'));

create policy scores_own on public.sch_exam_results
    for select using (student_id = public.sch_my_student()
                   or student_id in (select public.sch_my_children()));

-- ▸ الصحّة: العيادة والمدير وحدهما.
--   ووليّ الأمر يرى الحساسية وفصيلة الدم فقط — وذلك بعرضٍ لا بسياسة،
--   لأن السياسة تفتح الصفّ كلّه أو تغلقه، ولا تفتح عمودين منه.
create policy clinic_staff on public.sch_clinic_visits
    for all using (public.sch_has('sch_view_health'))
        with check (public.sch_has('sch_view_health'));

create policy meds_staff on public.sch_medications
    for all using (public.sch_has('sch_manage_meds'))
        with check (public.sch_has('sch_manage_meds'));

create policy health_staff on public.sch_student_health
    for all using (public.sch_has('sch_view_health'))
        with check (public.sch_has('sch_view_health'));

-- والعرض `security definer` عن قصد — وهو الموضع الوحيد في النظام كلّه.
--
-- بـ`security_invoker` يسري بسياسات القارئ، وسياسة وليّ الأمر على جدول
-- الصحة هي المنع، فيعود العرض فارغًا دائمًا: تُكتب الميزة ولا تعمل.
--
-- والمطلوب أدقّ ممّا تستطيعه السياسة أصلًا: السياسة تفتح **الصفّ** أو
-- تغلقه، وهنا نريد فتح **عمودين منه**. فالعرض يتجاوز الجدول ويحمل هو
-- شرط الأسرة بنفسه — ولا يُختار منه إلا العمودان.
create or replace view public.sch_health_for_family
with (security_invoker = false) as
    select student_id, blood_type, allergies
      from public.sch_student_health
     where student_id in (select public.sch_my_children());

comment on view public.sch_health_for_family is
    'ما يراه وليّ الأمر من الملفّ الصحّي: الحساسية وفصيلة الدم — لا تشخيصات ولا أدوية';

-- ولأنه يتجاوز الجدول، يُمنع منه كل من ليس وليّ أمر بحكم شرطه نفسه:
-- من لا أبناء له لا يُطابق شيئًا.
revoke all on public.sch_health_for_family from public;

-- ▸ العهدة: تُقرأ بالصلاحية أو للأسرة، **ولا تُعدَّل ولا تُحذف أبدًا**.
--   الخطأ يُصحَّح بحدث مضادّ لا بمحو — وسجلٌّ يُمحى لا يصلح دليلًا.
create policy custody_read on public.sch_custody_events
    for select using (public.sch_has('sch_view_custody')
                   or student_id in (select public.sch_my_children()));

create policy custody_append on public.sch_custody_events
    for insert with check (public.sch_has('sch_scan_gate')
                        or public.sch_has('sch_manage_attendance'));
-- لا سياسة UPDATE ولا DELETE: غيابهما هو المنع.

-- ▸ نداء الانصراف: ينادي من يحقّ له الاستلام وحده
create policy pickup_call on public.sch_pickup_calls
    for insert with check (student_id in (select public.sch_my_pickups()));

create policy pickup_read on public.sch_pickup_calls
    for select using (public.sch_has('sch_view_custody')
                   or public.sch_has('sch_scan_gate')
                   or caller_id = public.sch_uid());

create policy pickup_close on public.sch_pickup_calls
    for update using (public.sch_has('sch_view_custody') or public.sch_has('sch_scan_gate'))
        with check (public.sch_has('sch_view_custody') or public.sch_has('sch_scan_gate'));

-- ▸ سجلّ التدقيق: يُقرأ بصلاحيته، ويُكتب ولا يُعدَّل ولا يُحذف
create policy audit_read on public.sch_audit_log
    for select using (public.sch_has('sch_view_audit'));

create policy audit_append on public.sch_audit_log
    for insert with check (public.sch_uid() is not null);

-- ▸ الإشعارات: لصاحبها وحده
create policy notif_own on public.sch_notifications
    for select using (user_id = public.sch_uid());

create policy notif_own_edit on public.sch_notifications
    for update using (user_id = public.sch_uid()) with check (user_id = public.sch_uid());

-- ▸ السائق يرى رحلته هو
create policy trips_driver on public.sch_trips
    for select using (public.sch_has('sch_manage_transport') or driver_user_id = public.sch_uid());

create policy trips_driver_edit on public.sch_trips
    for update using (driver_user_id = public.sch_uid() and public.sch_has('sch_drive_trip'))
        with check (driver_user_id = public.sch_uid());
