<?php
/**
 * `$wpdb` بجداولَ في الذاكرة — يفهم ما تكتبه `SCH_Exams` فعلًا.
 *
 * ليس محرّك SQL: هو مخزنُ صفوفٍ ومقيّمُ شروطٍ بسيطة (`=` · `<>` · `IS NULL`
 * · `IS NOT NULL` مفصولةً بـ`AND`) — وهي كل ما تكتبه الطبقة. والفائدة أن
 * **الكتابة تُغيّر الحالة والقراءة التالية ترى ما كُتب**، فتختبر الدعاوى
 * السلوك لا شكل النصّ.
 */
declare(strict_types=1);

final class MemWpdb
{
    public string $users      = 'wp_users';
    public string $prefix     = 'wp_';
    public string $last_error = '';
    public int $insert_id     = 0;
    public array $seen        = [];

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $t = [];
    private int $auto = 1000;

    public function reset(): void
    {
        $this->t    = ['exams' => [], 'exam_results' => [], 'class_subjects' => [], 'students' => [], 'enrollments' => []];
        $this->seen = [];
    }

    public function seed(string $table, array $row): int
    {
        $row['id'] ??= ++$this->auto;
        $this->t[$table][] = $row;

        return (int) $row['id'];
    }

    public function rows(string $table): array
    {
        return $this->t[$table] ?? [];
    }

    // ───────────────────────── prepare ─────────────────────────

    public function prepare($q, ...$a)
    {
        if (count($a) === 1 && is_array($a[0])) {
            $a = $a[0];
        }

        $i = 0;

        return preg_replace_callback('/%[dfs]/', static function (array $m) use (&$i, $a): string {
            $v = $a[$i++] ?? null;

            if ($m[0] === '%d') {
                return (string) (int) $v;
            }
            if ($m[0] === '%f') {
                return (string) (float) $v;
            }

            return $v === null ? "''" : "'" . str_replace("'", "''", (string) $v) . "'";
        }, (string) $q);
    }

    // ───────────────────────── الشروط ─────────────────────────

    /** @return array<int,array{col:string,op:string,val:mixed}> */
    private function conds(string $sql): array
    {
        $p = stripos($sql, ' WHERE ');
        if ($p === false) {
            return [];
        }

        $tail = substr($sql, $p + 7);
        foreach ([' GROUP BY ', ' ORDER BY ', ' LIMIT '] as $stop) {
            $q = stripos($tail, $stop);
            if ($q !== false) {
                $tail = substr($tail, 0, $q);
            }
        }

        $out = [];

        foreach (preg_split('/\s+AND\s+/i', $tail) ?: [] as $one) {
            $one = trim($one);

            if (preg_match('/^(?:\w+\.)?`?(\w+)`?\s+IS\s+NOT\s+NULL$/i', $one, $m) === 1) {
                $out[] = ['col' => $m[1], 'op' => 'notnull', 'val' => null];
                continue;
            }
            if (preg_match('/^(?:\w+\.)?`?(\w+)`?\s+IS\s+NULL$/i', $one, $m) === 1) {
                $out[] = ['col' => $m[1], 'op' => 'null', 'val' => null];
                continue;
            }
            if (preg_match("/^(?:\w+\.)?`?(\w+)`?\s*(=|<>)\s*'(.*)'$/s", $one, $m) === 1) {
                $out[] = ['col' => $m[1], 'op' => $m[2], 'val' => str_replace("''", "'", $m[3])];
                continue;
            }
            if (preg_match('/^(?:\w+\.)?`?(\w+)`?\s*(=|<>)\s*(-?\d+)$/', $one, $m) === 1) {
                $out[] = ['col' => $m[1], 'op' => $m[2], 'val' => (int) $m[3]];
            }
        }

        return $out;
    }

