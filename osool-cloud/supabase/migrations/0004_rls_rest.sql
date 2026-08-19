-- ═══════════════════════════════════════════════════════════════════
--  تكملة سياسات الأمان — الجداول الباقية
--
--  بعد 0003 بقي ثلاثة وثلاثون جدولًا بلا سياسة واحدة. وهي مقفلة —
--  فالمنع هو الأصل — لكن جدولًا مقفلًا يعني ميزةً معطّلة لا ميزةً آمنة.
--
--  ولا يُفتح جدول لأن ميزته لا تعمل: كل صفّ هنا سُئل عنه سؤالان —
--  من يقرأ؟ ومن يكتب؟ — قبل أن يُكتب.
-- ═══════════════════════════════════════════════════════════════════

do $$
declare
    m record;
    map constant text[][] := array[
        -- [الجدول, صلاحية القراءة, صلاحية الكتابة]
        ['academic_years',   'sch_view_students',    'sch_manage_settings'],
        ['class_subjects',   'sch_view_students',    'sch_manage_subjects'],
        ['assignments',      'sch_view_students',    'sch_manage_subjects'],
        ['installments',     'sch_manage_finance',   'sch_manage_finance'],
        ['payroll_runs',     'sch_manage_hr',        'sch_manage_hr'],
        ['payslips',         'sch_manage_hr',        'sch_manage_hr'],
        ['leaves',           'sch_manage_hr',        'sch_decide_leaves'],
        ['books',            'sch_view_students',    'sch_manage_content'],
        ['book_loans',       'sch_view_students',    'sch_manage_content'],
        ['inventory',        'sch_manage_assets',    'sch_manage_assets'],
        ['stock_moves',      'sch_manage_assets',    'sch_manage_assets'],
        ['maintenance',      'sch_manage_assets',    'sch_manage_assets'],
        ['deliveries',       'sch_manage_services',  'sch_manage_services'],
        ['student_docs',     'sch_view_students',    'sch_manage_docs'],
        ['substitutions',    'sch_view_students',    'sch_manage_deputy'],
        ['flow_marks',       'sch_view_students',    'sch_view_students'],
        ['watchlist',        'sch_view_insights',    'sch_view_insights'],
        ['year_summary',     'sch_view_students',    'sch_handle_notes'],
        ['route_stops',      'sch_view_students',    'sch_manage_transport'],
        ['transport_subs',   'sch_view_students',    'sch_manage_transport'],
        ['perm_log',         'sch_manage_staff',     'sch_manage_staff'],
        ['user_perms',       'sch_manage_staff',     'sch_manage_staff'],
        ['user_scope',       'sch_manage_staff',     'sch_manage_staff']
    ];
begin
    for m in
        select map[i][1] as tbl, map[i][2] as r_cap, map[i][3] as w_cap
          from generate_subscripts(map, 1) as i
    loop
        if not exists (select 1 from pg_tables
                        where schemaname = 'public' and tablename = 'sch_' || m.tbl) then
            continue;
        end if;

        execute format('create policy %I on public.%I for select using (public.sch_has(%L))',
            m.tbl || '_read', 'sch_' || m.tbl, m.r_cap);
        execute format('create policy %I on public.%I for insert with check (public.sch_has(%L))',
            m.tbl || '_add', 'sch_' || m.tbl, m.w_cap);
        execute format('create policy %I on public.%I for update using (public.sch_has(%L)) with check (public.sch_has(%L))',
            m.tbl || '_edit', 'sch_' || m.tbl, m.w_cap, m.w_cap);
        execute format('create policy %I on public.%I for delete using (public.sch_has(%L))',
            m.tbl || '_drop', 'sch_' || m.tbl, m.w_cap);
    end loop;
end $$;

-- ── ما يخصّ الأسرة والطالب ──────────────────────────────────────────

-- ▸ تفويض الاستلام: «خالته اليوم فقط». يفوّض من يحقّ له هو الاستلام،
--   ولا يفوّض أحدٌ في طفلٍ ليس له.
create policy deleg_read on public.sch_pickup_delegations
    for select using (public.sch_has('sch_scan_gate')
                   or public.sch_has('sch_view_custody')
                   or student_id in (select public.sch_my_children()));

