#!/usr/bin/env python3
"""فحص حزمة DOCX — ما يمكن كشفه بلا فتح وورد.

وسببه أن **عطل DOCX لا يظهر إلّا عند الفتح**: الملفّ يُبنى بلا شكوى،
ويُرسَل، ثمّ يقول وورد «الملفّ تالف» بلا سطرٍ يدلّ على الموضع. وهذه
الفحوص الخمسة تمسك ما رأيناه فعلًا:

  ① الحزمة سليمة وكل XML فيها مقروء.
  ② **كل فقرة تحمل `<w:bidi/>`** — وإلّا تدفّقت من اليسار.
  ③ **كل مقطع عربيّ يحمل `<w:rtl/>`** — وإلّا قُلبت النقطة والأرقام.
  ④ **ترتيب الأبناء يتبع ECMA-376** — `color` قبل `sz`، و`numPr` قبل
     `bidi`، و`bidiVisual` قبل `tblW`. ووورد متسامح، وغيره ليس كذلك.
  ⑤ `rtlGutter` في `sectPr` لا في `settings` — وهي مخالفةٌ وقعت فعلًا.
"""

import re
import sys
import xml.dom.minidom as minidom
import zipfile

# التسلسل المُلزَم لأبناء كل عنصر — من ECMA-376 Part 1.
ORDER = {
    'rPr': ['rStyle', 'rFonts', 'b', 'bCs', 'i', 'iCs', 'caps', 'smallCaps', 'strike',
            'dstrike', 'outline', 'shadow', 'emboss', 'imprint', 'noProof', 'snapToGrid',
            'vanish', 'webHidden', 'color', 'spacing', 'w', 'kern', 'position', 'sz',
            'szCs', 'highlight', 'u', 'effect', 'bdr', 'shd', 'fitText', 'vertAlign',
            'rtl', 'cs', 'em', 'lang', 'eastAsianLayout', 'specVanish', 'oMath'],
    'pPr': ['pStyle', 'keepNext', 'keepLines', 'pageBreakBefore', 'framePr', 'widowControl',
            'numPr', 'suppressLineNumbers', 'pBdr', 'shd', 'tabs', 'suppressAutoHyphens',
            'kinsoku', 'wordWrap', 'overflowPunct', 'topLinePunct', 'autoSpaceDE',
            'autoSpaceDN', 'bidi', 'adjustRightInd', 'snapToGrid', 'spacing', 'ind',
            'contextualSpacing', 'mirrorIndents', 'suppressOverlap', 'jc', 'textDirection',
            'textAlignment', 'textboxTightWrap', 'outlineLvl', 'divId', 'cnfStyle',
            'rPr', 'sectPr', 'pPrChange'],
    'tblPr': ['tblStyle', 'tblpPr', 'tblOverlap', 'bidiVisual', 'tblStyleRowBandSize',
              'tblStyleColBandSize', 'tblW', 'tblJc', 'tblCellSpacing', 'tblInd',
              'tblBorders', 'shd', 'tblLayout', 'tblCellMar', 'tblLook', 'tblCaption',
              'tblDescription'],
    'tcPr': ['cnfStyle', 'tcW', 'gridSpan', 'hMerge', 'vMerge', 'tcBorders', 'shd',
             'noWrap', 'tcMar', 'textDirection', 'tcFitText', 'vAlign', 'hideMark'],
    'sectPr': ['footnotePr', 'endnotePr', 'type', 'pgSz', 'pgMar', 'paperSrc', 'pgBorders',
               'lnNumType', 'pgNumType', 'cols', 'formProt', 'vAlign', 'noEndnote',
               'titlePg', 'textDirection', 'bidi', 'rtlGutter', 'docGrid', 'printerSettings'],
    'style': ['name', 'aliases', 'basedOn', 'next', 'link', 'autoRedefine', 'hidden',
              'uiPriority', 'semiHidden', 'unhideWhenUsed', 'qFormat', 'locked', 'personal',
              'personalCompose', 'personalReply', 'rsid', 'pPr', 'rPr', 'tblPr', 'trPr',
              'tcPr', 'tblStylePr'],
    'lvl': ['start', 'numFmt', 'lvlRestart', 'pStyle', 'isLgl', 'suff', 'lvlText',
            'lvlPicBulletId', 'legacy', 'lvlJc', 'pPr', 'rPr'],
}

# الأجزاء التي يجب أن تكون في كل حزمة.
REQUIRED = ['[Content_Types].xml', '_rels/.rels', 'word/document.xml',
            'word/_rels/document.xml.rels']

fails = []


def bad(kind, msg):
    fails.append((kind, msg))


