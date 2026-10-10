/**
 * «ثبّت التطبيق»: Chrome, Edge and Samsung Internet fire `beforeinstallprompt`
 * when the PWA is installable (on Android this installs a real app). We keep the
 * event and show our own button; iPhone Safari has no such event, so the user
 * adds the app from the Share menu instead.
 */
export interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  readonly userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform?: string }>;
}

export interface InstallState {
  /** the browser offered installation and we can show the native dialog */
  canPrompt: boolean;
  /** installed during this visit */
  installed: boolean;
}

let deferred: BeforeInstallPromptEvent | null = null;
let installed = false;
let snapshot: InstallState = { canPrompt: false, installed: false };
const listeners = new Set<() => void>();

function update(): void {
  snapshot = { canPrompt: deferred !== null, installed };
  for (const l of listeners) l();
}

function isPromptEvent(e: Event): e is BeforeInstallPromptEvent {
  return 'prompt' in e && typeof e.prompt === 'function' && 'userChoice' in e;
}

/** Call once at startup, before React renders: the browser may fire the event early. */
export function captureInstallPrompt(target: Window): () => void {
  const onPrompt = (e: Event) => {
    if (!isPromptEvent(e)) return;
    e.preventDefault(); // our button replaces the browser's mini-infobar
    deferred = e;
    update();
  };
  const onInstalled = () => {
    deferred = null;
    installed = true;
    update();
  };
  target.addEventListener('beforeinstallprompt', onPrompt);
  target.addEventListener('appinstalled', onInstalled);
  return () => {
    target.removeEventListener('beforeinstallprompt', onPrompt);
    target.removeEventListener('appinstalled', onInstalled);
  };
}

export function subscribeInstall(listener: () => void): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

export function getInstallState(): InstallState {
  return snapshot;
}

/** Opens the browser's install dialog. An event can prompt only once. */
export async function promptInstall(): Promise<'accepted' | 'dismissed' | 'unavailable'> {
  const e = deferred;
  if (!e) return 'unavailable';
  deferred = null;
  update();
  await e.prompt();
  const choice = await e.userChoice;
  return choice.outcome;
}

/** iPhone, iPod, and iPad (which reports itself as a Mac with touch). */
export function isIos(userAgent: string, maxTouchPoints: number): boolean {
  return /iPhone|iPad|iPod/i.test(userAgent) || (/Macintosh/.test(userAgent) && maxTouchPoints > 1);
}

/** Already opened as an installed app: nothing to install. */
export function isStandalone(win: Window): boolean {
  const nav = win.navigator as Navigator & { standalone?: boolean };
  return win.matchMedia('(display-mode: standalone)').matches || nav.standalone === true;
}

/** Test hook: forget the captured event between tests. */
export function resetInstallPromptForTests(): void {
  deferred = null;
  installed = false;
  update();
}
