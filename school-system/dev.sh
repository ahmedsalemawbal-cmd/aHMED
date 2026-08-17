#!/usr/bin/env bash
#
# بيئة تطوير كاملة بأمر واحد: ووردبريس + MySQL + النظام + بيانات تجريبية.
#
#   ./dev.sh up        يبني كل شيء ويشغّل الخادم         (أول مرة: ~3 دقائق)
#   ./dev.sh reset     يمحو القاعدة ويعيد البناء من صفر
#   ./dev.sh sync      يعيد نسخ الإضافة بعد تعديل (أقل من ثانية)
#   ./dev.sh seed      بيانات تجريبية فقط
#   ./dev.sh audit     python3 audit.py + php -l على كل ملف
#   ./dev.sh shots     صور لكل شاشة + تقرير أخطاء (يحتاج Playwright)
#   ./dev.sh stop      يوقف الخادم وقاعدة البيانات
#   ./dev.sh logs      يتابع سجل أخطاء PHP
#
# **لماذا سكربت لا Docker:** التركيب هنا أخف وأسرع، ولا يحتاج صلاحيات
# خاصة ولا سحب صور. ومن يريد Docker يجد الملف مكتوبًا بخطوات صريحة
# يسهل ترجمتها. الأهم أن من يستلم المشروع يشتغل في دقيقتين لا في يومين.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORK="${SCH_DEV_HOME:-$HOME/.sch-dev}"
WP="$WORK/wp"
PORT="${SCH_PORT:-8080}"
DB_NAME="${SCH_DB:-school_wp}"
DB_USER="schuser"
DB_PASS="schpass"
WPCLI="$WORK/wp-cli.phar"
SOCK=/var/run/mysqld/mysqld.sock

ADMIN_USER="admin"
ADMIN_PASS='Admin!2345'
STAFF_PASS='Test!12345'

c()  { printf '\033[1;36m%s\033[0m\n' "$*"; }
ok() { printf '\033[1;32m✓ %s\033[0m\n' "$*"; }
er() { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; }

wp() { php "$WPCLI" --allow-root --path="$WP" "$@"; }

