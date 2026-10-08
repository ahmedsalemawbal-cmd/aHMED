-- ميداني · فهارس owner_id (تستخدمها كل سياسات RLS) والمفاتيح الأجنبية غير المفهرسة
create index if not exists profiles_owner_idx on public.profiles (owner_id);
create index if not exists visits_owner_idx on public.visits (owner_id);
create index if not exists visit_media_owner_idx on public.visit_media (owner_id);
create index if not exists assessments_owner_idx on public.assessments (owner_id);
create index if not exists assessments_visit_idx on public.assessments (visit_id);
create index if not exists lead_services_owner_idx on public.lead_services (owner_id);
create index if not exists lead_services_service_idx on public.lead_services (service_id);
create index if not exists message_templates_owner_idx on public.message_templates (owner_id);
create index if not exists message_templates_activity_idx on public.message_templates (activity_type_id);
create index if not exists messages_owner_idx on public.messages (owner_id);
create index if not exists messages_task_idx on public.messages (task_id);
create index if not exists messages_template_idx on public.messages (template_id);
create index if not exists stage_history_owner_idx on public.stage_history (owner_id);
create index if not exists tasks_owner_idx on public.tasks (owner_id);
create index if not exists leads_activity_idx on public.leads (activity_type_id);
