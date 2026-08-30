import React from 'react'

/** لقطة محرّر الملفّ كما تعمل — الحقول في جهة والورقة الحيّة في الأخرى.
 *  مرسومةٌ بالعناصر لا بصورة: تتبع الرموز، وتعمل في الوضعين، وبلا ملفّ يُحمّل. */
export default function ProductShot() {
  return (
    <div className="mdd-shot" role="img"
      aria-label="محرّر مِداد: الحقول في جهة والورقة تتحدّث معها في الجهة الأخرى">
      <div className="mdd-shot__bar">
        <span className="mdd-shot__dot" />
        <span className="mdd-shot__dot" />
        <span className="mdd-shot__dot" />
        <span className="mdd-shot__name">حقيبة مدير المدرسة 1447</span>
        <span className="mdd-shot__saved">حُفظ قبل لحظات</span>
      </div>

      <div className="mdd-shot__body">
        <div className="mdd-shot__fields">
          <div>
            <div className="mdd-shot__label">اسم المدرسة</div>
            <div className="mdd-shot__input" style={{ marginBlockStart: 5 }}>ابتدائية الأمل</div>
          </div>
          <div>
            <div className="mdd-shot__label">إدارة التعليم</div>
            <div className="mdd-shot__input" style={{ marginBlockStart: 5 }}>إدارة تعليم الرياض</div>
          </div>
          <div>
            <div className="mdd-shot__label">مدير المدرسة</div>
            <div className="mdd-shot__input mdd-shot__input--live" style={{ marginBlockStart: 5 }}>
              أحمد سالم الغامدي<span className="mdd-shot__caret" />
            </div>
          </div>
          <div>
            <div className="mdd-shot__label">عدد الطلاب</div>
            <div className="mdd-shot__input mdd-num" style={{ marginBlockStart: 5 }}>412</div>
          </div>
          <div style={{ marginBlockStart: 'auto', display: 'flex', gap: 6, alignItems: 'center' }}>
            <div style={{ flex: 1, height: 5, borderRadius: 3, background: 'var(--mdd-sunken)', overflow: 'hidden' }}>
              <div style={{ width: '72%', height: '100%', background: 'var(--mdd-accent)' }} />
            </div>
            <span className="mdd-shot__label mdd-num">13/18</span>
          </div>
        </div>

        <div className="mdd-shot__paper">
          <div className="mdd-shot__sheet">
            <div className="mdd-shot__sheet-head">
              <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                <span style={{ fontSize: 6.5, fontWeight: 700 }}>وزارة التعليم</span>
                <span style={{ fontSize: 6, color: '#777' }}>إدارة تعليم الرياض</span>
              </div>
              <div style={{ width: 17, height: 17, border: '1px dashed #c4c9c3', borderRadius: 3 }} />
              <div style={{ display: 'flex', flexDirection: 'column', gap: 2, textAlign: 'end' }}>
                <span style={{ fontSize: 6.5, fontWeight: 700 }}>ابتدائية الأمل</span>
                <span style={{ fontSize: 6, color: '#777' }}>1447 هـ</span>
              </div>
            </div>

            <div className="mdd-shot__sheet-title">حقيبة مدير المدرسة</div>

            <div className="mdd-shot__line" style={{ width: '32%' }} />
            <div className="mdd-shot__line mdd-shot__line--fill" style={{ width: '58%' }} />
            <div className="mdd-shot__line" style={{ width: '88%' }} />
            <div className="mdd-shot__line" style={{ width: '74%' }} />

            <div className="mdd-shot__tbl">
              <div className="mdd-shot__trow">
                {[0, 1, 2, 3].map((i) => <span key={i} className="mdd-shot__cell mdd-shot__cell--head" />)}
              </div>
              {[0, 1, 2, 3].map((r) => (
                <div className="mdd-shot__trow" key={r}>
                  {[0, 1, 2, 3].map((i) => <span key={i} className="mdd-shot__cell" />)}
                </div>
              ))}
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', marginBlockStart: 6 }}>
              <div className="mdd-shot__line" style={{ width: '26%' }} />
              <div className="mdd-shot__line" style={{ width: '26%' }} />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
