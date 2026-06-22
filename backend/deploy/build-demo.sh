#!/usr/bin/env bash
#
# สร้าง artifact สำหรับ deploy ขึ้น cPanel ที่เข้าได้แค่ FTP/File Manager (ไม่มี shell).
# build ทุกอย่างที่เครื่อง local → ได้ 2 ไฟล์ใน deploy/dist/ :
#   - dataflow-demo.zip  (โค้ด + vendor/ + public/build/ พร้อมอัป)
#   - demo.sql           (DB ที่ seed แล้ว สำหรับ import ผ่าน phpMyAdmin)
#
# Usage:  deploy/build-demo.sh [factory|school|demo]   (default: factory)
#   demo = บริษัทกลางๆ (GenericDemoSeeder) — 6 eForm workflow ขั้นสูง + dashboard กราฟ
# ดูขั้นตอนเต็มที่ doc/deploy-cpanel.md
#
set -euo pipefail

VERTICAL="${1:-factory}"
case "$VERTICAL" in
    factory|school|demo) ;;
    *) echo "vertical ต้องเป็น factory, school หรือ demo (ได้รับ: $VERTICAL)"; exit 1 ;;
esac

cd "$(dirname "$0")/.."          # → backend/
DIST="$(pwd)/deploy/dist"
mkdir -p "$DIST"

# DB creds จาก .env — ใช้สร้าง "build DB" แยก เพื่อ seed+dump โดย "ไม่แตะ dev DB"
envval() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }
DB_HOST="$(envval DB_HOST)"; DB_USER="$(envval DB_USERNAME)"; DB_PASS="$(envval DB_PASSWORD)"
BUILD_DB="${BUILD_DB:-dataflow_demo_build}"

echo "==> [1/5] composer install (prod, ไม่มี dev deps)"
composer install --no-dev --optimize-autoloader

echo "==> [2/5] npm build (Vite + Tailwind v4 → public/build)"
npm ci
npm run build

echo "==> [3/5] seed build DB '$BUILD_DB' (vertical=$VERTICAL) — ไม่แตะ $(envval DB_DATABASE)"
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
    -e "CREATE DATABASE IF NOT EXISTS \`$BUILD_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
export DB_DATABASE="$BUILD_DB"      # Dotenv ไม่ override env ที่ export แล้ว → artisan ใช้ build DB
php artisan migrate:fresh --force --seed
case "$VERTICAL" in
    factory)
        php artisan db:seed --class=NteqPolymerDemoSeeder --force
        ;;
    school)
        php artisan db:seed --class=IndustryTemplateSeeder --force
        php artisan db:seed --class=BodindechaDemoSeeder --force
        ;;
    demo)
        php artisan db:seed --class=GenericDemoSeeder --force
        ;;
esac
php artisan org:switch "$VERTICAL"
unset DB_DATABASE

echo "==> [4/5] dump → deploy/dist/demo.sql"
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
    --no-tablespaces --default-character-set=utf8mb4 "$BUILD_DB" > "$DIST/demo.sql"

echo "==> [5/5] zip artifact → deploy/dist/dataflow-demo.zip"
rm -f "$DIST/dataflow-demo.zip"
zip -rq "$DIST/dataflow-demo.zip" . \
    -x './node_modules/*' './.git/*' './tests/*' './deploy/dist/*' \
       './.phpunit.cache/*' './storage/logs/*' \
       './storage/framework/cache/data/*' './storage/framework/sessions/*' \
       './storage/framework/views/*' './public/storage/*' './public/storage' \
       './.env' './.env.bak' './database/database.sqlite'

echo
echo "เสร็จ → $DIST/"
echo "  dataflow-demo.zip  : อัปไป /home/<user>/dataflow-app/ แล้ว Extract"
echo "  demo.sql           : import ผ่าน phpMyAdmin เข้า DB ที่สร้างใน cPanel"
echo
echo "APP_KEY ใหม่สำหรับ .env บน server (คัดลอกไปวาง):"
php artisan key:generate --show
