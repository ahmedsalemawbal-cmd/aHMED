import { useState, type ReactNode } from 'react';
import { useToast } from '@/components/app/toast/context';
import {
  BottomSheet,
  Button,
  EmptyState,
  LeadCard,
  PriorityBadge,
  ScoreBar,
  STAGES,
  StageBadge,
  TextField,
  Toast,
  TriSelect,
  type TriValue,
} from '@/components/ui';

/** Every design-system component, in light and dark. Development builds only. */
export default function DevUi() {
  return (
    <div className="min-h-dvh bg-surface text-ink">
      <header className="px-4 py-6 tablet:px-12">
        <h1 className="m-0 text-title-1 font-bold">مكونات نظام التصميم</h1>
        <p className="m-0 text-body-sm text-ink-muted">مطابقة لـ design/components. الوضع الفاتح ثم الداكن.</p>
      </header>
      <div className="grid gap-0 wide:grid-cols-2">
        <Panel theme="light" />
        <Panel theme="dark" />
      </div>
    </div>
  );
}

function Panel({ theme }: { theme: 'light' | 'dark' }) {
  return (
    <section data-theme={theme} aria-label={theme === 'light' ? 'الوضع الفاتح' : 'الوضع الداكن'} className="bg-surface px-4 py-8 text-ink tablet:px-12">
      <h2 className="m-0 mb-6 text-title-2 font-bold">{theme === 'light' ? 'فاتح' : 'داكن'}</h2>
      <div className="flex max-w-[var(--bp-mobile)] flex-col gap-8">
        <Buttons />
        <Fields />
        <Badges />
        <Scores />
        <Tri />
        <Cards />
        <Sheet />
        <Toasts />
        <Empty />
      </div>
    </section>
  );
}

function Group({ title, children, note }: { title: string; children: ReactNode; note?: string }) {
  return (
    <div className="flex flex-col gap-3">
      <h3 className="m-0 text-title-3 font-bold">{title}</h3>
      {note ? <p className="m-0 text-caption text-ink-muted">{note}</p> : null}
      {children}
    </div>
  );
}

function Buttons() {
  return (
    <Group title="Button">
      <Button variant="primary" block>
        احفظ وولّد الرسالة
      </Button>
      <Button variant="whatsapp" block>
        إرسال عبر واتساب
      </Button>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" icon="phone">
          اتصال
        </Button>
        <Button variant="ghost" icon="plus">
          أضف خدمة
        </Button>
        <Button variant="danger">نقل إلى خسارة</Button>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button variant="primary" loading>
          جارٍ التوليد
        </Button>
        <Button variant="secondary" disabled>
          معطّل
        </Button>
      </div>
      <div className="flex flex-wrap gap-2" data-desktop-only="">
        <Button variant="primary" size="sm">
          عميل جديد
        </Button>
        <Button variant="secondary" size="sm">
          تنزيل PDF
        </Button>
        <Button variant="whatsapp" size="sm">
          واتساب
        </Button>
      </div>
    </Group>
  );
}

function Fields() {
  const [name, setName] = useState('مطعم ريدان');
  const [phone, setPhone] = useState('055');
  return (
    <Group title="TextField">
      <TextField
        label="اسم المحل"
        required
        value={name}
        onChange={(e) => {
          setName(e.target.value);
        }}
      />
      <TextField
        label="الجوال"
        ltr
        inputMode="tel"
        value={phone}
        error={phone.length < 10 ? 'الرقم ناقص. اكتب 10 أرقام تبدأ بـ 05' : undefined}
        hint="يُحوَّل تلقائياً إلى 9665… عند الحفظ"
        onChange={(e) => {
          setPhone(e.target.value);
        }}
      />
      <TextField label="القيمة المتوقعة" ltr inputMode="numeric" suffix="ر.س / شهرياً" defaultValue="2500" />
      <TextField label="ملاحظات حرة" multiline rows={3} defaultValue="الطاولات الخارجية ممتلئة بعد المغرب." />
    </Group>
  );
}

function Badges() {
  return (
    <Group title="StageBadge · PriorityBadge">
      <div className="flex flex-wrap gap-2">
        {STAGES.map((s) => (
          <StageBadge key={s.id} stage={s.id} />
        ))}
      </div>
      <div className="flex flex-wrap gap-2">
        {STAGES.map((s) => (
          <StageBadge key={s.id} stage={s.id} size="sm" />
        ))}
      </div>
      <div className="flex flex-wrap gap-2">
        <PriorityBadge priority="hot" />
        <PriorityBadge priority="warm" />
        <PriorityBadge priority="cold" />
        <PriorityBadge priority="hot" size="sm" />
      </div>
    </Group>
  );
}

