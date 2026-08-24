"""
PDF نموذجٍ مدرسيّ ← HTML منظَّم يصلح متنًا لمحرّر مِداد.

المبدأ: **البنية قبل الشكل.** الغرض ليس صورةً طبق الأصل — لو أردنا ذلك
لأبقينا الـPDF. الغرض مستندٌ يفتحه المعلّم فيحرّره: جداول حقيقيّة
(‎<table>‎ بخلايا)، وعنواناتٌ حقيقيّة، ونصٌّ يجري في فقرات. فما كان في
الأصل مربّعًا مرسومًا يصير خليّةً فارغة، وما كان سطرًا للتعبئة يصير خليّةً
فارغة كذلك.

الجداول تُكتشف بخطوط الرسم أوّلًا (PyMuPDF يقرؤها)، وهو الأمتن في النماذج
السعوديّة لأنّها مؤطّرة كلّها. وحين لا توجد خطوط نرجع إلى تجميع النصّ
بمواضعه.

يُستعمل من سطر الأوامر:
    python3 extract.py الملفّ.pdf --out out.json
"""
from __future__ import annotations

import argparse
import html
import json
import re
import sys
from dataclasses import dataclass, field
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import arabic as A  # noqa: E402

try:
    import pymupdf as fitz
except ImportError:  # الاسم القديم
    import fitz


# ─────────────────────────── أنواع ───────────────────────────

@dataclass
class Line:
    text: str
    x0: float
    y0: float
    x1: float
    y1: float
    size: float
    bold: bool

    @property
    def cy(self) -> float:
        return (self.y0 + self.y1) / 2


@dataclass
class PageOut:
    number: int
    width: float
    height: float
    blocks: list = field(default_factory=list)   # ('h1'|'h2'|'h3'|'p'|'table', payload)
    warnings: list = field(default_factory=list)
    health: dict = field(default_factory=dict)


# ─────────────────────── استخراج الأسطر ───────────────────────

def page_lines(page) -> list[Line]:
    """أسطر النصّ بمواضعها وأحجامها، مع تطبيع العربيّة."""
    out: list[Line] = []
    d = page.get_text('dict')
    for blk in d.get('blocks', []):
        if blk.get('type') != 0:
            continue
        for ln in blk.get('lines', []):
            spans = ln.get('spans', [])
            if not spans:
                continue
            raw = ''.join(s.get('text', '') for s in spans)
            txt = A.normalize(raw)
            if not txt.strip():
                continue
            x0, y0, x1, y1 = ln['bbox']
            sizes = [s.get('size', 0) for s in spans if s.get('text', '').strip()]
            flags = [s.get('flags', 0) for s in spans if s.get('text', '').strip()]
            # البتّ ٤ في flags يعني عريض في PyMuPDF
            bold = bool(flags) and sum(1 for f in flags if f & (1 << 4)) > len(flags) / 2
            out.append(Line(txt, x0, y0, x1, y1,
                            max(sizes) if sizes else 0, bold))
    out.sort(key=lambda l: (round(l.y0, 1), -l.x1))   # من الأعلى، ومن اليمين
    return out


# ─────────────────────────── الجداول ───────────────────────────

def page_tables(page) -> list[dict]:
    """
    جداول الصفحة بخطوط الرسم. لكلّ جدول: صناديقه وخلاياه المطبَّعة.

    نعتمد find_tables في PyMuPDF: يقرأ الخطوط الأفقيّة والرأسيّة ويستنتج
    الشبكة. وهو الأصحّ للنماذج المؤطّرة، ويعطي الخلايا المدموجة None
    فنترجمها إلى امتدادٍ في HTML.
    """
    found = []
    try:
        tabs = page.find_tables(strategy='lines_strict')
        if not tabs.tables:
            tabs = page.find_tables(strategy='lines')
        if not tabs.tables:
            tabs = page.find_tables(strategy='text')
    except Exception:
        return found

    for t in tabs.tables:
        try:
            grid = t.extract()
        except Exception:
            continue
        if not grid:
            continue
        rows = []
        for r in grid:
            rows.append([A.normalize(c) if isinstance(c, str) else '' for c in r])
        # جدولٌ بصفٍّ واحدٍ وعمودٍ واحد ليس جدولًا
        if len(rows) < 2 and len(rows[0]) < 2:
            continue
        found.append({'bbox': list(t.bbox), 'rows': rows,
                      'ncols': max(len(r) for r in rows)})
    return found


