# -*- coding: utf-8 -*-
"""يولّد قالب «تحليل نتائج نافس» بتصميمٍ مُعاد بناؤه لمحرّر مِداد."""

INK   = '#1F2440'
MUTED = '#5B5878'
HEAD  = '#EEF0F8'      # ترويسة الجدول — رماديّ لبنيّ يطبع نظيفًا
SOFT  = '#F7F8FC'
HI    = '#0E7A55'      # مرتفع
MED   = '#B4791B'      # متوسّط
LOW   = '#C4562F'      # منخفض
VLOW  = '#B23A3A'      # منخفض جدًّا
ACC   = '#2E5B8A'      # لون الأقسام

def h1(t): return f'<h1>{t}</h1>'
def h2(t): return f'<h2>{t}</h2>'
def h3(t): return f'<h3>{t}</h3>'
def p(t, align='right'):
    a = f' style="text-align:{align}"' if align != 'right' else ''
    return f'<p{a}>{t}</p>'
def c(t, col): return f'<span style="color:{col}">{t}</span>'
def b(t): return f'<strong>{t}</strong>'

def table(rows, widths=None):
    """rows: list of (tag, [cells]) حيث الخليّة نصٌّ أو (نصّ, attrs)"""
    out = ['<table>']
    head = [r for r in rows if r[0] == 'th']
    body = [r for r in rows if r[0] != 'th']
    def cell(v, tag):
        if isinstance(v, tuple):
            txt, attrs = v
        else:
            txt, attrs = v, ''
        return f'<{tag}{attrs}>{txt}</{tag}>'
    if head:
        out.append('<thead>')
        for _, cells in head:
            out.append('<tr>' + ''.join(cell(x, 'th') for x in cells) + '</tr>')
        out.append('</thead>')
    out.append('<tbody>')
    for _, cells in body:
        out.append('<tr>' + ''.join(cell(x, 'td') for x in cells) + '</tr>')
    out.append('</tbody></table>')
    return ''.join(out)

BG = lambda col: f' style="background-color:{col}"'
CEN = ' style="text-align:center"'
def cenbg(col):
    # ترويسة الجدول لا تحتاج نمطًا سطريًّا: المحرّر يُنسّق th (تظليل ووسط)،
    # ومُصدّر الوورد يفعل المثل. فالنمط السطريّ تكرارٌ يُثقل المتن ويُصعّب
    # تغيير الهويّة لاحقًا من مكانٍ واحد.
    return CEN if col == HEAD else f' style="text-align:center;background-color:{col}"'

# ═════════════════ الترويسة ═════════════════
def identity():
    return (
        table([
            ('td', [(b('المملكة العربية السعودية'), CEN), ('شعار المدرسة', cenbg(SOFT))]),
            ('td', [(b('وزارة التعليم'), CEN), ('', BG(SOFT))]),
            ('td', [('الإدارة العامة للتعليم بـ ......................', CEN), ('', BG(SOFT))]),
            ('td', [('مكتب التعليم بـ ......................', CEN), ('', BG(SOFT))]),
        ])
        + table([('th', [('مدرسة ......................................', cenbg(HEAD))])])
    )

# ═════════════════ الأقسام ═════════════════
LEVELS = [('المرتفع', HI), ('المتوسّط', MED), ('المنخفض', LOW), ('المنخفض جدًّا', VLOW)]

def domain_block(name, subs):
    """قسم مجالٍ رئيسيّ: جدول النِّسب والأعداد، ثمّ المجالات الفرعيّة."""
    rows = [('th', [('المؤشّر', cenbg(HEAD)), ('النسبة %', cenbg(HEAD)), ('العدد', cenbg(HEAD))])]
    rows.append(('td', [b('اجتازوا الحدّ الأدنى للإتقان'), ('', CEN), ('', CEN)]))
    for lvl, col in LEVELS:
        rows.append(('td', [f'أصحاب المستوى {c(lvl, col)}', ('', CEN), ('', CEN)]))
    out = [h2(f'مجال: {name}'), table(rows)]

    out.append(h3('المجالات الفرعيّة'))
    sub_rows = [('th', [('المجال الفرعيّ', cenbg(HEAD)), ('النسبة %', cenbg(HEAD)),
                        ('عدد الطلبة', cenbg(HEAD)), ('المستوى', cenbg(HEAD))])]
    for s in subs:
        sub_rows.append(('td', [s, ('', CEN), ('', CEN), ('', CEN)]))
    out.append(table(sub_rows))

    out.append(h3('قراءة النتيجة وإجراءات التحسين'))
    out.append(p('نقاط القوّة: ' + '.' * 60))
    out.append(p('مواطن التحسين: ' + '.' * 56))
    out.append(p('الإجراء المقترح: ' + '.' * 55))
    return '\n'.join(out)

# ═════════════════ بناء المستند ═════════════════
parts = []

parts.append(identity())
parts.append(h1('تحليل نتيجة اختبار نافس'))
parts.append(p(b('العام الدراسيّ ١٤٤٦ هـ') + ' — الفصل الدراسيّ ..........', 'center'))
parts.append('<hr>')

