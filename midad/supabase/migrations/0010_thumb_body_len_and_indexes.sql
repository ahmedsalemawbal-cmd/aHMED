-- ══ المكتبة لا تجلب المتون ══
--
-- خمسةَ عشرَ قالبًا متونُها ثلاثةَ عشرَ ميغابايتًا. وصفحةُ المكتبة تجلبها
-- **كلَّها** لترسم بطاقاتٍ ارتفاعُها مئةٌ واثنان وثلاثون بكسلًا، ولوحةُ
-- المالك تجلبها لتعرض جدولَ عناوين. فيقف المشترك أمام هياكل تحميلٍ حتّى
-- ينزل ما لا يُعرض منه واحدٌ بالمئة.
--
--     ما لا يُعرض لا يُجلَب.
--
-- فيُشتقّ في القاعدة عمودان يُغنيان عن المتن:
--   `thumb_html` أوّلُ صفحةٍ وحدها — خمسةٌ بالمئة من المتن (٧٣٥ك مقابل ١٣م).
--   `body_len`   طولُ المتن — يكفي لمعرفة أفارغٌ هو أم لا.
--
-- ويُشتقّان بمُحفِّزٍ لا بيد الكاتب: من يستورد ومن يحرّر ومن يضاعف
-- ثلاثةُ مواضع، ونسيانُ أحدها يترك مصغّرةً قديمةً لا يُدرى متى شاخت.

create or replace function app.first_page(h text) returns text
language plpgsql immutable as $$
declare i int; j int; back int;
begin
  if h is null or h = '' then return ''; end if;
  i := position('data-page="true"' in h);
  if i = 0 then return h; end if;                     -- بلا صناديق: المتن كلُّه
  j := position('data-page="true"' in substr(h, i + 16));
  if j = 0 then return h; end if;                     -- صفحةٌ واحدة
  j := i + 16 + j - 1;                                -- علامةُ الصفحة الثانية
  back := position('<' in reverse(substr(h, 1, j)));  -- أقربُ '<' قبلها
  if back = 0 then return h; end if;
  return substr(h, 1, j - back);
end $$;

alter table public.templates
  add column if not exists thumb_html text not null default '',
  add column if not exists body_len   integer not null default 0;

create or replace function app.templates_derive() returns trigger
language plpgsql as $$
begin
  new.thumb_html := app.first_page(new.content_html);
  new.body_len   := length(coalesce(new.content_html, ''));
  return new;
end $$;

drop trigger if exists templates_derive on public.templates;
create trigger templates_derive
  before insert or update of content_html on public.templates
  for each row execute function app.templates_derive();

update public.templates
   set thumb_html = app.first_page(content_html),
       body_len   = length(coalesce(content_html, ''));

-- ══ وفهرسان يتبعان الاستعلامين اللذين يُشغَّلان في كلّ فتحة ══
--
-- والفهرس يُبنى على شكل الاستعلام لا على العمود وحده: ترشيحٌ ثمّ ترتيب
-- في فهرسٍ واحدٍ يقرؤه المحرّك مرّةً، وفي فهرسين يجمع ثمّ يرتّب.

-- ① قائمةُ المكتبة: منشورٌ لجمهوري، مرتَّبًا كما رتّبه المالك.
--    والسياسة نفسها ترشّح بـ`audience`، فيخدمها الفهرس أيضًا.
create index if not exists templates_audience_idx
  on public.templates (status, audience, sort);

-- ② «ملفّاتي»: ملفّات مشتركٍ بعينه، أحدثُها أوّلًا.
create index if not exists documents_subscriber_updated_idx
  on public.documents (subscriber_id, updated_at desc);
