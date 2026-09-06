"""
تطبيع العربيّة المستخرَجة من PDF.

أخطر ما في استخراج العربيّة من PDF شيئان، وكلاهما يجعل النصّ يبدو سليمًا
للعين ويكون فاسدًا في البيانات:

١) **صور العرض** (U+FE70–FEFF): كثيرٌ من مولّدات PDF تكتب الحرف بشكله
   المتّصل لا بحرفه الأساسيّ. فـ«معلم» تُخزَّن أربعة محارف مختلفة عن
   «م ع ل م». والبحث والفرز والتصدير كلّها تفسد. NFKC يعيدها، ويفكّ
   لام-ألف المدمجة إلى حرفين.

٢) **الترتيب البصريّ**: بعض الملفّات تخزّن الحروف بترتيب الرسم من اليسار،
   فيخرج النصّ مقلوبًا. لا يوجد كشفٌ قاطع، فنعتمد شاهدين: نسبة الكلمات
   المعروفة، وموضع «ال» التعريف.
"""
import re
import unicodedata

# كلماتٌ لا تكاد تخلو منها وثيقةٌ مدرسيّة سعوديّة — شاهدُنا على الاتّجاه
ANCHORS = {
    'المدرسة', 'الطالب', 'الطالبة', 'المعلم', 'المعلّم', 'الصف', 'الصفّ',
    'المادة', 'المادّة', 'التاريخ', 'اليوم', 'الاسم', 'ملاحظات', 'التوقيع',
    'إدارة', 'تعليم', 'الفصل', 'الدراسي', 'الدراسيّ', 'العام', 'رقم',
    'وزارة', 'التربية', 'والتعليم', 'مدير', 'وكيل', 'المشرف', 'الحضور',
    'الغياب', 'الدرجة', 'المجموع', 'النتيجة', 'خطة', 'خطّة', 'تقرير',
    'محضر', 'اجتماع', 'نموذج', 'استمارة', 'سجل', 'سجلّ', 'متابعة',
}

PRESENTATION = re.compile(r'[ﭐ-﷿ﹰ-﻿]')
ARABIC = re.compile(r'[؀-ۿݐ-ݿ]')
TATWEEL = 'ـ'
# التشكيل: نُبقيه: الوثائق المدرسيّة تستعمله في الأسماء والعناوين
DIACRITICS = re.compile(r'[ً-ٰٟ]')


def has_presentation_forms(s: str) -> bool:
    return bool(PRESENTATION.search(s))


def to_logical_letters(s: str) -> str:
    """صور العرض ← الحروف الأساسيّة، مع فكّ لام-ألف."""
    if not has_presentation_forms(s):
        return s
    out = unicodedata.normalize('NFKC', s)
    return out.replace(TATWEEL, '')


def _score(text: str) -> int:
    """عدد الكلمات المرساة الموجودة في النصّ."""
    words = re.findall(r'[؀-ۿ]+', text)
    hits = 0
    for w in words:
        bare = DIACRITICS.sub('', w)
        if w in ANCHORS or bare in ANCHORS:
            hits += 1
    return hits


def _al_prefix_ratio(text: str) -> float:
    """نسبة الكلمات التي تبدأ بـ«ال». في النصّ المقلوب تنتهي بها."""
    words = [w for w in re.findall(r'[؀-ۿ]+', text) if len(w) > 3]
    if not words:
        return 0.0
    starts = sum(1 for w in words if w.startswith('ال'))
    return starts / len(words)


def looks_visual(text: str) -> bool:
    """
    هل النصّ بترتيبٍ بصريّ (مقلوب)؟

    نقارن الأصل بمقلوبه بشاهدين. ولا نحكم إن تعادلا — فالقلب الخاطئ
    أسوأ من تركِ ما لا نتيقّن منه.
    """
    if not ARABIC.search(text):
        return False
    rev = text[::-1]
    s_fwd, s_rev = _score(text), _score(rev)
    if s_fwd != s_rev:
        return s_rev > s_fwd
    a_fwd, a_rev = _al_prefix_ratio(text), _al_prefix_ratio(rev)
    # فرقٌ يسير لا يكفي للحكم
    return a_rev > a_fwd + 0.15