    private function match(array $row, array $conds, array $skip = []): bool
    {
        foreach ($conds as $c) {
            if (in_array($c['col'], $skip, true)) {
                continue;
            }

            $have = $row[$c['col']] ?? null;

            $ok = match ($c['op']) {
                'null'    => $have === null,
                'notnull' => $have !== null,
                '='       => $have !== null && (string) $have === (string) $c['val'],
                '<>'      => $have === null || (string) $have !== (string) $c['val'],
                default   => true,
            };

            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    private function pick(string $table, string $sql, array $skip = []): array
    {
        $c   = $this->conds($sql);
        $out = [];

        foreach ($this->t[$table] as $i => $row) {
            if ($this->match($row, $c, $skip)) {
                $out[$i] = $row;
            }
        }

        return $out;
    }

    // ───────────────────────── القراءة ─────────────────────────

    public function get_results($q = null, $o = null): array
    {
        $q = (string) $q;
        $this->seen[] = $q;

        // مواد الشعبة ومعلّموها
        if (str_contains($q, 'class_subjects cs')) {
            $out = [];
            foreach ($this->pick('class_subjects', $q) as $r) {
                $s = SCH_Subjects::get((int) $r['subject_id']);
                if (!$s) {
                    continue;
                }
                $out[(int) $r['subject_id']] = (object) [
                    'id'              => (int) $s->id,
                    'name'            => (string) $s->name,
                    'teacher_user_id' => $r['teacher_user_id'] ?? null,
                    'teacher_name'    => $r['teacher_user_id'] ? 'م' . $r['teacher_user_id'] : null,
                ];
            }
            return array_values($out);
        }

        // خانات الدورة
        if (str_contains($q, 'proctor_name')) {
            $out = [];
            foreach ($this->pick('exams', $q) as $r) {
                $s = SCH_Subjects::get((int) $r['subject_id']);
                $out[] = (object) ($r + [
                    'subject_name' => $s ? (string) $s->name : '',
                    'proctor_name' => $r['proctor_user_id'] ? 'م' . $r['proctor_user_id'] : null,
                ]);
            }
            return $out;
        }

        // زوج المادة
        if (str_contains($q, 'SELECT id, exam_type FROM')) {
            $out = [];
            foreach ($this->pick('exams', $q) as $r) {
                $out[] = (object) ['id' => (int) $r['id'], 'exam_type' => (string) $r['exam_type']];
            }
            return $out;
        }

        // «الجداول المُنشأة» — ضمٌّ بالمجموعات
        if (str_contains($q, 'GROUP BY e.class_id')) {
            $g = [];
            foreach ($this->pick('exams', $q, ['year_id']) as $r) {
                $k = $r['class_id'] . '|' . $r['session'] . '|' . ($r['session_month'] ?? '');
                $g[$k] ??= ['n' => 0, 'at' => '', 'row' => $r];
                $g[$k]['n']++;
                $g[$k]['at'] = max($g[$k]['at'], (string) ($r['published_at'] ?? ''));
            }

            $out = [];
            foreach ($g as $one) {
                $c = SCH_Classes::get((int) $one['row']['class_id']);
                $out[] = (object) [
                    'class_id'      => (int) $one['row']['class_id'],
                    'session'       => (string) $one['row']['session'],
                    'session_month' => $one['row']['session_month'],
                    'n'             => $one['n'],
                    'at'            => $one['at'],
                    'stage'         => $c->stage ?? '',
                    'grade_level'   => $c->grade_level ?? '',
                    'section'       => $c->section ?? '',
                ];
            }
            return $out;
        }

        // الكشف: الطلاب ودرجتاهم
        if (str_contains($q, 'year_score')) {
            preg_match('/ry\.exam_id = (\d+)/', $q, $a);
            preg_match('/rf\.exam_id = (\d+)/', $q, $b);
            $yid = (int) ($a[1] ?? 0);
            $fid = (int) ($b[1] ?? 0);

            $cls = 0;
            foreach ($this->conds($q) as $c) {
                if ($c['col'] === 'class_id') {
                    $cls = (int) $c['val'];
                }
            }

            $res = static function (array $rows, int $exam, int $student): ?array {
                foreach ($rows as $r) {
                    if ((int) $r['exam_id'] === $exam && (int) $r['student_id'] === $student) {
                        return $r;
                    }
                }
                return null;
            };

            $out = [];
            foreach ($this->t['enrollments'] as $en) {
                if ((int) $en['class_id'] !== $cls || $en['status'] !== 'active') {
                    continue;
                }

                foreach ($this->t['students'] as $st) {
                    if ((int) $st['id'] !== (int) $en['student_id']) {
                        continue;
                    }

                    $ry = $res($this->t['exam_results'], $yid, (int) $st['id']);
                    $rf = $res($this->t['exam_results'], $fid, (int) $st['id']);

                    $out[] = (object) [
                        'id'          => (int) $st['id'],
                        'full_name'   => (string) $st['full_name'],
                        'academic_no' => (string) $st['academic_no'],
                        'year_score'  => $ry['score'] ?? null,
                        'final_score' => $rf['score'] ?? null,
                        'excused'     => max((int) ($ry['excused'] ?? 0), (int) ($rf['excused'] ?? 0)),
                    ];
                }
            }
            return $out;
        }

        return [];
    }

    public function get_row($q = null, $o = null, $y = 0)
    {
        $q = (string) $q;
        $this->seen[] = $q;

        // حالة الاعتماد
        if (str_contains($q, 'MIN(submitted_at)')) {
            $sub = null;
            $app = null;
            $any = false;

            foreach ($this->pick('exams', $q) as $r) {
                $any = true;
                if ($r['submitted_at'] !== null) {
                    $sub = $sub === null ? $r['submitted_at'] : min($sub, $r['submitted_at']);
                } else {
                    $sub = null;
                }
                if ($r['approved_at'] !== null) {
                    $app = $app === null ? $r['approved_at'] : min($app, $r['approved_at']);
                } else {
                    $app = null;
                }
            }

            return $any ? (object) ['sub' => $sub, 'app' => $app] : null;
        }

        $rows = $this->get_results($q);

        return $rows[0] ?? null;
    }

    public function get_var($q = null, $x = 0, $y = 0)
    {
        $q = (string) $q;
        $this->seen[] = $q;

        if (str_contains($q, 'COUNT(*)')) {
            $table = str_contains($q, 'class_subjects') ? 'class_subjects' : 'exams';
            return count($this->pick($table, $q));
        }

        if (str_contains($q, 'SELECT max_score')) {
            $r = $this->pick('exams', $q);
            $r = reset($r);
            return $r === false ? null : $r['max_score'];
        }

        if (str_contains($q, 'SELECT id FROM wp_sch_exam_results')) {
            $r = $this->pick('exam_results', $q);
            $r = reset($r);
            return $r === false ? null : $r['id'];
        }

        if (str_contains($q, 'SELECT excused FROM')) {
            $r = $this->pick('exam_results', $q);
            $r = reset($r);
            return $r === false ? null : $r['excused'];
        }

        return null;
    }

    public function get_col($q = null, $x = 0): array
    {
        $this->seen[] = (string) $q;
        return [];
    }

    // ───────────────────────── الكتابة ─────────────────────────

    public function query($q)
    {
        $q = (string) $q;
        $this->seen[] = $q;

        if (str_starts_with(ltrim($q), 'START') || str_starts_with(ltrim($q), 'COMMIT') || str_starts_with(ltrim($q), 'ROLLBACK')) {
            return 0;
        }

        if (preg_match('/^\s*UPDATE\s+wp_sch_(\w+)\s+SET\s+(.*?)\s+WHERE\s/is', $q, $m) !== 1) {
            return 0;
        }

        $table = $m[1];
        $set   = [];

        foreach (preg_split('/,(?![^(]*\))/', $m[2]) ?: [] as $one) {
            if (preg_match("/^\s*`?(\w+)`?\s*=\s*'(.*)'\s*$/s", $one, $p) === 1) {
                $set[$p[1]] = str_replace("''", "'", $p[2]);
            } elseif (preg_match('/^\s*`?(\w+)`?\s*=\s*NULL\s*$/i', $one, $p) === 1) {
                $set[$p[1]] = null;
            } elseif (preg_match('/^\s*`?(\w+)`?\s*=\s*(-?\d+)\s*$/', $one, $p) === 1) {
                $set[$p[1]] = (int) $p[2];
            }
        }

        $hit = 0;
        foreach ($this->pick($table, $q) as $i => $row) {
            $this->t[$table][$i] = array_merge($row, $set);
            $hit++;
        }

        return $hit;
    }

    public function update($table, $data, $where)
    {
        $t   = str_replace('wp_sch_', '', $table);
        $hit = 0;

        foreach ($this->t[$t] as $i => $row) {
            $ok = true;
            foreach ($where as $k => $v) {
                if ((string) ($row[$k] ?? '') !== (string) $v) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $this->t[$t][$i] = array_merge($row, $data);
                $hit++;
            }
        }

        return $hit;
    }

    public function insert($table, $data)
    {
        $t = str_replace('wp_sch_', '', $table);

        $data += [
            'exam_date' => null, 'room' => null, 'proctor_user_id' => null,
            'published_at' => null, 'submitted_at' => null, 'approved_at' => null,
            'session_month' => null, 'score' => null, 'excused' => 0,
        ];

        $this->insert_id = $this->seed($t, $data);

        return 1;
    }

    public function esc_like($t) { return $t; }
    public function suppress_errors($s = true) { return false; }
}
