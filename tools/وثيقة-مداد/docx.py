#!/usr/bin/env python3
"""بانِي DOCX عربيّ — zip + XML بلا مكتبة.

وهو **بروفةُ `MDD_Docx`** التي ستُكتب في المرحلة الثانية. والملفّ المكتبيّ
حزمةُ zip فيها XML، لا شيء أكثر — لكنّ فيه فخّين لا يظهران إلّا عند الفتح:

  ① **العربية تحتاج علامتين لا واحدة:** `<w:bidi/>` في `w:pPr` يجعل الفقرة
     تتدفّق من اليمين، و`<w:rtl/>` في `w:rPr` يجعل المقطع نفسه عربيًّا.
     وإغفال أحدهما يُخرج ملفًّا يفتح مقلوبًا: النقطة يسار السطر، والأرقام
     قبل الكلمات.

  ② **ترتيب الأبناء في OOXML تسلسلٌ مُلزَم لا مجموعة.** `<w:color/>` بعد
     `<w:sz/>` مخالفةُ مخطّط، و`<w:bidiVisual/>` بعد `<w:tblW/>` كذلك.
     ووورد متسامحٌ فيقرؤها، ومحرّرات أخرى ترفض الملفّ كلَّه. والترتيب
     المكتوب هنا من ECMA-376، ويحرسه `تحقّق.py`.
"""

import re
import zipfile

NS = (
    'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
    'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
)

# ترتيب `w:rPr` — والمخالفة تُرفَض في المدقّق الصارم.
RPR_ORDER = ['rFonts', 'b', 'bCs', 'i', 'iCs', 'color', 'sz', 'szCs', 'rtl']


