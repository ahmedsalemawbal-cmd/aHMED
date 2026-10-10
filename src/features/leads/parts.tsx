import { useNavigate } from 'react-router-dom';
import { NavIcon } from '@/components/app/NavIcon';
import { Button } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';

/** «بحث بالاسم أو الرقم»: 52px on the phone, the 40px desktop control at 15px. */
export function SearchBox({ value, onChange, size = 'md' }: { value: string; onChange: (v: string) => void; size?: 'md' | 'sm' }) {
  const sm = size === 'sm';
  return (
    <form
      role="search"
      className={sm ? 'min-w-[220px] flex-[0_1_320px]' : ''}
      onSubmit={(e) => {
        e.preventDefault();
        const input = e.currentTarget.querySelector('input');
        input?.blur();
      }}
    >
      <label className="relative block">
        <span className={`pointer-events-none absolute top-1/2 flex -translate-y-1/2 text-ink-muted ${sm ? 'start-3' : 'start-[14px]'}`}>
          <NavIcon name="search" size={sm ? 18 : 20} />
        </span>
        <input
          type="search"
          aria-label="بحث بالاسم أو الرقم"
          placeholder="ابحث باسم المحل أو الجوال"
          autoComplete="off"
          enterKeyHint="search"
          className={`md-input ${sm ? 'min-h-control-sm ps-[38px] text-[15px]' : 'ps-[44px]'}`}
          value={value}
          onChange={(e) => {
            onChange(e.target.value);
          }}
        />
      </label>
    </form>
  );
}

/** No leads at all: start from the field. */
export function NoLeads() {
  const navigate = useNavigate();
  return (
    <EmptyState
      icon="store"
      title="لا عملاء بعد"
      message="سجّل أول محل من الزيارة ليظهر هنا."
      action={
        <Button
          variant="primary"
          icon="plus"
          onClick={() => {
            void navigate('/leads/new');
          }}
        >
          عميل جديد
        </Button>
      }
    />
  );
}

/** Leads exist but none match: say why and offer one way back. */
export function NoResults({ q, onClear }: { q: string; onClear: () => void }) {
  const text = q.trim();
  return (
    <EmptyState
      icon="store"
      title="لا نتائج"
      message={text ? `لا عميل يطابق «${text}». امسح البحث والفلاتر أو جرّب كلمة أخرى.` : 'لا عميل يطابق هذه الفلاتر. امسحها لعرض كل العملاء.'}
      action={
        <Button variant="secondary" onClick={onClear}>
          امسح البحث والفلاتر
        </Button>
      }
    />
  );
}

const SORT_PATHS = {
  none: 'M7 4v16M3 16l4 4 4-4M17 20V4M13 8l4-4 4 4',
  ascending: 'M12 19V5M5 12l7-7 7 7',
  descending: 'M12 5v14M19 12l-7 7-7-7',
} as const;

export function SortGlyph({ state, size = 16 }: { state: keyof typeof SORT_PATHS; size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={SORT_PATHS[state]} />
    </svg>
  );
}

export function Chevron() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M6 9l6 6 6-6" />
    </svg>
  );
}