def rows_to_html(rows: list[list[str]], header: bool = True) -> str:
    """
    شبكةُ خلايا ← ‎<table>‎، بدمج الخلايا المتكرّرة أفقيًّا.

    مستخرج PyMuPDF يعيد نصّ الخليّة المدموجة مكرّرًا في كلّ عمودٍ تحتها،
    فنطوي التكرار إلى colspan. والخليّة الفارغة تبقى فارغة — وهي مقصودة:
    مكانُ ما يكتبه المعلّم.
    """
    if not rows:
        return ''
    ncols = max(len(r) for r in rows)
    out = ['<table>']

    def cells(row: list[str], tag: str) -> str:
        r = list(row) + [''] * (ncols - len(row))
        parts = []
        i = 0
        while i < ncols:
            v = r[i]
            span = 1
            # الفراغ لا يُدمَج: عمودان فارغان خليّتان لا خليّة ممتدّة
            if v.strip():
                while i + span < ncols and r[i + span] == v:
                    span += 1
            attr = f' colspan="{span}"' if span > 1 else ''
            parts.append(f'<{tag}{attr}>{html.escape(v)}</{tag}>')
            i += span
        return ''.join(parts)

    body_start = 0
    if header and len(rows) > 1 and any(c.strip() for c in rows[0]):
        nhead = 1 + (1 if _second_row_is_header(rows) else 0)
        out.append('<thead>' + ''.join(
            '<tr>' + cells(rows[i], 'th') + '</tr>' for i in range(nhead)) + '</thead>')
        body_start = nhead
    out.append('<tbody>')
    for r in rows[body_start:]:
        out.append('<tr>' + cells(r, 'td') + '</tr>')
    out.append('</tbody></table>')
    return ''.join(out)


def _numeric(cell: str) -> bool:
    c = cell.strip()
    return bool(c) and bool(re.fullmatch(r'[\d٠-٩.,/\-\s]+', c))


def _second_row_is_header(rows: list[list[str]]) -> bool:
    """
    ترويسةٌ من صفّين — شائعةٌ في النماذج السعوديّة: «الفصل الأول» ممتدٌّ فوق
    «أعمال · عملي · نهائي». نستدلّ بأنّ الصفّ الثاني كلّه نصٌّ بلا أرقام،
    وأنّ صفًّا بعده فيه أرقام. ولا نحكم إلّا بثلاثة صفوفٍ على الأقلّ.
    """
    if len(rows) < 3:
        return False
    second = [c for c in rows[1] if c.strip()]
    if not second or any(_numeric(c) for c in second):
        return False
    # هل في الصفّ الأوّل خليّةٌ ممتدّة (نصٌّ مكرّر)؟ قرينةٌ قويّة
    first = rows[0]
    spanned = any(first[i].strip() and first[i] == first[i + 1] for i in range(len(first) - 1))
    below = rows[2:]
    has_numbers = any(_numeric(c) for r in below for c in r)
    return has_numbers and (spanned or len(second) >= 2)


# ─────────────────────── تصنيف الأسطر ───────────────────────

def classify(lines: list[Line]) -> tuple[float, float]:
    """حجم النصّ الغالب، وحدّ العنوان — من توزيع الأحجام لا بأرقامٍ ثابتة."""
    sizes = [round(l.size, 1) for l in lines if l.text.strip()]
    if not sizes:
        return 10.0, 13.0
    freq: dict[float, int] = {}
    for s in sizes:
        freq[s] = freq.get(s, 0) + 1
    body = max(freq.items(), key=lambda kv: kv[1])[0]
    return body, body * 1.18


def line_tag(l: Line, body: float, head_cut: float, page_w: float) -> str:
    if l.size >= body * 1.55:
        return 'h1'
    if l.size >= body * 1.28 or (l.bold and l.size >= head_cut):
        return 'h2'
    if l.bold and l.size >= body * 1.05:
        return 'h3'
    # سطرٌ عريضٌ قصيرٌ في وسط الصفحة: عنوانٌ في الغالب
    width = l.x1 - l.x0
    centered = abs(((l.x0 + l.x1) / 2) - page_w / 2) < page_w * 0.08
    if l.bold and centered and width < page_w * 0.6:
        return 'h2'
    return 'p'


