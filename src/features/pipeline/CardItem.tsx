import type { DragEvent } from 'react';
import { LeadCard } from '@/components/ui/LeadCard';
import { Glyph } from '@/features/lead/Glyph';
import type { PipelineCard } from './board';
import { useLongPress } from './useLongPress';

export interface CardDrag {
  dragging: boolean;
  onDragStart: (e: DragEvent<HTMLElement>) => void;
  onDragEnd: () => void;
}

/**
 * One compact LeadCard: a tap opens the lead; a long press, right-click or
 * the Menu key opens «تغيير المرحلة», and so does the visible «⋮» button
 * (always shown on the phone; on the desktop on hover or keyboard focus).
 * On the desktop the card is also dragged between columns.
 */
export function CardItem({ card, desktop, drag, onOpen, onMenu }: { card: PipelineCard; desktop: boolean; drag?: CardDrag; onOpen: () => void; onMenu: () => void }) {
  const press = useLongPress(onMenu);
  return (
    <div
      data-lead={card.id}
      className={`group relative select-none [-webkit-touch-callout:none] ${desktop ? 'cursor-grab active:cursor-grabbing' : '[&_.md-lead-titles]:pe-10'} ${drag?.dragging ? 'opacity-40' : ''}`}
      draggable={drag ? true : undefined}
      onDragStart={drag?.onDragStart}
      onDragEnd={drag?.onDragEnd}
      {...press}
    >
      <LeadCard
        variant="compact"
        businessName={card.businessName}
        activity={card.activity}
        value={card.value}
        daysInStage={card.daysInStage}
        stale={card.stale}
        onClick={onOpen}
      />
      <button
        type="button"
        aria-haspopup="dialog"
        aria-label={`تغيير المرحلة: ${card.businessName}`}
        title="تغيير المرحلة"
        onClick={onMenu}
        className={`absolute end-1 top-1 grid place-items-center rounded-md border-0 text-ink-muted ${desktop ? 'size-10 bg-surface-raised opacity-0 focus-visible:opacity-100 group-focus-within:opacity-100 group-hover:opacity-100' : 'size-touch bg-transparent'}`}
      >
        <Glyph name="more" size={20} strokeWidth={2.6} />
      </button>
    </div>
  );
}
