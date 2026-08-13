<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * مولّد QR Code مستقل — بلا مكتبة خارجية وبلا خدمة طرف ثالث.
 * رمز الطالب لا يغادر الخادم إطلاقًا.
 *
 * النطاق: النسخ 1..4، مستوى تصحيح M، وضع البايت.
 * يكفي لأي رقم أكاديمي أو معرّف قصير (حتى 62 حرفًا).
 *
 * تم التحقق من: جداول GF(256)، مولّد Reed-Solomon مقابل القيم القياسية،
 * ومعلومات الصيغة مقابل خوارزمية BCH(15,5) في المواصفة.
 */
final class SCH_QR
{
    /** [سعة البيانات بالبايت, بايتات ECC لكل كتلة, عدد الكتل] */
    private const VERSIONS = [
        1 => [16, 10, 1],
        2 => [28, 16, 1],
        3 => [44, 26, 1],
        4 => [64, 18, 2],
    ];

    private const ALIGN = [1 => null, 2 => 18, 3 => 22, 4 => 26];

    /** معلومات الصيغة للمستوى M مع الأقنعة 0..7 */
    private const FORMAT_M = [
        0b101010000010010, 0b101000100100101, 0b101111001111100, 0b101101101001011,
        0b100010111111001, 0b100000011001110, 0b100111110010111, 0b100101010100000,
    ];

    /** @var array<int,int>|null */
    private static ?array $exp = null;
    /** @var array<int,int>|null */
    private static ?array $log = null;

    /**
     * يُرجع صورة SVG جاهزة للعرض أو الطباعة.
     */
    public static function svg(string $text, int $module = 6, int $quiet = 4): string
    {
        $matrix = self::matrix($text);
        if ($matrix === []) {
            return '';
        }

        $size  = count($matrix);
        $total = ($size + $quiet * 2) * $module;

        $rects = '';
        foreach ($matrix as $r => $row) {
            $c = 0;
            while ($c < $size) {
                if ($row[$c] !== 1) {
                    $c++;
                    continue;
                }
                // دمج الوحدات المتجاورة في مستطيل واحد — ملف أصغر ورسم أسرع.
                $start = $c;
                while ($c < $size && $row[$c] === 1) {
                    $c++;
                }
                $rects .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d"/>',
                    ($start + $quiet) * $module,
                    ($r + $quiet) * $module,
                    ($c - $start) * $module,
                    $module
                );
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d" '
            . 'shape-rendering="crispEdges" role="img" aria-label="QR"><rect width="%1$d" height="%1$d" fill="#fff"/>'
            . '<g fill="#000">%2$s</g></svg>',
            $total,
            $rects
        );
    }

    /** يُرجع مصفوفة الوحدات (0/1). */
    public static function matrix(string $text): array
    {
        $len = strlen($text);
        if ($len === 0) {
            return [];
        }

        $version = self::pick_version($len);
        if ($version === 0) {
            return [];
        }

        $codewords = self::encode($text, $version);
        $size      = 17 + $version * 4;

        [$base, $reserved] = self::layout($version, $size, $codewords);
        $functions         = self::function_cells($version, $size);

        $best  = null;
        $score = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $cand = self::apply_mask($base, $size, $mask, $functions, $reserved);
            self::place_format($cand, $size, $mask);

            $clean = [];
            foreach ($cand as $row) {
                $clean[] = array_map(static fn ($v): int => $v === null ? 0 : (int) $v, $row);
            }

            $p = self::penalty($clean, $size);
            if ($score === null || $p < $score) {
                $score = $p;
                $best  = $clean;
            }
        }

