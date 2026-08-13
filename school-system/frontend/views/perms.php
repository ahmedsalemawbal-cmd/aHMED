<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$target_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
$target    = $target_id > 0 ? get_userdata($target_id) : null;
$staff     = SCH_Staff::list(['status' => 'active']);

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

<form method="post" id="sch-perms-form">
    <?php wp_nonce_field('sch_save_perms', '_sch_nonce'); ?>
    <input type="hidden" name="sch_action" value="save_perms">
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

        <div class="sch-permbar__acts">
            <button type="button" class="sch-btn sch-btn--quiet" id="p-all"><?php esc_html_e('فتح الكل', 'school-system'); ?></button>
            <button type="button" class="sch-btn sch-btn--quiet" id="p-none"><?php esc_html_e('إغلاق الكل', 'school-system'); ?></button>
            <button class="sch-btn" type="submit"><?php esc_html_e('حفظ الصلاحيات', 'school-system'); ?></button>
        </div>
    </div>

    <div class="sch-permgrid">
        <div class="sch-permgrid__main">
            <?php foreach ($groups as $key => $sections) :
                [$card, $group] = explode('|', $key);
                $heading = trim(($cards[$card][0] ?? '') . ($group !== '' ? ' · ' . $group : '')); ?>

                <section class="sch-pg" data-group>
                    <button type="button" class="sch-pg__h" data-toggle>
                        <svg class="sch-pg__caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 10 6 6 6-6"/></svg>
                        <b><?php echo esc_html($heading); ?></b>
                        <span class="sch-pg__count" data-count></span>
                    </button>

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
                                <span class="sch-pr__t">
                                    <?php echo sch_icon((string) ($meta[4] ?? 'dot'), 15); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    <b><?php echo esc_html((string) $meta[0]); ?></b>
                                    <?php if ($hot && !$locked) : ?>
                                        <em class="sch-hot" title="<?php esc_attr_e('يصل إشعار للمدير عند منحها', 'school-system'); ?>">
                                            <?php esc_html_e('حسّاس', 'school-system'); ?>
                                        </em>
                                    <?php endif; ?>
                                </span>

                                <?php if ($locked) : ?>
                                    <span class="sch-pr__lock" title="<?php echo esc_attr($reason); ?>">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                                        <?php esc_html_e('مقفل', 'school-system'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="sch-tri" data-tri>
                                        <?php foreach ([
                                            'none' => __('مخفي', 'school-system'),
                                            'view' => __('يرى', 'school-system'),
                                            'edit' => __('يعدّل', 'school-system'),
                                        ] as $m => $label) : ?>
                                            <label class="sch-tri__o sch-tri__o--<?php echo esc_attr($m); ?>">
                                                <input type="radio" name="perm[<?php echo esc_attr($slug); ?>][mode]"
                                                       value="<?php echo esc_attr($m); ?>" <?php checked($mode, $m); ?>>
                                                <span><?php echo esc_html($label); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </span>
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
        <form method="post" class="sch-toolbar">
            <?php wp_nonce_field('sch_copy_perms', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="copy_perms">
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

        <form method="post" onsubmit="return confirm('<?php esc_attr_e('سيعود لصلاحيات دوره الافتراضية. متابعة؟', 'school-system'); ?>');">
            <?php wp_nonce_field('sch_reset_perms', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="reset_perms">
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

  function setRow(row, mode) {
    var i = row.querySelector('input[value="' + mode + '"]');
    if (i) { i.checked = true; }
  }
  function rowMode(row) {
    var on = row.querySelector('input:checked');
    return on ? on.value : 'none';
  }

  form.querySelectorAll('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.closest('[data-group]').classList.toggle('is-open');
    });
  });

  form.addEventListener('change', function (e) {
    if (e.target.matches('[data-tri] input')) { sync(); }
  });

  document.getElementById('p-all').addEventListener('click', function () {
    form.querySelectorAll('[data-row]').forEach(function (r) { setRow(r, 'edit'); });
    sync();
  });
  document.getElementById('p-none').addEventListener('click', function () {
    form.querySelectorAll('[data-row]').forEach(function (r) { setRow(r, 'none'); });
    sync();
  });

  document.getElementById('p-tpl').addEventListener('change', function () {
    var open = TEMPLATES[this.value];
    if (!open) { return; }
    form.querySelectorAll('[data-row]').forEach(function (row) {
      var i = row.querySelector('input[name^="perm["]');
      if (!i) { return; }
      var slug = i.name.slice(5, i.name.indexOf(']'));
      setRow(row, open.indexOf(slug) !== -1 ? 'edit' : 'none');
    });
    sync();
  });

  function sync() {
    var total = 0, on = 0;

    form.querySelectorAll('[data-group]').forEach(function (g) {
      var rows = g.querySelectorAll('[data-row] [data-tri]');
      var open = 0;
      rows.forEach(function (s) {
        if (rowMode(s.closest('[data-row]')) !== 'none') { open++; }
      });
      total += rows.length;
      on += open;

      var c = g.querySelector('[data-count]');
      if (c) {
        c.textContent = open + ' / ' + rows.length;
        c.className = 'sch-pg__count' + (open === 0 ? ' is-off' : (open === rows.length ? ' is-full' : ''));
      }
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

  sync();
  var first = form.querySelector('[data-group]');
  if (first) { first.classList.add('is-open'); }
})();
</script>