# ①
parts.append(h2('أوّلًا: بيانات المدرسة والاختبار'))
parts.append(table([
    ('th', [('البيان', cenbg(HEAD)), ('القيمة', cenbg(HEAD)),
            ('البيان', cenbg(HEAD)), ('القيمة', cenbg(HEAD))]),
    ('td', ['اسم المدرسة', '', 'المرحلة', '']),
    ('td', ['الإدارة العامة للتعليم', '', 'مكتب التعليم', '']),
    ('td', ['الصفّ', '', 'العام الدراسيّ', '']),
    ('td', ['عدد الطلاب', ('', CEN), 'عدد المختبَرين', ('', CEN)]),
    ('td', ['مدير المدرسة', '', 'معلّم الرياضيّات', '']),
    ('td', ['معلّم القراءة', '', 'معلّم العلوم', '']),
]))

# ②
parts.append(h2('ثانيًا: النتيجة العامّة'))
parts.append(table([
    ('th', [('المؤشّر', cenbg(HEAD)), ('القيمة', cenbg(HEAD)), ('المستوى', cenbg(HEAD))]),
    ('td', ['نسبة الطلبة الذين اجتازوا الحدّ الأدنى للإتقان في جميع المجالات', ('', CEN), ('', CEN)]),
    ('td', ['نسبة التغيّر عن العام السابق', ('', CEN), ('', CEN)]),
]))
parts.append(p(c('يُكتب المستوى: مرتفع · متوسّط · منخفض · منخفض جدًّا', MUTED)))
parts.append('<hr>')

# ③④⑤ المجالات
parts.append(domain_block('الرياضيّات', [
    'الأعداد والعمليات عليها', 'الجبر', 'الهندسة والقياس', 'البيانات والاحتمالات']))
parts.append('<hr>')
parts.append(domain_block('القراءة', ['دلالات الألفاظ', 'استيعاب المقروء']))
parts.append('<hr>')
parts.append(domain_block('العلوم', [
    'العلوم الفيزيائية والكيميائية', 'علوم الحياة', 'علم الأرض والفلك']))
parts.append('<hr>')

# ⑥ الملخّص
parts.append(h2('رابعًا: ملخّص التحليل والمقارنة'))
parts.append(table([
    ('th', [('نسبة الإتقان', cenbg(HEAD)), ('الرياضيّات', cenbg(HEAD)),
            ('القراءة', cenbg(HEAD)), ('العلوم', cenbg(HEAD))]),
    ('td', ['نتيجة العام السابق', ('', CEN), ('', CEN), ('', CEN)]),
    ('td', ['نتيجة العام الحاليّ', ('', CEN), ('', CEN), ('', CEN)]),
    ('td', [b('المستهدَف لعام ٢٠٣٠'), ('', CEN), ('', CEN), ('', CEN)]),
    ('td', [b('الفجوة عن المستهدَف'), ('', CEN), ('', CEN), ('', CEN)]),
]))

parts.append(h3('المستوى العامّ للمدرسة'))
parts.append(table([
    ('th', [('المستوى', cenbg(HEAD)), ('وصف الأداء', cenbg(HEAD))]),
    ('td', [(c(b('مرتفع'), HI), CEN),
            'يُظهر الطلاب أداءً متقدّمًا عند اختبارهم في المهارات والمفاهيم على مستوى الصفّ، '
            'ويُظهرون تميّزًا وحلولًا إبداعيّة للأسئلة غير المباشرة التي تتطلّب مهارات تفكيرٍ عليا.']),
    ('td', [(c(b('متوسّط'), MED), CEN),
            'يُظهر الطلاب إتقانًا لمعظم المهارات والمفاهيم على مستوى الصفّ، مع حاجةٍ إلى دعمٍ '
            'في الأسئلة التي تتطلّب استدلالًا أو ربطًا بين أكثر من مفهوم.']),
    ('td', [(c(b('منخفض'), LOW), CEN),
            'يُظهر الطلاب إتقانًا جزئيًّا للمهارات الأساسيّة، ويحتاجون إلى خطّةٍ علاجيّةٍ '
            'مركّزةٍ على المفاهيم الأساسيّة قبل الانتقال إلى ما بعدها.']),
    ('td', [(c(b('منخفض جدًّا'), VLOW), CEN),
            'لم يبلغ الطلاب الحدّ الأدنى للإتقان في معظم المهارات، ويحتاجون إلى تدخّلٍ '
            'مكثّفٍ وفرديّ ومتابعةٍ أسبوعيّة.']),
]))

parts.append(h2('خامسًا: الخطّة الإجرائيّة'))
parts.append(table([
    ('th', [('م', cenbg(HEAD)), ('الإجراء', cenbg(HEAD)), ('المسؤول', cenbg(HEAD)),
            ('تاريخ التنفيذ', cenbg(HEAD)), ('مؤشّر القياس', cenbg(HEAD))]),
] + [('td', [(str(i), CEN), '', '', ('', CEN), '']) for i in range(1, 6)]))

parts.append(h2('سادسًا: الاعتماد'))
parts.append(table([
    ('th', [('معلّم المادة', cenbg(HEAD)), ('وكيل الشؤون التعليميّة', cenbg(HEAD)),
            ('مدير المدرسة', cenbg(HEAD))]),
    ('td', [('<br>', CEN), ('<br>', CEN), ('<br>', CEN)]),
    ('td', [('الاسم والتوقيع', CEN), ('الاسم والتوقيع', CEN), ('الختم', CEN)]),
]))

html = '\n'.join(parts)
open('template.html', 'w', encoding='utf-8').write(html)
print('حجم HTML:', len(html), 'حرفًا')
import re
print('جداول:', html.count('<table>'), '· عنوانات:', len(re.findall(r'<h[123]>', html)),
      '· فقرات:', html.count('<p'), '· فواصل:', html.count('<hr>'))
