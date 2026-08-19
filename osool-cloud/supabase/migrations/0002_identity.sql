-- ═══════════════════════════════════════════════════════════════════
--  الهوية والصلاحيات
--
--  النظام القديم كان يستعمل جدول مستخدمي ووردبريس، وكل الجداول تشير
--  إليه برقم صحيح (`user_id`, `granted_by`, `recorded_by` …).
--
--  وSupabase يعرّف المستخدم بـ uuid في `auth.users`. وتحويل كل عمود
--  يشير لمستخدم إلى uuid يعني تعديل عشرات الجداول ومئات الاستعلامات،
--  **ويكسر ترحيل البيانات القديمة** لأن الأرقام لن تُطابق شيئًا.
--
--  فالجسر هنا: جدول `sch_users` يحتفظ بالرقم الصحيح كما كان، ويحمل
--  عمودًا واحدًا `auth_id` يربطه بحساب Supabase. نقطة التقاء واحدة
--  بدل مئة — والبيانات القديمة تُنقل بأرقامها كما هي.
-- ═══════════════════════════════════════════════════════════════════

create table if not exists public.sch_users (
    id            bigint generated always as identity primary key,
    auth_id       uuid unique references auth.users (id) on delete set null,
    login         text not null unique,
    email         text not null unique,
    display_name  text not null,
    phone         text,
    role          text not null,
    is_active     boolean not null default true,
    created_at    timestamptz not null default now()
);

comment on table  public.sch_users is 'مستخدمو النظام — الجسر بين أرقام النظام القديمة وحسابات Supabase';
comment on column public.sch_users.auth_id is 'حساب Supabase؛ يبقى فارغًا لمن لم يُفعّل دخوله بعد';

create index sch_users_role_idx on public.sch_users (role);

-- ── الأدوار ─────────────────────────────────────────────────────────

create table if not exists public.sch_roles (
    slug   text primary key,
    label  text not null,
    rank   smallint not null default 0     -- للترتيب في الواجهة فقط
);

insert into public.sch_roles (slug, label, rank) values
    ('sch_principal',            'مدير المدرسة',              1),
    ('sch_deputy',               'وكيل الشؤون التعليمية',      2),
    ('sch_supervisor',           'مشرف مرحلة',                3),
    ('sch_counselor',            'أخصائي اجتماعي',            4),
    ('sch_content',              'منسق المحتوى التعليمي',      5),
    ('sch_teacher',              'معلم',                      6),
    ('sch_accountant',           'محاسب',                     7),
    ('sch_transport_supervisor', 'مشرف نقل',                  8),
    ('sch_driver',               'سائق',                      9),
    ('sch_guard',                'حارس البوابة',              10),
    ('sch_staff',                'موظف إداري',                11),
    ('sch_guardian',             'ولي أمر',                   12),
    ('sch_student',              'طالب',                      13),
    ('administrator',            'مالك النظام',               0)
on conflict (slug) do nothing;

alter table public.sch_users
    add constraint sch_users_role_fk foreign key (role) references public.sch_roles (slug);

-- ── صلاحيات الدور ───────────────────────────────────────────────────

create table if not exists public.sch_role_caps (
    role text not null references public.sch_roles (slug) on delete cascade,
    cap  text not null,
    primary key (role, cap)
);

insert into public.sch_role_caps (role, cap) values
    ('sch_teacher','sch_view_students'),('sch_teacher','sch_manage_attendance'),
    ('sch_teacher','sch_manage_grades'),('sch_teacher','sch_manage_exams'),
    ('sch_teacher','sch_send_messages'),('sch_teacher','sch_manage_kg'),
    ('sch_teacher','sch_write_notes'),('sch_teacher','sch_manage_homework'),
    ('sch_teacher','sch_manage_content'),

    ('sch_accountant','sch_view_students'),('sch_accountant','sch_manage_finance'),
    ('sch_accountant','sch_manage_accounting'),

    ('sch_transport_supervisor','sch_view_students'),('sch_transport_supervisor','sch_manage_transport'),
    ('sch_transport_supervisor','sch_send_messages'),('sch_transport_supervisor','sch_view_custody'),

    ('sch_driver','sch_drive_trip'),

    ('sch_guardian','sch_view_own_children'),

    ('sch_student','sch_view_own_record'),('sch_student','sch_student_app'),('sch_student','sch_learn'),

    ('sch_principal','sch_view_students'),('sch_principal','sch_view_custody'),
    ('sch_principal','sch_manage_alerts'),('sch_principal','sch_view_insights'),
    ('sch_principal','sch_view_audit'),('sch_principal','sch_approve_timetable'),
    ('sch_principal','sch_manage_staff'),('sch_principal','sch_manage_settings'),
    ('sch_principal','sch_grant_installments'),

    ('sch_supervisor','sch_view_students'),('sch_supervisor','sch_manage_attendance'),
    ('sch_supervisor','sch_manage_classes'),('sch_supervisor','sch_manage_staff'),
    ('sch_supervisor','sch_supervise_stage'),('sch_supervisor','sch_view_custody'),
    ('sch_supervisor','sch_manage_alerts'),('sch_supervisor','sch_send_messages'),
    ('sch_supervisor','sch_handle_notes'),

    ('sch_deputy','sch_view_students'),('sch_deputy','sch_manage_classes'),
    ('sch_deputy','sch_manage_subjects'),('sch_deputy','sch_manage_exams'),
    ('sch_deputy','sch_manage_attendance'),('sch_deputy','sch_view_custody'),
    ('sch_deputy','sch_manage_alerts'),('sch_deputy','sch_send_messages'),
    ('sch_deputy','sch_view_insights'),('sch_deputy','sch_manage_deputy'),
    ('sch_deputy','sch_grant_installments'),('sch_deputy','sch_handle_notes'),
    ('sch_deputy','sch_write_notes'),('sch_deputy','sch_decide_leaves'),
    ('sch_deputy','sch_build_timetable'),('sch_deputy','sch_manage_staff'),

    ('sch_content','sch_manage_content'),

    ('sch_counselor','sch_view_students'),('sch_counselor','sch_write_notes'),
    ('sch_counselor','sch_handle_notes'),('sch_counselor','sch_send_messages'),
    ('sch_counselor','sch_view_insights'),

    ('sch_guard','sch_scan_gate'),

    ('sch_staff','sch_view_students'),('sch_staff','sch_manage_students'),
    ('sch_staff','sch_manage_guardians'),('sch_staff','sch_manage_services'),
    ('sch_staff','sch_view_health'),('sch_staff','sch_manage_docs'),
    ('sch_staff','sch_handle_notes'),('sch_staff','sch_manage_meds')
