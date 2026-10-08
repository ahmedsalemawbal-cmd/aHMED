import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Segmented } from '@/components/app/Segmented';
import { TextField } from '@/components/ui/TextField';
import type { ActivityType, LeadIndexRow } from '@/data/catalog';
import { duplicateMessage } from '@/lib/duplicates';
import type { ContactTime } from '@/lib/followups';
import { activityPath } from './activity-icons';
import type { NewLeadDraft, Role } from './draft';
import { step1Problems } from './helpers';

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
const PIN = 'M12 21s-7-6.1-7-11.5a7 7 0 0 1 14 0C19 14.9 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z';

export interface Step1Errors {
  businessName?: string;
  activity?: string;
  phone?: string;
}

export function Step1Details({
  draft,
  update,
  activities,
  index,
  errors,
}: {
  draft: NewLeadDraft;
  update: (patch: Partial<NewLeadDraft>) => void;
  activities: ActivityType[];
  index: LeadIndexRow[];
  errors: Step1Errors;
}) {
  const [locating, setLocating] = useState(false);
  const [locError, setLocError] = useState<string | null>(null);
  const [phoneTouched, setPhoneTouched] = useState(false);
  const { phone, phoneDup, nameDup } = step1Problems(draft, index);

  const capture = () => {
    if (!('geolocation' in navigator)) {
      setLocError('الجهاز لا يدعم تحديد الموقع. تابع بدون موقع.');
      return;
    }
    setLocating(true);
    setLocError(null);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLocating(false);
        update({ location: { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: Math.round(pos.coords.accuracy) } });
      },
      (err) => {
        setLocating(false);
        setLocError(
          err.code === err.PERMISSION_DENIED
            ? 'لم نصل للموقع. اسمح للمتصفح باستخدام الموقع ثم أعد المحاولة.'
            : 'تعذّر تحديد الموقع. اقترب من الباب أو أعد المحاولة.',
        );
      },
      { enableHighAccuracy: true, timeout: 15_000, maximumAge: 30_000 },
    );
  };

  const phoneError = errors.phone ?? (phoneTouched && !phone.ok ? phone.error : undefined) ?? (phoneDup && !draft.allowDuplicate ? duplicateMessage('phone', phoneDup.lead.businessName) : undefined);

  return (
    <div className="flex flex-col gap-5">
      <TextField
        label="اسم المحل"
        required
        autoComplete="off"
        value={draft.businessName}
        error={errors.businessName}
        hint={nameDup ? duplicateMessage(nameDup.reason, nameDup.lead.businessName) : undefined}
        onChange={(e) => {
          update({ businessName: e.target.value });
        }}
      />

      <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 p-0 text-[15px] font-bold leading-[22px]">
          نوع النشاط <span className="text-danger" aria-hidden="true">*</span>
        </legend>
        <div className="grid grid-cols-4 gap-2">
          {activities.map((a) => {
            const on = draft.activityTypeId === a.id;
            return (
              <button
                key={a.id}
                type="button"
                aria-pressed={on}
                onClick={() => {
                  update({ activityTypeId: a.id });
                }}
                className={`flex min-h-[76px] flex-col items-center justify-center gap-1 rounded-md border-[1.5px] text-body-sm ${on ? 'border-action bg-action font-bold text-on-action' : 'border-line-strong bg-surface-raised font-normal text-ink'}`}
              >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d={activityPath(a.icon)} />
                </svg>
                <span>{a.name}</span>
              </button>
            );
          })}
        </div>
        {errors.activity ? (
          <p className="m-0 text-label-sm text-danger" role="alert">
            {errors.activity}
          </p>
        ) : null}
      </fieldset>

      <div className="grid grid-cols-2 gap-3">
        <TextField
          label="اسم المسؤول"
          autoComplete="off"
          value={draft.contactName}
          onChange={(e) => {
            update({ contactName: e.target.value });
          }}
        />
        <div className="flex flex-col gap-2">
          <span className="text-[15px] font-bold leading-[22px]" id="role-label">
            صفته
          </span>
          <Segmented
            label="صفة المسؤول"
            value={draft.contactRole}
            options={ROLES}
            onChange={(v) => {
              update({ contactRole: v });
            }}
          />
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <TextField
          label="الجوال"
          ltr
          type="tel"
          inputMode="tel"
          autoComplete="off"
          value={draft.phone}
          error={phoneError}
          hint="يُحوَّل تلقائياً إلى 9665… عند الحفظ"
          onBlur={() => {
            setPhoneTouched(true);
          }}
          onChange={(e) => {
            update({ phone: e.target.value, allowDuplicate: false });
          }}
        />
        {phoneDup && !draft.allowDuplicate ? (
          <div className="flex flex-wrap gap-x-4">
            <Link to={`/leads/${phoneDup.lead.id}`} className="flex min-h-touch items-center text-body-sm font-bold text-ink underline underline-offset-4">
              {`افتح ${phoneDup.lead.businessName}`}
            </Link>
            <button
              type="button"
              className="min-h-touch border-0 bg-transparent text-body-sm font-bold text-ink-muted underline underline-offset-4"
              onClick={() => {
                update({ allowDuplicate: true });
              }}
            >
              محل آخر لنفس المالك، تابع
            </button>
          </div>
        ) : null}
      </div>

      <button
        type="button"
        role="switch"
        aria-checked={draft.waConsent}
        onClick={() => {
          update({ waConsent: !draft.waConsent });
        }}
        className="flex min-h-[56px] items-center justify-between gap-3 rounded-md border-[1.5px] border-line-strong bg-surface-raised px-4 py-2 text-start text-ink"
      >
        <span className="flex flex-col">
          <span className="text-[15px] font-bold leading-[22px]">وافق على رسالة واتساب</span>
          <span className="text-label-sm font-normal text-ink-muted">{draft.waConsent ? 'أخذت موافقته شفهياً في الزيارة' : 'لن يظهر زر الواتساب قبل الموافقة'}</span>
        </span>
        <span className={`flex h-8 w-[52px] flex-none rounded-full p-[3px] ${draft.waConsent ? 'justify-end bg-success' : 'justify-start bg-line-strong'}`} aria-hidden="true">
          <span className="size-[26px] rounded-full bg-surface-raised" />
        </span>
      </button>

      <div className="flex flex-col gap-2">
        <span className="text-[15px] font-bold leading-[22px]">أفضل وقت للتواصل</span>
        <div className="flex flex-wrap gap-2">
          {TIMES.map((t) => {
            const on = draft.bestTime === t.value;
            return (
              <button
                key={t.value}
                type="button"
                aria-pressed={on}
                onClick={() => {
                  update({ bestTime: on ? null : t.value });
                }}
                className={`app-seg min-h-[44px] rounded-full border-[1.5px] px-4 text-body-sm ${on ? 'border-ink bg-ink font-bold text-surface-raised' : 'border-line-strong bg-surface-raised font-normal text-ink'}`}
              >
                {t.label}
              </button>
            );
          })}
        </div>
      </div>

      {draft.location ? (
        <div className="flex items-center gap-3 rounded-md bg-stage-visited-soft px-4 py-3 text-stage-visited">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d={PIN} />
          </svg>
          <span className="flex grow flex-col">
            <span className="text-[15px] font-bold leading-[22px]">تم التقاط الموقع</span>
            <span className="text-label-sm font-normal text-ink" dir="rtl">
              {`الدقة ± ${draft.location.accuracy.toString()} م`}
            </span>
          </span>
          <button type="button" onClick={capture} disabled={locating} className="min-h-touch border-0 bg-transparent px-1 text-body-sm font-bold text-ink underline underline-offset-4">
            {locating ? 'جارٍ الالتقاط' : 'أعد الالتقاط'}
          </button>
        </div>
      ) : (
        <div className="flex flex-col gap-2">
          <button type="button" className="md-btn md-btn-secondary md-btn-block" onClick={capture} disabled={locating} aria-busy={locating || undefined}>
            <svg className="md-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d={PIN} />
            </svg>
            <span>{locating ? 'جارٍ تحديد الموقع' : 'التقط موقعي'}</span>
          </button>
          {locError ? (
            <p className="m-0 text-label-sm text-danger" role="alert">
              {locError}
            </p>
          ) : null}
        </div>
      )}
    </div>
  );
}
