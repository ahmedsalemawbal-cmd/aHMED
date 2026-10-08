import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState, type ChangeEvent } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { BottomSheet, Button, LeadCard, ScoreBar, scoreLevelText, TextField, Toast, TriSelect, type TriValue } from './index';

describe('Button', () => {
  it('loading disables, keeps the label and announces busy', () => {
    render(
      <Button variant="primary" loading>
        احفظ
      </Button>,
    );
    const btn = screen.getByRole('button', { name: 'احفظ' });
    expect(btn).toBeDisabled();
    expect(btn).toHaveAttribute('aria-busy', 'true');
  });
  it('whatsapp variant carries the chat glyph by default', () => {
    const { container } = render(<Button variant="whatsapp">واتساب</Button>);
    expect(container.querySelector('svg path')?.getAttribute('d')).toMatch(/^M4 19.5/);
  });
});

describe('TriSelect', () => {
  function Harness({ binary = false }: { binary?: boolean }) {
    const [v, setV] = useState<TriValue | null>(null);
    return <TriSelect label="ينشر فيديو أو ريلز" weight={10} value={v} onChange={setV} binary={binary} />;
  }
  it('starts neutral, no default answer', () => {
    render(<Harness />);
    for (const r of screen.getAllByRole('radio')) expect(r).not.toBeChecked();
  });
  it('one tap selects and reports the value', async () => {
    render(<Harness />);
    await userEvent.click(screen.getByLabelText('جزئي'));
    expect(screen.getByLabelText('جزئي')).toBeChecked();
    expect(screen.getByLabelText('جزئي').closest('label')).toHaveClass('is-on', 'md-tri-partial');
  });
  it('binary items offer yes/no only', () => {
    render(<Harness binary />);
    expect(screen.getAllByRole('radio').map((r) => (r as HTMLInputElement).value)).toEqual(['yes', 'no']);
  });
  it('shows the weight in points', () => {
    render(<Harness />);
    expect(screen.getByText('10 نقاط')).toBeInTheDocument();
  });
});

describe('ScoreBar levels match the priority thresholds (<50, 50–69, ≥70)', () => {
  it.each([
    [0, 'فرصة عالية', 'حضور ضعيف'],
    [49, 'فرصة عالية', 'حضور ضعيف'],
    [50, 'فرصة متوسطة', 'حضور متوسط'],
    [69, 'فرصة متوسطة', 'حضور متوسط'],
    [70, 'فرصة منخفضة', 'حضور قوي'],
    [100, 'فرصة منخفضة', 'حضور قوي'],
  ])('%i', (score, opp, pres) => {
    expect(scoreLevelText(score, 'opportunity')).toBe(opp);
    expect(scoreLevelText(score, 'presence')).toBe(pres);
  });
  it('clamps above 100 and below 0, and shows the number with the level text', () => {
    const { container, rerender } = render(<ScoreBar score={104.6} />);
    expect(screen.getByRole('meter')).toHaveAttribute('aria-valuenow', '100');
    expect(container.querySelector('.md-score-value b')).toHaveTextContent('100');
    expect(screen.getByText('فرصة منخفضة')).toBeInTheDocument();
    rerender(<ScoreBar score={-3} />);
    expect(screen.getByRole('meter')).toHaveAttribute('aria-valuenow', '0');
  });
  it('rounds across a level boundary: 49.6 → 50, medium', () => {
    const { container } = render(<ScoreBar score={49.6} />);
    expect(screen.getByRole('meter')).toHaveAttribute('aria-valuenow', '50');
    expect(container.querySelector('.md-score-value b')).toHaveTextContent('50');
    expect(screen.getByText('فرصة متوسطة')).toBeInTheDocument();
  });
});

