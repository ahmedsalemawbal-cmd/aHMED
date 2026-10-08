import { TriSelect } from '@/components/ui/TriSelect';
import type { Answer, Checklist } from '@/lib/scoring';
import { groupItems } from './helpers';

export function Step2Assessment({
  checklist,
  activityName,
  answers,
  onAnswer,
}: {
  checklist: Checklist;
  activityName: string;
  answers: Record<string, Answer>;
  onAnswer: (id: string, v: Answer) => void;
}) {
  return (
    <div className="flex flex-col gap-[28px]">
      {groupItems(checklist).map((g) => (
        <section key={g.title} aria-label={g.title} className="flex flex-col gap-5">
          <h2 className="m-0 text-label-sm text-ink-muted">{g.title}</h2>
          {g.items.map((it) => (
            <TriSelect
              key={it.id}
              name={`tri-${it.id}`}
              label={it.label}
              weight={it.weight}
              value={answers[it.id] ?? null}
              onChange={(v) => {
                onAnswer(it.id, v);
              }}
            />
          ))}
        </section>
      ))}
      {checklist.specific.length ? (
        <section aria-label="بنود خاصة بالنشاط" className="flex flex-col gap-5">
          <h2 className="m-0 text-label-sm text-ink-muted">{`بنود خاصة بنشاط «${activityName}» · لا تدخل في الدرجة`}</h2>
          {checklist.specific.map((it) => (
            <TriSelect
              key={it.id}
              name={`tri-${it.id}`}
              label={it.label}
              binary
              value={answers[it.id] ?? null}
              onChange={(v) => {
                onAnswer(it.id, v);
              }}
            />
          ))}
        </section>
      ) : null}
    </div>
  );
}