need() {
  local missing=()
  for b in php mysqld mysql curl; do command -v "$b" >/dev/null || missing+=("$b"); done
  if ((${#missing[@]})); then
    er "ناقص: ${missing[*]}"
    echo "على أوبنتو/ديبيان:  sudo apt-get install -y php-cli php-mysql php-gd php-mbstring php-xml php-intl php-zip mysql-server curl"
    exit 1
  fi
  # الإضافة تشترط PHP 8.2
  php -r 'exit(PHP_VERSION_ID < 80200 ? 1 : 0);' || { er "PHP 8.2+ مطلوب (الحالي $(php -r 'echo PHP_VERSION;'))"; exit 1; }
}

db_up() {
  if ! mysqladmin --socket="$SOCK" status >/dev/null 2>&1; then
    c "تشغيل MySQL…"
    sudo mkdir -p /var/run/mysqld && sudo chown mysql:mysql /var/run/mysqld 2>/dev/null || true
    (sudo mysqld_safe --skip-syslog >/dev/null 2>&1 &) || (mysqld_safe --skip-syslog >/dev/null 2>&1 &)
    for _ in $(seq 1 40); do
      mysqladmin --socket="$SOCK" status >/dev/null 2>&1 && break
      sleep 1
    done
  fi
  mysqladmin --socket="$SOCK" status >/dev/null 2>&1 || { er "تعذّر تشغيل MySQL"; exit 1; }

  mysql --socket="$SOCK" -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
  ok "قاعدة البيانات جاهزة ($DB_NAME)"
}

get_wp() {
  [[ -f "$WP/wp-settings.php" ]] && return 0
  c "تنزيل ووردبريس…"
  mkdir -p "$WORK"
  # المسار الأول: wp-cli (يحتاج wordpress.org)
  if [[ ! -f "$WPCLI" ]]; then
    curl -fsSL -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x "$WPCLI"
  fi
  mkdir -p "$WP"
  if ! wp core download --locale=ar --force 2>/dev/null; then
    # المسار البديل: حزمة أوبنتو — تعمل حين يُحجب wordpress.org
    c "wordpress.org غير متاح — تركيب من حزمة أوبنتو"
    local deb="$WORK/wordpress.deb"
    curl -fsSL -o "$deb" \
      "http://archive.ubuntu.com/ubuntu/pool/universe/w/wordpress/wordpress_6.4.3+dfsg1-1ubuntu1_all.deb"
    dpkg-deb -x "$deb" "$WORK/wpdeb"
    cp -a "$WORK/wpdeb/usr/share/wordpress/." "$WP/"
    rm -f "$WP/wp-config.php"
    # الحزمة تستبدل مكتبات JS بروابط للنظام — نحوّل المعلّقة إلى ملفات فارغة
    # حتى لا يفشل أي include. (getid3 لا يستعمله النظام: الفيديو رابط لا رفع.)
    while IFS= read -r link; do
      rm -f "$link"; case "$link" in *.php) printf '<?php\n' > "$link";; *) : > "$link";; esac
    done < <(find "$WP" -xtype l)

    # ‎underscore.js‎ **يجب** أن يُستعاد: لوحة ووردبريس وبعض مكوّناتها تعتمده،
    # وبلاه يظهر «‎_ is not defined‎» في الـconsole. نأخذه من npm.
    if command -v npm >/dev/null; then
      ( cd "$WORK" && npm pack underscore@1.13.6 --silent >/dev/null 2>&1 \
        && tar xzf underscore-1.13.6.tgz 2>/dev/null \
        && cp package/underscore.js     "$WP/wp-includes/js/underscore.js" \
        && cp package/underscore-umd-min.js "$WP/wp-includes/js/underscore.min.js" ) 2>/dev/null \
        || echo "  تنبيه: تعذّر استعادة underscore.js"
    fi
  fi
  ok "ووردبريس $(wp core version 2>/dev/null || echo '?')"
}

# نسخ الإضافة إلى مجلد ووردبريس.
#
# **لماذا نسخ لا وصلة رمزية:** جُرّبت الوصلة فانكسر كل أصل ثابت. سببان:
# · خادم PHP المدمج يرفض خدمة ملف خارج جذر المستند، والوصلة تشير خارجه —
#   فيسقط الطلب على ووردبريس فيعيد توجيهًا قانونيًا بشرطة مائلة، ويدور
#   المتصفح حتى ‎ERR_ABORTED‎. كانت النتيجة 260 استجابة 500 و855 بندًا.
# · و‎plugin_dir_url(__FILE__)‎ تحسب الرابط من مسار الملف الحقيقي، والوصلة
#   تجعله خارج ‎wp-content/plugins‎ فيخرج رابط خاطئ.
#
# فالنسخ هو الصحيح، و‎./dev.sh sync‎ يعيده بعد كل تعديل (أقل من ثانية).
sync_plugin() {
  local dest="$WP/wp-content/plugins/school-system"
  local tmp="$WP/wp-content/plugins/.school-system-new"

  # يُبنى جانبًا ثم يُبدَّل بحركة واحدة. الحذف ثم النسخ يترك نافذة تكون فيها
  # الإضافة غير موجودة — وأي طلب يصل فيها يرى ووردبريس بلا إضافتنا، فقد
  # يُعيد توليد جدول قواعد التوجيه بلا قواعدنا. حدث هذا في الاختبار.
  rm -rf "$tmp"
  mkdir -p "$tmp"
  tar -C "$PLUGIN_DIR" -cf - \
      --exclude=node_modules --exclude=.git --exclude=tools --exclude=dev.sh \
      --exclude=audit.py --exclude=build.sh . \
    | tar -C "$tmp" -xf -

  rm -rf "$dest.old"
  [[ -d "$dest" ]] && mv "$dest" "$dest.old"
  mv "$tmp" "$dest"
  rm -rf "$dest.old"
}

configure() {
  c "الإعداد…"
  wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" \
     --dbhost="localhost:$SOCK" --dbcharset=utf8mb4 --locale=ar --force --extra-php <<PHP
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', '$WP/debug.log');
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
define('WP_HOME', 'http://localhost:$PORT');
define('WP_SITEURL', 'http://localhost:$PORT');
define('DISABLE_WP_CRON', true);
define('FS_METHOD', 'direct');
PHP

  wp core install --url="http://localhost:$PORT" --title="مدرسة تجريبية" \
     --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" \
     --admin_email=dev@example.test --skip-email >/dev/null

  # الروابط الجميلة شرط: كل الداشبورد والتطبيقات مسارات لا معاملات
  wp rewrite structure '/%postname%/' >/dev/null

  # اللغة العربية: بلا حزمة اللغة تُعرض التواريخ بالإنجليزية داخل واجهة عربية
  wp language core install ar --activate >/dev/null 2>&1 \
    || echo "  تنبيه: تعذّر تنزيل حزمة اللغة العربية — ستظهر التواريخ بالإنجليزية."

  sync_plugin
  wp plugin activate school-system

  local missing
  missing=$(wp eval 'SCH_Loader::load_modules(); echo count(SCH_Activator::missing_tables());' 2>/dev/null | tr -d '[:space:]')
  if [[ "$missing" == "0" ]]; then
    ok "كل الجداول أُنشئت"
  else
    er "$missing جدولًا لم يُنشأ — راجع الإعدادات في الداشبورد"
  fi
}

seed() {
  c "بيانات تجريبية…"
  local f="$PLUGIN_DIR/tools/seed.php"
  [[ -f "$f" ]] || { er "لا ملف $f"; return 1; }
  wp eval-file "$f" 2>&1 | grep -v '^Deprecated' || true

  # كلمة مرور معروفة لكل دور — للتجربة اليدوية وللصور
  wp eval "
    SCH_Loader::load_modules();
    \$out = [];
    foreach (['sch_guardian','sch_teacher','sch_driver','sch_guard','sch_student','sch_principal'] as \$r) {
      \$u = get_users(['role' => \$r, 'number' => 1]);
      if (!\$u) { continue; }
      wp_set_password('$STAFF_PASS', \$u[0]->ID);
      \$out[\$r] = \$u[0]->user_login;
    }
    file_put_contents('$WORK/accounts.json', wp_json_encode(\$out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  " 2>/dev/null | grep -v '^Deprecated' || true
  ok "البيانات جاهزة"
}

router() {
  cat > "$WORK/router.php" <<'PHP'
<?php
// موجّه لخادم PHP المدمج — يحاكي .htaccess: الملف الموجود يُخدَم،
// وكل ما عداه يذهب لـindex.php. بلا هذا لا تعمل الروابط الجميلة.
$root = getenv('SCH_WP_ROOT');
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = realpath($root . urldecode($uri));
if ($path !== false && str_starts_with($path, realpath($root)) && is_file($path)) {
    return false;
}
if ($path !== false && is_dir($path) && is_file($path . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($uri, '/') . '/index.php';
    require $path . '/index.php';
    return true;
}
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';
PHP
}

serve() {
  router
  if curl -fsS -o /dev/null --max-time 2 "http://localhost:$PORT/" 2>/dev/null; then
    ok "الخادم يعمل على http://localhost:$PORT"
    return
  fi
  c "تشغيل الخادم…"
  SCH_WP_ROOT="$WP" nohup php -S "0.0.0.0:$PORT" -t "$WP" "$WORK/router.php" \
    > "$WORK/server.log" 2>&1 &
  for _ in $(seq 1 30); do
    curl -fsS -o /dev/null --max-time 2 "http://localhost:$PORT/" 2>/dev/null && break
    sleep 0.5
  done
  ok "http://localhost:$PORT"
}

banner() {
  cat <<TXT

$(c '── جاهز ──')
  الداشبورد   http://localhost:$PORT/dashboard/    $ADMIN_USER / $ADMIN_PASS
  بوابة الدخول http://localhost:$PORT/login/
  ولي الأمر    http://localhost:$PORT/app/
  المعلم       http://localhost:$PORT/teacher/
  السائق       http://localhost:$PORT/driver/
  البوابة      http://localhost:$PORT/gate/
  الطالب       http://localhost:$PORT/student/

  كلمة مرور كل الحسابات التجريبية: $STAFF_PASS
  أسماء الدخول: $WORK/accounts.json
  سجل الأخطاء:  ./dev.sh logs
  بعد أي تعديل: ./dev.sh sync

TXT
}

case "${1:-up}" in
  up)
    need; db_up; get_wp
    [[ -f "$WP/wp-config.php" ]] || configure
    wp core is-installed 2>/dev/null || configure
    serve; banner
    ;;
  reset)
    need; db_up; get_wp
    c "محو القاعدة…"
    wp db reset --yes >/dev/null 2>&1 || true
    configure; seed; serve; banner
    ;;
  sync)
    sync_plugin
    # الخادم المدمج عملية واحدة طويلة العمر، و‎__FILE__‎ (ومنه ‎SCH_URL‎)
    # يُحسب عند أول ترجمة ويبقى في ذاكرة العملية. فبلا إعادة تشغيل تبقى
    # روابط الأصول على المسار القديم — ظهر هذا كـ260 استجابة 500 بعد أن
    # استُبدلت الوصلة الرمزية بنسخة حقيقية.
    pkill -f "php -S 0.0.0.0:$PORT" 2>/dev/null || true
    sleep 1
    serve
    ok "الإضافة حُدِّثت وأُعيد تشغيل الخادم — حدّث الصفحة"
    ;;
  seed) need; seed ;;
  audit)
    c "php -l على كل ملف…"
    bad=0
    while IFS= read -r f; do
      php -l "$f" >/dev/null 2>&1 || { er "$f"; php -l "$f" | head -2; bad=1; }
    done < <(find "$PLUGIN_DIR" -name '*.php' -not -path '*/node_modules/*')
    ((bad)) || ok "صفر خطأ نحوي"
    c "audit.py…"
    ( cd "$PLUGIN_DIR" && python3 audit.py )
    ;;
  shots)
    [[ -f "$PLUGIN_DIR/tools/shoot.js" ]] || { er "لا ملف tools/shoot.js"; exit 1; }
    command -v node >/dev/null || { er "node مطلوب"; exit 1; }
    # Playwright يُثبَّت في مجلد العمل لا في مجلد الإضافة — فلا يدخل الـZIP.
    if [[ ! -d "$WORK/node_modules/playwright" ]]; then
      c "تثبيت Playwright…"
      ( cd "$WORK" && npm init -y >/dev/null 2>&1; npm install --silent playwright ) \
        || { er "تعذّر تثبيت Playwright"; exit 1; }
      ( cd "$WORK" && npx playwright install chromium ) || true
    fi
    # ‎NODE_PATH‎ ضروري: node يبحث عن الوحدات بجوار **ملف السكربت** لا مجلد
    # التشغيل، والسكربت في مجلد الإضافة والوحدات في مجلد العمل.
    ( cd "$WORK" && NODE_PATH="$WORK/node_modules" \
      SCH_BASE="http://localhost:$PORT" SCH_OUT="$WORK/shots" \
      SCH_ACCOUNTS="$WORK/accounts.json" SCH_ADMIN="$ADMIN_USER" \
      SCH_ADMIN_PASS="$ADMIN_PASS" SCH_PASS="$STAFF_PASS" \
      node "$PLUGIN_DIR/tools/shoot.js" )
    ok "الصور في $WORK/shots"
    ;;
  logs) tail -f "$WP/debug.log" ;;
  stop)
    pkill -f "php -S 0.0.0.0:$PORT" 2>/dev/null && ok "الخادم أُوقف" || echo "الخادم غير مشغَّل"
    mysqladmin --socket="$SOCK" -u root shutdown 2>/dev/null && ok "MySQL أُوقف" || true
    ;;
  *) sed -n '2,20p' "$0" ; exit 2 ;;
esac
