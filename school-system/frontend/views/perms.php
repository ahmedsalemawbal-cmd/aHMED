<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$target_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
$target    = $target_id > 0 ? get_userdata($target_id) : null;
/*
 * `SCH_Staff::list()` تُعيد غلافًا `['items' => …, 'total' => …]` لا قائمة.
 * والتكرار على الغلاف نفسه كان يُخرج صفَّين مكسورَين — مصفوفةً وعددًا — بدل
 * الموظفين، ولا يظهر «لا يوجد موظفون» أبدًا لأن الغلاف ليس فارغًا قطّ.
 * وبقيّة الشاشات (`employees` و`hr` و`overview`) تقرؤه صحيحًا.
 *
 * ولا ترقيم هنا عمدًا: الشاشة تختار موظفًا لتعدّل صلاحياته، وسقفٌ افتراضيّ
 * من خمسة وعشرين كان يُخفي بقيّة المدرسة بلا أن يقول.
 */
$staff     = SCH_Staff::list(['status' => 'active', 'per_page' => 100])['items'];

if (!$target) : ?>

    <h1 class="sch-title"><?php echo sch_icon('shield', 22); ?><?php esc_html_e('الصلاحيات', 'school-system'); ?></h1>
    <p class="sch-sub"><?php esc_html_e('الدور قالب لا قيد: تختاره فتُفتح مفاتيحه، ثم تعدّل ما تشاء لهذا الشخص وحده.', 'school-system'); ?></p>

    <div class="sch-card">
        <h2><?php esc_html_e('اختر موظفًا', 'school-system'); ?></h2>

        <?php if ($staff === []) : ?>
            <div class="sch-empty"><strong><?php esc_html_e('لا يوجد موظفون', 'school-system'); ?></strong></div>
        <?php else : ?>
            <div class="sch-table-wrap">
                <table class="sch-table">
                    <thead><tr>
                        <th><?php esc_html_e('الموظف', 'school-system'); ?></th>
                        <th><?php esc_html_e('الدور', 'school-system'); ?></th>
                        <th><?php esc_html_e('الصلاحيات', 'school-system'); ?></th>
                        <th><?php esc_html_e('النطاق', 'school-system'); ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($staff as $s) :
                        $uid    = (int) $s->user_id;
                        $custom = SCH_Perms::is_custom($uid);
                        $scope  = SCH_Perms::scope($uid);
                        $gone   = SCH_Perms::expired($uid); ?>
                        <tr>
                            <td class="sch-name"><?php echo esc_html($s->display_name ?? ''); ?></td>
                            <td class="sch-sub"><?php echo esc_html(SCH_Staff::ROLES[$s->role ?? ''] ?? ($s->job_title ?: '—')); ?></td>
                            <td>
                                <?php if ($gone) : ?>
                                    <span class="sch-badge sch-badge--danger"><?php esc_html_e('انتهت المدة', 'school-system'); ?></span>
                                <?php elseif ($custom) : ?>
                                    <span class="sch-badge sch-badge--ok"><?php esc_html_e('مخصصة', 'school-system'); ?></span>
                                <?php else : ?>
                                    <span class="sch-badge sch-badge--muted"><?php esc_html_e('صلاحيات دوره', 'school-system'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="sch-sub"><?php echo esc_html(SCH_Perms::SCOPES[$scope] ?? ''); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('user_id', $uid, SCH_Dashboard::url('perms'))); ?>">
                                    <?php esc_html_e('تعديل', 'school-system'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php $log = SCH_Perms::history(); ?>
    <?php if ($log !== []) : ?>
        <div class="sch-card">
            <h2><?php esc_html_e('سجل تغييرات الصلاحيات', 'school-system'); ?></h2>
            <p class="sch-sub"><?php esc_html_e('من منح ماذا لمن ومتى — في نظام فيه بيانات أطفال هذا ليس ترفًا.', 'school-system'); ?></p>
            <div class="sch-table-wrap">
                <table class="sch-table">
                    <thead><tr>
                        <th><?php esc_html_e('الموظف', 'school-system'); ?></th>
                        <th><?php esc_html_e('مُنح', 'school-system'); ?></th>
                        <th><?php esc_html_e('سُحب', 'school-system'); ?></th>
                        <th><?php esc_html_e('بيد', 'school-system'); ?></th>
                        <th><?php esc_html_e('متى', 'school-system'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($log as $l) : ?>
                        <tr>
                            <td class="sch-name"><?php echo esc_html($l->target_name ?? ''); ?></td>
                            <td class="sch-sub"><?php echo esc_html($l->granted ?: '—'); ?></td>
                            <td class="sch-sub"><?php echo esc_html($l->revoked ?: '—'); ?></td>
                            <td class="sch-sub"><?php echo esc_html($l->actor_name ?? ''); ?></td>
                            <td class="sch-sub" dir="ltr"><?php echo esc_html(substr((string) $l->created_at, 0, 16)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php
    return;
endif;

$role    = (string) ($target->roles[0] ?? '');
$map     = SCH_Perms::map($target_id);
$custom  = SCH_Perms::is_custom($target_id);
$scope   = SCH_Perms::scope($target_id);
$groups  = SCH_Perms::grouped();
$cards   = SCH_Dashboard::areas();
$applied = $custom ? $map : SCH_Perms::template($role);
?>

<a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('perms')); ?>">‹ <?php esc_html_e('الصلاحيات', 'school-system'); ?></a>

<h1 class="sch-title"><?php echo esc_html($target->display_name); ?></h1>
<p class="sch-sub">
    <?php echo esc_html(SCH_Staff::ROLES[$role] ?? $role); ?>
    · <?php echo esc_html($custom ? __('صلاحيات مخصصة', 'school-system') : __('يعمل بصلاحيات دوره', 'school-system')); ?>
</p>

<form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" id="sch-perms-form">
    <?php wp_nonce_field('sch_save_perms', '_sch_nonce'); ?>
    <input type="hidden" name="sch_action" value="save_perms">
    <?php /* الموظف المعروض يعبر POST: المعالجات الثلاثة تعيد `bool|WP_Error`
             بلا مفتاح `keep`، و`back()` تسقط على `perms` بـ`user_id = 0` —
             أي منتقي الموظفين. فمن حفظ صلاحيات موظفٍ يُقذف عنه. */ ?>
    <input type="hidden" name="keep[user_id]" value="<?php echo esc_attr((string) $target_id); ?>">

    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $target_id); ?>">

    <!-- شريط الأدوات: القالب والنسخ والنطاق والمدة -->
    <div class="sch-permbar">
        <div class="sch-permbar__f">
            <label for="p-scope"><?php esc_html_e('على مَن؟', 'school-system'); ?></label>
            <select id="p-scope" name="scope">
                <?php foreach (SCH_Perms::SCOPES as $slug => $label) : ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($scope, $slug); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-permbar__f">
            <label for="p-exp"><?php esc_html_e('تنتهي في', 'school-system'); ?></label>
            <input id="p-exp" type="date" name="expires_at" value="">
        </div>

        <div class="sch-permbar__f">
            <label for="p-tpl"><?php esc_html_e('ابدأ من قالب', 'school-system'); ?></label>
            <select id="p-tpl">
                <option value=""><?php esc_html_e('— لا تغيير —', 'school-system'); ?></option>
                <?php foreach (SCH_Staff::ROLES as $slug => $label) : ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sch-permbar__f sch-permbar__f--grow">
            <label for="p-search"><?php esc_html_e('بحث', 'school-system'); ?></label>
            <input id="p-search" type="search" placeholder="<?php esc_attr_e('ابحث عن صلاحية…', 'school-system'); ?>" autocomplete="off">
        </div>

        <div class="sch-permbar__acts">
            <button type="button" class="sch-btn sch-btn--quiet" id="p-all"><?php esc_html_e('فتح الكل', 'school-system'); ?></button>
            <button type="button" class="sch-btn sch-btn--quiet" id="p-none"><?php esc_html_e('إغلاق الكل', 'school-system'); ?></button>
            <button class="sch-btn" type="submit"><?php esc_html_e('حفظ الصلاحيات', 'school-system'); ?></button>
        </div>
    </div>

    <div class="sch-permgrid">
        <nav class="sch-prail" aria-label="<?php esc_attr_e('الفئات', 'school-system'); ?>">
            <?php $sch_gi = 0; foreach ($groups as $sch_k => $sch_secs) : $sch_gi++;
                [$sch_c, $sch_g] = explode('|', $sch_k);
                $sch_head = trim(($cards[$sch_c][0] ?? '') . ($sch_g !== '' ? ' · ' . $sch_g : '')); ?>
                <a class="sch-prail__i" href="#sch-pg-<?php echo esc_attr((string) $sch_gi); ?>" data-rail="<?php echo esc_attr((string) $sch_gi); ?>">
                    <span><?php echo esc_html($sch_head); ?></span>
                    <b data-railcount="<?php echo esc_attr((string) $sch_gi); ?>">٠</b>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sch-permgrid__main">
            <?php $sch_gi = 0; foreach ($groups as $key => $sections) : $sch_gi++;
                [$card, $group] = explode('|', $key);
                $heading = trim(($cards[$card][0] ?? '') . ($group !== '' ? ' · ' . $group : '')); ?>

                <section class="sch-pg is-open" id="sch-pg-<?php echo esc_attr((string) $sch_gi); ?>" data-group data-gi="<?php echo esc_attr((string) $sch_gi); ?>">
                    <div class="sch-pg__h">
                        <button type="button" class="sch-pg__caretbtn" data-toggle aria-label="<?php esc_attr_e('طيّ أو فتح المجموعة', 'school-system'); ?>">
                            <svg class="sch-pg__caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 10 6 6 6-6"/></svg>
                        </button>
                        <b><?php echo esc_html($heading); ?></b>
                        <span class="sch-pg__count" data-count>٠ / ٠</span>
                        <label class="sch-switch sch-switch--master" title="<?php esc_attr_e('تفعيل أو تعطيل المجموعة كاملة', 'school-system'); ?>">
                            <input type="checkbox" data-master aria-label="<?php echo esc_attr($heading); ?>">
                            <span class="sch-switch__t"></span>
                        </label>
                    </div>

                    <div class="sch-pg__rows">
                        <?php foreach ($sections as $slug => $meta) :
                            $locked = SCH_Perms::is_locked($slug, $target_id);
                            $reason = $locked ? SCH_Perms::lock_reason($slug, $role) : '';
                            $hot    = in_array($slug, SCH_Perms::SENSITIVE, true);
                            $mode   = !empty($applied[$slug]['edit'])
                                ? 'edit'
                                : (!empty($applied[$slug]['view']) ? 'view' : 'none'); ?>

                            <div class="sch-pr<?php echo $locked ? ' is-locked' : ''; ?>"
                                 data-row data-name="<?php echo esc_attr((string) $meta[0]); ?>">
                                <?php if ($locked) : ?>
                                    <span class="sch-pr__t">
                                        <?php echo sch_icon((string) ($meta[4] ?? 'dot'), 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                        <b><?php echo esc_html((string) $meta[0]); ?></b>
                                    </span>
                                    <span class="sch-pr__lock" title="<?php echo esc_attr($reason); ?>">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                                        <?php esc_html_e('مقفل', 'school-system'); ?>
                                    </span>
                                <?php else : ?>
                                    <input type="hidden" name="perm[<?php echo esc_attr($slug); ?>][mode]" value="<?php echo esc_attr($mode); ?>" data-mode>
                                    <label class="sch-pr__chk">
                                        <input type="checkbox" data-access <?php checked($mode !== 'none'); ?> aria-label="<?php echo esc_attr((string) $meta[0]); ?>">
                                        <span class="sch-pr__box" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6.5 9.5 17 4 11.5"/></svg>
                                        </span>
                                    </label>
                                    <span class="sch-pr__t">
                                        <?php echo sch_icon((string) ($meta[4] ?? 'dot'), 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                        <b><?php echo esc_html((string) $meta[0]); ?></b>
                                        <?php if ($hot) : ?>
                                            <em class="sch-hot" title="<?php esc_attr_e('يصل إشعار للمدير عند منحها', 'school-system'); ?>">
                                                <?php esc_html_e('حسّاس', 'school-system'); ?>
                                            </em>
                                        <?php endif; ?>
                                    </span>
                                    <label class="sch-switch sch-switch--edit" title="<?php esc_attr_e('يعدّل — تحكّم كامل', 'school-system'); ?>">
                                        <input type="checkbox" data-edit <?php checked($mode === 'edit'); ?>>
                                        <span class="sch-switch__t"></span>
                                        <em><?php esc_html_e('يعدّل', 'school-system'); ?></em>
                                    </label>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <aside class="sch-permside">
            <div class="sch-permside__box">
                <span class="sch-sum__k"><?php esc_html_e('ما سيراه', 'school-system'); ?></span>
                <strong class="sch-sum__n" id="p-count">٠</strong>
                <span class="sch-sum__of" id="p-of"></span>
                <div class="sch-sum__bar"><i id="p-bar"></i></div>
                <ul class="sch-sum__list" id="p-preview"></ul>
            </div>
        </aside>
    </div>
</form>

<div class="sch-card sch-mt">
    <h2><?php esc_html_e('أدوات سريعة', 'school-system'); ?></h2>
    <div class="sch-toolbar">
        <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" class="sch-toolbar">
            <?php wp_nonce_field('sch_copy_perms', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="copy_perms">
            <?php /* الموظف المعروض يعبر POST — انظر التعليق أعلى النموذج الأول. */ ?>
            <input type="hidden" name="keep[user_id]" value="<?php echo esc_attr((string) $target_id); ?>">

            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $target_id); ?>">
            <select name="source_id" required aria-label="المصدر">
                <option value=""><?php esc_html_e('انسخ صلاحيات من…', 'school-system'); ?></option>
                <?php foreach ($staff as $s) : ?>
                    <?php if ((int) $s->user_id !== $target_id) : ?>
                        <option value="<?php echo esc_attr((string) $s->user_id); ?>">
                            <?php echo esc_html($s->display_name ?? ''); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button class="sch-btn sch-btn--quiet"><?php esc_html_e('نسخ', 'school-system'); ?></button>
        </form>

        <form method="post" action="<?php echo esc_url(SCH_Dashboard::post_url()); ?>" onsubmit="return confirm('<?php esc_attr_e('سيعود لصلاحيات دوره الافتراضية. متابعة؟', 'school-system'); ?>');">
            <?php wp_nonce_field('sch_reset_perms', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="reset_perms">
            <?php /* الموظف المعروض يعبر POST — انظر التعليق أعلى النموذج الأول. */ ?>
            <input type="hidden" name="keep[user_id]" value="<?php echo esc_attr((string) $target_id); ?>">

            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $target_id); ?>">
            <button class="sch-link-danger"><?php esc_html_e('العودة لصلاحيات الدور', 'school-system'); ?></button>
        </form>
    </div>
</div>

<script>
(function () {
  var form = document.getElementById('sch-perms-form');
  if (!form) { return; }

  var TEMPLATES = <?php echo wp_json_encode(array_map(
      static fn (string $r): array => array_keys(SCH_Perms::template($r)),
      array_combine(array_keys(SCH_Staff::ROLES), array_keys(SCH_Staff::ROLES))
  )); ?>;

  // الخريطة: بلا وصول=none(مخفي) · وصول=view(يرى) · وصول+يعدّل=edit(يعدّل).
  function rowMode(row) {
    var h = row.querySelector('[data-mode]');
    return h ? h.value : 'none';
  }
  function apply(row, mode) {
    var h = row.querySelector('[data-mode]');
    if (!h) { return; } // صف مقفل — يُتجاوز
    var acc = row.querySelector('[data-access]');
    var ed  = row.querySelector('[data-edit]');
    h.value = mode;
    if (acc) { acc.checked = mode !== 'none'; }
    if (ed)  { ed.checked = mode === 'edit'; ed.disabled = mode === 'none'; }
    row.classList.toggle('is-on', mode !== 'none');
    row.classList.toggle('is-edit', mode === 'edit');
  }
  function recompute(row) {
    var acc = row.querySelector('[data-access]');
    if (!acc) { return; }
    var ed = row.querySelector('[data-edit]');
    apply(row, !acc.checked ? 'none' : (ed && ed.checked ? 'edit' : 'view'));
  }

  form.querySelectorAll('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('[data-group]').classList.toggle('is-open'); });
  });

  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches('[data-access]')) { recompute(t.closest('[data-row]')); sync(); }
    else if (t.matches('[data-edit]')) {
      var row = t.closest('[data-row]'), acc = row.querySelector('[data-access]');
      if (t.checked && acc && !acc.checked) { acc.checked = true; }
      recompute(row); sync();
    } else if (t.matches('[data-master]')) {
      var g = t.closest('[data-group]'), on = t.checked;
      g.querySelectorAll('[data-row]').forEach(function (r) { apply(r, on ? 'view' : 'none'); });
      sync();
    }
  });

  document.getElementById('p-all').addEventListener('click', function () {
    form.querySelectorAll('[data-row]').forEach(function (r) { apply(r, 'edit'); });
    sync();
  });
  document.getElementById('p-none').addEventListener('click', function () {
    form.querySelectorAll('[data-row]').forEach(function (r) { apply(r, 'none'); });
    sync();
  });

  document.getElementById('p-tpl').addEventListener('change', function () {
    var open = TEMPLATES[this.value];
    if (!open) { return; }
    form.querySelectorAll('[data-row]').forEach(function (row) {
      var h = row.querySelector('[data-mode]');
      if (!h) { return; }
      var slug = h.name.slice(5, h.name.indexOf(']'));
      apply(row, open.indexOf(slug) !== -1 ? 'edit' : 'none');
    });
    sync();
  });

  var search = document.getElementById('p-search');
  if (search) {
    search.addEventListener('input', function () {
      var q = this.value.trim();
      form.querySelectorAll('[data-group]').forEach(function (g) {
        var shown = 0;
        g.querySelectorAll('[data-row]').forEach(function (r) {
          var hit = !q || (r.dataset.name || '').indexOf(q) !== -1;
          r.hidden = !hit;
          if (hit) { shown++; }
        });
        g.hidden = q !== '' && shown === 0;
        if (q) { g.classList.add('is-open'); }
      });
    });
  }

  form.querySelectorAll('[data-rail]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var t = document.getElementById('sch-pg-' + a.dataset.rail);
      if (t) { t.classList.add('is-open'); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  function sync() {
    var total = 0, on = 0;

    form.querySelectorAll('[data-group]').forEach(function (g) {
      var cnt = 0, grp = 0;
      g.querySelectorAll('[data-row]').forEach(function (r) {
        if (!r.querySelector('[data-access]')) { return; } // تجاوز المقفل
        cnt++;
        if (rowMode(r) !== 'none') { grp++; }
      });
      total += cnt; on += grp;

      var c = g.querySelector('[data-count]');
      if (c) {
        c.textContent = grp + ' / ' + cnt;
        c.className = 'sch-pg__count' + (grp === 0 ? ' is-off' : (grp === cnt ? ' is-full' : ''));
      }
      var m = g.querySelector('[data-master]');
      if (m) { m.checked = cnt > 0 && grp === cnt; m.indeterminate = grp > 0 && grp < cnt; }
      var rc = form.querySelector('[data-railcount="' + g.dataset.gi + '"]');
      if (rc) { rc.textContent = grp; rc.className = grp === 0 ? 'is-off' : ''; }
    });

    document.getElementById('p-count').textContent = on;
    document.getElementById('p-of').textContent = 'من ' + total + ' قسمًا';

    var bar = document.getElementById('p-bar');
    bar.style.width = total ? Math.round((on / total) * 100) + '%' : '0';
    bar.className = on === 0 ? 'is-zero' : '';

    var list = document.getElementById('p-preview');
    list.innerHTML = '';
    if (on === 0) {
      var w = document.createElement('li');
      w.className = 'sch-sum__warn';
      w.textContent = 'لن يستطيع الدخول للنظام.';
      list.appendChild(w);
      return;
    }
    form.querySelectorAll('[data-row]').forEach(function (row) {
      if (!row.querySelector('[data-access]')) { return; }
      var m = rowMode(row);
      if (m === 'none') { return; }
      var li = document.createElement('li');
      li.textContent = row.dataset.name;
      if (m === 'edit') {
        var b = document.createElement('em');
        b.textContent = 'يعدّل';
        li.appendChild(b);
      }
      list.appendChild(li);
    });
  }

  form.querySelectorAll('[data-row]').forEach(function (r) { recompute(r); });
  sync();
})();
</script>
