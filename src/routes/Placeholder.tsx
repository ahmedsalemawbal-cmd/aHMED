import { AppShell, type Section } from '@/components/app/AppShell';
import { EmptyState } from '@/components/ui/EmptyState';

/** Temporary screen for sections built in later phases (keeps navigation working). */
export default function Placeholder({ section, title, phase }: { section: Section; title: string; phase: string }) {
  return (
    <AppShell section={section}>
      <main className="px-4 py-8 desk:px-10">
        <h1 className="m-0 mb-6 text-title-1 font-bold">{title}</h1>
        <EmptyState icon="clock" title="قيد البناء" message={`هذه الشاشة تُبنى في المرحلة ${phase}.`} />
      </main>
    </AppShell>
  );
}
