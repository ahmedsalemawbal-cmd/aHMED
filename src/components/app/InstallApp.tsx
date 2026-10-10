import { useState, useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/Button';
import { getInstallState, isIos, isStandalone, promptInstall, subscribeInstall } from '@/lib/install-prompt';

/**
 * One tap to install ميداني on the phone: the browser's own install dialog where it
 * exists (Android Chrome installs a real app), the Share-menu steps on iPhone, and
 * nothing once the app runs installed.
 */
export function InstallApp() {
  const state = useSyncExternalStore(subscribeInstall, getInstallState, getInstallState);
  const [note, setNote] = useState<string | null>(null);

  if (isStandalone(window) || state.installed) {
    return note ? <p className="m-0 text-center text-label-sm text-success" role="status">{note}</p> : null;
  }
  if (state.canPrompt) {
    return (
      <Button
        variant="secondary"
        block
        onClick={() => {
          void promptInstall().then((outcome) => {
            setNote(outcome === 'accepted' ? 'جارٍ تثبيت ميداني على جوالك.' : 'لم يُثبَّت. ثبّته لاحقاً من قائمة المتصفح.');
          });
        }}
      >
        ثبّت التطبيق على جوالك
      </Button>
    );
  }
  if (note) return <p className="m-0 text-center text-label-sm text-ink-muted" role="status">{note}</p>;
  if (isIos(navigator.userAgent, navigator.maxTouchPoints)) {
    return (
      <p className="m-0 text-center text-label-sm text-ink-muted">
        للتثبيت على الآيفون: افتح الرابط في Safari، ثم اضغط زر المشاركة واختر «إضافة إلى الشاشة الرئيسية».
      </p>
    );
  }
  return null;
}
