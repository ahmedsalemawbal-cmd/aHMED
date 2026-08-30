-- ============================================================
-- 0004 — الإدارة الصفّية: الفصول والطلاب والحصص والرصد
--
-- خدمةٌ ثالثة مستقلّة عن القوالب وعن جداول نور.
-- الأسبوع الدراسيّ السعوديّ: weekday 0 الأحد … 4 الخميس.
-- ============================================================

do $$ begin
  create type public.attendance_status as enum ('present','late','absent','excused');
exception when duplicate_object then null; end $$;

do $$ begin
  create type public.student_status as enum ('active','transferred','withdrawn');
exception when duplicate_object then null; end $$;

create table if not exists public.classes (
  id uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  name text not null,
  stage text,
  room text,
  sort int not null default 0,
  created_at timestamptz not null default now()
);
create index if not exists classes_subscriber_idx on public.classes(subscriber_id, sort);
create unique index if not exists classes_unique_name on public.classes(subscriber_id, name);

create table if not exists public.students (
  id uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  class_id uuid references public.classes(id) on delete set null,
  full_name text not null,
  national_id text,
  guardian_phone text,
  status public.student_status not null default 'active',
  sort int not null default 0,
  created_at timestamptz not null default now()
);
create index if not exists students_class_idx on public.students(class_id, sort);
create index if not exists students_subscriber_idx on public.students(subscriber_id);

create table if not exists public.periods (
  id uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  teacher_id uuid references public.profiles(id) on delete set null,
  class_id uuid references public.classes(id) on delete cascade,
  subject text not null,
  weekday smallint not null check (weekday between 0 and 6),
  slot smallint not null check (slot between 1 and 12),
  starts_at time not null,
  ends_at time not null,
  room text,
  created_at timestamptz not null default now(),
  check (ends_at > starts_at)
);
create index if not exists periods_teacher_idx on public.periods(teacher_id, weekday, slot);
create index if not exists periods_subscriber_idx on public.periods(subscriber_id);
-- معلّمٌ واحد لا يكون في فصلين في الوقت نفسه
create unique index if not exists periods_no_clash on public.periods(teacher_id, weekday, slot)
  where teacher_id is not null;

create table if not exists public.attendance (
  id uuid primary key default gen_random_uuid(),
  subscriber_id uuid not null references public.subscribers(id) on delete cascade,
  period_id uuid not null references public.periods(id) on delete cascade,
  student_id uuid not null references public.students(id) on delete cascade,
  taken_by uuid references public.profiles(id) on delete set null,
  on_date date not null default current_date,
  status public.attendance_status not null default 'present',
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
-- سطرٌ واحدٌ لكلّ طالبٍ في حصّةٍ في يوم — فإعادة الرصد تُحدّث ولا تُكرّر
create unique index if not exists attendance_once on public.attendance(period_id, student_id, on_date);
create index if not exists attendance_lookup on public.attendance(subscriber_id, on_date desc);
create index if not exists attendance_student_idx on public.attendance(student_id, on_date desc);

drop trigger if exists trg_touch_attendance on public.attendance;
create trigger trg_touch_attendance before update on public.attendance
  for each row execute function app.touch_updated_at();
