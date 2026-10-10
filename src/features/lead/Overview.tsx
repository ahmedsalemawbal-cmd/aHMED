import { Icon } from '@/components/ui/Icon';
import { ScoreBar } from '@/components/ui/ScoreBar';
import type { LeadDetail, LeadService } from '@/data/lead';
import { formatNumber } from '@/lib/format';
import { formatPhone } from '@/lib/phone';
import { consentView, contactNameText, instagramView, moreWeaknessesText, TIME_LABEL, WEAKNESSES_SHOWN } from './text';

const CARD = 'flex flex-col rounded-lg border border-line bg-surface-raised';

/** «درجة الحضور الرقمي» and the weaknesses that cost the most points. */
export function ScoreCard({ score, weaknesses, desktop }: { score: number | null; weaknesses: string[]; desktop: boolean }) {
  const shown = weaknesses.slice(0, WEAKNESSES_SHOWN);
  const more = weaknesses.length - shown.length;
  return (
    <section aria-labelledby="lead-score" className={`${CARD} ${desktop ? 'gap-[14px] p-5' : 'gap-[14px] p-4'}`}>
      <h2 id="lead-score" className={desktop ? 'm-0 text-[15px] font-bold leading-[22px]' : 'sr-only'}>
        التقييم
      </h2>
      {score === null ? (
        <p className="m-0 text-body-sm text-ink-muted">لم يُقيَّم بعد. سجّل زيارة لتقييم حضوره الرقمي.</p>
      ) : (
        <>
          <ScoreBar score={score} label="درجة الحضور الرقمي" />
          {shown.length ? (
            <ul aria-label="نقاط الضعف" className={`m-0 flex list-none flex-col p-0 ${desktop ? 'gap-[6px] text-body-sm' : 'gap-2 text-[15px] leading-[22px]'}`}>
              {shown.map((w) =>
                desktop ? (
                  <li key={w} className="flex items-center gap-2">
                    <span aria-hidden="true" className="size-2 flex-none rounded-full bg-danger" />
                    {w}
                  </li>
                ) : (
                  <li key={w} className="flex items-center gap-[10px]">
                    <span aria-hidden="true" className="grid size-6 flex-none place-items-center rounded-full bg-danger-soft text-danger">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round">
                        <path d="M6 6l12 12M18 6L6 18" />
                      </svg>
                    </span>
                    {w}
                  </li>
                ),
              )}
            </ul>
          ) : (
            <p className="m-0 text-body-sm text-ink-muted">لا نقاط ضعف في آخر تقييم.</p>
          )}
          {more > 0 ? <p className="m-0 text-body-sm text-ink-muted">{moreWeaknessesText(more)}</p> : null}
        </>
      )}
    </section>
  );
}

/** «الخدمات المقترحة» with the expected monthly value (NewLead4: «القيمة المتوقعة … ر.س / شهرياً»). */
export function ServicesCard({ services, expectedValue, desktop }: { services: LeadService[]; expectedValue: number | null; desktop: boolean }) {
  const suggested = services.filter((s) => s.status === 'suggested');
  return (
    <section aria-labelledby="lead-services" className={`${CARD} ${desktop ? 'gap-[10px] p-5' : 'gap-3 p-4'}`}>
      <div className="flex items-baseline justify-between gap-3">
        <h2 id="lead-services" className="m-0 text-[15px] font-bold leading-[22px]">
          الخدمات المقترحة
        </h2>
        {expectedValue !== null ? (
          <span className="whitespace-nowrap text-body-sm text-ink-muted">
            <span className="sr-only">القيمة المتوقعة: </span>
            <b className="text-title-3 text-ink tabular-nums">{formatNumber(expectedValue)}</b> ر.س شهرياً
          </span>
        ) : null}
      </div>
      {suggested.length === 0 ? (
        <p className="m-0 text-body-sm text-ink-muted">لا خدمات مقترحة بعد. اخترها في الزيارة القادمة.</p>
      ) : desktop ? (
        <ul className="m-0 flex list-none flex-wrap gap-2 p-0">
          {suggested.map((s) => (
            <li key={s.id} className="rounded-full bg-surface-sunken px-3 py-[6px] text-body-sm">
              {s.name}
            </li>
          ))}
        </ul>
      ) : (
        <ul className="m-0 flex list-none flex-col gap-2 p-0">
          {suggested.map((s) => (
            <li key={s.id} className="flex items-center gap-[10px] rounded-md bg-surface-sunken px-3 py-[10px] text-[15px] leading-[22px]">
              <Icon name="check" size={18} className="flex-none text-success" />
              {s.name}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

const CONSENT_TONE = { success: 'font-bold text-success', muted: 'text-ink-muted', danger: 'font-bold text-danger' } as const;

/** «المسؤول» (DeskLead.dc.html): name, phone, best time, WhatsApp consent, Instagram. */
export function ContactCard({ lead, desktop }: { lead: LeadDetail; desktop: boolean }) {
  const consent = consentView(lead);
  const insta = lead.instagramUrl ? instagramView(lead.instagramUrl) : null;
  return (
    <section aria-labelledby="lead-contact" className={`${CARD} ${desktop ? 'p-5' : 'p-4'}`}>
      <h2 id="lead-contact" className="m-0 mb-3 text-[15px] font-bold leading-[22px]">
        المسؤول
      </h2>
      <dl className="m-0 grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 text-body-sm">
        <dt className="text-ink-muted">الاسم</dt>
        <dd className="m-0 font-bold">{contactNameText(lead)}</dd>
        <dt className="text-ink-muted">الجوال</dt>
        <dd className="m-0">{lead.phone ? <span dir="ltr">{formatPhone(lead.phone)}</span> : <span className="text-ink-muted">لا يوجد</span>}</dd>
        <dt className="text-ink-muted">أفضل وقت</dt>
        <dd className="m-0">{lead.bestTime ? TIME_LABEL[lead.bestTime] : <span className="text-ink-muted">غير محدد</span>}</dd>
        <dt className="text-ink-muted">واتساب</dt>
        <dd className={`m-0 ${CONSENT_TONE[consent.tone]}`}>{consent.text}</dd>
        {insta ? (
          <>
            <dt className="text-ink-muted">إنستقرام</dt>
            <dd className="m-0 min-w-0">
              {insta.href ? (
                <a href={insta.href} target="_blank" rel="noopener noreferrer" dir="ltr" className="flex min-h-touch min-w-0 items-center justify-end text-ink underline-offset-4">
                  <span className="truncate">{insta.text}</span>
                </a>
              ) : (
                <span dir="ltr">{insta.text}</span>
              )}
            </dd>
          </>
        ) : null}
      </dl>
    </section>
  );
}
