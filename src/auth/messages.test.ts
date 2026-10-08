import { describe, expect, it } from 'vitest';
import { signInErrorMessage } from './messages';

describe('signInErrorMessage', () => {
  it('wrong credentials: what happened + what to do', () => {
    expect(signInErrorMessage({ message: 'Invalid login credentials', status: 400 })).toBe(
      'البريد أو كلمة المرور غير صحيحة. تأكد منهما وحاول مرة أخرى.',
    );
  });
  it('network failure', () => {
    expect(signInErrorMessage({ message: 'fetch failed', name: 'AuthRetryableFetchError' })).toBe(
      'تعذّر الاتصال بالخادم. تأكد من الشبكة وحاول مرة أخرى.',
    );
  });
  it('rate limited', () => {
    expect(signInErrorMessage({ message: 'Too many requests', status: 429 })).toBe('محاولات كثيرة. انتظر دقيقة ثم حاول مرة أخرى.');
  });
  it('never leaks the raw English message', () => {
    expect(signInErrorMessage({ message: 'Something odd', status: 500 })).toBe('تعذّر الدخول. حاول مرة أخرى بعد قليل.');
  });
});