function Scores() {
  return (
    <Group title="ScoreBar">
      <ScoreBar score={44} label="الدرجة · 9 من 13 بنود" />
      <ScoreBar score={58} label="درجة الحضور الرقمي" />
      <ScoreBar score={82} />
      <div className="flex flex-wrap gap-6">
        <ScoreBar score={44} variant="ring" />
        <ScoreBar score={82} variant="ring" context="presence" size={120} />
      </div>
    </Group>
  );
}

function Tri() {
  const [a, setA] = useState<TriValue | null>('yes');
  const [b, setB] = useState<TriValue | null>(null);
  const [c, setC] = useState<TriValue | null>('no');
  return (
    <Group title="TriSelect">
      <div className="flex flex-col gap-6">
        <TriSelect label="التقييم 4.3 أو أعلى" weight={10} value={a} onChange={setA} />
        <TriSelect label="يرد على المراجعات" weight={5} value={b} onChange={setB} />
        <TriSelect label="منيو QR" binary value={c} onChange={setC} />
      </div>
    </Group>
  );
}

function Cards() {
  const toast = useToast();
  const open = () => toast.show({ tone: 'info', title: 'فتح صفحة العميل' });
  return (
    <Group title="LeadCard">
      <LeadCard
        businessName="مطعم ريدان"
        activity="مطعم"
        contactName="أ. خالد"
        stage="contacted"
        score={48}
        priority="hot"
        lastContact="اليوم 11:20 ص"
        nextAction="متابعة أولى: عينة الريل"
        nextActionAt="السبت 10 أكتوبر"
        onClick={open}
        onWhatsApp={() => toast.show({ tone: 'info', title: 'فتح الواتساب' })}
      />
      <LeadCard
        businessName="كافيه نسمة الحمدانية"
        activity="كافيه"
        contactName="أ. سارة"
        stage="visited"
        score={58}
        priority="warm"
        lastContact="قبل 4 أيام"
        nextAction="أرسل الرسالة الأولى"
        nextActionAt="متأخرة منذ يومين"
        overdue
        onClick={open}
      />
      <div className="grid grid-cols-2 gap-2">
        <LeadCard variant="compact" businessName="مطعم ريدان" activity="مطعم" value="2,500 ر.س" daysInStage={0} onClick={open} />
        <LeadCard variant="compact" businessName="حلويات السنبلة" activity="حلويات" value="1,500 ر.س" daysInStage={9} stale onClick={open} />
      </div>
    </Group>
  );
}

function Sheet() {
  const [open, setOpen] = useState(false);
  const close = () => {
    setOpen(false);
  };
  return (
    <Group title="BottomSheet">
      <Button
        variant="secondary"
        block
        onClick={() => {
          setOpen(true);
        }}
      >
        افتح «هل أرسلت الرسالة؟»
      </Button>
      <BottomSheet
        open={open}
        onClose={close}
        title="هل أرسلت الرسالة؟"
        actions={
          <>
            <Button variant="primary" block onClick={close}>
              نعم، أرسلتها
            </Button>
            <Button variant="ghost" block onClick={close}>
              ليس بعد
            </Button>
          </>
        }
      >
        عند التأكيد تصبح مرحلة مطعم ريدان «تم الإرسال»، وتُنشأ متابعتان.
      </BottomSheet>
    </Group>
  );
}

function Toasts() {
  const toast = useToast();
  return (
    <Group title="Toast">
      <Toast tone="success" title="المرحلة الآن: تم الإرسال" message="أُنشئت متابعتان بعد 3 و7 أيام." action="تراجع" />
      <Toast tone="error" title="تعذّر توليد الرسالة" message="استخدمنا القالب الجاهز بدلاً منها." action="أعد المحاولة" />
      <Toast tone="info" title="حُفظت كمسودة" />
      <div className="flex flex-wrap gap-2">
        <Button
          variant="secondary"
          onClick={() => toast.show({ tone: 'success', title: 'حُفظ العميل', message: 'يظهر الآن في القائمة.' })}
        >
          إشعار نجاح
        </Button>
        <Button
          variant="secondary"
          onClick={() => toast.show({ tone: 'error', title: 'تعذّر الحفظ', message: 'تأكد من الشبكة ثم أعد المحاولة.', action: 'أعد المحاولة' })}
        >
          إشعار خطأ
        </Button>
      </div>
    </Group>
  );
}

function Empty() {
  return (
    <Group title="EmptyState">
      <EmptyState
        icon="pin"
        title="انزل للميدان"
        message="لا متابعات اليوم. سجّل محلاً جديداً من الزيارة."
        action={
          <Button variant="primary" icon="plus">
            عميل جديد
          </Button>
        }
      />
    </Group>
  );
}
