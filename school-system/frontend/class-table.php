<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * أدوات الجدول المشتركة — رأس عمود يُفرَز، وشريط ترقيم.
 *
 * أول ما يفعله أي مستخدم في جدول هو النقر على رأس عمود، ولم يكن يحدث شيء:
 * ترتيب كل شاشة مكتوب في استعلامها مباشرةً. والقوائم كانت بلا ترقيم أصلًا،
 * فالسقف الصامت يقصّها ولا يقول.
 *
 * وميزة هذا النظام أن مرشّحاته **معاملات رابط** أصلًا — فالفرز والصفحة يصيران
 * جزءًا من الرابط: يُشارَك ويُحفظ في المفضّلة ويعمل معه زر الرجوع. وهذا أفضل
 * من الفرز في المتصفح الذي يضيع بأول تحديث، لا مساوٍ له.
 */
final class SCH_Table
{
    /**
     * رأس عمود قابل للفرز.
     *
     * يحفظ كل معاملات الرابط الحالية ويقلب الاتجاه وحده، ويصفّر الصفحة —
     * فرزٌ يُبقيك في الصفحة السابعة يعرض نتائج لا معنى لها.
     */
    public static function th(string $label, string $key, string $class = ''): string
    {
        $cur = isset($_GET['orderby']) ? sanitize_key(wp_unslash((string) $_GET['orderby'])) : '';
        $dir = (isset($_GET['order']) && strtoupper((string) $_GET['order']) === 'DESC') ? 'DESC' : 'ASC';
        $on  = ($cur === $key);
        $nxt = ($on && $dir === 'ASC') ? 'DESC' : 'ASC';

        $args = $_GET;
        unset($args['sch_dash'], $args['sch_section'], $args['sch_id'], $args['paged']);
        $args['orderby'] = $key;
        $args['order']   = $nxt;

        $url = add_query_arg(array_map('sanitize_text_field', array_map('strval', $args)));

        $sort = $on ? ($dir === 'ASC' ? 'ascending' : 'descending') : 'none';

        return '<th' . ($class !== '' ? ' class="' . esc_attr($class) . '"' : '')
            . ' aria-sort="' . esc_attr($sort) . '">'
            . '<a class="sch-th" href="' . esc_url($url) . '">'
            . '<span>' . esc_html($label) . '</span>'
            . '<svg class="sch-th__i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . ($on
                ? ($dir === 'ASC' ? '<path d="m6 15 6-6 6 6"/>' : '<path d="m6 9 6 6 6-6"/>')
                : '<path d="m7 10 5-5 5 5"/><path d="m7 14 5 5 5-5"/>')
            . '</svg></a></th>';
    }

    /** ما تقرؤه طبقة البيانات من الرابط — قائمة بيضاء تُطبَّق في الاستعلام. */
    public static function order_args(): array
    {
        return [
            'orderby' => isset($_GET['orderby']) ? sanitize_key(wp_unslash((string) $_GET['orderby'])) : '',
            'order'   => (isset($_GET['order']) && strtoupper((string) $_GET['order']) === 'DESC') ? 'DESC' : 'ASC',
        ];
    }

    /** رقم الصفحة الحالية. */
    public static function page(): int
    {
        return max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    }

    /**
     * شريط الترقيم — لا يظهر إن كانت صفحة واحدة.
     *
     * ويقول العدد الكلي دائمًا: «١–٢٠ من ٤١٢» تُخبر الموظف أن هناك ما بعدها،
     * والسقف الصامت كان يخفي ذلك تمامًا.
     */
    public static function pager(int $total, int $per_page): string
    {
        if ($per_page < 1) {
            return '';
        }

        $pages = (int) ceil($total / $per_page);
        $page  = min(max(1, self::page()), max(1, $pages));
        $from  = $total === 0 ? 0 : (($page - 1) * $per_page) + 1;
        $to    = min($total, $page * $per_page);

        $link = static function (int $p): string {
            $args = $_GET;
            unset($args['sch_dash'], $args['sch_section'], $args['sch_id']);
            $args['paged'] = $p;

            return add_query_arg(array_map('sanitize_text_field', array_map('strval', $args)));
        };

        $out = '<div class="sch-pager"><span class="sch-pager__n">' . esc_html(sprintf(
            /* translators: 1: من 2: إلى 3: الإجمالي */
            __('%1$s–%2$s من %3$s', 'school-system'),
            number_format_i18n($from),
            number_format_i18n($to),
            number_format_i18n($total)
        )) . '</span>';

        if ($pages > 1) {
            $out .= '<div class="sch-pager__b">';

            $out .= $page > 1
                ? '<a class="sch-btn sch-btn--quiet" href="' . esc_url($link($page - 1)) . '">'
                    . esc_html__('السابق', 'school-system') . '</a>'
                : '<span class="sch-btn sch-btn--quiet" aria-disabled="true">'
                    . esc_html__('السابق', 'school-system') . '</span>';

            $out .= '<span class="sch-pager__p">' . esc_html(sprintf(
                /* translators: 1: الصفحة 2: العدد */
                __('صفحة %1$s من %2$s', 'school-system'),
                number_format_i18n($page),
                number_format_i18n($pages)
            )) . '</span>';

            $out .= $page < $pages
                ? '<a class="sch-btn sch-btn--quiet" href="' . esc_url($link($page + 1)) . '">'
                    . esc_html__('التالي', 'school-system') . '</a>'
                : '<span class="sch-btn sch-btn--quiet" aria-disabled="true">'
                    . esc_html__('التالي', 'school-system') . '</span>';

            $out .= '</div>';
        }

        return $out . '</div>';
    }
}