describe('BottomSheet', () => {
  function Harness({ onClose }: { onClose: () => void }) {
    const [open, setOpen] = useState(false);
    return (
      <>
        <button
          type="button"
          onClick={() => {
            setOpen(true);
          }}
        >
          افتح
        </button>
        <BottomSheet
          open={open}
          title="هل أرسلت الرسالة؟"
          onClose={() => {
            onClose();
            setOpen(false);
          }}
          actions={<Button variant="primary">نعم، أرسلتها</Button>}
        >
          نص
        </BottomSheet>
      </>
    );
  }
  it('renders nothing when closed', () => {
    render(<BottomSheet open={false} title="x" />);
    expect(screen.queryByRole('dialog')).toBeNull();
  });
  it('moves focus in, Escape closes, focus returns to the trigger', async () => {
    const onClose = vi.fn();
    render(<Harness onClose={onClose} />);
    const trigger = screen.getByRole('button', { name: 'افتح' });
    await userEvent.click(trigger);
    expect(screen.getByRole('dialog', { name: 'هل أرسلت الرسالة؟' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'نعم، أرسلتها' })).toHaveFocus();
    await userEvent.keyboard('{Escape}');
    expect(onClose).toHaveBeenCalledTimes(1);
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(trigger).toHaveFocus();
  });
  it('scrim click closes', async () => {
    const onClose = vi.fn();
    const { container } = render(<Harness onClose={onClose} />);
    await userEvent.click(screen.getByRole('button', { name: 'افتح' }));
    const scrim = container.ownerDocument.querySelector('.md-scrim');
    if (!scrim) throw new Error('no scrim');
    fireEvent.click(scrim);
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});

describe('LeadCard', () => {
  it('whole card opens the lead; WhatsApp is a separate button', async () => {
    const onClick = vi.fn();
    const onWhatsApp = vi.fn();
    render(<LeadCard businessName="مطعم ريدان" activity="مطعم" stage="contacted" score={48} priority="hot" onClick={onClick} onWhatsApp={onWhatsApp} />);
    await userEvent.click(screen.getByRole('button', { name: 'واتساب مطعم ريدان' }));
    expect(onWhatsApp).toHaveBeenCalledTimes(1);
    expect(onClick).not.toHaveBeenCalled();
    await userEvent.click(screen.getByText('مطعم ريدان'));
    expect(onClick).toHaveBeenCalledTimes(1);
  });
  it('no WhatsApp button without onWhatsApp (do_not_contact)', () => {
    render(<LeadCard businessName="مطعم" activity="مطعم" stage="visited" score={50} />);
    expect(screen.queryByRole('button', { name: /واتساب/ })).toBeNull();
  });
  it('stage and priority are readable as text, not colour only', () => {
    render(<LeadCard businessName="مطعم" activity="مطعم" stage="replied" score={44} priority="hot" />);
    expect(screen.getByText('رد')).toBeInTheDocument();
    expect(screen.getByText('حار')).toBeInTheDocument();
  });
  it('overdue next action is marked', () => {
    const { container } = render(<LeadCard businessName="م" activity="م" stage="visited" nextAction="أرسل الرسالة الأولى" nextActionAt="متأخرة منذ يومين" overdue />);
    expect(container.querySelector('.md-lead-next')).toHaveClass('is-overdue');
  });
  it('compact card opens with Enter and Space', async () => {
    const onClick = vi.fn();
    render(<LeadCard variant="compact" businessName="حلويات السنبلة" activity="حلويات" value="1,500 ر.س" daysInStage={9} stale onClick={onClick} />);
    const card = screen.getByRole('button');
    card.focus();
    await userEvent.keyboard('{Enter}');
    await userEvent.keyboard(' ');
    expect(onClick).toHaveBeenCalledTimes(2);
    expect(screen.getByText('9 يوم')).toBeInTheDocument();
  });
});

describe('TextField', () => {
  it('error is the accessible description and marks the field invalid', () => {
    render(<TextField label="الجوال" ltr defaultValue="055" error="الرقم ناقص. اكتب 10 أرقام تبدأ بـ 05" hint="تلميح" />);
    const input = screen.getByLabelText('الجوال');
    expect(input).toHaveAccessibleDescription('الرقم ناقص. اكتب 10 أرقام تبدأ بـ 05');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAttribute('dir', 'ltr');
  });
  it('hint stays when error is false', () => {
    render(<TextField label="الجوال" error={false} hint="يُحوَّل تلقائياً إلى 9665… عند الحفظ" />);
    expect(screen.getByLabelText('الجوال')).toHaveAccessibleDescription('يُحوَّل تلقائياً إلى 9665… عند الحفظ');
  });
  it('multiline keeps native props such as onBlur and readOnly', async () => {
    const onBlur = vi.fn();
    render(<TextField label="ملاحظات" multiline onBlur={onBlur} inputMode="text" />);
    const ta = screen.getByLabelText('ملاحظات');
    expect(ta.tagName).toBe('TEXTAREA');
    expect(ta).toHaveAttribute('inputmode', 'text');
    await userEvent.click(ta);
    await userEvent.tab();
    expect(onBlur).toHaveBeenCalledTimes(1);
  });
  it('accepts a standard ChangeEvent handler', async () => {
    const seen: string[] = [];
    const onChange = (e: ChangeEvent<HTMLInputElement>) => {
      seen.push(e.target.value);
    };
    render(<TextField label="اسم المحل" onChange={onChange} />);
    await userEvent.type(screen.getByLabelText('اسم المحل'), 'م');
    expect(seen).toEqual(['م']);
  });
});

describe('Toast', () => {
  it('errors are alerts, others are status', () => {
    const { rerender } = render(<Toast tone="error" title="تعذّر الحفظ" />);
    expect(screen.getByRole('alert')).toHaveTextContent('تعذّر الحفظ');
    rerender(<Toast tone="success" title="تم" />);
    expect(screen.getByRole('status')).toHaveTextContent('تم');
  });
  it('action button calls back', async () => {
    const onAction = vi.fn();
    render(<Toast tone="success" title="المرحلة الآن: تم الإرسال" action="تراجع" onAction={onAction} />);
    await userEvent.click(screen.getByRole('button', { name: 'تراجع' }));
    expect(onAction).toHaveBeenCalledTimes(1);
  });
});
