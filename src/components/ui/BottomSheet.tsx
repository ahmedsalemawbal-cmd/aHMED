import { useEffect, useEffectEvent, useRef, type ReactNode } from 'react';

export interface BottomSheetProps {
  open: boolean;
  onClose?: () => void;
  title?: string;
  children?: ReactNode;
  /** full-width Buttons, primary first */
  actions?: ReactNode;
}

/**
 * Sheet over a scrim. Scrim click or Escape closes it, so never put an
 * irreversible decision here without an explicit confirm button.
 * Focus moves into the sheet on open and back to the trigger on close.
 */
export function BottomSheet({ open, onClose, title, children, actions }: BottomSheetProps) {
  const sheetRef = useRef<HTMLElement>(null);
  const requestClose = useEffectEvent(() => {
    onClose?.();
  });

  useEffect(() => {
    if (!open) return;
    const previous = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const sheet = sheetRef.current;
    const first = sheet?.querySelector<HTMLElement>('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    (first ?? sheet)?.focus();
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') requestClose();
    }
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      previous?.focus();
    };
  }, [open]);

  if (!open) return null;
  return (
    <div className="md-sheet-layer">
      <div className="md-scrim" onClick={onClose} />
      <section ref={sheetRef} className="md-sheet" role="dialog" aria-modal="true" aria-label={title} tabIndex={-1}>
        <div className="md-sheet-grip" aria-hidden="true" />
        {title ? <h2 className="md-sheet-title">{title}</h2> : null}
        {children ? <div className="md-sheet-body">{children}</div> : null}
        {actions ? <div className="md-sheet-actions">{actions}</div> : null}
      </section>
    </div>
  );
}
