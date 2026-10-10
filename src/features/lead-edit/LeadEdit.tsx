import { useState, type ReactNode } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ActivityGrid } from '@/components/app/ActivityGrid';
import { ErrorRetry } from '@/components/app/ErrorRetry';
import { FlowFrame } from '@/components/app/FlowFrame';
import { Segmented } from '@/components/app/Segmented';
import { Skeleton } from '@/components/app/Skeleton';
import { StickyFooter } from '@/components/app/StickyFooter';
import { useToast } from '@/components/app/toast/context';
import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { TextField } from '@/components/ui/TextField';
import { useCatalog, useLeadIndex } from '@/data/catalog';
import { OfflineError } from '@/data/create-lead';
import { useLead, type LeadDetail } from '@/data/lead';
import { LeadNotFoundError, useUpdateLead } from '@/data/update-lead';
import { useOnline } from '@/hooks/useOnline';
import { duplicateMessage } from '@/lib/duplicates';
import type { ContactTime } from '@/lib/followups';
import type { Role } from '@/lib/leads-list';
import { buildLeadPatch, formFromLead, isDirty, phoneDuplicate, type EditField, type EditForm } from './form';

const ROLES: { value: Role; label: string }[] = [
  { value: 'owner', label: 'مالك' },
  { value: 'manager', label: 'مدير' },
  { value: 'employee', label: 'موظف' },
];
const TIMES: { value: ContactTime; label: string }[] = [
  { value: 'morning', label: 'الصباح' },
  { value: 'afternoon', label: 'بعد العصر' },
  { value: 'evening', label: 'المساء' },
];
const CLOSE = 'M6 6l12 12M18 6L6 18';

function useGoBack() {
  const navigate = useNavigate();
  return (fallback: string) => {
    const idx = (window.history.state as { idx?: number } | null)?.idx ?? 0;
    if (idx > 0) void navigate(-1);
    else void navigate(fallback, { replace: true });
  };
}

function Header({ title, sub, onClose }: { title: ReactNode; sub?: ReactNode; onClose: () => void }) {
  return (
    <header className="sticky top-0 z-20 flex items-center gap-1 border-b border-line bg-surface-raised px-4 pb-3 pt-4 desk:px-8 desk:pt-5">
      <button type="button" onClick={onClose} aria-label="إغلاق" className="-ms-3 grid size-touch flex-none place-items-center border-0 bg-transparent text-ink">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d={CLOSE} />
        </svg>
      </button>
      <div className="flex min-w-0 grow flex-col">
        <h1 className="m-0 truncate text-title-3 font-bold">{title}</h1>
        {sub ? <span className="truncate text-body-sm leading-5 text-ink-muted">{sub}</span> : null}
      </div>
    </header>
  );
}

/** A labelled on/off row (the same look as «وافق على رسالة واتساب» in NewLead1). */
function SwitchRow({ label, hint, checked, onChange, danger = false }: { label: string; hint: string; checked: boolean; onChange: (v: boolean) => void; danger?: boolean }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => {
        onChange(!checked);
      }}
      className="flex min-h-[56px] items-center justify-between gap-3 rounded-md border-[1.5px] border-line-strong bg-surface-raised px-4 py-2 text-start text-ink"
    >
      <span className="flex flex-col">
        <span className="text-[15px] font-bold leading-[22px]">{label}</span>
        <span className="text-label-sm font-normal text-ink-muted">{hint}</span>
      </span>
      <span className={`flex h-8 w-[52px] flex-none rounded-full p-[3px] ${checked ? `justify-end ${danger ? 'bg-danger' : 'bg-success'}` : 'justify-start bg-line-strong'}`} aria-hidden="true">
        <span className="size-[26px] rounded-full bg-surface-raised" />
      </span>
    </button>
  );
}

/** Brief screen 6 action «تعديل البيانات»: /leads/:id/edit. */
export default function LeadEdit() {
  const { id = '' } = useParams();
  const q = useLead(id);
  const goBack = useGoBack();
  const close = () => {
    goBack(`/leads/${id}`);
  };

  if (q.isError) {
    return (
      <FlowFrame section="leads">
        <Header title="تعديل البيانات" onClose={close} />
        <main className="flex grow flex-col gap-4 p-4 desk:px-8 desk:py-6">
          <ErrorRetry
            title="تعذّر تحميل بيانات العميل"
            onRetry={() => {
              void q.refetch();
            }}
          />
        </main>
      </FlowFrame>
    );
  }
  if (q.isPending) {
    return (
      <FlowFrame section="leads">
        <Header title="تعديل البيانات" onClose={close} />
        <main className="flex grow flex-col gap-5 p-4 desk:px-8 desk:py-6" aria-busy="true" aria-label="جارٍ التحميل">
          <Skeleton className="h-[80px]" />
          <Skeleton className="h-[110px]" />
          <Skeleton className="h-[80px]" />
          <Skeleton className="h-[80px]" />
        </main>
      </FlowFrame>
    );
  }
  if (!q.data) {
    return (
      <FlowFrame section="leads">
        <Header title="تعديل البيانات" onClose={close} />
        <main className="flex grow flex-col p-4 desk:px-8 desk:py-6">
          <EmptyState
            icon="store"
            title="لم نجد هذا العميل"
            message="ربما حُذف أو الرابط ناقص. ارجع لقائمة العملاء واختره منها."
            action={
              <Link to="/leads" className="md-btn md-btn-secondary no-underline">
                ارجع للعملاء
              </Link>
            }
          />
        </main>
      </FlowFrame>
    );
  }
  return <EditScreen key={q.data.lead.id} lead={q.data.lead} />;
}

