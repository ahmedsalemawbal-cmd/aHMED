"""
إثبات استعلامات شاشة حضور المعلمين على قاعدة بيانات فعلية.

لا MySQL في هذه البيئة، فتُبنى مرآةٌ لأعمدة `class-activator.php` في
SQLite ويُشغَّل عليها **نصّ الاستعلامات نفسه** بعد ثلاث ترجمات لا تمسّ
منطقه: `INSERT IGNORE` ← `INSERT OR IGNORE` · `%s/%d` ← `?` ·
`{prefix}` محذوفة. فما يُثبَت هنا هو المنطق لا الصياغة.

والأسئلة التي يجيبها:
  ① الكشف يحمل حالة كل موظف ونصاب يومه وكم حصة منه بلا بديل
  ② الإجازة المعتمدة تسبق ما في جدول الحضور
  ③ الإغلاق يكتب «غياب» للغائب و«إجازة» لمن أُذِن له، ولا يمسّ قرارًا قائمًا
  ④ الضغطة الثانية على الإغلاق تكتب صفرًا
  ⑤ الغائب والمُجاز خارج الترشيح، ومن يومه فارغ داخله
  ⑥ من لا مادة له ولا حصة (الإدارة) لا يُرشَّح بديلًا
  ⑦ المرشّح المشغول في تلك الحصة لا يُرشَّح ثانيةً — وهو العطل الذي
     كان يجعل معلمَين غائبَين يأخذان البديل نفسه
  ⑧ لوح التغطية يُبنى من الجدول لا من جدول الاحتياط
"""

import sqlite3

c = sqlite3.connect(':memory:')
c.row_factory = sqlite3.Row

c.executescript("""
CREATE TABLE users (ID INTEGER PRIMARY KEY, display_name TEXT, user_email TEXT);
CREATE TABLE employees (id INTEGER PRIMARY KEY, user_id INT, employee_no TEXT,
  job_title TEXT, department TEXT, status TEXT);
CREATE TABLE staff_attendance (id INTEGER PRIMARY KEY, user_id INT, att_date TEXT,
  status TEXT, method TEXT, checked_at TEXT, checkout_at TEXT, minutes_late INT DEFAULT 0,
  note TEXT, recorded_by INT, created_at TEXT, UNIQUE(user_id, att_date));
CREATE TABLE leaves (id INTEGER PRIMARY KEY, user_id INT, start_date TEXT, end_date TEXT, status TEXT);
CREATE TABLE classes (id INTEGER PRIMARY KEY, stage TEXT, grade_level TEXT, section TEXT);
CREATE TABLE subjects (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE class_subjects (id INTEGER PRIMARY KEY, teacher_user_id INT, subject_id INT);
CREATE TABLE timetable (id INTEGER PRIMARY KEY, class_id INT, subject_id INT,
  teacher_user_id INT, day_of_week INT, period_no INT, UNIQUE(class_id, day_of_week, period_no));
CREATE TABLE substitutions (id INTEGER PRIMARY KEY, sub_date TEXT, class_id INT, period_no INT,
  subject_id INT, original_teacher_id INT, substitute_teacher_id INT, reason TEXT,
  status TEXT, assigned_by INT, created_at TEXT, UNIQUE(sub_date, class_id, period_no));
""")

TODAY, DAY = '2026-08-20', 5   # الخميس
NOW = TODAY + ' 12:00:00'

# ── الموظفون ────────────────────────────────────────────────────────
staff = [
    (1, 'فهد القحطاني',  'مدير المدرسة',    'إدارة',      'active'),
    (2, 'محمد عوبل',     'معلم رياضيات',    'رياضيات',    'active'),
    (3, 'وداد عوبل',     'معلمة لغة عربية', 'لغة عربية',  'active'),
    (4, 'نورة الحربي',   'معلمة رياضيات',   'رياضيات',    'active'),
    (5, 'خالد العتيبي',  'معلم علوم',       'علوم',       'active'),
    (6, 'أمل المطيري',   'معلمة اجتماعيات', 'اجتماعيات',  'active'),
    (7, 'موظف موقوف',    'معلم',            'علوم',       'suspended'),
]
for uid, name, job, dept, st in staff:
    c.execute("INSERT INTO users VALUES (?,?,?)", (uid, name, f'u{uid}@x.sa'))
    c.execute("INSERT INTO employees (user_id,employee_no,job_title,department,status) VALUES (?,?,?,?,?)",
              (uid, f'8420{uid:02d}', job, dept, st))