def fix_order(text: str) -> str:
    """يقلب النصّ إن ثبت أنّه بصريّ، سطرًا سطرًا."""
    lines = text.split('\n')
    out = []
    for ln in lines:
        out.append(ln[::-1] if looks_visual(ln) else ln)
    return '\n'.join(out)


def normalize(text: str) -> str:
    """التطبيع الكامل: صور العرض ثمّ الاتّجاه ثمّ المسافات."""
    s = to_logical_letters(text or '')
    s = fix_order(s)
    s = s.replace('‏', '').replace('‎', '')     # علامات الاتّجاه
    s = re.sub(r'[ \t ]+', ' ', s)
    s = re.sub(r' *\n *', '\n', s)
    return s.strip()


def is_arabic_heavy(text: str, threshold: float = 0.35) -> bool:
    letters = [c for c in text if c.isalpha()]
    if not letters:
        return False
    ar = sum(1 for c in letters if ARABIC.match(c))
    return ar / len(letters) >= threshold


# ─────────────────── سلامة طبقة النصّ ───────────────────

# ما نتوقّعه في وثيقةٍ مدرسيّة سعوديّة: عربيّة، ولاتينيّة أساسيّة، وأرقام،
# وترقيم. وما خرج عن هذا في الغالب خريطةُ محارفَ فاسدة في الـPDF نفسه.
_EXPECTED = re.compile(
    r'[\u0600-\u06FF\u0750-\u077F\uFB50-\uFDFF\uFE70-\uFEFF'   # عربيّة
    r'\u0020-\u007E'                                                 # لاتينيّة أساسيّة وترقيم
    r'\u00A0\u00B7\u00AB\u00BB\u00D7\u00F7'                       # · « » × ÷
    r'\u060C\u061B\u061F\u0640\u066A-\u066D'
    r'\u2000-\u206F\u20AA-\u20BF'                                  # مسافات وعملات
    r'\u2190-\u21FF\u2500-\u257F\u25A0-\u25FF\u2713\u2714\u2717'
    r'\uFDF2\uFDFA\uFDFB\uFDFD]'                                    # الله ﷺ ﷻ ﷽
)


def text_layer_health(text: str) -> dict:
    """
    نسبة المحارف غير المتوقّعة. حين تعلو، فطبقة النصّ في الـPDF فاسدة:
    الخطّ يُخزّن رسومَ الحروف مربوطةً برموزٍ لاتينيّة موسَّعة (ﻟ ← ǁ مثلًا)،
    فلا استخراجَ يُصلحها — تحتاج قراءةً ضوئيّة (OCR).

    نكشفها ونُبلّغ بها بدل أن نُخرج مستندًا يبدو صحيحًا ونصُّه خُرافة.
    """
    chars = [c for c in text if not c.isspace()]
    if not chars:
        return {'chars': 0, 'bad_ratio': 0.0, 'healthy': True, 'sample': ''}
    bad = [c for c in chars if not _EXPECTED.match(c)]
    ratio = len(bad) / len(chars)
    seen, sample = set(), []
    for c in bad:
        if c not in seen:
            seen.add(c); sample.append(c)
        if len(sample) >= 12:
            break
    # النصّ القصير يحتمل محرفًا غريبًا واحدًا بلا أن يكون فاسدًا،
    # فنطلب شاهدين: نسبةً عالية وعددًا مطلقًا لا يُفسَّر بالمصادفة.
    healthy = ratio < 0.03 or len(bad) <= 2
    return {
        'chars': len(chars),
        'bad': len(bad),
        'bad_ratio': round(ratio, 4),
        'healthy': healthy,
        'sample': ''.join(sample),
    }