def check_order(where, block, kind):
    seq = ORDER[kind]
    kids = [k for k in re.findall(r'<w:(\w+)[ />]', block) if k in seq]
    pos = [seq.index(k) for k in kids]

    if pos == sorted(pos):
        return

    late = [kids[i] for i in range(1, len(pos)) if pos[i] < pos[i - 1]]
    bad('ORDER', '%s · %s: %s ← المخالف %s' % (where, kind, kids, late))


def run(path):
    try:
        z = zipfile.ZipFile(path)
    except Exception as e:
        bad('ZIP', 'تعذّر فتح الحزمة: %s' % e)
        return

    if z.testzip() is not None:
        bad('ZIP', 'حزمةٌ تالفة عند %s' % z.testzip())

    names = z.namelist()

    for need in REQUIRED:
        if need not in names:
            bad('PART', 'جزءٌ ناقص: %s' % need)

    if names and names[0] != '[Content_Types].xml':
        bad('PART', '[Content_Types].xml ليس أوّل الحزمة — يقتضيه OPC.')

    parts = {}

    for n in names:
        raw = z.read(n)

        if n.endswith('.xml') or n.endswith('.rels'):
            try:
                minidom.parseString(raw)
            except Exception as e:
                bad('XML', '%s: %s' % (n, e))
                continue

            parts[n] = raw.decode('utf-8')

    doc = parts.get('word/document.xml', '')

    # ── ② كل فقرة من اليمين ──
    paras = re.findall(r'<w:p>(.*?)</w:p>', doc, re.S)

    for i, p in enumerate(paras):
        if '<w:bidi/>' not in p:
            bad('NO_BIDI', 'الفقرة #%d بلا <w:bidi/> — تتدفّق من اليسار.' % i)

    # ── ③ كل مقطعٍ عربيّ عربيّ ──
    for i, r in enumerate(re.findall(r'<w:r>(.*?)</w:r>', doc, re.S)):
        m = re.search(r'<w:t[^>]*>(.*?)</w:t>', r, re.S)

        if not m or m.group(1).strip() == '':
            continue

        has_arabic = re.search(r'[؀-ۿ]', m.group(1)) is not None

        if has_arabic and '<w:rtl/>' not in r:
            bad('NO_RTL', 'مقطعٌ عربيّ #%d بلا <w:rtl/>: «%s…»' % (i, m.group(1)[:30]))

        # والخطر في `rtl` على اللاتينيّ اختيارُ خطّ الحروف المركّبة له.
        # أمّا الأرقام والترقيم فمحايدة، وتتبع اتّجاه الفقرة — ووسمُها
        # صحيحٌ لا خطأ، فلا تُحسَب مخالفة.
        has_latin = re.search(r'[A-Za-z]', m.group(1)) is not None

        if has_latin and not has_arabic and '<w:rtl/>' in r:
            bad('WRONG_RTL', 'مقطعٌ لاتينيّ #%d عليه <w:rtl/> — سيُرسَم '
                'بخطّ الحروف المركّبة: «%s…»' % (i, m.group(1)[:30]))

    # ── ④ الترتيب في كل الأجزاء ──
    for name, src in parts.items():
        for kind in ('rPr', 'pPr', 'tblPr', 'tcPr', 'sectPr', 'lvl'):
            for m in re.finditer(r'<w:%s>(.*?)</w:%s>' % (kind, kind), src, re.S):
                check_order(name, m.group(1), kind)

        for m in re.finditer(r'<w:style [^>]*w:styleId="(\w+)">(.*?)</w:style>', src, re.S):
            check_order('%s · %s' % (name, m.group(1)), m.group(2), 'style')

    # ── ⑤ rtlGutter موضعُها sectPr ──
    if '<w:rtlGutter/>' in parts.get('word/settings.xml', ''):
        bad('MISPLACED', 'rtlGutter في settings.xml — موضعها sectPr.')

    if '<w:rtlGutter/>' not in doc:
        bad('MISPLACED', 'لا rtlGutter في sectPr — هامش التجليد يسارًا.')

    # ── ملخّصٌ إعلاميّ ──
    print('■ %s' % path)
    print('   أجزاء: %d · فقرات: %d · جداول: %d · مقاطع: %d'
          % (len(names), len(paras), doc.count('<w:tbl>'), doc.count('<w:r>')))


for target in (sys.argv[1:] or ['مداد — وثيقة المشروع الكاملة.docx']):
    run(target)

if not fails:
    print('\n✅ الحزمة سليمة — كل الفحوص الخمسة نظيفة.')
    sys.exit(0)

groups = {}
for kind, msg in fails:
    groups.setdefault(kind, []).append(msg)

for kind in sorted(groups):
    print('\n■ %s (%d)' % (kind, len(groups[kind])))
    for msg in groups[kind][:12]:
        print('   - %s' % msg)
    if len(groups[kind]) > 12:
        print('   … و%d أخرى' % (len(groups[kind]) - 12))

print('\nالمجموع: %d' % len(fails))
sys.exit(1)
