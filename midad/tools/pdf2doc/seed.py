"""
يبذر قالبًا في مِداد من ملفّ PDF: يستخرج، يتحقّق، يرفع الأصل، ثمّ يكتب الصفّ.

يرفض البذر إن كانت طبقة النصّ فاسدة — قالبٌ نصُّه خُرافة أسوأ من لا قالب.

    python3 seed.py الملفّ.pdf --folder classroom --title "سجل متابعة الطالب"
    python3 seed.py دفعة/*.pdf --folder classroom --dry-run
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
import unicodedata
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from extract import convert  # noqa: E402

SUPABASE_URL = os.environ.get('SUPABASE_URL', '').rstrip('/')
SERVICE_KEY = os.environ.get('SUPABASE_SERVICE_KEY', '')


def slugify(name: str) -> str:
    """مُعرِّفٌ لاتينيّ من اسمٍ عربيّ — نقلٌ حرفيّ بسيطٌ يكفي للمسارات."""
    table = {
        'ا': 'a', 'أ': 'a', 'إ': 'i', 'آ': 'a', 'ب': 'b', 'ت': 't', 'ث': 'th',
        'ج': 'j', 'ح': 'h', 'خ': 'kh', 'د': 'd', 'ذ': 'dh', 'ر': 'r', 'ز': 'z',
        'س': 's', 'ش': 'sh', 'ص': 's', 'ض': 'd', 'ط': 't', 'ظ': 'z', 'ع': 'a',
        'غ': 'gh', 'ف': 'f', 'ق': 'q', 'ك': 'k', 'ل': 'l', 'م': 'm', 'ن': 'n',
        'ه': 'h', 'و': 'w', 'ي': 'y', 'ى': 'a', 'ة': 'h', 'ء': '', 'ؤ': 'w',
        'ئ': 'y', 'ّ': '', 'َ': '', 'ِ': '', 'ُ': '', 'ْ': '', 'ً': '', 'ٍ': '', 'ٌ': '',
    }
    s = unicodedata.normalize('NFKC', name or '')
    out = []
    for ch in s:
        if ch in table:
            out.append(table[ch])
        elif ch.isalnum() and ch.isascii():
            out.append(ch.lower())
        elif ch in ' -_/':
            out.append('-')
    slug = re.sub(r'-+', '-', ''.join(out)).strip('-')
    return slug[:60] or 'template'


def api(method: str, path: str, body=None, headers=None, raw=None):
    if not SUPABASE_URL or not SERVICE_KEY:
        raise SystemExit('اضبط SUPABASE_URL و SUPABASE_SERVICE_KEY في البيئة')
    url = f'{SUPABASE_URL}{path}'
    h = {
        'apikey': SERVICE_KEY,
        'Authorization': f'Bearer {SERVICE_KEY}',
        **(headers or {}),
    }
    data = raw if raw is not None else (json.dumps(body).encode() if body is not None else None)
    if raw is None and body is not None:
        h.setdefault('Content-Type', 'application/json')
    req = urllib.request.Request(url, data=data, headers=h, method=method)
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            txt = r.read().decode()
            return json.loads(txt) if txt.strip().startswith(('{', '[')) else txt
    except urllib.error.HTTPError as e:
        raise SystemExit(f'{method} {path} → {e.code}: {e.read().decode()[:400]}')


def upload_source(pdf: Path, slug: str) -> str:
    """يرفع الأصل إلى دلوٍ خاصّ — للرجوع والمقارنة، لا للعرض."""
    key = f'{slug}.pdf'
    api('POST', f'/storage/v1/object/template-sources/{key}',
        raw=pdf.read_bytes(),
        headers={'Content-Type': 'application/pdf', 'x-upsert': 'true'})
    return key


def folder_id(slug: str) -> str | None:
    if not slug:
        return None
    rows = api('GET', f'/rest/v1/template_folders?slug=eq.{slug}&select=id')
    if not rows:
        raise SystemExit(f'لا مجلّد بالمُعرّف «{slug}» — أنشئه أوّلًا')
    return rows[0]['id']


def seed_one(pdf: Path, folder: str, title: str | None, dry: bool, force: bool) -> dict:
    res = convert(str(pdf))
    st = res['stats']
    name = title or res['title'] or pdf.stem
    slug = slugify(name)

    report = {
        'file': pdf.name, 'title': name, 'slug': slug,
        'pages': st['pages'], 'tables': st['tables'],
        'headings': st['headings'], 'paragraphs': st['paragraphs'],
        'healthy': st['healthy_pages'] == st['pages'],
        'worst_bad_ratio': st['worst_bad_ratio'],
        'warnings': st['warnings'],
        'seeded': False,
    }

    if not report['healthy'] and not force:
        report['skipped'] = 'طبقة النصّ فاسدة — تحتاج قراءةً ضوئيّة'
        return report
    if st['tables'] == 0 and st['paragraphs'] == 0:
        report['skipped'] = 'لا محتوى مُستخرَج'
        return report
    if dry:
        return report

    src = upload_source(pdf, slug)
    row = {
        'slug': slug,
        'title': name,
        'category_key': 'general',
        'description': None,
        'kind': 'doc',
        'folder_id': folder_id(folder),
        'content_html': res['html'],
        'page': res['page'],
        'source_pdf_path': src,
        'source_pages': st['pages'],
        'status': 'published',
        'outputs': ['pdf', 'docx'],
        'estimated_minutes': max(2, min(20, 2 + st['tables'] * 2 + st['paragraphs'] // 3)),
    }
    api('POST', '/rest/v1/templates',
        body=row,
        headers={'Prefer': 'resolution=merge-duplicates,return=minimal'})
    report['seeded'] = True
    report['source'] = src
    return report


def main() -> int:
    ap = argparse.ArgumentParser(description='بذر قوالب مِداد من ملفّات PDF')
    ap.add_argument('pdfs', nargs='+')
    ap.add_argument('--folder', default='', help='مُعرّف المجلّد، مثل classroom')
    ap.add_argument('--title', default=None, help='عنوانٌ صريح (لملفٍّ واحد)')
    ap.add_argument('--dry-run', action='store_true', help='استخراجٌ وتقريرٌ بلا كتابة')
    ap.add_argument('--force', action='store_true', help='ابذر حتّى مع نصٍّ غير موثوق')
    ap.add_argument('--report', help='ملفّ JSON للتقرير')
    a = ap.parse_args()

    reports = []
    for f in a.pdfs:
        p = Path(f)
        if not p.exists():
            print(f'✗ لا وجود لـ {f}')
            continue
        r = seed_one(p, a.folder, a.title if len(a.pdfs) == 1 else None, a.dry_run, a.force)
        reports.append(r)
        mark = '✅' if r['seeded'] else ('◻' if a.dry_run and r['healthy'] else '⚠')
        print(f"{mark} {r['file']}")
        print(f"   «{r['title']}» → {r['slug']}")
        print(f"   {r['pages']} صفحة · {r['tables']} جدولًا · {r['headings']} عنوانًا · {r['paragraphs']} فقرة")
        if r.get('skipped'):
            print(f"   ⚠ تُخطّي: {r['skipped']}")
        for w in r['warnings'][:2]:
            print(f"   • {w[:150]}")

    if a.report:
        Path(a.report).write_text(json.dumps(reports, ensure_ascii=False, indent=1), encoding='utf-8')

    okc = sum(1 for r in reports if r['seeded'] or (a.dry_run and r['healthy']))
    print(f"\n{okc} من {len(reports)} صالح.")
    return 0 if okc == len(reports) else 1


if __name__ == '__main__':
    raise SystemExit(main())