        return $best ?? [];
    }

    // ---------- GF(256) و Reed-Solomon ----------

    private static function init_gf(): void
    {
        if (self::$exp !== null) {
            return;
        }

        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x   = 1;

        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        self::$exp = $exp;
        self::$log = $log;
    }

    private static function gf_mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    private static function rs_generator(int $n): array
    {
        self::init_gf();
        $poly = [1];

        for ($i = 0; $i < $n; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $c) {
                $next[$j]     ^= $c;
                $next[$j + 1] ^= self::gf_mul($c, self::$exp[$i]);
            }
            $poly = $next;
        }

        return $poly;
    }

    private static function rs_ecc(array $data, int $n): array
    {
        $gen = self::rs_generator($n);
        $res = array_merge($data, array_fill(0, $n, 0));

        foreach ($data as $i => $_) {
            $coef = $res[$i];
            if ($coef === 0) {
                continue;
            }
            foreach ($gen as $j => $g) {
                $res[$i + $j] ^= self::gf_mul($g, $coef);
            }
        }

        return array_slice($res, count($data));
    }

    // ---------- الترميز ----------

    private static function pick_version(int $len): int
    {
        foreach (self::VERSIONS as $v => $meta) {
            if ($len + 2 <= $meta[0]) {
                return $v;
            }
        }
        return 0;
    }

    private static function encode(string $text, int $version): array
    {
        [$cap, $ecc_len, $blocks] = self::VERSIONS[$version];

        $bits = '';
        $bits .= '0100';                                    // وضع البايت
        $bits .= str_pad(decbin(strlen($text)), 8, '0', STR_PAD_LEFT);

        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }

        $total = $cap * 8;
        $bits .= str_repeat('0', min(4, max(0, $total - strlen($bits))));
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }

        $codewords = [];
        foreach (str_split($bits, 8) as $byte) {
            $codewords[] = bindec($byte);
        }

        $pad = [0xEC, 0x11];
        $i   = 0;
        while (count($codewords) < $cap) {
            $codewords[] = $pad[$i % 2];
            $i++;
        }

        $per    = intdiv($cap, $blocks);
        $data   = [];
        $eccs   = [];

        for ($b = 0; $b < $blocks; $b++) {
            $chunk  = array_slice($codewords, $b * $per, $per);
            $data[] = $chunk;
            $eccs[] = self::rs_ecc($chunk, $ecc_len);
        }

        $out = [];
        for ($i = 0; $i < $per; $i++) {
            foreach ($data as $blk) {
                $out[] = $blk[$i];
            }
        }
        for ($i = 0; $i < $ecc_len; $i++) {
            foreach ($eccs as $blk) {
                $out[] = $blk[$i];
            }
        }

        return $out;
    }

    // ---------- بناء المصفوفة ----------

    private static function layout(int $version, int $size, array $codewords): array
    {
        $m = array_fill(0, $size, array_fill(0, $size, null));

        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r, $c]) {
            self::finder($m, $size, $r, $c);
        }

        for ($i = 8; $i < $size - 8; $i++) {
            $v = $i % 2 === 0 ? 1 : 0;
            if ($m[6][$i] === null) {
                $m[6][$i] = $v;
            }
            if ($m[$i][6] === null) {
                $m[$i][6] = $v;
            }
        }

        $a = self::ALIGN[$version];
        if ($a !== null) {
            for ($i = -2; $i <= 2; $i++) {
                for ($j = -2; $j <= 2; $j++) {
                    $m[$a + $i][$a + $j] = (max(abs($i), abs($j)) !== 1) ? 1 : 0;
                }
            }
        }

        $m[$size - 8][8] = 1;

        $reserved = [];
        for ($i = 0; $i <= 8; $i++) {
            if ($m[8][$i] === null) {
                $reserved["8,$i"] = true;
            }
            if ($m[$i][8] === null) {
                $reserved["$i,8"] = true;
            }
        }
        for ($i = $size - 8; $i < $size; $i++) {
            if ($m[8][$i] === null) {
                $reserved["8,$i"] = true;
            }
            if ($m[$i][8] === null) {
                $reserved["$i,8"] = true;
            }
        }

        $bits = '';
        foreach ($codewords as $cw) {
            $bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        $idx    = 0;
        $len    = strlen($bits);
        $col    = $size - 1;
        $upward = true;

        while ($col > 0) {
            if ($col === 6) {
                $col--;
            }

            $rows = $upward ? range($size - 1, 0) : range(0, $size - 1);

            foreach ($rows as $row) {
                foreach ([$col, $col - 1] as $c) {
                    if ($m[$row][$c] !== null || isset($reserved["$row,$c"])) {
                        continue;
                    }
                    $m[$row][$c] = $idx < $len ? (int) $bits[$idx] : 0;
                    $idx++;
                }
            }

            $upward = !$upward;
            $col   -= 2;
        }

        return [$m, $reserved];
    }

    private static function finder(array &$m, int $size, int $r, int $c): void
    {
        for ($i = -1; $i <= 7; $i++) {
            for ($j = -1; $j <= 7; $j++) {
                $rr = $r + $i;
                $cc = $c + $j;

                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                    continue;
                }

                if (($i === 0 || $i === 6) && $j >= 0 && $j <= 6) {
                    $v = 1;
                } elseif (($j === 0 || $j === 6) && $i >= 0 && $i <= 6) {
                    $v = 1;
                } elseif ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4) {
                    $v = 1;
                } else {
                    $v = 0;
                }

                $m[$rr][$cc] = $v;
            }
        }
    }

    private static function function_cells(int $version, int $size): array
    {
        $cells = [];

        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r, $c]) {
            for ($i = -1; $i <= 7; $i++) {
                for ($j = -1; $j <= 7; $j++) {
                    $rr = $r + $i;
                    $cc = $c + $j;
                    if ($rr >= 0 && $rr < $size && $cc >= 0 && $cc < $size) {
                        $cells["$rr,$cc"] = true;
                    }
                }
            }
        }

        for ($i = 0; $i < $size; $i++) {
            $cells["6,$i"] = true;
            $cells["$i,6"] = true;
        }

        $a = self::ALIGN[$version];
        if ($a !== null) {
            for ($i = -2; $i <= 2; $i++) {
                for ($j = -2; $j <= 2; $j++) {
                    $r = $a + $i;
                    $c = $a + $j;
                    $cells["$r,$c"] = true;
                }
            }
        }

        $dr = $size - 8;
        $cells["$dr,8"] = true;

        return $cells;
    }

    private static function apply_mask(array $m, int $size, int $mask, array $functions, array $reserved): array
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (isset($functions["$r,$c"]) || isset($reserved["$r,$c"]) || $m[$r][$c] === null) {
                    continue;
                }
                if (self::mask_at($mask, $r, $c)) {
                    $m[$r][$c] ^= 1;
                }
            }
        }

        return $m;
    }

    private static function mask_at(int $mask, int $r, int $c): bool
    {
        return match ($mask) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
            6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
            7 => ((($r + $c) % 2) + (($r * $c) % 3)) % 2 === 0,
            default => false,
        };
    }

    private static function place_format(array &$m, int $size, int $mask): void
    {
        $bits = self::FORMAT_M[$mask];

        for ($i = 0; $i < 15; $i++) {
            $bit = ($bits >> (14 - $i)) & 1;

            if ($i < 6) {
                $m[8][$i] = $bit;
            } elseif ($i === 6) {
                $m[8][7] = $bit;
            } elseif ($i === 7) {
                $m[8][8] = $bit;
            } elseif ($i === 8) {
                $m[7][8] = $bit;
            } else {
                $m[14 - $i][8] = $bit;
            }

            if ($i < 8) {
                $m[$size - 1 - $i][8] = $bit;
            } else {
                $m[8][$size - 15 + $i] = $bit;
            }
        }

        $m[$size - 8][8] = 1;
    }

    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        $lines = $m;

        for ($c = 0; $c < $size; $c++) {
            $col = [];
            for ($r = 0; $r < $size; $r++) {
                $col[] = $m[$r][$c];
            }
            $lines[] = $col;
        }

        // تتابع 5 وحدات فأكثر بنفس اللون
        foreach ($lines as $line) {
            $run  = 1;
            $prev = $line[0];
            for ($i = 1; $i < $size; $i++) {
                if ($line[$i] === $prev) {
                    $run++;
                    continue;
                }
                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
                $run  = 1;
                $prev = $line[$i];
            }
            if ($run >= 5) {
                $score += 3 + ($run - 5);
            }
        }

        // مربعات 2×2 متجانسة
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                if ($m[$r][$c] === $m[$r][$c + 1]
                    && $m[$r][$c] === $m[$r + 1][$c]
                    && $m[$r][$c] === $m[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        // نمط 1:1:3:1:1 الذي يُشبه مربع البحث
        $p1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $p2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];

        foreach ($lines as $line) {
            for ($i = 0; $i <= $size - 11; $i++) {
                $seg = array_slice($line, $i, 11);
                if ($seg === $p1 || $seg === $p2) {
                    $score += 40;
                }
            }
        }

        // انحراف نسبة الوحدات الداكنة عن 50%
        $dark = 0;
        foreach ($m as $row) {
            $dark += array_sum($row);
        }
        $pct    = intdiv($dark * 100, $size * $size);
        $score += 10 * intdiv(abs($pct - 50), 5);

        return $score;
    }
}
