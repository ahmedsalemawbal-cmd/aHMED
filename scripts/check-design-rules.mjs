// Design-system guard (CLAUDE.md): no raw colours outside tokens.css,
// logical properties only (no left/right), no emoji in UI text.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const root = new URL('..', import.meta.url).pathname;
const srcDir = join(root, 'src');
const allowColour = new Set(['src/styles/tokens.css', 'src/styles/components.css']);

const rules = [
  { name: 'raw hex colour', re: /#[0-9a-fA-F]{3,8}\b(?![\w-])/g, skip: (f) => allowColour.has(f) },
  { name: 'raw rgb/hsl colour', re: /\b(rgba?|hsla?)\(/g, skip: (f) => allowColour.has(f) },
  { name: 'physical direction class (use ms/me/ps/pe/start/end)', re: /(?<![\w-])-?(ml|mr|pl|pr|left|right|border-l|border-r|rounded-l|rounded-r|rounded-tl|rounded-tr|rounded-bl|rounded-br|text-left|text-right|scroll-ml|scroll-mr)-[\w[\]./-]+/g, skip: (f) => !/\.tsx?$/.test(f) },
  { name: 'physical CSS property (use inset-inline / margin-inline)', re: /\b(margin-left|margin-right|padding-left|padding-right|marginLeft|marginRight|paddingLeft|paddingRight|textAlign:\s*'(left|right)')\b/g, skip: () => false },
  { name: 'emoji in source', re: /\p{Extended_Pictographic}/gu, skip: () => false },
];

function walk(dir) {
  return readdirSync(dir).flatMap((name) => {
    const p = join(dir, name);
    return statSync(p).isDirectory() ? walk(p) : [p];
  });
}

let failures = 0;
for (const file of walk(srcDir)) {
  const rel = relative(root, file);
  if (!/\.(tsx?|css)$/.test(rel)) continue;
  const lines = readFileSync(file, 'utf8').split('\n');
  for (const rule of rules) {
    if (rule.skip(rel)) continue;
    lines.forEach((line, i) => {
      const trimmed = line.trim();
      // prose in comments is not markup (e.g. "left-to-right")
      if (rule.name.startsWith('physical') && /^(\/\/|\/\*|\*)/.test(trimmed)) return;
      rule.re.lastIndex = 0;
      const m = rule.re.exec(line);
      if (m) {
        failures++;
        console.error(`${rel}:${i + 1}  ${rule.name}: ${m[0]}`);
      }
    });
  }
}
if (failures) {
  console.error(`\n${failures} design-rule violation(s).`);
  process.exit(1);
}
console.log('design rules: ok');