c.execute("INSERT INTO classes VALUES (10,'ابتدائي','الرابع','أ')")
c.execute("INSERT INTO classes VALUES (11,'ابتدائي','الخامس','ب')")
c.executescript("INSERT INTO subjects VALUES (1,'رياضيات'); INSERT INTO subjects VALUES (2,'لغة عربية');"
                "INSERT INTO subjects VALUES (3,'علوم');")

# جدول الخميس: محمد ثلاث حصص · وداد حصتان · نورة وخالد وأمل مشغولون جزئيًّا
slots = [
    (10, 1, 2, DAY, 1), (11, 1, 2, DAY, 2), (10, 1, 2, DAY, 3),   # محمد (غائب لاحقًا)
    (11, 2, 3, DAY, 1), (10, 2, 3, DAY, 4),                        # وداد (إجازة لاحقًا)
    (11, 3, 5, DAY, 3),                                            # خالد مشغول في الحصة ٣
    (10, 3, 6, DAY, 5),                                            # أمل مشغولة في الحصة ٥
]
for cls, subj, teacher, d, p in slots:
    c.execute("INSERT INTO timetable (class_id,subject_id,teacher_user_id,day_of_week,period_no)"
              " VALUES (?,?,?,?,?)", (cls, subj, teacher, d, p))

# نورة تدرّس الرياضيات (لترشيحها لنفس التخصص) — بلا حصة الخميس فهي متفرغة
c.execute("INSERT INTO class_subjects (teacher_user_id,subject_id) VALUES (4,1)")

# البوابة رصدت اثنين، ووداد لها إجازة معتمدة
SCAN = TODAY + ' 06:38:00'
c.execute("INSERT INTO staff_attendance (user_id,att_date,status,method,checked_at,minutes_late,created_at)"
          " VALUES (1,?,'present','qr',?,0,?)", (TODAY, TODAY + ' 06:38:00', SCAN))
c.execute("INSERT INTO staff_attendance (user_id,att_date,status,method,checked_at,minutes_late,created_at)"
          " VALUES (4,?,'late','qr',?,14,?)", (TODAY, TODAY + ' 07:24:00', TODAY + ' 07:24:00'))
# خالد مسح دخوله — فيبقى مرشّحًا بديلًا، ونختبر إسقاطه من حصّته المشغولة
c.execute("INSERT INTO staff_attendance (user_id,att_date,status,method,checked_at,minutes_late,created_at)"
          " VALUES (5,?,'present','qr',?,0,?)", (TODAY, TODAY + ' 06:51:00', TODAY + ' 06:51:00'))
c.execute("INSERT INTO leaves (user_id,start_date,end_date,status) VALUES (3,?,?,'approved')", (TODAY, TODAY))
c.commit()

# ── ① الكشف ─────────────────────────────────────────────────────────
SHEET = """
SELECT u.ID AS user_id, u.display_name, e.job_title, e.department, e.employee_no,
       a.status, a.method, a.checked_at, a.minutes_late,
       (SELECT COUNT(*) FROM leaves l
         WHERE l.user_id = u.ID AND l.status = 'approved'
           AND ? BETWEEN l.start_date AND l.end_date) AS on_leave,
       (SELECT COUNT(*) FROM timetable t
         WHERE t.teacher_user_id = u.ID AND t.day_of_week = ?) AS lessons,
       (SELECT COUNT(*) FROM substitutions sb
         WHERE sb.sub_date = ? AND sb.original_teacher_id = u.ID
           AND sb.substitute_teacher_id IS NOT NULL) AS cover_done
FROM employees e
INNER JOIN users u ON u.ID = e.user_id
LEFT JOIN staff_attendance a ON a.user_id = u.ID AND a.att_date = ?
WHERE e.status = 'active'
ORDER BY u.display_name
"""

def sheet():
    rows = [dict(r) for r in c.execute(SHEET, (TODAY, DAY, TODAY, TODAY))]
    for r in rows:
        r['state'] = 'leave' if r['on_leave'] else (r['status'] or 'none')
        r['needs'] = r['state'] in ('absent', 'leave') and r['lessons'] > 0
        r['cover_open'] = max(0, r['lessons'] - r['cover_done']) if r['needs'] else 0
    return {r['display_name']: r for r in rows}

