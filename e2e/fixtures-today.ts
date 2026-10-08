// Realistic Arabic data from design/screens/Main.dc.html and DeskToday.dc.html.
const H = 3600 * 1000;

/** Midnight in Riyadh (UTC+3) of the day containing `d`. */
function riyadhDayStart(d: Date): number {
  const OFFSET = 3 * H;
  return Math.floor((d.getTime() + OFFSET) / (24 * H)) * 24 * H - OFFSET;
}

export function todayFixtures(now = new Date()) {
  const iso = (ms: number) => new Date(now.getTime() + ms).toISOString();
  // tasks are anchored to the Riyadh calendar day so the test does not depend on the hour it runs
  const day = riyadhDayStart(now);
  const at = (days: number, hour: number) => new Date(day + days * 24 * H + hour * H).toISOString();
  const lead = (id: string, name: string, stage: string, contact: string, dnc = false) => ({
    id,
    business_name: name,
    stage,
    phone_e164: '9665512345' + id.slice(-2),
    do_not_contact: dnc,
    contact_name: contact,
  });
  const tasks = [
    { id: 't1', title: 'أرسل الرسالة الأولى', kind: 'first_message', due_at: at(-2, 16), lead: lead('l01', 'كافيه نسمة الحمدانية', 'visited', 'سارة'), messages: [] },
    {
      id: 't2', title: 'متابعة أولى', kind: 'followup_3', due_at: at(0, 0.02), lead: lead('l02', 'مطعم الديرة', 'contacted', 'سلمان'),
      messages: [{ body: 'هلا أستاذ سلمان، جهزت لك عينة ريل من تصوير يوم الزيارة…', status: 'draft', created_at: iso(-72 * H) }],
    },
    {
      id: 't3', title: 'متابعة ثانية وأخيرة', kind: 'followup_7', due_at: at(0, 20), lead: lead('l03', 'مطعم بيت المندي', 'contacted', 'فهد'),
      messages: [{ body: 'أستاذ فهد، ما أبي أثقل عليك، لو حاب نكمل الكلام…', status: 'draft', created_at: iso(-24 * H) }],
    },
    { id: 't4', title: 'حدد اجتماعاً', kind: 'schedule_meeting', due_at: at(0, 21), lead: lead('l04', 'مجمع ابتسامة لطب الأسنان', 'replied', 'ريم'), messages: [] },
  ];
  const hot = [
    {
      id: 'l04', business_name: 'مجمع ابتسامة لطب الأسنان', contact_name: 'د. ريم', stage: 'replied', score: 44, phone_e164: '966551234504', do_not_contact: false,
      last_contact_at: iso(-20 * H), activity: { name: 'مركز أسنان' }, tasks: [{ title: 'حدد اجتماعاً', due_at: at(0, 21), done_at: null, cancelled_at: null }],
    },
  ];
  return { tasks, hot };
}
