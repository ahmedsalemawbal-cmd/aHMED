<?php
/**
 * مُعين لـ‎audit.py‎: يُحلّل ملفات PHP **بمحلّل PHP نفسه** لا بتعبير نمطي.
 *
 * تعبير نمطي لا يستطيع تمييز ‎if (…) :‎ (صيغة بديلة) من ‎$a ? b : c‎ (ثلاثي)
 * من ‎case 'x':‎ من نقطتين داخل نص. جرّبته فأنتج 119 نتيجة خاطئة على كود سليم.
 * و‎token_get_all()‎ يعرف الفرق لأنه هو من يقرأه فعلًا.
 *
 * الاستعمال: php tools/php-tokens.php <ملف> [<ملف> …]  → JSON على المخرج
 *
 * لكل ملف:
 *   alt      : {if: n, foreach: n, …}  عدد الكتل المفتوحة بالصيغة البديلة
 *   ends     : {endif: n, endforeach: n, …}
 *   open_tags: عدد وسوم الفتح · close_tags: عدد وسوم الإغلاق
 */

declare(strict_types=1);

$files = array_slice($argv, 1);
$out   = [];

// الكلمة المفتاحية ← وسم الإغلاق المقابل
const PAIRS = [
    T_IF      => ['if',      'endif'],
    T_ELSEIF  => ['elseif',  null],      // لا يفتح كتلة جديدة
    T_ELSE    => ['else',    null],
    T_FOREACH => ['foreach', 'endforeach'],
    T_FOR     => ['for',     'endfor'],
    T_WHILE   => ['while',   'endwhile'],
    T_SWITCH  => ['switch',  'endswitch'],
];

const ENDS = [
    T_ENDIF      => 'endif',
    T_ENDFOREACH => 'endforeach',
    T_ENDFOR     => 'endfor',
    T_ENDWHILE   => 'endwhile',
    T_ENDSWITCH  => 'endswitch',
];

foreach ($files as $file) {
    $src = @file_get_contents($file);
    if ($src === false) {
        continue;
    }

    $tokens = @token_get_all($src);
    if (!is_array($tokens)) {
        continue;
    }

    $alt  = [];
    $ends = [];
    $open_tags = $close_tags = 0;
    $n = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];

        if (is_array($t)) {
            if ($t[0] === T_OPEN_TAG || $t[0] === T_OPEN_TAG_WITH_ECHO) {
                $open_tags++;
                continue;
            }
            if ($t[0] === T_CLOSE_TAG) {
                $close_tags++;
                continue;
            }
            if (isset(ENDS[$t[0]])) {
                $key = ENDS[$t[0]];
                $ends[$key] = ($ends[$key] ?? 0) + 1;
                continue;
            }
            if (!isset(PAIRS[$t[0]])) {
                continue;
            }

            [$kw, $end] = PAIRS[$t[0]];
            if ($end === null) {
                continue; // elseif/else لا يفتحان كتلة تحتاج إغلاقًا خاصًا
            }

            // ابحث عن نهاية الشرط: تجاوز الأقواس متوازنةً
            $j = $i + 1;
            while ($j < $n && !(is_string($tokens[$j]) && $tokens[$j] === '(')) {
                // شيء غير قوس بين الكلمة والشرط ⇒ ليست بنية تحكّم (مثل ‎::if‎)
                if (is_array($tokens[$j]) && !in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    break;
                }
                if (is_string($tokens[$j])) {
                    break;
                }
                $j++;
            }
            if ($j >= $n || !(is_string($tokens[$j]) && $tokens[$j] === '(')) {
                continue;
            }

            $depth = 0;
            for (; $j < $n; $j++) {
                if (is_string($tokens[$j])) {
                    if ($tokens[$j] === '(') {
                        $depth++;
                    } elseif ($tokens[$j] === ')') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                }
            }

            // أول رمز ذي معنى بعد ‎)‎ — إن كان ‎:‎ فهي الصيغة البديلة
            for ($k = $j + 1; $k < $n; $k++) {
                $tk = $tokens[$k];
                if (is_array($tk) && in_array($tk[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if (is_string($tk) && $tk === ':') {
                    $alt[$kw] = ($alt[$kw] ?? 0) + 1;
                }
                break;
            }
        }
    }

    $out[$file] = [
        'alt'        => $alt,
        'ends'       => $ends,
        'open_tags'  => $open_tags,
        'close_tags' => $close_tags,
    ];
}

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