# ─────────────────────── تحويل صفحة ───────────────────────

def convert_page(page, index: int) -> PageOut:
    W, H = page.rect.width, page.rect.height
    out = PageOut(number=index + 1, width=W, height=H)

    lines = page_lines(page)
    tables = page_tables(page)

    # سلامة طبقة النصّ قبل أيّ بناء: النصّ الفاسد لا يُصلحه ترتيبٌ ولا تجميع
    all_text = ' '.join(l.text for l in lines) + ' ' + ' '.join(
        c for t in tables for r in t['rows'] for c in r)
    health = A.text_layer_health(all_text)
    out.health = health
    if not health['healthy']:
        out.warnings.append(
            f"طبقة النصّ في هذه الصفحة فاسدة ({health['bad_ratio']:.0%} محارف غريبة"
            f"{': ' + health['sample'] if health['sample'] else ''}) — "
            'الخطّ يخزّن رسوم الحروف برموزٍ غير عربيّة. تحتاج قراءةً ضوئيّة.')

    if not lines and not tables:
        out.warnings.append('صفحةٌ بلا نصٍّ مُستخرَج — قد تكون صورةً ممسوحة')
        return out

    # رقمٌ ملاصقٌ لحرفٍ عربيّ بلا مسافة: موضعٌ قد ينقلب فيه ترتيب الأرقام
    # عند الاستخراج (لا bidi في طبقة النصّ). لا نُصلحه تخمينًا — نُبلّغ به
    # كي يُراجَع في المحرّر، فالرقم الخاطئ أسوأ من الرقم المفقود.
    _glued = [l.text for l in lines
              if re.search(r'[\u0600-\u06FF]\d|\d[\u0600-\u06FF]', l.text)]
    if _glued:
        out.warnings.append(
            'أرقامٌ ملاصقةٌ لحروفٍ عربيّة في '
            + str(len(_glued)) + ' سطرًا — راجع ترتيبها: '
            + ' | '.join(t[:34] for t in _glued[:3]))

    def in_table(l: Line) -> bool:
        """
        السطر داخل جدول إن كان **مركزه** في صندوقه، أو تراكب معه في أكثر
        من نصفه. الاشتراط على الطرفين معًا كان يُفلت أسطرًا تتجاوز الإطار
        بقليل — فتظهر مرّتين: خليّةً في الجدول وفقرةً بعده. وقع ذلك فعلًا.
        """
        cx = (l.x0 + l.x1) / 2
        w = max(1e-6, l.x1 - l.x0)
        for t in tables:
            x0, y0, x1, y1 = t['bbox']
            pad = 3.0
            if x0 - pad <= cx <= x1 + pad and y0 - pad <= l.cy <= y1 + pad:
                return True
            # تراكبٌ أفقيّ يزيد على النصف مع تطابقٍ رأسيّ
            ov = min(l.x1, x1) - max(l.x0, x0)
            if ov / w > 0.5 and y0 - pad <= l.cy <= y1 + pad:
                return True
        return False

    free = [l for l in lines if not in_table(l)]
    body, head_cut = classify(lines)

    # نرتّب العناصر بترتيب القراءة الرأسيّ: الأسطر الحرّة والجداول معًا
    items: list[tuple[float, str, object]] = []
    for l in free:
        items.append((l.y0, 'line', l))
    for t in tables:
        items.append((t['bbox'][1], 'table', t))
    items.sort(key=lambda it: it[0])

    # نجمع الأسطر المتتابعة من نوعٍ واحد في كتلةٍ واحدة
    buf: list[Line] = []
    buf_tag = None

    def flush():
        nonlocal buf, buf_tag
        if not buf:
            return
        text = ' '.join(x.text for x in buf).strip()
        if text:
            out.blocks.append((buf_tag or 'p', text))
        buf, buf_tag = [], None

    for _, kind, payload in items:
        if kind == 'table':
            flush()
            t = payload  # type: ignore
            out.blocks.append(('table', t['rows']))
            continue
        l = payload  # type: ignore
        tag = line_tag(l, body, head_cut, W)
        if tag != 'p':
            flush()
            out.blocks.append((tag, l.text))
            continue
        # فقرة: نضمّ إلى ما قبلها إن كان قريبًا رأسيًّا
        if buf and (l.y0 - buf[-1].y1) > body * 1.1:
            flush()
        buf.append(l)
        buf_tag = 'p'
    flush()

    return out


