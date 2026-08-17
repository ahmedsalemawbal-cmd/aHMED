<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

$summary  = SCH_Finance::summary();
$plans    = SCH_Finance::plans();
$overdue  = SCH_Finance::overdue_list(30);
$inv_id   = isset($_GET['inv']) ? absint($_GET['inv']) : 0;
$invoice  = $inv_id > 0 ? SCH_Finance::get_invoice($inv_id) : null;
$students = SCH_Students::list(['status' => 'active', 'per_page' => 300]);
$list     = SCH_Finance::invoices(['status' => isset($_GET['st']) ? sanitize_key(wp_unslash((string) $_GET['st'])) : '']);

// قائمة الأسماء للقوائم المنسدلة: تُستدعى مرة لا مرة لكل قائمة،
// و`with => false` تُطفئ الضمّ فلا نجلب شعبة وولي أمر لا نعرضهما.
$sch_pick = SCH_Students::list(['status' => 'active', 'per_page' => 400, 'with' => false])['items'];
?>
<div class="sch-head">
    <div>
        <h1 class="sch-title"><?php esc_html_e('المالية', 'school-system'); ?></h1>
        <p class="sch-sub"><?php esc_html_e('الفواتير والأقساط والتحصيل', 'school-system'); ?></p>
    </div>
    <div class="sch-head__acts">
        <?php SCH_Modal::button('sch-add-plan', __('خطة رسوم', 'school-system')); ?>
        <?php SCH_Modal::button('sch-issue-inv', __('إصدار فاتورة', 'school-system'), 'wallet'); ?>
    </div>
</div>

