import { afterEach, describe, expect, it, vi } from 'vitest';
import { captureInstallPrompt, getInstallState, isIos, promptInstall, resetInstallPromptForTests, subscribeInstall } from './install-prompt';

function fakePromptEvent(outcome: 'accepted' | 'dismissed') {
  const e = new Event('beforeinstallprompt', { cancelable: true });
  const prompt = vi.fn(() => Promise.resolve());
  Object.assign(e, { prompt, userChoice: Promise.resolve({ outcome, platform: 'web' }) });
  return { e, prompt };
}

describe('install prompt', () => {
  let stop: () => void = () => undefined;
  afterEach(() => {
    stop();
    resetInstallPromptForTests();
  });

  it('keeps the browser event, suppresses its mini-infobar, and prompts once', async () => {
    stop = captureInstallPrompt(window);
    const seen = vi.fn();
    const unsub = subscribeInstall(seen);
    expect(getInstallState().canPrompt).toBe(false);
    const { e, prompt } = fakePromptEvent('accepted');
    window.dispatchEvent(e);
    expect(e.defaultPrevented).toBe(true);
    expect(getInstallState()).toEqual({ canPrompt: true, installed: false });
    expect(seen).toHaveBeenCalled();
    await expect(promptInstall()).resolves.toBe('accepted');
    expect(prompt).toHaveBeenCalledTimes(1);
    expect(getInstallState().canPrompt).toBe(false);
    await expect(promptInstall()).resolves.toBe('unavailable');
    unsub();
  });

  it('a dismissed dialog is reported; installation hides everything', async () => {
    stop = captureInstallPrompt(window);
    window.dispatchEvent(fakePromptEvent('dismissed').e);
    await expect(promptInstall()).resolves.toBe('dismissed');
    window.dispatchEvent(new Event('appinstalled'));
    expect(getInstallState()).toEqual({ canPrompt: false, installed: true });
  });

  it('ignores look-alike events without prompt()', () => {
    stop = captureInstallPrompt(window);
    window.dispatchEvent(new Event('beforeinstallprompt', { cancelable: true }));
    expect(getInstallState().canPrompt).toBe(false);
  });

  it('detects iPhone and iPad (iPad reports a touch Mac)', () => {
    expect(isIos('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)', 5)).toBe(true);
    expect(isIos('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 5)).toBe(true);
    expect(isIos('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 0)).toBe(false);
    expect(isIos('Mozilla/5.0 (Linux; Android 14; Pixel 7)', 5)).toBe(false);
  });
});
