/** Wordmark bars in the stage colours, as in design/screens/Login.dc.html. */
const BARS = [
  { h: 'h-[40px]', c: 'bg-stage-visited' },
  { h: 'h-[32px]', c: 'bg-stage-contacted' },
  { h: 'h-[25px]', c: 'bg-stage-replied' },
  { h: 'h-[18px]', c: 'bg-stage-proposal' },
  { h: 'h-[12px]', c: 'bg-stage-won' },
];

export function LogoMark({ small = false }: { small?: boolean }) {
  return (
    <div aria-hidden="true" className={`flex items-end gap-1 ${small ? 'h-6 origin-bottom-right scale-[0.6]' : 'h-[40px]'}`}>
      {BARS.map((b) => (
        <span key={b.c} className={`w-3 rounded-[var(--space-1)] ${b.h} ${b.c}`} />
      ))}
    </div>
  );
}
