import { BottomSheet } from '@/components/ui/BottomSheet';
import { Button } from '@/components/ui/Button';

/** Flow 2.3 «العميل رد»: a confirm, because it cancels the follow-ups. */
export function RepliedSheet({ open, intro, busy, onClose, onConfirm }: { open: boolean; intro: string; busy: boolean; onClose: () => void; onConfirm: () => void }) {
  return (
    <BottomSheet
      open={open}
      onClose={busy ? undefined : onClose}
      title="هل رد العميل؟"
      actions={
        <>
          <Button variant="primary" block loading={busy} onClick={onConfirm}>
            {busy ? 'جارٍ التسجيل' : 'نعم، رد العميل'}
          </Button>
          <Button variant="ghost" block disabled={busy} onClick={onClose}>
            إلغاء
          </Button>
        </>
      }
    >
      <p className="m-0">{intro}</p>
    </BottomSheet>
  );
}
