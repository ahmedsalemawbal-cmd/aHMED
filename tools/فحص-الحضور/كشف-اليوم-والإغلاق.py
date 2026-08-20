import sqlite3, re, io

D = ':memory:'
c = sqlite3.connect(D); c.row_factory = sqlite3.Row
x = c.executescript

# مرآة للأعمدة الحقيقية كما في class-activator.php
x("""
CREATE TABLE students (id INTEGER PRIMARY KEY, full_name TEXT, first_name TEXT,
  academic_no TEXT, student_no TEXT, photo_file TEXT, stage TEXT, grade_level TEXT,
  section TEXT, status TEXT);
CREATE TABLE classes (id INTEGER PRIMARY KEY, year_id INT, stage TEXT, grade_level TEXT, section TEXT);
CREATE TABLE enrollments (id INTEGER PRIMARY KEY, student_id INT, class_id INT, year_id INT, status TEXT,
  UNIQUE(student_id, year_id));
CREATE TABLE attendance (id INTEGER PRIMARY KEY, student_id INT, class_id INT, att_date TEXT,
  status TEXT, method TEXT, checked_in_at TEXT, minutes_late INT DEFAULT 0, note TEXT,
  recorded_by INT, recorded_at TEXT, UNIQUE(student_id, att_date));
""")

YEAR, TODAY = 1, '2026-08-20'
c.execute("INSERT INTO classes VALUES (7,?,'ابتدائي','الرابع','أ')", (YEAR,))

roster = [
    (1,'احمد سالم محمد عوبل','84201042', 7),   # مسح البوابة → حاضر
    (2,'باسل سالم محمد عوبل','84201043', 7),   # مسح البوابة → حاضر
    (3,'سطسط سيس سييس سسطشش','84201051', 7),   # مسح متأخر
    (4,'علي سالم محمد عوبل','84201064', 7),    # لم يُمسح
    (5,'فهد احمد محمد سالم','84201078', 7),    # إجازة معتمدة → بعذر مسبقًا
    (6,'طالب بلا شعبة','84201099', None),      # نشط بلا تسجيل — يجب أن يبقى
    (7,'طالب منسحب','84201100', 7),            # غير نشط — يجب أن يسقط
]
for sid, name, ac, cls in roster:
    c.execute("INSERT INTO students VALUES (?,?,?,?,?,?,?,?,?,?)",
              (sid, name, name.split()[0], ac, 'S'+ac, None, 'ابتدائي', 'الرابع', 'أ',
               'active' if sid != 7 else 'withdrawn'))
    if cls:
        c.execute("INSERT INTO enrollments (student_id,class_id,year_id,status) VALUES (?,?,?,?)",
                  (sid, cls, YEAR, 'active'))

# ما يكتبه تطبيق الحارس عبر SCH_Custody::record → SCH_Attendance::mark
for sid, st, t, mins in [(1,'present','07:12:40',0), (2,'present','07:18:05',0), (3,'late','07:46:11',16)]:
    c.execute("INSERT INTO attendance (student_id,class_id,att_date,status,method,checked_in_at,minutes_late,recorded_at)"
              " VALUES (?,?,?,?,'qr',?,?,?)", (sid, 7, TODAY, st, TODAY+' '+t, mins, TODAY+' '+t))
# ما تكتبه وحدة الإجازات عند الاعتماد
c.execute("INSERT INTO attendance (student_id,class_id,att_date,status,method,recorded_at)"
          " VALUES (5,7,?, 'excused','manual',?)", (TODAY, TODAY+' 06:00:00'))

DAY_SHEET = """
SELECT s.id, s.full_name, s.academic_no,
       COALESCE(c.stage, s.stage) AS stage,
       COALESCE(c.grade_level, s.grade_level) AS grade_level,
       COALESCE(c.section, s.section) AS section,
       c.id AS class_id, a.status, a.method, a.checked_in_at, a.minutes_late
FROM students s
LEFT JOIN enrollments e ON e.student_id = s.id AND e.year_id = ? AND e.status = 'active'
LEFT JOIN classes c ON c.id = e.class_id
LEFT JOIN attendance a ON a.student_id = s.id AND a.att_date = ?
WHERE s.status = 'active'
ORDER BY s.full_name"""

def sheet():
    return c.execute(DAY_SHEET, (YEAR, TODAY)).fetchall()

print('═══ day_sheet ═══')
for r in sheet():
    print(f"  {r['full_name'][:22]:<24} {r['academic_no']}  {str(r['status'] or 'NULL=بانتظار المسح'):<20} "
          f"{r['method'] or '—':<9} {(r['checked_in_at'] or '—')[-8:]:<9} شعبة={r['class_id'] or '—'}")

n = len(sheet())
waiting = sum(1 for r in sheet() if r['status'] is None)
inside  = sum(1 for r in sheet() if r['status'] in ('present','late'))
print(f"\n  الكشف {n} · دخلوا البوابة {inside} · بانتظار المسح {waiting}")
assert n == 6, 'الطالب المنسحب يجب أن يسقط والباقي يبقى'
assert waiting == 2, 'المنتظران: علي (لم يُمسح) والطالب بلا شعبة'
assert inside == 3

CLOSE = """
INSERT OR IGNORE INTO attendance (student_id, class_id, att_date, status, method, recorded_by, recorded_at)
SELECT s.id, e.class_id, ?, 'absent', 'manual', NULLIF(?,0), ?
FROM students s
LEFT JOIN enrollments e ON e.student_id = s.id AND e.year_id = ? AND e.status = 'active'
LEFT JOIN attendance a ON a.student_id = s.id AND a.att_date = ?
WHERE s.status = 'active' AND a.id IS NULL"""

print('\n═══ close_day — الضغطة الأولى ═══')
cur = c.execute(CLOSE, (TODAY, 3, TODAY+' 08:00:00', YEAR, TODAY)); c.commit()
print('  رُصد غيابهم:', cur.rowcount)
assert cur.rowcount == 2, 'اثنان فقط بلا رصد'

after = {r['full_name']: r['status'] for r in sheet()}
print('  بعد الإغلاق:', {k[:14]: v for k, v in after.items()})
assert after['فهد احمد محمد سالم'] == 'excused', 'الإجازة المعتمدة لا تُقلب غيابًا'
assert after['احمد سالم محمد عوبل'] == 'present'
assert after['سطسط سيس سييس سسطشش'] == 'late'
assert after['طالب بلا شعبة'] == 'absent'

print('\n═══ close_day — الضغطة الثانية (نفس الزرّ) ═══')
cur2 = c.execute(CLOSE, (TODAY, 3, TODAY+' 08:05:00', YEAR, TODAY)); c.commit()
print('  رُصد غيابهم:', cur2.rowcount)
assert cur2.rowcount == 0, 'ضغطتان لا تُنشئان سجلًّا ثانيًا'
assert {r['full_name']: r['status'] for r in sheet()} == after, 'لا شيء تبدّل'

print('\n✅ كل الافتراضات صحّت: الكشف والإغلاق وأمان التكرار وحماية الإجازة')