def esc(text):
    return (str(text).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;'))


def rpr(**flags):
    """`w:rPr` مرتَّبًا — تُبنى من أسماءٍ لا من نصٍّ يُلصَق فيختلّ ترتيبه."""
    out = []

    for key in RPR_ORDER:
        if key in flags and flags[key] is not None:
            out.append(flags[key])

    return '<w:rPr>' + ''.join(out) + '</w:rPr>' if out else ''


class Docx:
    """يجمع الفقرات ثمّ يكتبها حزمةً واحدة."""

    def __init__(self, title='', font='Segoe UI'):
        self.body = []
        self.title = title
        self.font = font

    # ─────────────────── المقاطع ───────────────────

    @staticmethod
    def _is_rtl(part):
        """هل يُوسَم هذا المقطع `rtl`؟

        **نعم لكل شيء إلّا اللاتينيّ الخالص.** والعربيّ يحتاجها بداهةً؛
        والمحايد (نقطة · شرطة · رقم) يحتاجها أيضًا لأنه يتبع اتّجاه ما
        حوله، ونقطةٌ بلا وسمٍ تقفز إلى أوّل السطر. أمّا `PDF` و`Cairo`
        فوسمُها يجعل وورد يرسمها بخطّ الحروف المركّبة لا بخطّها.
        """
        return not (re.search(r'[A-Za-z]', part) and not re.search(r'[\u0600-\u06FF]', part))

    def _runs(self, text, bold=False):
        """`**غامق**` و`` `شفرة` `` تُحوَّل مقاطعَ — والباقي مقطعٌ واحد."""
        out = []

        for part in re.split(r'(\*\*.+?\*\*|`[^`]+`)', str(text)):
            if part == '':
                continue

            mono = False
            strong = bold

            if part.startswith('**') and part.endswith('**'):
                part, strong = part[2:-2], True
            elif part.startswith('`') and part.endswith('`'):
                part, mono = part[1:-1], True

            rtl = '<w:rtl/>' if self._is_rtl(part) else None

            props = rpr(
                rFonts=('<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/>'
                        if mono else None),
                b='<w:b/>' if strong else None,
                bCs='<w:bCs/>' if strong else None,
                color='<w:color w:val="1F5F52"/>' if mono else None,
                rtl=rtl,
            )

            out.append(
                '<w:r>' + props + '<w:t xml:space="preserve">' + esc(part) + '</w:t></w:r>'
            )

        return ''.join(out) or '<w:r>' + rpr(rtl='<w:rtl/>') + '<w:t/></w:r>'

    def _p(self, text, style=None, num=None, bold=False):
        # ترتيب `w:pPr`: pStyle ① · numPr ⑦ · bidi ⑲ — لا يُبدَّل.
        pr = '<w:pPr>'

        if style:
            pr += '<w:pStyle w:val="%s"/>' % style
        if num:
            pr += '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="%d"/></w:numPr>' % num

        pr += '<w:bidi/></w:pPr>'

        self.body.append('<w:p>' + pr + self._runs(text, bold) + '</w:p>')

    # ─────────────────── الواجهة ───────────────────

    def h(self, level, text):
        self._p(text, 'Heading%d' % level)

    def title_page(self, text, subtitle=''):
        self._p(text, 'MddTitle')
        if subtitle:
            self._p(subtitle, 'MddSub')

    def p(self, text=''):
        self._p(text, 'Normal')

    def bullet(self, items):
        for one in items:
            self._p(one, 'MddList', num=1)

    def number(self, items):
        for one in items:
            self._p(one, 'MddList', num=2)

    def note(self, text):
        self._p(text, 'MddNote')

    def page_break(self):
        self.body.append('<w:p><w:pPr><w:bidi/></w:pPr>'
                         '<w:r><w:br w:type="page"/></w:r></w:p>')

    def table(self, rows, widths=None, head=True):
        """جدولٌ من اليمين — `bidiVisual` تعكس ترتيب الأعمدة نفسه."""
        cols = max(len(r) for r in rows)
        widths = widths or [round(9000 / cols)] * cols

        # ترتيب `w:tblPr`: tblStyle ① · bidiVisual ④ · tblW ⑦ · tblLayout ⑬
        xml = ['<w:tbl><w:tblPr><w:tblStyle w:val="MddTable"/><w:bidiVisual/>'
               '<w:tblW w:w="5000" w:type="pct"/>'
               '<w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>']
        xml += ['<w:gridCol w:w="%d"/>' % w for w in widths]
        xml.append('</w:tblGrid>')

        for i, row in enumerate(rows):
            head_row = head and i == 0
            xml.append('<w:tr>')

            if head_row:
                xml.append('<w:trPr><w:tblHeader/></w:trPr>')

            for j in range(cols):
                cell = row[j] if j < len(row) else ''
                shade = '<w:shd w:val="clear" w:fill="EFEDE6"/>' if head_row else ''

                xml.append(
                    '<w:tc><w:tcPr><w:tcW w:w="%d" w:type="dxa"/>%s'
                    '<w:vAlign w:val="center"/></w:tcPr>'
                    '<w:p><w:pPr><w:pStyle w:val="MddCell"/><w:bidi/></w:pPr>%s</w:p></w:tc>'
                    % (widths[j], shade, self._runs(cell, head_row))
                )

            xml.append('</w:tr>')

        xml.append('</w:tbl>')
        self.body.append(''.join(xml))
        # فقرةٌ فاصلة: جدولان متلاصقان يندمجان في وورد بلا واحدة.
        self._p('', 'MddAfterTable')

    # ─────────────────── الحزمة ───────────────────

    def save(self, path):
        with zipfile.ZipFile(path, 'w', zipfile.ZIP_DEFLATED) as z:
            z.writestr('[Content_Types].xml', CONTENT_TYPES)
            z.writestr('_rels/.rels', RELS)
            z.writestr('docProps/core.xml', CORE % esc(self.title))
            z.writestr('word/_rels/document.xml.rels', DOC_RELS)
            z.writestr('word/settings.xml', SETTINGS)
            z.writestr('word/styles.xml', STYLES % (self.font, self.font))
            z.writestr('word/numbering.xml', NUMBERING)
            z.writestr('word/document.xml', DOCUMENT % (NS, ''.join(self.body)))


# ─────────────────── قوالب الحزمة ───────────────────

CONTENT_TYPES = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>'''

RELS = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>'''

DOC_RELS = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>'''

# ترتيب `CT_CoreProperties` تسلسلٌ أيضًا: `language` قبل `title`.
CORE = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
 xmlns:dc="http://purl.org/dc/elements/1.1/">
<dc:language>ar-SA</dc:language><dc:title>%s</dc:title>
</cp:coreProperties>'''

# `defaultTabStop` يسبق `themeFontLang` في `CT_Settings`.
# و`rtlGutter` ليست هنا أصلًا — هي ابنُ `sectPr`، وموضعها أدناه.
SETTINGS = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:defaultTabStop w:val="720"/>
<w:themeFontLang w:val="en-US" w:bidi="ar-SA"/>
</w:settings>'''

# `sectPr`: pgSz ④ · pgMar ⑤ · bidi ⑯ · rtlGutter ⑰ — وهامش التجليد يمينًا.
DOCUMENT = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document %s><w:body>%s
<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>
<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/>
<w:bidi/><w:rtlGutter/></w:sectPr></w:body></w:document>'''

NUMBERING = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="hybridMultilevel"/>
<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/>
<w:lvlJc w:val="right"/><w:pPr><w:ind w:start="454" w:hanging="284"/></w:pPr>
<w:rPr><w:rFonts w:ascii="Segoe UI" w:hAnsi="Segoe UI" w:cs="Segoe UI" w:hint="default"/></w:rPr></w:lvl>
</w:abstractNum>
<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="hybridMultilevel"/>
<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/>
<w:lvlJc w:val="right"/><w:pPr><w:ind w:start="454" w:hanging="284"/></w:pPr></w:lvl>
</w:abstractNum>
<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>
<w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>
</w:numbering>'''

# ترتيب `w:pPr`: pBdr ⑨ · shd ⑩ · bidi ⑲ · spacing ㉒ · ind ㉓ ·
#                contextualSpacing ㉔ · jc ㉗ · outlineLvl ㉜
# وترتيب `w:rPr`: b ③ · i ⑤ · color ⑲ · sz ㉔
STYLES = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:docDefaults><w:rPrDefault><w:rPr>
<w:rFonts w:ascii="%s" w:hAnsi="%s" w:cs="Segoe UI"/>
<w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:bidi="ar-SA"/>
</w:rPr></w:rPrDefault>
<w:pPrDefault><w:pPr><w:bidi/>
<w:spacing w:after="120" w:line="288" w:lineRule="auto"/>
<w:jc w:val="both"/></w:pPr></w:pPrDefault>
</w:docDefaults>

<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/>
<w:pPr><w:bidi/><w:jc w:val="both"/></w:pPr></w:style>

<w:style w:type="paragraph" w:styleId="MddTitle"><w:name w:val="Midad Title"/>
<w:pPr><w:bidi/><w:spacing w:before="1200" w:after="120"/><w:jc w:val="center"/></w:pPr>
<w:rPr><w:b/><w:bCs/><w:color w:val="14332C"/><w:sz w:val="64"/><w:szCs w:val="64"/></w:rPr></w:style>

<w:style w:type="paragraph" w:styleId="MddSub"><w:name w:val="Midad Subtitle"/>
<w:pPr><w:bidi/><w:spacing w:after="600"/><w:jc w:val="center"/></w:pPr>
<w:rPr><w:color w:val="6B7A73"/><w:sz w:val="28"/><w:szCs w:val="28"/></w:rPr></w:style>

<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/>
<w:basedOn w:val="Normal"/>
<w:pPr><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="4" w:color="1F5F52"/></w:pBdr>
<w:bidi/><w:spacing w:before="480" w:after="200"/><w:jc w:val="start"/>
<w:outlineLvl w:val="0"/></w:pPr>
<w:rPr><w:b/><w:bCs/><w:color w:val="14332C"/><w:sz w:val="40"/><w:szCs w:val="40"/></w:rPr></w:style>

<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/>
<w:basedOn w:val="Normal"/>
<w:pPr><w:bidi/><w:spacing w:before="360" w:after="140"/><w:jc w:val="start"/>
<w:outlineLvl w:val="1"/></w:pPr>
<w:rPr><w:b/><w:bCs/><w:color w:val="1F5F52"/><w:sz w:val="30"/><w:szCs w:val="30"/></w:rPr></w:style>

<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/>
<w:basedOn w:val="Normal"/>
<w:pPr><w:bidi/><w:spacing w:before="280" w:after="100"/><w:jc w:val="start"/>
<w:outlineLvl w:val="2"/></w:pPr>
<w:rPr><w:b/><w:bCs/><w:color w:val="14332C"/><w:sz w:val="25"/><w:szCs w:val="25"/></w:rPr></w:style>

<w:style w:type="paragraph" w:styleId="Heading4"><w:name w:val="heading 4"/>
<w:basedOn w:val="Normal"/>
<w:pPr><w:bidi/><w:spacing w:before="200" w:after="60"/><w:jc w:val="start"/>
<w:outlineLvl w:val="3"/></w:pPr>
<w:rPr><w:b/><w:bCs/><w:color w:val="8A6A2F"/><w:sz w:val="23"/><w:szCs w:val="23"/></w:rPr></w:style>

<w:style w:type="paragraph" w:styleId="MddList"><w:name w:val="Midad List"/>
<w:basedOn w:val="Normal"/>
<w:pPr><w:bidi/><w:spacing w:after="60"/><w:contextualSpacing/>
<w:jc w:val="start"/></w:pPr></w:style>

<w:style w:type="paragraph" w:styleId="MddNote"><w:name w:val="Midad Note"/>
<w:basedOn w:val="Normal"/>
<w:pPr><w:pBdr><w:end w:val="single" w:sz="24" w:space="8" w:color="C9A227"/></w:pBdr>
<w:shd w:val="clear" w:fill="FBF7EC"/>
<w:bidi/><w:spacing w:before="160" w:after="200"/>
<w:ind w:start="170" w:end="170"/><w:jc w:val="start"/></w:pPr>
<w:rPr><w:i/><w:iCs/><w:color w:val="4A4233"/></w:rPr></w:style>

<w:style w:type="paragraph" w:styleId="MddCell"><w:name w:val="Midad Cell"/>
<w:basedOn w:val="Normal"/>
<w:pPr><w:bidi/><w:spacing w:before="60" w:after="60" w:line="240" w:lineRule="auto"/>
<w:jc w:val="start"/></w:pPr>
<w:rPr><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:style>

<w:style w:type="paragraph" w:styleId="MddAfterTable"><w:name w:val="Midad After Table"/>
<w:basedOn w:val="Normal"/>
<w:pPr><w:bidi/><w:spacing w:after="0" w:line="120" w:lineRule="auto"/></w:pPr>
<w:rPr><w:sz w:val="6"/><w:szCs w:val="6"/></w:rPr></w:style>

<w:style w:type="table" w:styleId="MddTable"><w:name w:val="Midad Table"/>
<w:tblPr><w:tblBorders>
<w:top w:val="single" w:sz="4" w:space="0" w:color="D8D4C8"/>
<w:start w:val="single" w:sz="4" w:space="0" w:color="D8D4C8"/>
<w:bottom w:val="single" w:sz="4" w:space="0" w:color="D8D4C8"/>
<w:end w:val="single" w:sz="4" w:space="0" w:color="D8D4C8"/>
<w:insideH w:val="single" w:sz="4" w:space="0" w:color="E5E2DA"/>
<w:insideV w:val="single" w:sz="4" w:space="0" w:color="E5E2DA"/>
</w:tblBorders>
<w:tblCellMar>
<w:top w:w="60" w:type="dxa"/><w:start w:w="108" w:type="dxa"/>
<w:bottom w:w="60" w:type="dxa"/><w:end w:w="108" w:type="dxa"/>
</w:tblCellMar></w:tblPr></w:style>
</w:styles>'''