ok, fail = 0, 0
def check(label, got, want):
    global ok, fail
    if got == want:
        ok += 1
        print(f'  ✓ {label}')
    else:
        fail += 1
        print(f'  ✗ {label}\n      المتوقَّع: {want}\n      الناتج:   {got}')

print('① الكشف قبل الإغلاق')
s = sheet()
check('الموقوف لا يظهر', 'موظف موقوف' in s, False)
check('الموظفون النشطون ستة', len(s), 6)
check('المدير حاضر بالمسح', (s['فهد القحطاني']['state'], s['فهد القحطاني']['method']), ('present', 'qr'))
check('نورة متأخرة ١٤ دقيقة', (s['نورة الحربي']['state'], s['نورة الحربي']['minutes_late']), ('late', 14))
check('محمد بانتظار المسح', s['محمد عوبل']['state'], 'none')
check('نصاب محمد اليوم ٣ حصص', s['محمد عوبل']['lessons'], 3)
check('المدير بلا حصص', s['فهد القحطاني']['lessons'], 0)

print('\n② الإجازة المعتمدة تسبق جدول الحضور')
check('وداد «إجازة» بلا صفّ حضور', (s['وداد عوبل']['state'], s['وداد عوبل']['status']), ('leave', None))
check('ولها حصّتان بلا بديل', s['وداد عوبل']['cover_open'], 2)

# ── ③ الإغلاق ───────────────────────────────────────────────────────
CLOSE = """
INSERT OR IGNORE INTO staff_attendance (user_id, att_date, status, method, recorded_by, created_at)
SELECT e.user_id, ?,
       CASE WHEN EXISTS (
                SELECT 1 FROM leaves l
                WHERE l.user_id = e.user_id AND l.status = ?
                  AND ? BETWEEN l.start_date AND l.end_date
            ) THEN ? ELSE ? END,
       ?, NULLIF(?, 0), ?
FROM employees e
LEFT JOIN staff_attendance a ON a.user_id = e.user_id AND a.att_date = ?
WHERE e.status = ? AND a.id IS NULL
"""
ARGS = (TODAY, 'approved', TODAY, 'leave', 'absent', 'manual', 9, NOW, TODAY, 'active')

print('\n③ الإغلاق')
n1 = c.execute(CLOSE, ARGS).rowcount
c.commit()
check('كُتب ثلاثة صفوف (محمد · وداد · أمل)', n1, 3)

s = sheet()
check('محمد صار غائبًا يدويًّا', (s['محمد عوبل']['state'], s['محمد عوبل']['method']), ('absent', 'manual'))
check('وداد «إجازة» لا «غياب»', s['وداد عوبل']['status'], 'leave')
check('المدير لم يتغيّر (مسحه قائم)', (s['فهد القحطاني']['status'], s['فهد القحطاني']['method']), ('present', 'qr'))
check('نورة بقيت متأخرة لا غائبة', s['نورة الحربي']['status'], 'late')

fresh = [r['user_id'] for r in c.execute(
    "SELECT user_id FROM staff_attendance WHERE att_date = ? AND created_at = ? AND method = 'manual'"
    " AND status IN ('absent','leave')", (TODAY, NOW))]
check('الاقتراح يقع على الغائب والمُجاز وحدهما', sorted(fresh), [2, 3, 6])

print('\n④ الضغطة الثانية')
n2 = c.execute(CLOSE, ARGS).rowcount
c.commit()
check('لا تكتب شيئًا', n2, 0)

# ── ⑤⑥ ترشيح البدلاء ────────────────────────────────────────────────
CANDS = """
SELECT u.ID AS user_id, u.display_name, e.department,
       (SELECT COUNT(*) FROM class_subjects cs
         WHERE cs.teacher_user_id = u.ID AND cs.subject_id = ?) AS same_subject,
       (SELECT COUNT(*) FROM timetable tt WHERE tt.teacher_user_id = u.ID) AS load_count,
       (SELECT COUNT(*) FROM substitutions sb
         WHERE sb.substitute_teacher_id = u.ID AND sb.sub_date = ?) AS today_subs
FROM users u
INNER JOIN employees e ON e.user_id = u.ID AND e.status = 'active'
WHERE u.ID NOT IN (SELECT t.teacher_user_id FROM timetable t
                   WHERE t.day_of_week = ? AND t.period_no = ? AND t.teacher_user_id IS NOT NULL)
  AND u.ID NOT IN (SELECT sb2.substitute_teacher_id FROM substitutions sb2
                   WHERE sb2.sub_date = ? AND sb2.period_no = ?
                     AND sb2.substitute_teacher_id IS NOT NULL)
  AND u.ID NOT IN (SELECT l.user_id FROM leaves l
                   WHERE l.status = 'approved' AND ? BETWEEN l.start_date AND l.end_date)
  AND u.ID NOT IN (SELECT a.user_id FROM staff_attendance a
                   WHERE a.att_date = ? AND a.status IN ('absent','leave'))
  AND (EXISTS (SELECT 1 FROM class_subjects cx WHERE cx.teacher_user_id = u.ID)
    OR EXISTS (SELECT 1 FROM timetable tx WHERE tx.teacher_user_id = u.ID))
ORDER BY same_subject DESC, today_subs ASC, load_count ASC, u.display_name
LIMIT ?
"""

