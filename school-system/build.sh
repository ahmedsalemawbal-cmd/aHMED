#!/usr/bin/env bash
#
# بناء نسخة قابلة للتسليم.
#
#   ./build.sh              يبني بالنسخة الحالية
#   ./build.sh 8.4.0        يرفع الرقم أولًا ثم يبني
#   ./build.sh --patch      يرفع آخر رقم (8.3.0 ← 8.3.1) ثم يبني
#
# **لا تُسلَّم نسخة بلا تحريك ‎SCH_VERSION‎** — قاعدة مكتوبة في CLAUDE.md:
# الأصول موسومة بـ‎sch_asset()‎، والترقية تُفرّغ الكاش وتُعيد بناء قواعد
# التوجيه ومزامنة الأدوار — ورقم الإضافة هو إشارة الترقية نفسها.
#
# ولا يبني السكربت شيئًا قبل أن يمرّ ‎php -l‎ و‎audit.py‎ — فما لا يُفحص لا يُسلَّم.

set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NAME="school-system"
OUT="${SCH_BUILD_OUT:-$DIR/../dist}"

c()  { printf '\033[1;36m%s\033[0m\n' "$*"; }
ok() { printf '\033[1;32m✓ %s\033[0m\n' "$*"; }
er() { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

cur_version() {
  grep -oE "define\('SCH_VERSION', '[0-9.]+'\)" "$DIR/school-system.php" \
    | grep -oE '[0-9]+\.[0-9]+\.[0-9]+'
}

set_version() {
  local v="$1"
  # الرقم في موضعين — الترويسة والثابت — و‎VERSION_SYNC‎ يمسك اختلافهما
  sed -i -E "s/^( \* Version: +)[0-9.]+/\1$v/" "$DIR/school-system.php"
  sed -i -E "s/(define\('SCH_VERSION', ')[0-9.]+(')/\1$v\2/" "$DIR/school-system.php"
  ok "النسخة $v"
}

# ---------- الرقم ----------
case "${1:-}" in
  --patch)
    IFS=. read -r a b p <<< "$(cur_version)"
    set_version "$a.$b.$((p + 1))"
    ;;
  --minor)
    IFS=. read -r a b _ <<< "$(cur_version)"
    set_version "$a.$((b + 1)).0"
    ;;
  '' ) : ;;
  *  ) [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || er "رقم غير صالح: $1"; set_version "$1" ;;
esac

VERSION="$(cur_version)"
[[ -n "$VERSION" ]] || er "تعذّرت قراءة SCH_VERSION"

# ---------- الفحص قبل البناء ----------
c "php -l …"
bad=0
while IFS= read -r f; do
  php -l "$f" >/dev/null 2>&1 || { er_msg=$(php -l "$f" 2>&1 | head -2); printf '\033[1;31m✗ %s\n%s\033[0m\n' "$f" "$er_msg" >&2; bad=1; }
done < <(find "$DIR" -name '*.php' -not -path '*/node_modules/*')
((bad)) && er "أخطاء نحوية — لا بناء"
ok "صفر خطأ نحوي"

c "audit.py …"
( cd "$DIR" && python3 audit.py --quiet ) || er "audit.py غير نظيف — لا بناء"
ok "الفحص نظيف"

# ---------- البناء ----------
mkdir -p "$OUT"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# ما لا يدخل النسخة: أدوات التطوير، وملفات المستودع، وأي شيء مولَّد.
tar -C "$DIR" -cf - \
    --exclude='.git*' \
    --exclude='node_modules' \
    --exclude='tools' \
    --exclude='dev.sh' \
    --exclude='build.sh' \
    --exclude='audit.py' \
    --exclude='__pycache__' \
    --exclude='*.pyc' \
    --exclude='shots' \
    --exclude='dist' \
    . | tar -C "$STAGE" -xf - --one-top-level="$NAME" 2>/dev/null \
     || { mkdir -p "$STAGE/$NAME"; tar -C "$DIR" -cf - \
            --exclude='.git*' --exclude='node_modules' --exclude='tools' \
            --exclude='dev.sh' --exclude='build.sh' --exclude='audit.py' \
            --exclude='__pycache__' --exclude='*.pyc' --exclude='shots' --exclude='dist' . \
          | tar -C "$STAGE/$NAME" -xf -; }

ZIP="$OUT/${NAME}-${VERSION}.zip"
rm -f "$ZIP"
( cd "$STAGE" && zip -qr "$ZIP" "$NAME" -x '*.DS_Store' )

# ---------- تقرير ----------
FILES=$(unzip -l "$ZIP" | tail -1 | awk '{print $2}')
SIZE=$(du -h "$ZIP" | cut -f1)

cat <<TXT

$(ok "$ZIP")
   النسخة : $VERSION
   الملفات: $FILES
   الحجم  : $SIZE

   التركيب: انسخ مجلد $NAME إلى wp-content/plugins ثم فعّله،
            أو ارفع الملف من «الإضافات ← أضف جديدًا ← رفع».

TXT
