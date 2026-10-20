// Shatara Recruitment — page behaviour (no dependencies).
(() => {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ---------- Mobile menu ---------- */
  const menuBtn = $('.menu-btn');
  const mnav = $('#mnav');
  if (menuBtn && mnav) {
    const setMenu = open => {
      mnav.hidden = !open;
      menuBtn.setAttribute('aria-expanded', String(open));
    };
    menuBtn.addEventListener('click', () => setMenu(mnav.hidden));
    $$('a', mnav).forEach(a => a.addEventListener('click', () => setMenu(false)));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !mnav.hidden) {
        setMenu(false);
        menuBtn.focus();
      }
    });
  }

  /* ---------- Hero slider ---------- */
  const hero = $('[data-hero]');
  if (hero) {
    const slides = $$('[data-slide]', hero);
    const images = $$('[data-slide-img]', hero);
    const dots = $$('[data-dot]', hero);
    const count = slides.length;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let current = 0;
    let timer;
    const show = index => {
      current = (index + count) % count;
      slides.forEach((s, i) => { s.hidden = i !== current; });
      images.forEach((img, i) => img.classList.toggle('on', i === current));
      dots.forEach((d, i) => {
        d.classList.toggle('on', i === current);
        if (i === current) d.setAttribute('aria-current', 'true');
        else d.removeAttribute('aria-current');
      });
    };
    const start = () => {
      clearInterval(timer);
      if (!reduceMotion) timer = setInterval(() => show(current + 1), 7000);
    };
    const go = index => { show(index); start(); };
    $('[data-prev]', hero).addEventListener('click', () => go(current - 1));
    $('[data-next]', hero).addEventListener('click', () => go(current + 1));
    dots.forEach((d, i) => d.addEventListener('click', () => go(i)));
    start();
  }

  /* ---------- FAQ accordion (one item open at a time) ---------- */
  $$('[data-faq]').forEach(list => {
    const items = $$('.faq-item', list);
    const setOpen = (item, open) => {
      $('.faq-q', item).setAttribute('aria-expanded', String(open));
      $('.faq-a', item).hidden = !open;
    };
    items.forEach(item => {
      $('.faq-q', item).addEventListener('click', () => {
        const wasOpen = $('.faq-q', item).getAttribute('aria-expanded') === 'true';
        items.forEach(other => setOpen(other, false));
        if (!wasOpen) setOpen(item, true);
      });
    });
  });

  /* ---------- Forms: Formspree when configured, otherwise WhatsApp ---------- */
  const formspreeId = document.body.dataset.formspree;
  const whatsapp = document.body.dataset.whatsapp;

  const whatsappText = form => {
    const lines = [form.dataset.waTitle];
    $$('[name]', form).forEach(field => {
      const value = String(field.value || '').trim();
      if (value && field.dataset.label) lines.push(`${field.dataset.label}: ${value}`);
    });
    return lines.join('\n');
  };
  const openWhatsapp = form => {
    const url = `https://wa.me/${whatsapp}?text=${encodeURIComponent(whatsappText(form))}`;
    // (No "noopener" feature here: with it window.open always returns null.)
    const win = window.open(url, '_blank');
    if (win) win.opener = null;
    // Popup blocked: go to WhatsApp in this tab (skipped inside embedded previews).
    else if (window.top === window.self) window.location.href = url;
  };

  $$('[data-form]').forEach(form => {
    const wrap = form.closest('[data-form-wrap]');
    const sent = $('[data-sent]', wrap);
    const showSent = () => {
      form.hidden = true;
      sent.hidden = false;
      if (form.dataset.form === 'request') window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (!formspreeId) {
        openWhatsapp(form);
        showSent();
        return;
      }
      const button = $('[type="submit"]', form);
      button.disabled = true;
      try {
        const res = await fetch(`https://formspree.io/f/${encodeURIComponent(formspreeId)}`, {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json' }
        });
        if (!res.ok) throw new Error(String(res.status));
      } catch {
        openWhatsapp(form);
      }
      button.disabled = false;
      showSent();
    });
    const again = $('[data-again]', wrap);
    if (again) {
      again.addEventListener('click', () => {
        form.reset();
        sent.hidden = true;
        form.hidden = false;
      });
    }
  });

  /* ---------- Request page: choices, counter, selected CV ---------- */
  $$('[data-choice]').forEach(group => {
    const input = $('input[type="hidden"]', group);
    const buttons = $$('button', group);
    buttons.forEach(b => b.addEventListener('click', () => {
      buttons.forEach(o => {
        o.classList.toggle('on', o === b);
        o.setAttribute('aria-pressed', String(o === b));
      });
      input.value = b.dataset.value;
    }));
  });
  const counter = $('[data-counter]');
  if (counter) {
    const output = $('[data-count-value]', counter);
    const input = $('input', counter);
    const set = n => { input.value = output.textContent = String(n); };
    $('[data-inc]', counter).addEventListener('click', () => set(Number(input.value) + 1));
    $('[data-dec]', counter).addEventListener('click', () => set(Math.max(1, Number(input.value) - 1)));
    const form = counter.closest('form');
    form.addEventListener('reset', () => setTimeout(() => set(1)));
  }
  const cvBox = $('[data-cv-box]');
  if (cvBox) {
    const cv = new URLSearchParams(window.location.search).get('cv');
    if (cv && /^[A-Za-z0-9-]{1,20}$/.test(cv)) {
      $('[data-cv-code]', cvBox).textContent = cv;
      $('[data-cv-input]', cvBox).value = cv;
      cvBox.hidden = false;
    }
  }

  /* ---------- CVs page: filters and CV dialog ---------- */
  const cvRoot = $('[data-cvs]');
  if (cvRoot) {
    const panel = $('.cvf', cvRoot);
    const selects = $$('[data-filter]', cvRoot);
    const cards = $$('[data-cv]');
    const grid = $('[data-grid]');
    const empty = $('[data-empty]');
    const countEl = $('[data-count]');
    const clearBtn = $('[data-clear]', cvRoot);
    const toggle = $('[data-toggle]', cvRoot);

    const matches = (card, f) => {
      const d = card.dataset;
      const age = Number(d.age);
      const years = Number(d.years);
      return (!f.job || d.job === f.job)
        && (!f.country || d.cc === f.country)
        && (!f.age || (f.age === 'a' ? age <= 30 : f.age === 'b' ? age > 30 && age <= 40 : age > 40))
        && (!f.exp || (f.exp === 'yes' ? years > 0 : years === 0))
        && (!f.rel || d.rel === f.rel)
        && (!f.lang || d.langs.split(' ').includes(f.lang));
    };
    const apply = () => {
      const f = Object.fromEntries(selects.map(s => [s.dataset.filter, s.value]));
      let shown = 0;
      cards.forEach(card => {
        const ok = matches(card, f);
        card.hidden = !ok;
        if (ok) shown++;
      });
      countEl.textContent = shown ? countEl.dataset.label + shown : '';
      grid.hidden = !shown;
      empty.hidden = !!shown;
      clearBtn.hidden = !selects.some(s => s.value);
    };
    selects.forEach(s => s.addEventListener('change', apply));
    clearBtn.addEventListener('click', () => {
      selects.forEach(s => { s.value = ''; });
      apply();
    });
    toggle.addEventListener('click', () => {
      const open = !panel.classList.contains('open');
      panel.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', String(open));
      $('span', toggle).textContent = open ? toggle.dataset.hide : toggle.dataset.show;
    });

    const modal = $('[data-modal]');
    const dialog = $('.cvm-dialog', modal);
    let opener = null;
    const close = () => {
      modal.hidden = true;
      dialog.replaceChildren();
      if (opener) opener.focus();
    };
    $$('[data-view]').forEach(btn => btn.addEventListener('click', () => {
      const tpl = document.getElementById('cvm-' + btn.dataset.view);
      if (!tpl) return;
      opener = btn;
      dialog.replaceChildren(tpl.content.cloneNode(true));
      modal.hidden = false;
      dialog.scrollTop = 0;
      $('[data-close]', dialog).addEventListener('click', close);
      $('[data-close]', dialog).focus();
    }));
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
    document.addEventListener('keydown', e => {
      if (modal.hidden) return;
      if (e.key === 'Escape') close();
      if (e.key === 'Tab') {
        const focusable = $$('a[href], button', dialog);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }
})();
