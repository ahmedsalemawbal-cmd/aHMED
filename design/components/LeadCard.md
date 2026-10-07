# LeadCard

بطاقة العميل في القائمة وشاشة اليوم، بنسختين: `full` (الافتراضية) و`compact` لأعمدة الـ Pipeline.

**يوفّر المستهلك (full):** `businessName`، `activity`، `stage`، `score`، `priority`، `lastContact` (نص جاهز: «قبل 3 أيام»)، `nextAction` و`nextActionAt`، `overdue`، و`onClick` لفتح صفحة العميل. اختياري: `contactName`، `onWhatsApp` (لا تمرّره لعميل عليه `do_not_contact`).

**يوفّر المستهلك (compact):** `variant="compact"`، `businessName`، `activity`، `value` (نص منسّق: «2,500 ر.س»)، `daysInStage`، و`stale` إذا تجاوز 7 أيام بدون حركة.

- الشريط السفلي يجيب عن «ما الخطوة القادمة؟»؛ إذا فاتت يصبح بلون `warning` مع أيقونة تحذير ونص التاريخ.
- البطاقة كاملة قابلة للضغط، وزر الواتساب منفصل عنها.
- على الديسكتوب تُستبدل القائمة بجدول؛ استخدم نفس `StageBadge` و`PriorityBadge` داخل خلاياه.
- السحب للإجراءات السريعة (واتساب، زيارة جديدة) يضيفه المستهلك حول البطاقة.