def blocks_to_html(pages: list[PageOut]) -> str:
    parts: list[str] = []
    for i, pg in enumerate(pages):
        if i:
            # فاصلٌ بين صفحات الأصل — يراه المعلّم فيعرف أين انتهت صفحة
            parts.append('<hr>')
        for tag, payload in pg.blocks:
            if tag == 'table':
                parts.append(rows_to_html(payload))
            else:
                txt = html.escape(str(payload))
                parts.append(f'<{tag}>{txt}</{tag}>')
    if not parts:
        parts.append('<p></p>')
    return '\n'.join(parts)


def convert(path: str) -> dict:
    doc = fitz.open(path)
    pages = [convert_page(doc[i], i) for i in range(doc.page_count)]
    first = doc[0].rect if doc.page_count else None
    landscape = bool(first and first.width > first.height)
    html_out = blocks_to_html(pages)

    stats = {
        'pages': doc.page_count,
        'tables': sum(1 for p in pages for t, _ in p.blocks if t == 'table'),
        'headings': sum(1 for p in pages for t, _ in p.blocks if t in ('h1', 'h2', 'h3')),
        'paragraphs': sum(1 for p in pages for t, _ in p.blocks if t == 'p'),
        'warnings': [w for p in pages for w in p.warnings],
        'healthy_pages': sum(1 for p in pages if p.health.get('healthy', True)),
        'worst_bad_ratio': max([p.health.get('bad_ratio', 0.0) for p in pages] or [0.0]),
    }
    title = ''
    for p in pages:
        for tag, payload in p.blocks:
            if tag in ('h1', 'h2') and isinstance(payload, str) and len(payload) > 3:
                title = payload
                break
        if title:
            break
    doc.close()
    return {
        'title': title,
        'html': html_out,
        'page': {
            'size': 'A4',
            'orientation': 'landscape' if landscape else 'portrait',
            'margins': {'top': 16, 'right': 14, 'bottom': 16, 'left': 14},
        },
        'stats': stats,
    }


def main() -> int:
    ap = argparse.ArgumentParser(description='PDF نموذجٍ مدرسيّ ← HTML لمحرّر مِداد')
    ap.add_argument('pdf')
    ap.add_argument('--out', help='ملفّ JSON للناتج')
    ap.add_argument('--html', help='ملفّ HTML للمعاينة')
    a = ap.parse_args()

    res = convert(a.pdf)
    if a.out:
        Path(a.out).write_text(json.dumps(res, ensure_ascii=False, indent=1), encoding='utf-8')
    if a.html:
        Path(a.html).write_text(
            '<!doctype html><meta charset="utf-8"><style>'
            'body{font-family:"Noto Naskh Arabic",serif;direction:rtl;max-width:210mm;margin:20px auto;'
            'padding:18mm 16mm;background:#fff;color:#141320;line-height:1.9}'
            'table{border-collapse:collapse;width:100%;margin:.7em 0}'
            'td,th{border:1px solid #b9b5cf;padding:5px 8px}'
            'th{background:#f1eefb}h1{text-align:center}</style>' + res['html'],
            encoding='utf-8')

    s = res['stats']
    print(f"العنوان   : {res['title'] or '—'}")
    print(f"الصفحات   : {s['pages']}  ·  الاتّجاه: {res['page']['orientation']}")
    print(f"الجداول   : {s['tables']}")
    print(f"العنوانات : {s['headings']}")
    print(f"الفقرات   : {s['paragraphs']}")
    print(f"سلامة النصّ: {s['healthy_pages']}/{s['pages']} صفحة سليمة"
          f"  ·  أسوأ نسبة فساد: {s['worst_bad_ratio']:.1%}")
    if s['warnings']:
        print('تنبيهات   :')
        for w in s['warnings']:
            print('  • ' + w)
    if s['healthy_pages'] < s['pages']:
        print('\n⚠ لا تُبذَر هذه النتيجة قالبًا — النصّ المستخرج غير موثوق.')
        return 2
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