def cands(period, subject, limit=8):
    return [r['display_name'] for r in c.execute(
        CANDS, (subject, TODAY, DAY, period, TODAY, period, TODAY, TODAY, limit))]

print('\n⑤ ترشيح البدلاء')
c1 = cands(1, 1)
check('المرشّحون للحصة ١: نورة وخالد', sorted(c1), ['خالد العتيبي', 'نورة الحربي'])
check('نورة أولًا — نفس التخصص', c1[0], 'نورة الحربي')
check('الغائب والمُجاز خارج الترشيح', ('محمد عوبل' in c1) or ('وداد عوبل' in c1), False)
check('أمل غابت بالإغلاق فخرجت', 'أمل المطيري' in c1, False)
check('نورة بلا حصةٍ واحدة في الجدول ومع ذلك تُرشَّح', 'نورة الحربي' in c1, True)

print('\n⑥ من لا مادة له ولا حصة لا يُرشَّح')
check('المدير خارج الترشيح', 'فهد القحطاني' in cands(1, 1), False)
check('خالد مشغول في الحصة ٣ فيسقط منها', 'خالد العتيبي' in cands(3, 1), False)
check('وهو مرشَّح في الحصة ١', 'خالد العتيبي' in cands(1, 1), True)

print('\n⑦ البديل المشغول لا يُرشَّح مرّتين لنفس الحصة')
before = cands(1, 1)
c.execute("INSERT INTO substitutions (sub_date,class_id,period_no,subject_id,original_teacher_id,"
          "substitute_teacher_id,reason,status,created_at) VALUES (?,?,?,?,?,?,?,?,?)",
          (TODAY, 10, 1, 1, 2, 4, 'absence', 'assigned', NOW))
c.commit()
after = cands(1, 1)
check('نورة كانت مرشَّحة', 'نورة الحربي' in before, True)
check('وبعد إسنادها للحصة ١ خرجت منها', 'نورة الحربي' in after, False)
check('وتبقى مرشَّحة لحصةٍ أخرى', 'نورة الحربي' in cands(2, 1), True)

# ── ⑧ لوح التغطية ───────────────────────────────────────────────────
PANEL = """
SELECT t.class_id, t.period_no, t.subject_id, c.grade_level, c.section, s.name AS subject_name,
       sb.id AS sub_id, sb.substitute_teacher_id, v.display_name AS substitute_name
FROM timetable t
INNER JOIN classes c ON c.id = t.class_id
LEFT JOIN subjects s ON s.id = t.subject_id
LEFT JOIN substitutions sb ON sb.sub_date = ? AND sb.class_id = t.class_id AND sb.period_no = t.period_no
LEFT JOIN users v ON v.ID = sb.substitute_teacher_id
WHERE t.teacher_user_id = ? AND t.day_of_week = ?
ORDER BY t.period_no
"""

print('\n⑧ لوح التغطية')
panel = [dict(r) for r in c.execute(PANEL, (TODAY, 2, DAY))]
check('ثلاث حصص لمحمد', len(panel), 3)
check('الأولى مُغطّاة بنورة', (panel[0]['period_no'], panel[0]['substitute_name']), (1, 'نورة الحربي'))
check('والثانية والثالثة بلا بديل', [p['substitute_name'] for p in panel[1:]], [None, None])

s = sheet()
check('عمود التغطية يقول «١ من ٣ غُطّيت»',
      (s['محمد عوبل']['cover_done'], s['محمد عوبل']['cover_open']), (1, 2))

print('\n' + ('─' * 54))
print(f'نجح {ok} · فشل {fail}')
raise SystemExit(1 if fail else 0)