function EditScreen({ lead }: { lead: LeadDetail }) {
  const navigate = useNavigate();
  const goBack = useGoBack();
  const toast = useToast();
  const online = useOnline();
  const catalog = useCatalog();
  const index = useLeadIndex();
  const save = useUpdateLead();
  const [initial] = useState(() => formFromLead(lead));
  const [form, setForm] = useState<EditForm>(initial);
  const [errors, setErrors] = useState<Partial<Record<EditField, string>>>({});
  const [allowDuplicate, setAllowDuplicate] = useState(false);
  const [discardOpen, setDiscardOpen] = useState(false);

  const dirty = isDirty(initial, form);
  const dup = phoneDuplicate(form, index.data ?? [], lead.id);
  const set = (patch: Partial<EditForm>) => {
    setForm((f) => ({ ...f, ...patch }));
    setErrors({});
  };

  const close = () => {
    if (dirty) setDiscardOpen(true);
    else goBack(`/leads/${lead.id}`);
  };

  const submit = async () => {
    const r = buildLeadPatch(initial, form);
    if (!r.ok) {
      setErrors({ [r.field]: r.error });
      return;
    }
    if (dup && !allowDuplicate) {
      setErrors({ phone: duplicateMessage('phone', dup.businessName) });
      return;
    }
    if (Object.keys(r.patch).length === 0) {
      void navigate(`/leads/${lead.id}`, { replace: true });
      return;
    }
    try {
      await save.mutateAsync({ id: lead.id, patch: r.patch });
    } catch (err) {
      if (err instanceof LeadNotFoundError) {
        toast.show({ tone: 'error', title: 'لم نجد هذا العميل', message: 'ربما حُذف. ارجع لقائمة العملاء.' });
        return;
      }
      toast.show({
        tone: 'error',
        title: 'تعذّر حفظ التعديلات',
        message: err instanceof OfflineError ? 'بدون اتصال. تعديلاتك باقية هنا، احفظها عند عودة الشبكة.' : 'تأكد من الشبكة ثم أعد المحاولة.',
        action: 'أعد المحاولة',
        onAction: () => {
          void submit();
        },
      });
      return;
    }
    toast.show({ tone: 'success', title: 'حُفظت البيانات', message: r.patch.do_not_contact ? 'أُلغيت المتابعات المفتوحة ولن يظهر زر الواتساب.' : undefined });
    void navigate(`/leads/${lead.id}`, { replace: true });
  };

  const activities = catalog.data?.activities ?? [];

  return (
    <FlowFrame section="leads">
      <Header title="تعديل البيانات" sub={lead.businessName} onClose={close} />
      <main className="flex grow flex-col gap-5 px-4 pb-6 pt-5 desk:px-8 desk:pb-8 desk:pt-6">
        <TextField
          label="اسم المحل"
          required
          autoComplete="off"
          value={form.businessName}
          error={errors.businessName}
          onChange={(e) => {
            set({ businessName: e.target.value });
          }}
        />

        {catalog.isError ? (
          <ErrorRetry
            title="تعذّر تحميل الأنشطة"
            onRetry={() => {
              void catalog.refetch();
            }}
          />
        ) : !catalog.data ? (
          <Skeleton className="h-[110px]" />
        ) : (
          <ActivityGrid
            activities={activities}
            value={form.activityTypeId}
            required
            error={errors.activity}
            onChange={(v) => {
              set({ activityTypeId: v });
            }}
          />
        )}

        <div className="grid grid-cols-2 gap-3">
          <TextField
            label="اسم المسؤول"
            autoComplete="off"
            value={form.contactName}
            onChange={(e) => {
              set({ contactName: e.target.value });
            }}
          />
          <div className="flex flex-col gap-2">
            <span className="text-[15px] font-bold leading-[22px]">صفته</span>
            <Segmented
              label="صفة المسؤول"
              value={form.contactRole}
              options={ROLES}
              onChange={(v) => {
                set({ contactRole: v });
              }}
            />
          </div>
        </div>

        <SwitchRow
          label="صاحب القرار"
          hint={form.isDecisionMaker ? 'يقرر الشراء بنفسه' : 'يرجع لغيره في القرار'}
          checked={form.isDecisionMaker}
          onChange={(v) => {
            set({ isDecisionMaker: v });
          }}
        />

        <div className="flex flex-col gap-2">
          <TextField
            label="الجوال"
            ltr
            type="tel"
            inputMode="tel"
            autoComplete="off"
            value={form.phone}
            error={errors.phone ?? (dup && !allowDuplicate ? duplicateMessage('phone', dup.businessName) : undefined)}
            hint="يُحوَّل تلقائياً إلى 9665… عند الحفظ"
            onChange={(e) => {
              set({ phone: e.target.value });
              setAllowDuplicate(false);
            }}
          />
          {dup && !allowDuplicate ? (
            <div className="flex flex-wrap gap-x-4">
              <Link to={`/leads/${dup.id}`} className="flex min-h-touch items-center text-body-sm font-bold text-ink underline underline-offset-4">
                {`افتح ${dup.businessName}`}
              </Link>
              <button
                type="button"
                className="min-h-touch border-0 bg-transparent text-body-sm font-bold text-ink-muted underline underline-offset-4"
                onClick={() => {
                  setAllowDuplicate(true);
                  setErrors({});
                }}
              >
                محل آخر لنفس المالك، تابع
              </button>
            </div>
          ) : null}
        </div>

        <SwitchRow
          label="وافق على رسالة واتساب"
          hint={form.waConsent ? 'أخذت موافقته على التواصل عبر واتساب' : 'لن يظهر زر الواتساب قبل الموافقة'}
          checked={form.waConsent}
          onChange={(v) => {
            set({ waConsent: v });
          }}
        />
        <SwitchRow
          label="طلب عدم التواصل"
          hint="تُلغى المتابعات المفتوحة ولا يظهر زر الواتساب"
          checked={form.doNotContact}
          danger
          onChange={(v) => {
            set({ doNotContact: v });
          }}
        />

        <div className="flex flex-col gap-2">
          <span className="text-[15px] font-bold leading-[22px]">أفضل وقت للتواصل</span>
          <div className="flex flex-wrap gap-2">
            {TIMES.map((t) => {
              const on = form.bestTime === t.value;
              return (
                <button
                  key={t.value}
                  type="button"
                  aria-pressed={on}
                  onClick={() => {
                    set({ bestTime: on ? null : t.value });
                  }}
                  className={`app-seg min-h-[44px] rounded-full border-[1.5px] px-4 text-body-sm ${on ? 'border-ink bg-ink font-bold text-surface-raised' : 'border-line-strong bg-surface-raised font-normal text-ink'}`}
                >
                  {t.label}
                </button>
              );
            })}
          </div>
        </div>

        <div className="grid grid-cols-1 gap-5 desk:grid-cols-2 desk:gap-3">
          <TextField
            label="العنوان"
            hint="اختياري. مثلاً: شارع الأمير، بجانب الصيدلية."
            autoComplete="off"
            value={form.address}
            onChange={(e) => {
              set({ address: e.target.value });
            }}
          />
          <TextField
            label="حساب إنستقرام"
            ltr
            hint="اختياري. رابط الحساب أو @اسم_المستخدم"
            autoComplete="off"
            inputMode="url"
            value={form.instagram}
            error={errors.instagram}
            onChange={(e) => {
              set({ instagram: e.target.value });
            }}
          />
        </div>
      </main>

      <StickyFooter note={!online ? 'بدون اتصال. احفظ عند عودة الشبكة.' : dirty ? 'تعديلات غير محفوظة' : undefined}>
        <Button variant="secondary" onClick={close}>
          إلغاء
        </Button>
        <Button variant="primary" className="grow" loading={save.isPending} disabled={!online || !dirty} onClick={() => void submit()}>
          {save.isPending ? 'جارٍ الحفظ' : 'احفظ التعديلات'}
        </Button>
      </StickyFooter>

      {discardOpen ? (
        <BottomSheet
          open
          title="تجاهل التعديلات؟"
          onClose={() => {
            setDiscardOpen(false);
          }}
          actions={
            <>
              <Button
                variant="danger"
                block
                onClick={() => {
                  setDiscardOpen(false);
                  goBack(`/leads/${lead.id}`);
                }}
              >
                تجاهل التعديلات
              </Button>
              <Button
                variant="ghost"
                block
                onClick={() => {
                  setDiscardOpen(false);
                }}
              >
                أكمل التعديل
              </Button>
            </>
          }
        >
          لم تُحفظ تعديلاتك على {lead.businessName}. إن خرجت الآن تضيع.
        </BottomSheet>
      ) : null}
    </FlowFrame>
  );
}
