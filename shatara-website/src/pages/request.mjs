import { esc, icon } from '../lib.mjs';
import { layout, breadcrumb } from '../layout.mjs';
import { contact, whatsappUrl, countryCodes } from '../data/site.mjs';

export function renderRequest(ctx) {
  const { t, root } = ctx;
  const r = t.request;
  const steps = r.steps
    .map(
      (s, i, a) => `<div class="rq-step">
                <div class="rq-step-l"><span class="rq-step-n">${i + 1}</span>${i < a.length - 1 ? '<span class="rq-step-line"></span>' : ''}</div>
                <span class="rq-step-t">${esc(s)}</span>
              </div>`
    )
    .join('\n              ');

  const body = `
<section class="rq">
  <div class="rq-in">
    <div class="rq-head">
      ${breadcrumb(ctx, r.title)}
      <h1>${esc(r.title)}</h1>
      <p>${esc(r.text)}</p>
    </div>

    <div class="rq-cols">
      <div class="rq-card" data-form-wrap>
        <form class="rq-form" data-form="request" data-wa-title="${esc(r.whatsappTitle)}">
          <div class="rq-cv" data-cv-box hidden>${icon('identification-card')}${esc(r.selectedCv)} <strong dir="ltr" data-cv-code></strong><input type="hidden" name="cv" data-label="${esc(r.selectedCvLabel)}" data-cv-input></div>
          <div class="rq-group">
            <div class="rq-gh"><span>1</span>${esc(r.step1)}</div>
            <div class="rq-row">
              <label class="rq-l">${esc(r.name)}<input class="rq-i" required type="text" name="name" autocomplete="name" data-label="${esc(r.name)}"></label>
              <label class="rq-l">${esc(r.phone)}<input class="rq-i rq-tel" required type="tel" name="phone" placeholder="05xxxxxxxx" autocomplete="tel" inputmode="tel" pattern="[0-9+ \\-]{9,16}" title="${esc(t.phoneTitle)}" data-label="${esc(r.phone)}"></label>
              <label class="rq-l">${esc(r.city)}<input class="rq-i" required type="text" name="city" autocomplete="address-level2" data-label="${esc(r.city)}"></label>
            </div>
          </div>
          <div class="rq-group rq-group-2">
            <div class="rq-gh"><span>2</span>${esc(r.step2)}</div>
            <div class="rq-l" role="group" aria-label="${esc(r.type)}">${esc(r.type)}
              <div class="rq-types" data-choice="type">
                <button class="rq-type on" type="button" data-value="${esc(r.typeInd)}" aria-pressed="true">${icon('house-line')}${esc(r.typeInd)}</button>
                <button class="rq-type" type="button" data-value="${esc(r.typeBiz)}" aria-pressed="false">${icon('buildings')}${esc(r.typeBiz)}</button>
                <input type="hidden" name="type" value="${esc(r.typeInd)}" data-label="${esc(r.type)}">
              </div>
            </div>
            <div class="rq-row">
              <label class="rq-l">${esc(r.job)}
                <select class="rq-s" required name="job" data-label="${esc(r.job)}">
                  <option value="">${esc(r.jobPlaceholder)}</option>
                  ${r.jobs.map(j => `<option>${esc(j)}</option>`).join('')}
                </select>
              </label>
              <label class="rq-l">${esc(r.country)}
                <select class="rq-s" name="country" data-label="${esc(r.country)}">
                  <option value="">${esc(r.countryPlaceholder)}</option>
                  ${countryCodes.map(cc => `<option>${esc(t.countries[cc])}</option>`).join('')}
                </select>
              </label>
              <div class="rq-l" role="group" aria-label="${esc(r.count)}">${esc(r.count)}
                <div class="rq-count" data-counter>
                  <button type="button" data-inc aria-label="${esc(r.increase)}">${icon('plus')}</button>
                  <span data-count-value aria-live="polite">1</span>
                  <button type="button" data-dec aria-label="${esc(r.decrease)}">${icon('minus')}</button>
                  <input type="hidden" name="count" value="1" data-label="${esc(r.count)}">
                </div>
              </div>
              <div class="rq-l" role="group" aria-label="${esc(r.visa)}">${esc(r.visa)}
                <div class="rq-visa" data-choice="visa">
                  <button type="button" data-value="${esc(r.yes)}" aria-pressed="false">${esc(r.yes)}</button>
                  <button class="on" type="button" data-value="${esc(r.no)}" aria-pressed="true">${esc(r.no)}</button>
                  <input type="hidden" name="visa" value="${esc(r.no)}" data-label="${esc(r.visa)}">
                </div>
              </div>
            </div>
            <label class="rq-l">${esc(r.notes)}<textarea class="rq-t" rows="4" name="notes" placeholder="${esc(r.notesPlaceholder)}" data-label="${esc(r.notes)}"></textarea></label>
          </div>
          <button class="rq-submit" type="submit">${icon('paper-plane-tilt')}${esc(r.submit)}</button>
        </form>
        <div class="rq-sent" data-sent hidden>
          <span class="rq-sent-ic">${icon('check-circle')}</span>
          <p role="status">${esc(r.success)}</p>
          <div class="rq-sent-btns">
            <a class="rq-sent-o" href="index.html">${esc(t.common.home)}</a>
            <a class="rq-sent-p" href="cvs.html">${esc(r.browse)}</a>
          </div>
        </div>
      </div>

      <aside class="rq-aside">
        <div class="rq-steps">
          <img class="rq-wm" src="${root}assets/img/logo-white-480.webp" alt="" width="296" height="480" loading="lazy">
          <h2>${esc(r.stepsTitle)}</h2>
          <div class="rq-steps-list">
              ${steps}
          </div>
        </div>
        <div class="rq-direct">
          <span>${esc(r.direct)}</span>
          <a class="rq-direct-wa" href="${whatsappUrl}">${icon('whatsapp-logo')}${esc(t.common.whatsappBtn)}</a>
          <a class="rq-direct-tel" href="tel:${contact.phone}">${icon('phone')}${esc(r.customerService)} <span dir="ltr">${contact.phone}</span></a>
        </div>
        <div class="rq-privacy">${icon('lock-simple')}${esc(r.privacy)}</div>
      </aside>
    </div>
  </div>
</section>
`;
  return layout(ctx, { body, active: 'request' });
}
