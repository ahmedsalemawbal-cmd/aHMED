/**
 * Osoul Mail — جرس التنبيه عند وصول رسالة.
 *
 * الصوت مُولَّد بـ Web Audio لا ملف صوتي: لا حجم يُضاف للتطبيق، ولا فرق بين
 * ويندوز وماك، ويعمل بلا إنترنت. الجرس ضربات متتابعة على مدى خمس ثوانٍ حتى
 * يُسمع من غرفة أخرى، لا رنّة واحدة تمرّ دون انتباه.
 *
 * المتصفحات (وElectron معها) تمنع تشغيل الصوت قبل أول تفاعل من المستخدم،
 * ولذلك نفتح السياق الصوتي عند الدخول — وهو تفاعل حقيقي — ونبقيه مفتوحًا.
 */

const RING_SECONDS = 5;      // مدة الرنين الكاملة
const STRIKE_EVERY = 0.85;   // الفاصل بين ضربة وأخرى
const BASE_HZ = 880;         // نغمة الجرس الأساسية

/** توافقيات الجرس: نِسَب غير صحيحة عمدًا، فهي ما يميّز رنين المعدن من الصفير. */
const PARTIALS = [
  { ratio: 1.0, gain: 1.0, decay: 1.6 },
  { ratio: 2.0, gain: 0.55, decay: 1.2 },
  { ratio: 2.76, gain: 0.35, decay: 0.9 },
  { ratio: 3.98, gain: 0.22, decay: 0.65 },
  { ratio: 5.42, gain: 0.13, decay: 0.45 },
];

let ctx = null;
let master = null;
let stopAt = 0;
let timers = [];

function context() {
  if (ctx) return ctx;
  const AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return null;
  try {
    ctx = new AC();
    master = ctx.createGain();
    master.gain.value = 0.9;
    master.connect(ctx.destination);
  } catch (_) {
    ctx = null;
  }
  return ctx;
}

/**
 * فتح السياق الصوتي. يُستدعى عند أول تفاعل حقيقي (تسجيل الدخول) لأن
 * التشغيل التلقائي ممنوع قبل ذلك.
 */
export function unlock() {
  const c = context();
  if (c && c.state === 'suspended') c.resume().catch(() => {});
}

/** ضربة جرس واحدة تبدأ عند اللحظة المحددة. */
function strike(c, at, level) {
  for (const p of PARTIALS) {
    const osc = c.createOscillator();
    const gain = c.createGain();

    osc.type = 'sine';
    osc.frequency.value = BASE_HZ * p.ratio;

    // هجوم سريع ثم اضمحلال أسّي — هذا ما يجعلها ضربة لا نغمة مستمرة.
    const peak = level * p.gain * 0.28;
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(peak, at + 0.004);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + p.decay);

    osc.connect(gain);
    gain.connect(master);
    osc.start(at);
    osc.stop(at + p.decay + 0.05);
  }
}

/**
 * تشغيل الجرس لخمس ثوانٍ. استدعاؤه أثناء الرنين يمدّد المدة ولا يضاعف الصوت.
 * @param {number} [seconds]
 */
export function ringBell(seconds) {
  const c = context();
  if (!c) return;
  if (c.state === 'suspended') c.resume().catch(() => {});

  const total = Math.max(0.5, Number(seconds) || RING_SECONDS);
  const now = c.currentTime;

  // رسالة ثانية تصل أثناء الرنين: نمدّد لا نبدأ رنينًا فوق رنين.
  if (now < stopAt) {
    stopAt = Math.max(stopAt, now + total);
    return;
  }
  stopAt = now + total;

  const count = Math.max(1, Math.ceil(total / STRIKE_EVERY));
  for (let i = 0; i < count; i++) {
    const at = now + i * STRIKE_EVERY;
    if (at >= stopAt) break;
    // الضربات الأخيرة أخفت قليلًا فلا ينتهي الرنين ببتر مفاجئ.
    strike(c, at, i >= count - 2 ? 0.75 : 1);
  }
}

/** إسكات الجرس فورًا (عند فتح الرسالة مثلًا). */
export function stopBell() {
  if (!ctx || !master) return;
  const now = ctx.currentTime;
  stopAt = 0;
  try {
    master.gain.cancelScheduledValues(now);
    master.gain.setValueAtTime(master.gain.value, now);
    master.gain.exponentialRampToValueAtTime(0.0001, now + 0.08);
    // نعيد مستوى الصوت بعد الإخفات استعدادًا للرنّة القادمة.
    master.gain.setValueAtTime(0.9, now + 0.12);
  } catch (_) {
    /* سياق مغلق */
  }
  timers.forEach(clearTimeout);
  timers = [];
}

/** هل الجرس يرنّ الآن؟ */
export function isRinging() {
  return !!ctx && ctx.currentTime < stopAt;
}
