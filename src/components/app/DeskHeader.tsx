import { useState, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { NavIcon } from './NavIcon';

/** Desktop page header: title, optional search, and the fixed «عميل جديد» button. */
export function DeskHeader({ title, subtitle, search = true, extra }: { title: ReactNode; subtitle?: ReactNode; search?: boolean; extra?: ReactNode }) {
  const navigate = useNavigate();
  const [q, setQ] = useState('');
  return (
    <header className="flex flex-wrap items-center gap-4 border-b border-line bg-surface-raised px-10 py-5">
      <div className="flex grow flex-col">
        <h1 className="m-0 text-title-1 font-bold">{title}</h1>
        {subtitle ? <span className="text-body-sm text-ink-muted">{subtitle}</span> : null}
      </div>
      {extra}
      {search ? (
        <form
          role="search"
          className="relative block min-w-[200px] flex-[0_1_320px]"
          onSubmit={(e) => {
            e.preventDefault();
            void navigate(`/leads?q=${encodeURIComponent(q.trim())}`);
          }}
        >
          <span className="pointer-events-none absolute start-3 top-1/2 flex -translate-y-1/2 text-ink-muted">
            <NavIcon name="search" size={18} />
          </span>
          <input
            type="search"
            aria-label="بحث"
            placeholder="ابحث عن محل أو رقم"
            className="md-input min-h-control-sm ps-[38px] text-[15px]"
            value={q}
            onChange={(e) => {
              setQ(e.target.value);
            }}
          />
        </form>
      ) : null}
      <button
        type="button"
        className="md-btn md-btn-primary md-btn-sm"
        onClick={() => {
          void navigate('/leads/new');
        }}
      >
        <NavIcon name="plus" size={20} />
        <span>عميل جديد</span>
      </button>
    </header>
  );
}
