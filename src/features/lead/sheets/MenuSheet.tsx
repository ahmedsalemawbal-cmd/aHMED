import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';
import { Glyph, type GlyphName } from '../Glyph';

export interface MenuItem {
  id: string;
  label: string;
  icon: GlyphName;
  danger?: boolean;
  onSelect: () => void;
}

/** «⋮» on the phone and «المزيد» on the desktop: the lead's less frequent actions. */
export function MenuSheet({ open, items, onClose }: { open: boolean; items: MenuItem[]; onClose: () => void }) {
  return (
    <BottomSheet
      open={open}
      onClose={onClose}
      title="المزيد"
      actions={
        <Button variant="ghost" block onClick={onClose}>
          إلغاء
        </Button>
      }
    >
      <div className="flex flex-col gap-2">
        {items.map((it) => (
          <button
            key={it.id}
            type="button"
            onClick={it.onSelect}
            className={`flex min-h-touch items-center gap-3 rounded-md border-[1.5px] border-line bg-surface-raised px-4 text-start text-label font-bold ${it.danger ? 'text-danger' : 'text-ink'}`}
          >
            <Glyph name={it.icon} />
            <span>{it.label}</span>
          </button>
        ))}
      </div>
    </BottomSheet>
  );
}
