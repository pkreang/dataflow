# Deploy demo ขึ้น cPanel (FTP-only, ไม่มี shell)

คู่มือ deploy **Data Flow** (Laravel 12 + Filament + Vite) ขึ้น cPanel ที่เข้าได้แค่ **File Manager / FTP** — ไม่มี SSH/composer/artisan บน server. หลักการ: **build ทุกอย่างที่เครื่อง local → ยกขึ้นเป็นก้อน + import DB ที่ seed แล้ว**.

> Branch model: `main` = dev trunk, `demo` = deploy pointer (ff จาก main เท่านั้น **ห้าม commit ตรง**). อัป demo = `git checkout demo && git merge --ff-only main && git push` แล้ว rebuild.

---

## 0. Precondition (เช็คใน cPanel ก่อน ครั้งแรกครั้งเดียว)

| เช็ค | ที่ไหน | ต้องได้ |
|------|--------|---------|
| PHP version | MultiPHP Manager | **8.2 ขึ้นไป** (Laravel 12 บังคับ — ถ้าไม่มี ทำต่อไม่ได้) |
| MySQL | MySQL Databases | สร้าง DB + user + grant ALL → จด `dbname / user / pass` |
| Subdomain | Domains / Subdomains | สร้าง `demo.<domain>` **Document Root = `/home/<user>/dataflow-app/public`** |

> ทำไมต้อง subdomain ชี้ `…/public`: โค้ด Laravel อยู่นอก web root (ปลอดภัย) และไม่ต้องแก้ `index.php` paths.

---

## 1. Build artifact ที่เครื่อง local

```bash
cd backend
deploy/build-demo.sh demo         # บริษัทกลางๆ + 6 eForm workflow ขั้นสูง + dashboard กราฟ (GenericDemoSeeder)
# deploy/build-demo.sh factory    # หรือ school  (default: factory)
```

สคริปต์ทำให้ครบ: `composer install --no-dev` → `npm run build` → seed **build DB แยก** (ไม่แตะ dev DB `dataflow_uat`) → dump → zip. ได้ใน `backend/deploy/dist/`:
- **`dataflow-demo.zip`** — โค้ด + `vendor/` + `public/build/`
- **`demo.sql`** — DB ที่ seed แล้ว

ท้าย output จะพิมพ์ **APP_KEY ใหม่** — คัดลอกไว้ใส่ `.env` ขั้น 3.

---

## 2. อัปขึ้น cPanel (File Manager)

1. File Manager → ไปที่ `/home/<user>/dataflow-app/` (สร้างโฟลเดอร์ถ้ายังไม่มี — **นอก** `public_html`)
2. Upload `dataflow-demo.zip` → คลิกขวา **Extract** ที่นี่
3. ตรวจว่า `dataflow-app/public/index.php` มีจริง (ตรงกับ Document Root ของ subdomain)

---

## 3. สร้าง `.env` บน server

File Manager → ในโฟลเดอร์ `dataflow-app/` → New File `.env` → Edit แล้ววาง:

```
APP_NAME="Data Flow"
APP_ENV=production
APP_DEBUG=false
APP_KEY=<คีย์จากขั้น 1>
APP_URL=https://demo.<domain>

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<cpanel_dbname>
DB_USERNAME=<cpanel_dbuser>
DB_PASSWORD=<cpanel_dbpass>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

# vertical: demo (บริษัทกลางๆ) — ถ้าไม่ใส่ จะ default=factory ซึ่งก็เป็นคำกลางๆ เหมือนกัน
ORG_VERTICAL=demo

DEPLOY_TOKEN=<สุ่มยาวๆ เช่น openssl rand -hex 16>

# ตามที่จะโชว์ (ไม่ใส่ก็ได้):
# MAIL_MAILER=smtp ...
# LINE_CHANNEL_ACCESS_TOKEN=... LINE_CHANNEL_SECRET=...
```

> **ห้าม** ใช้ redis. **ห้าม** `config:cache` (จะ bake ค่าผิด — demo อ่าน `.env` ตรงๆ).

---

## 4. Import DB

phpMyAdmin → เลือก DB ที่สร้างไว้ → **Import** → เลือก `demo.sql` → Go.

---

## 5. ตั้ง permission + สร้าง storage symlink

1. File Manager → `dataflow-app/storage` และ `dataflow-app/bootstrap/cache` → คลิกขวา **Change Permissions → 755** (ทั้งโฟลเดอร์ย่อย)
2. สร้าง `public/storage` symlink (FTP สร้างเองไม่ได้) → ใช้ **escape-hatch route** เปิดในเบราว์เซอร์ครั้งเดียว:
   ```
   https://demo.<domain>/__deploy/<DEPLOY_TOKEN>/link
   ```
   ควรได้ output `The [public/storage] link has been connected…`. ถ้า cache เพี้ยน เปิด `…/<DEPLOY_TOKEN>/clear` ด้วย.

> route นี้ปิดสนิทถ้าไม่ตั้ง `DEPLOY_TOKEN` (404). หลัง demo จริงจัง แนะนำลบ `DEPLOY_TOKEN` ออกจาก `.env` เพื่อปิด.

---

## 6. ทดสอบ (end-to-end)

1. เปิด `https://demo.<domain>` → หน้า login + ธีม/CSS โหลดครบ (Vite build ใช้ได้)
2. login demo user → ส่งฟอร์ม 1 ใบ ผ่าน workflow (DB + session ใช้ได้)
3. เซ็น signature / อัปไฟล์ → แสดงผลกลับ (storage symlink ใช้ได้)
4. `/quote-request` ส่งคำขอ → ได้เลข RFQ (ถ้า demo รวมฟีเจอร์ RFQ)

demo user (build `demo`): `staff@demo.test` (ผู้ยื่น), `head@` / `manager@` / `director@` / `finance@` / `hr@demo.test` (ผู้อนุมัติ) — รหัสทุกคน `password`. (build `factory`/`school` ดู `backend/README.md`)

---

## อัปเดต demo ครั้งถัดไป

```bash
git checkout demo && git merge --ff-only main && git push
cd backend && deploy/build-demo.sh factory
```
อัป `dataflow-demo.zip` แทนที่ของเดิม (Extract overwrite). **import `demo.sql` ใหม่เฉพาะเมื่อ schema/seed เปลี่ยน** — ถ้าแก้แค่โค้ด ไม่ต้องแตะ DB. หลังอัปโค้ดที่แก้ migration ใหม่ เปิด `…/__deploy/<token>/migrate` เพื่อ migrate (หรือ import .sql ใหม่).

## Troubleshooting

| อาการ | สาเหตุ/แก้ |
|-------|-----------|
| 500 เปล่าๆ | perm `storage/`,`bootstrap/cache` ไม่ใช่ 755 → แก้ขั้น 5.1; หรือ APP_KEY ผิด |
| CSS/JS ไม่มา | ลืมอัป `public/build/` (อยู่ใน zip แล้ว — เช็คว่า extract ครบ) |
| รูป signature/avatar แตก | ยังไม่สร้าง `public/storage` symlink → ขั้น 5.2 |
| "No application encryption key" | `APP_KEY` ใน `.env` ว่าง → ใส่คีย์จากขั้น 1 |
| 404 ทั้งเว็บ | Document Root ของ subdomain ไม่ได้ชี้ `…/dataflow-app/public` |