<?php if ($invoice) : ?>
    <a class="sch-back" href="<?php echo esc_url(SCH_Dashboard::url('finance')); ?>">&larr; <?php esc_html_e('كل الفواتير', 'school-system'); ?></a>

    <div class="sch-card">
        <h2><?php echo esc_html($invoice->full_name); ?></h2>
        <dl class="sch-dl">
            <div><dt><?php esc_html_e('الخطة', 'school-system'); ?></dt><dd><?php echo esc_html($invoice->plan_name ?: '—'); ?></dd></div>
            <div><dt><?php esc_html_e('الإجمالي', 'school-system'); ?></dt><dd><?php echo esc_html(sch_money($invoice->total)); ?></dd></div>
            <div><dt><?php esc_html_e('المدفوع', 'school-system'); ?></dt><dd><?php echo esc_html(sch_money($invoice->paid)); ?></dd></div>
            <div><dt><?php esc_html_e('المتبقي', 'school-system'); ?></dt><dd><?php echo esc_html(sch_money((float) $invoice->total - (float) $invoice->paid)); ?></dd></div>
        </dl>
        <?php if ($invoice->status === 'void') : ?>
            <p class="sch-mt"><span class="sch-badge sch-badge--muted"><?php esc_html_e('فاتورة ملغاة', 'school-system'); ?></span></p>
        <?php elseif ((float) $invoice->paid == 0 && current_user_can('sch_manage_finance')) : ?>
            <form method="post" class="sch-toolbar sch-mt" onsubmit="return confirm('<?php esc_attr_e('إلغاء هذه الفاتورة وعكس قيدها المحاسبي؟ لا رجعة.', 'school-system'); ?>');">
                <?php wp_nonce_field('sch_void_invoice', '_sch_nonce'); ?>
                <input type="hidden" name="sch_action" value="void_invoice">
                <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $invoice->id); ?>">
                <input type="text" name="reason" required placeholder="<?php esc_attr_e('سبب الإلغاء — إجباري', 'school-system'); ?>">
                <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إلغاء الفاتورة', 'school-system'); ?></button>
            </form>
        <?php endif; ?>
    </div>

    <div class="sch-card">
        <h2><?php esc_html_e('الأقساط', 'school-system'); ?></h2>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th>#</th>
                    <th><?php esc_html_e('المبلغ', 'school-system'); ?></th>
                    <th><?php esc_html_e('الاستحقاق', 'school-system'); ?></th>
                    <th><?php esc_html_e('المدفوع', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach (SCH_Finance::installments($inv_id) as $i) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $i->seq); ?></td>
                        <td><?php echo esc_html(sch_money($i->amount)); ?></td>
                        <td dir="ltr"><?php echo esc_html($i->due_date ?: '—'); ?></td>
                        <td><?php echo esc_html(sch_money($i->paid)); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $i->status === 'paid' ? 'sch-badge--ok' : ''; ?>">
                                <?php echo esc_html($i->status === 'paid' ? __('مسدّد', 'school-system') : ($i->status === 'partial' ? __('جزئي', 'school-system') : __('مستحق', 'school-system'))); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="sch-card">
        <h2><?php esc_html_e('تسجيل دفعة', 'school-system'); ?></h2>
        <form method="post" class="sch-toolbar">
            <?php wp_nonce_field('sch_record_payment', '_sch_nonce'); ?>
            <input type="hidden" name="sch_action" value="record_payment">
            <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $invoice->id); ?>">
            <input type="hidden" name="client_uuid" value="<?php echo esc_attr(wp_generate_uuid4()); ?>">
            <input type="number" name="amount" step="0.01" min="0.01" placeholder="<?php esc_attr_e('المبلغ', 'school-system'); ?>" required>
            <select name="method" aria-label="الطريقة">
                <?php foreach (SCH_Finance::METHODS as $slug => $label) : ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="reference" placeholder="<?php esc_attr_e('رقم المرجع', 'school-system'); ?>" dir="ltr">
            <button class="sch-btn"><?php esc_html_e('تسجيل', 'school-system'); ?></button>
        </form>

        <?php $pays = SCH_Finance::payments($inv_id); ?>
        <?php if ($pays !== []) : ?>
            <div class="sch-table-wrap sch-mt">
                <table class="sch-table">
                    <thead><tr>
                        <th><?php esc_html_e('التاريخ', 'school-system'); ?></th>
                        <th><?php esc_html_e('المبلغ', 'school-system'); ?></th>
                        <th><?php esc_html_e('الطريقة', 'school-system'); ?></th>
                        <th><?php esc_html_e('المرجع', 'school-system'); ?></th>
                        <th><?php esc_html_e('استلمها', 'school-system'); ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($pays as $p) : ?>
                        <tr>
                            <td dir="ltr"><?php echo esc_html($p->paid_at); ?></td>
                            <td class="sch-name"><?php echo esc_html(sch_money($p->amount)); ?></td>
                            <td><?php echo esc_html(SCH_Finance::METHODS[$p->method] ?? $p->method); ?></td>
                            <td dir="ltr"><?php echo esc_html($p->reference ?: '—'); ?></td>
                            <td><?php echo esc_html($p->display_name ?: '—'); ?></td>
                            <td>
                                <?php if ($p->refunded_at) : ?>
                                    <span class="sch-badge sch-badge--muted"><?php esc_html_e('مستردّة', 'school-system'); ?></span>
                                <?php elseif (current_user_can('sch_manage_finance')) : ?>
                                    <form method="post" class="sch-toolbar" onsubmit="return confirm('<?php esc_attr_e('استرداد هذه الدفعة وعكس قيدها المحاسبي؟', 'school-system'); ?>');">
                                        <?php wp_nonce_field('sch_refund_payment', '_sch_nonce'); ?>
                                        <input type="hidden" name="sch_action" value="refund_payment">
                                        <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $p->id); ?>">
                                        <input type="text" name="reason" required placeholder="<?php esc_attr_e('السبب', 'school-system'); ?>">
                                        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('استرداد', 'school-system'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else : ?>

<div class="sch-stats">
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(sch_money($summary['billed'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('إجمالي المفوتر', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(sch_money($summary['collected'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('المحصّل', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(sch_money($summary['outstanding'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('المتبقي', 'school-system'); ?></span>
    </div>
    <div class="sch-stat">
        <span class="sch-stat__num"><?php echo esc_html(sch_money($summary['overdue'])); ?></span>
        <span class="sch-stat__label"><?php esc_html_e('متأخر السداد', 'school-system'); ?></span>
    </div>
</div>

<?php if ($overdue !== []) : ?>
<div class="sch-card">
    <h2><?php esc_html_e('أقساط متأخرة', 'school-system'); ?></h2>
    <div class="sch-table-wrap">
        <table class="sch-table">
            <thead><tr>
                <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                <th><?php esc_html_e('القسط', 'school-system'); ?></th>
                <th><?php esc_html_e('المستحق', 'school-system'); ?></th>
                <th><?php esc_html_e('تاريخ الاستحقاق', 'school-system'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($overdue as $o) : ?>
                <tr>
                    <td class="sch-name"><?php echo esc_html($o->full_name); ?></td>
                    <td><?php echo esc_html((string) $o->seq); ?></td>
                    <td><?php echo esc_html(sch_money($o->due_amount)); ?></td>
                    <td dir="ltr"><?php echo esc_html($o->due_date); ?></td>
                    <td><a href="<?php echo esc_url(add_query_arg('inv', (int) $o->invoice_id, SCH_Dashboard::url('finance'))); ?>"><?php esc_html_e('الفاتورة', 'school-system'); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="sch-card">
    <h2><?php esc_html_e('الفواتير', 'school-system'); ?></h2>
    <?php if ($list['items'] === []) : ?>
        <div class="sch-empty"><strong><?php esc_html_e('لا توجد فواتير', 'school-system'); ?></strong><?php esc_html_e('أنشئ خطة رسوم ثم أصدر فواتير الطلاب.', 'school-system'); ?></div>
    <?php else : ?>
        <div class="sch-table-wrap">
            <table class="sch-table">
                <thead><tr>
                    <th class="sch-th-pick"><?php SCH_Bulk::pick_all(); ?></th>
                    <th><?php esc_html_e('الطالب', 'school-system'); ?></th>
                    <th><?php esc_html_e('الإجمالي', 'school-system'); ?></th>
                    <th><?php esc_html_e('المدفوع', 'school-system'); ?></th>
                    <th><?php esc_html_e('المتبقي', 'school-system'); ?></th>
                    <th><?php esc_html_e('الحالة', 'school-system'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($list['items'] as $inv) : ?>
                    <tr data-row>
                        <td class="sch-th-pick"><?php SCH_Bulk::pick((int) $inv->id); ?></td>
                        <td class="sch-name">
                            <a href="<?php echo esc_url(add_query_arg('inv', (int) $inv->id, SCH_Dashboard::url('finance'))); ?>"><?php echo esc_html($inv->full_name); ?></a>
                        </td>
                        <td><?php echo esc_html(sch_money($inv->total)); ?></td>
                        <td><?php echo esc_html(sch_money($inv->paid)); ?></td>
                        <td><?php echo esc_html(sch_money((float) $inv->total - (float) $inv->paid)); ?></td>
                        <td>
                            <span class="sch-badge <?php echo $inv->status === 'paid' ? 'sch-badge--ok' : ''; ?>">
                                <?php echo esc_html(match ($inv->status) {
                                    'paid'    => __('مسدّدة', 'school-system'),
                                    'partial' => __('سداد جزئي', 'school-system'),
                                    'void'    => __('ملغاة', 'school-system'),
                                    default   => __('مفتوحة', 'school-system'),
                                }); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>


<?php endif; ?>

<?php SCH_Bulk::bar('finance'); ?>

<script>
(function () {
  var form = document.getElementById('sch-inv-form');
  if (!form) { return; }

  var plan   = document.getElementById('i-plan');
  var disc   = document.getElementById('i-disc');
  var first  = document.getElementById('i-first');
  var grant  = document.getElementById('sch-grant');
  var rows   = document.getElementById('p-rows');

  var AR = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
  function ar(s) { return String(s).split('').map(function (d) { return /\d/.test(d) ? AR[+d] : d; }).join(''); }
  function money(n) { return ar(n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) + ' ر.س'; }

  function mode() {
    var m = form.querySelector('[name="pay_mode"]:checked');
    return m ? m.value : 'full';
  }

  function count() {
    if (mode() !== 'school') { return 1; }
    var n = form.querySelector('[name="installments"]:checked');
    return n ? +n.value : 2;
  }

  function addMonths(iso, n) {
    var d = new Date(iso + 'T00:00:00');
    var day = d.getDate();
    d.setDate(1);
    d.setMonth(d.getMonth() + n);
    /* آخر الشهر: ٣١ يناير + شهر ليس ٣ مارس */
    d.setDate(Math.min(day, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()));
    return d.toISOString().slice(0, 10);
  }

  function render() {
    var opt = plan.options[plan.selectedIndex];
    var gross = opt ? parseFloat(opt.dataset.amount || '0') : 0;
    var pct = Math.max(0, Math.min(100, parseFloat(disc.value || '0')));
    var cut = Math.round(gross * pct) / 100;
    var total = Math.round((gross - cut) * 100) / 100;

    document.getElementById('p-gross').textContent = money(gross);
    document.getElementById('p-total').textContent = money(total);
    document.getElementById('p-cut-w').hidden = pct <= 0;
    document.getElementById('p-cut').textContent = '− ' + money(cut) + ' (' + ar(pct) + '٪)';

    grant.hidden = mode() !== 'school';

    var n = count();
    var each = Math.round((total / n) * 100) / 100;
    var start = first.value || new Date().toISOString().slice(0, 10);

    rows.innerHTML = '';

    for (var i = 1; i <= n; i++) {
      /* الكسر على الأولى: يدفع الأكثر أولًا فيقلّ المتبقي */
      var amt = i === 1 ? Math.round((total - each * (n - 1)) * 100) / 100 : each;
      var due = addMonths(start, (i - 1) * 2);

      var row = document.createElement('div');
      row.className = 'sch-plan__row';

      var seq = document.createElement('span');
      seq.className = 'sch-plan__seq';
      seq.textContent = ar(i);

      var lbl = document.createElement('span');
      lbl.className = 'sch-plan__lbl';
      lbl.textContent = n === 1 ? 'المبلغ كاملًا' : 'الدفعة ' + ar(i);

      var val = document.createElement('b');
      val.textContent = money(amt);

      var date = document.createElement('input');
      date.type = 'date';
      date.name = 'due_dates[]';
      date.value = due;
      date.className = 'sch-plan__date';
      date.setAttribute('aria-label', 'تاريخ الدفعة ' + ar(i));

      row.appendChild(seq);
      row.appendChild(lbl);
      row.appendChild(val);
      row.appendChild(date);
      rows.appendChild(row);
    }
  }

  form.addEventListener('change', render);
  form.addEventListener('input', function (e) {
    if (e.target === disc) { render(); }
  });

  render();
})();
</script>

<?php SCH_Modal::open('sch-add-plan', __('خطة رسوم', 'school-system'), __('اسمها ومبلغها وعدد أقساطها الافتراضي', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('خطط الرسوم', 'school-system'); ?></h2>
    <?php if ($plans !== []) : ?>
        <p class="sch-chips">
            <?php foreach ($plans as $p) : ?>
                <span class="sch-chip"><?php echo esc_html($p->name . ' — ' . sch_money($p->amount) . ' / ' . $p->installments); ?></span>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <form method="post" class="sch-toolbar sch-mt">
        <?php wp_nonce_field('sch_add_fee_plan', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="add_fee_plan">
        <input type="text" name="name" placeholder="<?php esc_attr_e('اسم الخطة', 'school-system'); ?>" required>
        <input type="number" name="amount" step="0.01" min="1" placeholder="<?php esc_attr_e('المبلغ', 'school-system'); ?>" required>
        <input type="number" name="installments" min="1" max="12" value="1" title="<?php esc_attr_e('عدد الأقساط', 'school-system'); ?>" aria-label="عدد الأقساط">
        <button class="sch-btn sch-btn--quiet"><?php esc_html_e('إضافة خطة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>

<?php SCH_Modal::open('sch-issue-inv', __('إصدار فاتورة', 'school-system'), __('الأصل دفعة واحدة — والتقسيط امتياز يُمنَح', 'school-system')); ?>
    <h2 hidden><?php esc_html_e('إصدار فاتورة', 'school-system'); ?></h2>
    <p class="sch-sub"><?php esc_html_e('الأصل دفعة واحدة. وتقسيط المدرسة امتياز يُمنَح بسبب مكتوب، لا خيار يُفتح للجميع.', 'school-system'); ?></p>

    <form method="post" id="sch-inv-form">
        <?php wp_nonce_field('sch_issue_invoice', '_sch_nonce'); ?>
        <input type="hidden" name="sch_action" value="issue_invoice">

        <div class="sch-grid">
            <div class="sch-field">
                <label for="i-student"><?php esc_html_e('الطالب', 'school-system'); ?></label>
                <select id="i-student" name="student_id" required>
                    <?php foreach ($sch_pick as $sch_s) : ?>
                        <option value="<?php echo esc_attr((string) $sch_s->id); ?>">
                            <?php echo esc_html(SCH_Enrollment::full_name($sch_s)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="i-plan"><?php esc_html_e('خطة الرسوم', 'school-system'); ?></label>
                <select id="i-plan" name="plan_id" required>
                    <?php foreach (SCH_Finance::plans() as $sch_p) : ?>
                        <option value="<?php echo esc_attr((string) $sch_p->id); ?>"
                                data-amount="<?php echo esc_attr((string) $sch_p->amount); ?>">
                            <?php echo esc_html($sch_p->name . ' — ' . sch_money($sch_p->amount)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sch-field">
                <label for="i-disc"><?php esc_html_e('الخصم ٪', 'school-system'); ?></label>
                <input id="i-disc" type="number" name="discount_pct" min="0" max="100" step="0.5" value="0">
            </div>

            <div class="sch-field">
                <label for="i-first"><?php esc_html_e('تاريخ أول دفعة', 'school-system'); ?></label>
                <input id="i-first" type="date" name="due_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
            </div>
        </div>

        <!-- ثلاثة مسارات لا اثنان -->
        <h3 class="sch-lbl"><?php esc_html_e('كيف يدفع؟', 'school-system'); ?></h3>

        <div class="sch-modes">
            <label class="sch-mode">
                <input type="radio" name="pay_mode" value="full" checked>
                <span class="sch-mode__b">
                    <b><?php esc_html_e('دفعة واحدة', 'school-system'); ?></b>
                    <em><?php esc_html_e('المبلغ كاملًا — الأصل', 'school-system'); ?></em>
                </span>
            </label>

            <label class="sch-mode">
                <input type="radio" name="pay_mode" value="bnpl">
                <span class="sch-mode__b">
                    <b><?php esc_html_e('تابي أو تمارا', 'school-system'); ?></b>
                    <em><?php esc_html_e('المدرسة تقبض كاملًا والشركة تُحصّل من الأب', 'school-system'); ?></em>
                </span>
            </label>

            <?php if (current_user_can('sch_grant_installments')) : ?>
                <label class="sch-mode sch-mode--grant">
                    <input type="radio" name="pay_mode" value="school">
                    <span class="sch-mode__b">
                        <b><?php esc_html_e('تقسيط على المدرسة', 'school-system'); ?></b>
                        <em><?php esc_html_e('امتياز يُمنَح — والمخاطرة على المدرسة', 'school-system'); ?></em>
                    </span>
                </label>
            <?php endif; ?>
        </div>

        <!-- يظهر عند اختيار تقسيط المدرسة فقط -->
        <div class="sch-grant" id="sch-grant" hidden>
            <div class="sch-field">
                <label for="i-n"><?php esc_html_e('عدد الدفعات', 'school-system'); ?></label>
                <div class="sch-nums" id="sch-nums">
                    <?php foreach ([2, 3, 4] as $sch_n) : ?>
                        <label class="sch-num">
                            <input type="radio" name="installments" value="<?php echo esc_attr((string) $sch_n); ?>"
                                   <?php checked($sch_n, 2); ?>>
                            <span><?php echo esc_html(number_format_i18n($sch_n)); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sch-field">
                <label for="i-reason"><?php esc_html_e('سبب المنح', 'school-system'); ?></label>
                <input id="i-reason" type="text" name="grant_reason" maxlength="190"
                       placeholder="<?php esc_attr_e('ظرف مادي · أخوة في المدرسة · قرار المدير', 'school-system'); ?>">
                <span class="sch-hint"><?php esc_html_e('يذكّرك بعد سنة لماذا وافقت، ويحمي المحاسب من السؤال.', 'school-system'); ?></span>
            </div>
        </div>

        <!-- جدول الدفعات: يُحسب فورًا ويُعدَّل تاريخه -->
        <div class="sch-plan" id="sch-plan">
            <div class="sch-plan__sum">
                <span><?php esc_html_e('الرسوم', 'school-system'); ?><b id="p-gross">—</b></span>
                <span id="p-cut-w" hidden><?php esc_html_e('الخصم', 'school-system'); ?><b id="p-cut">—</b></span>
                <span class="is-total"><?php esc_html_e('الإجمالي', 'school-system'); ?><b id="p-total">—</b></span>
            </div>
            <div class="sch-plan__rows" id="p-rows"></div>
        </div>

        <button class="sch-btn"><?php esc_html_e('إصدار الفاتورة', 'school-system'); ?></button>
    </form>
<?php SCH_Modal::close(); ?>