create policy deleg_grant on public.sch_pickup_delegations
    for insert with check (student_id in (select public.sch_my_pickups()));

create policy deleg_revoke on public.sch_pickup_delegations
    for update using (granted_by = public.sch_uid() or public.sch_has('sch_view_custody'))
        with check (granted_by = public.sch_uid() or public.sch_has('sch_view_custody'));

-- ▸ طلب الإجازة: يقدّمه وليّ الأمر، ويبتّ فيه من يملك القرار
create policy sleaves_read on public.sch_student_leaves
    for select using (public.sch_has('sch_decide_leaves')
                   or public.sch_has('sch_manage_attendance')
                   or student_id in (select public.sch_my_children()));

create policy sleaves_ask on public.sch_student_leaves
    for insert with check (student_id in (select public.sch_my_children()));

create policy sleaves_decide on public.sch_student_leaves
    for update using (public.sch_has('sch_decide_leaves'))
        with check (public.sch_has('sch_decide_leaves'));

-- ▸ الملاحظات: **المعلّم يسجّل ولا يراسل**، ووليّ الأمر لا يراها.
--   من يشاهد لا يقرّر: تُوجَّه للمختصّ، وجهة واحدة تتكلّم مع الأسرة.
create policy notes_read on public.sch_student_notes
    for select using (public.sch_has('sch_handle_notes')
                   or public.sch_has('sch_view_insights')
                   or author_user_id = public.sch_uid());

create policy notes_write on public.sch_student_notes
    for insert with check (public.sch_has('sch_write_notes'));

create policy notes_handle on public.sch_student_notes
    for update using (public.sch_has('sch_handle_notes'))
        with check (public.sch_has('sch_handle_notes'));

-- ▸ جرعات الدواء: العيادة تكتب، ووليّ الأمر يرى ما أُعطي لابنه
create policy doses_staff on public.sch_med_doses
    for all using (public.sch_has('sch_manage_meds'))
        with check (public.sch_has('sch_manage_meds'));

create policy doses_family on public.sch_med_doses
    for select using (med_id in (select id from public.sch_medications));

-- ▸ التعاميم: تصل جمهورها المقصود، ويكتبها من يملك الإرسال.
--   والتصفية بالجمهور تتمّ في الاستعلام لا في السياسة — الجمهور هنا
--   وصفٌ (شعبة/مرحلة/دور) لا قائمة أشخاص تُطابَق بصفّ واحد.
create policy msg_mine on public.sch_messages
    for select using (public.sch_uid() is not null);

create policy msg_send on public.sch_messages
    for insert with check (public.sch_has('sch_send_messages'));

-- ▸ فتح المحتوى التعليمي: أثرُ الطالب على مادته
create policy opens_own on public.sch_content_opens
    for select using (student_id = public.sch_my_student()
                   or student_id in (select public.sch_my_children())
                   or public.sch_has('sch_manage_content'));

create policy opens_log on public.sch_content_opens
    for insert with check (student_id = public.sch_my_student());

-- ▸ أحداث الرحلة وموقع الباص: الأسرة ترى رحلة ابنها، والسائق رحلته
create policy tripev_read on public.sch_trip_events
    for select using (public.sch_has('sch_manage_transport')
                   or public.sch_has('sch_view_custody')
                   or student_id in (select public.sch_my_children()));

create policy tripev_write on public.sch_trip_events
    for insert with check (public.sch_has('sch_drive_trip')
                        or public.sch_has('sch_manage_transport'));

create policy pos_read on public.sch_vehicle_positions
    for select using (public.sch_has('sch_manage_transport')
                   or public.sch_has('sch_view_custody')
                   or trip_id in (select id from public.sch_trips));

create policy pos_write on public.sch_vehicle_positions
    for insert with check (public.sch_has('sch_drive_trip'));

-- ▸ اشتراكات الإشعارات ورموز التجديد: لصاحبها وحده، ولا يقرؤها أحد سواه.
--   ورمز التجديد مفتاحُ حسابٍ لا بيانَ حساب — فحتى المدير لا يراه.
create policy push_own on public.sch_push_subs
    for all using (user_id = public.sch_uid()) with check (user_id = public.sch_uid());

create policy refresh_own on public.sch_refresh_tokens
    for all using (user_id = public.sch_uid()) with check (user_id = public.sch_uid());
