<?php
/**
 * محوّل مخطط: MySQL (ووردبريس) ← → PostgreSQL (Supabase).
 *
 * يقرأ تعريفات الجداول من ملف التفعيل حرفيًّا بدل إعادة كتابتها يدويًّا —
 * لأن ٧٢ جدولًا تُنقل باليد تعني أخطاء صامتة في أنواع وقيود لا تُكتشف
 * إلا بعد أن تدخل البيانات.
 */

declare(strict_types=1);

$src = file_get_contents($argv[1] ?? 'school-system/includes/class-activator.php');

preg_match_all('/CREATE TABLE \{\$p\}(\w+) \((.*?)\n\s*\) \{\$charset\};/s', $src, $m, PREG_SET_ORDER);

if (!$m) {
    fwrite(STDERR, "لم يُعثر على تعريفات الجداول.\n");
    exit(1);
}

$out    = [];
$idx    = [];
$checks = [];

/** نوع MySQL ← نوع Postgres */
function pg_type(string $t): string
{
    $t = trim($t);

    // ENUM يُعالَج خارج هذه الدالة (يحتاج اسم العمود لقيد CHECK)
    return match (true) {
        (bool) preg_match('/^BIGINT/i', $t)                 => 'bigint',
        (bool) preg_match('/^INT/i', $t)                    => 'integer',
        (bool) preg_match('/^TINYINT\(1\)/i', $t)           => 'boolean',
        (bool) preg_match('/^(TINY|SMALL)INT/i', $t)        => 'smallint',
        (bool) preg_match('/^MEDIUMINT/i', $t)              => 'integer',
        (bool) preg_match('/^VARCHAR\((\d+)\)/i', $t, $v)   => "varchar({$v[1]})",
        (bool) preg_match('/^CHAR\((\d+)\)/i', $t, $v)      => "char({$v[1]})",
        (bool) preg_match('/^(LONG|MEDIUM)?TEXT/i', $t)     => 'text',
        (bool) preg_match('/^DATETIME/i', $t)               => 'timestamptz',
        (bool) preg_match('/^TIMESTAMP/i', $t)              => 'timestamptz',
        (bool) preg_match('/^DATE/i', $t)                   => 'date',
        (bool) preg_match('/^TIME/i', $t)                   => 'time',
        (bool) preg_match('/^DECIMAL\(([\d,\s]+)\)/i', $t, $v) => 'numeric(' . preg_replace('/\s+/', '', $v[1]) . ')',
        (bool) preg_match('/^(FLOAT|DOUBLE)/i', $t)         => 'double precision',
        (bool) preg_match('/^JSON/i', $t)                   => 'jsonb',
        default                                             => 'text',
    };
}

foreach ($m as $tbl) {
    $name = $tbl[1];
    $body = $tbl[2];

    $cols  = [];
    $cons  = [];
    $seen  = [];

    // تقسيم على الفواصل التي في المستوى الأعلى فقط — ENUM يحوي فواصل داخله
    $parts = [];
    $depth = 0;
    $buf   = '';

    foreach (str_split($body) as $ch) {
        if ($ch === '(') { $depth++; }
        if ($ch === ')') { $depth--; }
        if ($ch === ',' && $depth === 0) { $parts[] = $buf; $buf = ''; continue; }
        $buf .= $ch;
    }
    $parts[] = $buf;

    foreach ($parts as $raw) {
        $line = trim(preg_replace('/\s+/', ' ', $raw));

        if ($line === '') { continue; }

        // ── المفاتيح والفهارس ──
        if (preg_match('/^PRIMARY KEY \((.+)\)$/i', $line, $k)) {
            $cons[] = 'PRIMARY KEY (' . cols($k[1]) . ')';
            continue;
        }
        if (preg_match('/^UNIQUE KEY (\w+) \((.+)\)$/i', $line, $k)) {
            $cons[] = "CONSTRAINT {$name}_{$k[1]} UNIQUE (" . cols($k[2]) . ')';
            continue;
        }
        if (preg_match('/^KEY (\w+) \((.+)\)$/i', $line, $k)) {
            $idx[] = "CREATE INDEX {$name}_{$k[1]} ON public.sch_{$name} (" . cols($k[2]) . ');';
            continue;
        }
        if (preg_match('/^(FULLTEXT|SPATIAL|CONSTRAINT|FOREIGN)/i', $line)) {
            continue;   // لا يوجد منها في هذا المخطط
        }

        // ── عمود ──
        if (!preg_match('/^(\w+)\s+(.*)$/', $line, $c)) { continue; }

        $col  = $c[1];
        $rest = $c[2];

        if (isset($seen[$col])) { continue; }
        $seen[$col] = true;

        // AUTO_INCREMENT ← IDENTITY
        $auto = (bool) preg_match('/AUTO_INCREMENT/i', $rest);
        $rest = preg_replace('/\s*AUTO_INCREMENT/i', '', $rest);

        // ENUM ← text + CHECK: يبقى القيد في القاعدة، ويظلّ توسيع القائمة لاحقًا سهلًا
        if (preg_match("/^ENUM\((.+?)\)/i", $rest, $e)) {
            $vals      = $e[1];
            $checks[]  = "ALTER TABLE public.sch_{$name} ADD CONSTRAINT {$name}_{$col}_chk CHECK ({$col} IN ({$vals}));";
            $type      = 'text';
            $rest      = preg_replace("/^ENUM\(.+?\)/i", '', $rest);
        } else {
            preg_match('/^([A-Z]+(\(\s*[\d,\s]+\s*\))?(\s+UNSIGNED)?)/i', $rest, $t);
            $type = pg_type($t[1] ?? 'TEXT');
            $rest = substr($rest, strlen($t[1] ?? ''));
        }

        $rest = trim(preg_replace('/\s*UNSIGNED/i', '', $rest));

        $null    = preg_match('/NOT NULL/i', $rest) ? ' NOT NULL' : '';
        $default = '';

        if (preg_match("/DEFAULT (CURRENT_TIMESTAMP|'[^']*'|[\d.]+|NULL)/i", $rest, $d)) {
            $v = $d[1];

            if (strcasecmp($v, 'NULL') !== 0) {
                if ($type === 'boolean') {
                    $v = ((int) trim($v, "'")) ? 'true' : 'false';
                } elseif (strcasecmp($v, 'CURRENT_TIMESTAMP') === 0) {
                    $v = 'now()';
                }
                $default = " DEFAULT {$v}";
            }
        }

        if ($auto) {
            $cols[] = "    {$col} bigint GENERATED ALWAYS AS IDENTITY";
            continue;
        }

        $cols[] = "    {$col} {$type}{$default}{$null}";
    }

    $out[] = "CREATE TABLE public.sch_{$name} (\n"
        . implode(",\n", array_merge($cols, array_map(fn ($c) => '    ' . $c, $cons)))
        . "\n);";
}

function cols(string $s): string
{
    return implode(', ', array_map('trim', explode(',', $s)));
}

echo "-- مخطّط أُصول على PostgreSQL — مولَّد من مخطّط MySQL الأصلي\n";
echo '-- ' . count($out) . " جدولًا\n\n";
echo implode("\n\n", $out), "\n\n";
echo "-- الفهارس\n", implode("\n", $idx), "\n\n";
echo "-- قيود القيم المحصورة (ENUM سابقًا)\n", implode("\n", $checks), "\n";

fwrite(STDERR, sprintf("✅ %d جدولًا · %d فهرسًا · %d قيد قيمة\n", count($out), count($idx), count($checks)));
