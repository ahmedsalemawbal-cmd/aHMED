/* @ds-bundle: {"format":4,"namespace":"Maidani","components":[{"name":"Button"},{"name":"TextField"},{"name":"StageBadge"},{"name":"PriorityBadge"},{"name":"LeadCard"},{"name":"ScoreBar"},{"name":"TriSelect"},{"name":"BottomSheet"},{"name":"Toast"},{"name":"EmptyState"}]} */
(function () {
  var React = window.React;
  var h = React.createElement;
  function cx() { return Array.prototype.filter.call(arguments, Boolean).join(' '); }

  /* ---- internal icons (24px grid, 2px stroke, currentColor) ---- */
  var PATHS = {
    plus: 'M12 5v14M5 12h14',
    chat: 'M4 19.5l1.4-3.6A8 8 0 1 1 8.2 18.7L4 19.5z',
    phone: 'M6.6 3.5h3l1.5 4-2 1.3a11 11 0 0 0 6.1 6.1l1.3-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z',
    check: 'M5 12.5l4.5 4.5L19 7.5',
    x: 'M6 6l12 12M18 6L6 18',
    minus: 'M6 12h12',
    clock: 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
    alert: 'M12 8v5M12 16.5v.5M10.3 3.9L2.6 17.5A2 2 0 0 0 4.3 20.5h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
    info: 'M12 11v6M12 7.5v.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
    pin: 'M12 21s-7-6.1-7-11.5a7 7 0 0 1 14 0C19 14.9 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
    store: 'M4 9.5V20h16V9.5M3 4h18l-1.5 5.5a2.7 2.7 0 0 1-5.2 0 2.7 2.7 0 0 1-5.1 0 2.7 2.7 0 0 1-5.2 0L3 4zM10 20v-5h4v5',
    flame: 'M12 21a6.5 6.5 0 0 0 6.5-6.5c0-4.5-4-6.5-4.5-10.5-2 1.5-3.5 3.5-3.5 6-1-.5-1.8-1.5-2-2.5-1.7 1.6-3 4-3 7A6.5 6.5 0 0 0 12 21z',
    snow: 'M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9',
    sun: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2',
    forward: 'M15 6l-6 6 6 6'
  };
  function Icon(p) {
    return h('svg', { className: cx('md-icon', p.className), width: p.size || 20, height: p.size || 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': 'true' },
      h('path', { d: PATHS[p.name] }));
  }

  /* ---- Button ---- */
  function Button(p) {
    var variant = p.variant || 'secondary', size = p.size || 'md';
    var rest = Object.assign({}, p);
    ['variant', 'size', 'block', 'icon', 'loading', 'className', 'children'].forEach(function (k) { delete rest[k]; });
    var icon = p.icon || (variant === 'whatsapp' ? 'chat' : null);
    return h('button', Object.assign({ type: 'button' }, rest, {
      className: cx('md-btn', 'md-btn-' + variant, 'md-btn-' + size, p.block && 'md-btn-block', p.loading && 'is-loading', p.className),
      disabled: p.disabled || p.loading, 'aria-busy': p.loading ? 'true' : undefined
    }), p.loading ? h('span', { className: 'md-spinner', 'aria-hidden': 'true' }) : icon ? h(Icon, { name: icon }) : null, h('span', null, p.children));
  }

  /* ---- TextField ---- */
  var fid = 0;
  function TextField(p) {
    var idRef = React.useRef(p.id || 'md-f-' + (++fid));
    var id = idRef.current;
    var rest = Object.assign({}, p);
    ['label', 'hint', 'error', 'multiline', 'className', 'suffix', 'ltr'].forEach(function (k) { delete rest[k]; });
    var describedBy = (p.error || p.hint) ? id + '-d' : undefined;
    var input = h(p.multiline ? 'textarea' : 'input', Object.assign({ rows: p.multiline ? (p.rows || 3) : undefined }, rest, {
      id: id, className: 'md-input', dir: p.ltr ? 'ltr' : undefined, 'aria-invalid': p.error ? 'true' : undefined, 'aria-describedby': describedBy
    }));
    return h('div', { className: cx('md-field', p.error && 'has-error', p.className) },
      h('label', { className: 'md-field-label', htmlFor: id }, p.label, p.required ? h('span', { className: 'md-req', 'aria-hidden': 'true' }, ' *') : null),
      p.suffix ? h('div', { className: 'md-input-wrap' }, input, h('span', { className: 'md-suffix' }, p.suffix)) : input,
      describedBy ? h('p', { id: id + '-d', className: 'md-field-help' }, p.error ? h(Icon, { name: 'alert', size: 16 }) : null, p.error || p.hint) : null);
  }

  /* ---- StageBadge / PriorityBadge ---- */
  var STAGES = [
    { id: 'not_visited', label: 'لم يُزر' }, { id: 'visited', label: 'تمت الزيارة' },
    { id: 'contacted', label: 'تم الإرسال' }, { id: 'replied', label: 'رد' },
    { id: 'meeting', label: 'اجتماع' }, { id: 'proposal', label: 'عرض سعر' },
    { id: 'won', label: 'تم الإغلاق' }, { id: 'lost', label: 'خسارة' }
  ];
  var STAGE_LABEL = {}; STAGES.forEach(function (s) { STAGE_LABEL[s.id] = s.label; });
  function StageBadge(p) {
    var s = p.stage || 'not_visited';
    return h('span', { className: cx('md-badge', 'md-stage', 'md-stage-' + s.replace('_', '-'), p.size === 'sm' && 'md-badge-sm', p.className) },
      h('span', { className: 'md-dot', 'aria-hidden': 'true' }), p.children || STAGE_LABEL[s]);
  }
  var PRIORITY = { hot: { label: 'حار', icon: 'flame' }, warm: { label: 'دافئ', icon: 'sun' }, cold: { label: 'بارد', icon: 'snow' } };
  function PriorityBadge(p) {
    var pr = PRIORITY[p.priority] || PRIORITY.warm;
    return h('span', { className: cx('md-badge', 'md-prio', 'md-prio-' + (p.priority || 'warm'), p.size === 'sm' && 'md-badge-sm', p.className) },
      h(Icon, { name: pr.icon, size: 14 }), pr.label);
  }

  /* ---- ScoreBar ---- */
  function level(score) { return score < 50 ? 'low' : score < 70 ? 'mid' : 'high'; }
  var LEVEL_TEXT = {
    opportunity: { low: 'فرصة عالية', mid: 'فرصة متوسطة', high: 'فرصة منخفضة' },
    presence: { low: 'حضور ضعيف', mid: 'حضور متوسط', high: 'حضور قوي' }
  };
  function ScoreBar(p) {
    var score = Math.max(0, Math.min(100, Math.round(p.score || 0)));
    var ctx = p.context || 'opportunity', lv = level(score), text = LEVEL_TEXT[ctx][lv];
    var cls = cx('md-score', 'md-score-' + ctx + '-' + lv, p.className);
    if (p.variant === 'ring') {
      var r = 52, C = 2 * Math.PI * r;
      return h('div', { className: cx(cls, 'md-score-ring'), role: 'img', 'aria-label': 'الدرجة ' + score + ' من 100، ' + text },
        h('svg', { viewBox: '0 0 120 120', width: p.size || 140, height: p.size || 140 },
          h('circle', { cx: 60, cy: 60, r: r, className: 'md-score-track', fill: 'none', strokeWidth: 10 }),
          h('circle', { cx: 60, cy: 60, r: r, className: 'md-score-fill', fill: 'none', strokeWidth: 10, strokeLinecap: 'round', strokeDasharray: C, strokeDashoffset: C * (1 - score / 100), transform: 'rotate(-90 60 60)' })),
        h('div', { className: 'md-score-center', style: { height: (p.size || 140) + 'px' } }, h('span', { className: 'md-score-num' }, score), h('span', { className: 'md-score-of' }, 'من 100')),
        h('div', { className: 'md-score-level' }, text));
    }
    return h('div', { className: cx(cls, 'md-score-bar') },
      h('div', { className: 'md-score-head' },
        h('span', { className: 'md-score-label' }, p.label || 'الدرجة'),
        h('span', { className: 'md-score-value' }, h('b', null, score), ' / 100 · ', h('span', { className: 'md-score-level' }, text))),
      h('div', { className: 'md-score-track', role: 'meter', 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-valuenow': score, 'aria-valuetext': score + ' — ' + text },
        h('div', { className: 'md-score-fill', style: { width: score + '%' } }),
        h('span', { className: 'md-score-tick', style: { insetInlineStart: '50%' } }),
        h('span', { className: 'md-score-tick', style: { insetInlineStart: '70%' } })));
  }

  /* ---- TriSelect ---- */
  var TRI = [{ v: 'yes', label: 'نعم', icon: 'check' }, { v: 'partial', label: 'جزئي', icon: 'minus' }, { v: 'no', label: 'لا', icon: 'x' }];
  var tid = 0;
  function TriSelect(p) {
    var nameRef = React.useRef(p.name || 'md-tri-' + (++tid));
    var options = p.binary ? [TRI[0], TRI[2]] : TRI;
    return h('fieldset', { className: cx('md-tri', p.className) },
      h('legend', { className: 'md-tri-label' }, h('span', null, p.label), p.weight != null ? h('span', { className: 'md-tri-weight' }, p.weight + ' نقاط') : null),
      h('div', { className: 'md-tri-options' }, options.map(function (o) {
        var on = p.value === o.v;
        return h('label', { key: o.v, className: cx('md-tri-opt', 'md-tri-' + o.v, on && 'is-on') },
          h('input', { type: 'radio', name: nameRef.current, value: o.v, checked: on, onChange: function () { p.onChange && p.onChange(o.v); } }),
          h(Icon, { name: o.icon, size: 18 }), h('span', null, o.label));
      })));
  }

  /* ---- LeadCard ---- */
  function LeadCard(p) {
    var compact = p.variant === 'compact';
    var flags = cx('md-lead', compact && 'md-lead-compact', p.stale && 'is-stale', p.className);
    var head = h('div', { className: 'md-lead-head' },
      h('span', { className: 'md-lead-icon', 'aria-hidden': 'true' }, h(Icon, { name: 'store', size: 20 })),
      h('div', { className: 'md-lead-titles' },
        h('div', { className: 'md-lead-name' }, p.businessName),
        h('div', { className: 'md-lead-activity' }, p.activity, p.contactName ? ' · ' + p.contactName : '')),
      compact ? null : h('div', { className: 'md-lead-score', title: 'الدرجة' }, h('b', null, p.score), h('span', null, '/100')));
    if (compact) {
      return h('article', { className: flags, onClick: p.onClick, tabIndex: p.onClick ? 0 : undefined },
        head,
        h('div', { className: 'md-lead-meta' },
          p.value != null ? h('span', { className: 'md-lead-value' }, p.value) : null,
          h('span', { className: cx('md-lead-days', p.stale && 'is-stale') }, p.stale ? h(Icon, { name: 'alert', size: 14 }) : h(Icon, { name: 'clock', size: 14 }), p.daysInStage + ' يوم')));
    }
    return h('article', { className: flags },
      h('button', { type: 'button', className: 'md-lead-main', onClick: p.onClick },
        head,
        h('div', { className: 'md-lead-badges' }, h(StageBadge, { stage: p.stage, size: 'sm' }), p.priority ? h(PriorityBadge, { priority: p.priority, size: 'sm' }) : null),
        p.lastContact ? h('div', { className: 'md-lead-last' }, 'آخر تواصل: ', p.lastContact) : null),
      h('div', { className: cx('md-lead-next', p.overdue && 'is-overdue') },
        h(Icon, { name: p.overdue ? 'alert' : 'clock', size: 18 }),
        h('div', { className: 'md-lead-next-text' }, h('span', null, p.nextAction || 'بدون خطوة قادمة'), p.nextActionAt ? h('small', null, p.nextActionAt) : null),
        p.onWhatsApp ? h(Button, { variant: 'whatsapp', size: 'sm', onClick: p.onWhatsApp, 'aria-label': 'واتساب ' + p.businessName }, 'واتساب') : null));
  }

  /* ---- BottomSheet ---- */
  function BottomSheet(p) {
    React.useEffect(function () {
      if (!p.open) return;
      function onKey(e) { if (e.key === 'Escape' && p.onClose) p.onClose(); }
      document.addEventListener('keydown', onKey);
      return function () { document.removeEventListener('keydown', onKey); };
    }, [p.open]);
    if (!p.open) return null;
    return h('div', { className: 'md-sheet-layer' },
      h('div', { className: 'md-scrim', onClick: p.onClose }),
      h('section', { className: 'md-sheet', role: 'dialog', 'aria-modal': 'true', 'aria-label': p.title },
        h('div', { className: 'md-sheet-grip', 'aria-hidden': 'true' }),
        p.title ? h('h2', { className: 'md-sheet-title' }, p.title) : null,
        p.children ? h('div', { className: 'md-sheet-body' }, p.children) : null,
        p.actions ? h('div', { className: 'md-sheet-actions' }, p.actions) : null));
  }

  /* ---- Toast ---- */
  var TOAST_ICON = { success: 'check', error: 'alert', info: 'info' };
  function Toast(p) {
    var tone = p.tone || 'info';
    return h('div', { className: cx('md-toast', 'md-toast-' + tone, p.className), role: tone === 'error' ? 'alert' : 'status' },
      h('span', { className: 'md-toast-icon' }, h(Icon, { name: TOAST_ICON[tone], size: 20 })),
      h('div', { className: 'md-toast-text' }, h('strong', null, p.title), p.message ? h('span', null, p.message) : null),
      p.action ? h('button', { type: 'button', className: 'md-toast-action', onClick: p.onAction }, p.action) : null);
  }

  /* ---- EmptyState ---- */
  function EmptyState(p) {
    return h('div', { className: cx('md-empty', p.className) },
      h('span', { className: 'md-empty-icon', 'aria-hidden': 'true' }, h(Icon, { name: p.icon || 'pin', size: 28 })),
      h('h3', { className: 'md-empty-title' }, p.title),
      p.message ? h('p', { className: 'md-empty-msg' }, p.message) : null,
      p.action ? h('div', { className: 'md-empty-action' }, p.action) : null);
  }

  window.Maidani = Object.assign(window.Maidani || {}, {
    Button: Button, TextField: TextField, StageBadge: StageBadge, PriorityBadge: PriorityBadge, LeadCard: LeadCard,
    ScoreBar: ScoreBar, TriSelect: TriSelect, BottomSheet: BottomSheet, Toast: Toast, EmptyState: EmptyState,
    STAGES: STAGES
  });
})();