on conflict do nothing;

-- مالك النظام يملك كل صلاحية موجودة — تُشتقّ ولا تُكتب، فلا تُنسى واحدة عند إضافة ميزة
insert into public.sch_role_caps (role, cap)
select 'administrator', cap from (
    select distinct cap from public.sch_role_caps
    union values
        ('sch_manage_hr'),('sch_manage_assets'),('sch_manage_transport'),
        ('sch_manage_finance'),('sch_manage_accounting'),('sch_drive_trip'),
        ('sch_manage_settings'),('sch_view_audit'),('sch_scan_gate')
) c
on conflict do nothing;

-- ── الصلاحيات المخصّصة لكل موظف ─────────────────────────────────────
--
-- الدور قالبٌ تبدأ منه لا قيدٌ تسكن فيه: من له صفٌّ هنا تُقرأ صلاحياته
-- منه وحده. ومن ليس له صفٌّ واحد يعمل بصلاحيات دوره كما هي.

create table if not exists public.sch_user_caps (
    user_id bigint not null references public.sch_users (id) on delete cascade,
    cap     text   not null,
    scope   text   not null default 'all' check (scope in ('all','stage','own')),
    granted_by bigint references public.sch_users (id),
    granted_at timestamptz not null default now(),
    primary key (user_id, cap)
);

comment on column public.sch_user_caps.scope is 'البُعد الثاني: على مَن؟ كل المدرسة · مرحلته · فصوله وطلابه';

-- ═══ دوالّ مساعدة تُستعمل في كل سياسة أمان ═══════════════════════════
--
-- STABLE و SECURITY DEFINER: تُنفَّذ مرة واحدة لكل استعلام لا لكل صفّ،
-- وتقرأ جدول المستخدمين رغم أن سياساته تمنع القراءة المباشرة.

create or replace function public.sch_uid()
returns bigint
language sql stable security definer set search_path = public
as $$ select id from public.sch_users where auth_id = auth.uid() and is_active $$;

create or replace function public.sch_role()
returns text
language sql stable security definer set search_path = public
as $$ select role from public.sch_users where auth_id = auth.uid() and is_active $$;

create or replace function public.sch_has(cap text)
returns boolean
language sql stable security definer set search_path = public
as $$
    select case
        -- صلاحيات مخصّصة لهذا الشخص؟ فهي وحدها المرجع
        when exists (select 1 from public.sch_user_caps uc where uc.user_id = public.sch_uid())
            then exists (
                select 1 from public.sch_user_caps uc
                 where uc.user_id = public.sch_uid() and uc.cap = sch_has.cap
            )
        else exists (
            select 1 from public.sch_role_caps rc
             where rc.role = public.sch_role() and rc.cap = sch_has.cap
        )
    end
$$;

-- نطاق الصلاحية: على مَن يملك هذا الشخص أن يطبّقها
create or replace function public.sch_scope(cap text)
returns text
language sql stable security definer set search_path = public
as $$
    select coalesce(
        (select scope from public.sch_user_caps
          where user_id = public.sch_uid() and cap = sch_scope.cap),
        'all'
    )
$$;

-- أبناء وليّ الأمر الحالي — تُستعمل في كل سياسة تخصّ الأسرة
create or replace function public.sch_my_children()
returns setof bigint
language sql stable security definer set search_path = public
as $$
    select student_id
      from public.sch_guardian_student
     where guardian_user_id = public.sch_uid()
$$;

-- ومن أبنائه من يحقّ له استلامه — الربط يحمل الإذن، ولا يُستنتج من القرابة
create or replace function public.sch_my_pickups()
returns setof bigint
language sql stable security definer set search_path = public
as $$
    select student_id
      from public.sch_guardian_student
     where guardian_user_id = public.sch_uid() and can_pickup
$$;

-- سجلّ الطالب الحالي (حين يدخل الطالب بحسابه)
create or replace function public.sch_my_student()
returns bigint
language sql stable security definer set search_path = public
as $$ select id from public.sch_students where user_id = public.sch_uid() $$;
