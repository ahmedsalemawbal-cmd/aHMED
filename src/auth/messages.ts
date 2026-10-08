/** Arabic error text: what happened, then what to do. */
export function signInErrorMessage(error: { message: string; status?: number; name?: string }): string {
  const msg = error.message.toLowerCase();
  if (msg.includes('invalid login credentials') || error.status === 400) {
    return 'البريد أو كلمة المرور غير صحيحة. تأكد منهما وحاول مرة أخرى.';
  }
  if (msg.includes('email not confirmed')) {
    return 'البريد لم يُفعَّل بعد. فعّله من الرسالة التي وصلتك ثم ادخل.';
  }
  if (msg.includes('fetch') || msg.includes('network') || error.name === 'AuthRetryableFetchError') {
    return 'تعذّر الاتصال بالخادم. تأكد من الشبكة وحاول مرة أخرى.';
  }
  if (error.status === 429) {
    return 'محاولات كثيرة. انتظر دقيقة ثم حاول مرة أخرى.';
  }
  return 'تعذّر الدخول. حاول مرة أخرى بعد قليل.';
}
