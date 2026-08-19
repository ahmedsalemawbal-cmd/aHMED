import { useEffect, useState } from 'react';
import { supabase } from '../lib/supabase';
import { Card, Grid, Loading, Stat, Table, Tag, Title } from '../ui/kit';

type Row = { id: number; full_name: string; grade_level: string | null;
             section: string | null; custody_state: string };

const STATE: Record<string, { label: string; tone: 'success' | 'warn' | 'mute' | 'danger' }> = {
  at_school:  { label: 'في المدرسة', tone: 'success' },
  on_bus:     { label: 'في الباص',   tone: 'warn' },
  home:       { label: 'في المنزل',  tone: 'mute' },
  left_early: { label: 'خرج مبكرًا',  tone: 'danger' },
};

export default function Overview() {
  const [rows, setRows] = useState<Row[] | null>(null);

  useEffect(() => {
    void supabase
      .from('sch_students')
      .select('id, full_name, grade_level, section, custody_state')
      .eq('status', 'active')
      .order('full_name')
      .then(({ data }) => setRows(data ?? []));
  }, []);

  if (!rows) return <Loading what="جارٍ قراءة سجلّ اليوم" />;

  const count = (s: string) => rows.filter((r) => r.custody_state === s).length;

  return (
    <>
      <Title sub="لوحة العهدة — أين كل طالب الآن، لا أين كان آخر اليوم.">
        نظرة عامة
      </Title>

      <Grid>
        <Stat label="في المدرسة" value={count('at_school')} unit="طالبًا" tone="success" />
        <Stat label="في الباص"   value={count('on_bus')}    unit="طالبًا" tone="warn" />
        <Stat label="في المنزل"  value={count('home')}      unit="طالبًا" tone="mute" />
        <Stat label="خرج مبكرًا"  value={count('left_early')} unit="طالبًا" tone="danger" />
      </Grid>

      <Card pad={false} style={{ marginTop: 'var(--sch-s5)', overflow: 'hidden' }}>
        <Table
          rows={rows}
          empty="لا يوجد طلاب بعد — ابدأ بالاستيراد أو أضف طالبًا."
          cols={[
            { key: 'name', head: 'الطالب', cell: (r) => (
              <span style={{ fontWeight: 600 }}>{r.full_name}</span>
            ) },
            { key: 'class', head: 'الصف', w: '30%', cell: (r) => (
              <span style={{ color: 'var(--sch-muted)' }}>
                {[r.grade_level, r.section].filter(Boolean).join(' / ') || 'بلا شعبة'}
              </span>
            ) },
            { key: 'state', head: 'الحالة الآن', w: '20%', cell: (r) => {
              const s = STATE[r.custody_state] ?? { label: r.custody_state, tone: 'mute' as const };
              return <Tag tone={s.tone}>{s.label}</Tag>;
            } },
          ]}
        />
      </Card>
    </>
  );
}
