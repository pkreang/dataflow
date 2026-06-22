# Git + Deploy workflow (Data Flow)

แนวทางใช้ git กับการ deploy โปรเจกต์นี้ — อ่านคู่กับ `doc/deploy-cpanel.md` (ขั้นตอน cPanel ละเอียด).

## 1. Git model

| Branch | บทบาท | กฎ |
|--------|-------|-----|
| **`main`** | trunk — source of truth | โค้ดที่ผ่าน gate แล้วเท่านั้น; ไม่ commit งานครึ่งๆ ตรง main |
| **`feature/*`** | งานย่อย แตกจาก main | **สั้น** (ไม่กี่ commit) → merge กลับเร็ว → ลบ branch |
| **`demo`** | deploy pointer (ชี้ว่า server รันโค้ดไหน) | **ff จาก main เท่านั้น ห้าม commit ตรง**: `git checkout demo && git merge --ff-only main && git push` |

**รอบการทำงาน:**
1. `git switch -c feature/xyz main` → ทำงาน
2. ก่อน merge: `composer test` (ปัจจุบัน 685) · `composer lint` · `composer analyse` (= baseline 96) ต้องเขียว
3. merge → main (PR หรือ `--no-ff`) → `git branch -d feature/xyz`
4. ตอน deploy: tag ที่ main (`git tag deploy-YYYY-MM-DD && git push --tags`) ไว้ย้อนรู้ว่า server = commit ไหน

**ห้าม commit:** `backend/deploy/dist/*` (artifact), `.env`, server creds, `database/database.sqlite` — gitignore ครอบแล้ว.

> **สถานะปัจจุบัน (2026-06-22):** งาน demo + deploy tooling อยู่บน `feature/remove-cmms` (นำ main 10 commits, 0 behind, gate เขียว) — **ควร merge → main** แล้ว ff `demo` ตามให้ทัน (ตอนนี้ `demo` ตามหลัง main ~20 commits). main ที่ stale ทำให้สับสนว่าอะไรคือของจริง.

## 2. หลายลูกค้า (multi-customer) — แยกยังไง

**อย่าแยก git branch ต่อลูกค้า** — จะกลายเป็นภาระ maintain (N branches ต้องตาม main, ทุก bugfix ต้อง cherry-pick, merge conflict บานปลาย).

**โมเดลที่แนะนำ: โค้ดชุดเดียว + instance-per-customer**
| มิติ | วิธีแยกต่อลูกค้า |
|------|------------------|
| โค้ด | **ชุดเดียวจาก `main`** — ลูกค้าทุกรายรันโค้ดเดียวกัน |
| Deployment | **subdomain + DB + `.env` แยกต่อราย** (เหมือน flow.dataplc.net) |
| Branding/ตั้งค่า | `.env`: `APP_NAME`, `ORG_VERTICAL`, feature flag; + ข้อมูล **DB-driven** (settings / navigation / RBAC / ฟอร์ม / dashboard) seed/ตั้งต่อราย |
| โค้ดเฉพาะลูกค้า | ทำเป็น **config / feature flag ใน main** (ทุกคนได้ + อยู่ codebase เดียว) — **ไม่ fork** |
| track version ต่อราย | **tag** (`acme-prod-2026-06`) หรือ deploy-pointer branch ที่แค่ชี้ commit — ไม่ใช่ feature branch ที่ diverge |

**ข้อควรระวัง — อย่ายัดหลายลูกค้าใน instance/DB เดียว (multi-tenant):** ระบบนี้ `settings`/`navigation_menus`/RBAC (Spatie) เป็น **global ไม่ได้ scope ต่อ company** → ข้อมูล/สิทธิ์/เมนูจะปนข้ามลูกค้า. **instance-per-customer (DB แยก) สะอาดและ isolate กว่า** + deploy ด้วย `build-demo.sh` + `.env` ต่อราย.

```
main ── โค้ดผลิตภัณฑ์ (ลูกค้าทุกรายดึงจากนี่)
 ├ feature/*               งานย่อย → merge main
 └ tags: acme-prod-*, beta-prod-*   ← ลูกค้าไหนรัน release ไหน
deploy ต่อลูกค้า = subdomain + DB + .env   (ไม่ใช่ branch)
```

## 3. Deploy (cPanel FTP-only) — quickref

infra ตั้งครั้งเดียวจบแล้ว (subdomain + DB + **PHP 8.4** ผ่าน "Select PHP Version"). รอบถัดไป:

```bash
cd backend
composer install                 # กู้ dev deps ถ้าเพิ่ง build (build ใช้ --no-dev)
deploy/build-demo.sh demo        # → deploy/dist/dataflow-demo.zip + demo.sql + APP_KEY
```
แล้ว (ผ่าน FTP/File Manager + escape-hatch URL):
1. อัป `dataflow-demo.zip` → extract ทับ `dataflow-app/` (โค้ด+vendor+assets)
2. ถ้า assets ใน docroot ใช้ split layout — copy `public/*` ใหม่เข้า docroot (ดู §3)
3. รัน hatch:
   - แก้โค้ด/migration เท่านั้น → `https://<host>/__deploy/<DEPLOY_TOKEN>/migrate` (**ไม่ลบข้อมูล**)
   - เปลี่ยน seed/schema ใหม่หมด → `…/__deploy/<DEPLOY_TOKEN>/seed` (**DROP+seed demo ใหม่ — ล้างข้อมูลเดิม**)
   - หลังย้ายไฟล์ → `…/__deploy/<DEPLOY_TOKEN>/link` (storage symlink), `…/clear` (ล้าง cache **+ reset OPcache** — สำคัญ: ถ้า FTP-patch ไฟล์ PHP แล้วไม่ `/clear` server จะเสิร์ฟ bytecode เก่า)

> build `demo` พก `storage/framework/{views,cache,sessions}` + `bootstrap/cache` ไปแล้ว (ไม่ต้อง mkdir เองหลัง extract).

## 4. flow.dataplc.net (instance demo ปัจจุบัน)

- **URL**: https://flow.dataplc.net · **DB**: `dataplc_flow` · sessions = file · `ORG_VERTICAL=demo`
- **Layout = split** (เพราะ subdomain ตั้ง docroot ใหม่ไม่ได้บน host นี้):
  - framework: `/home/dataplc/dataflow-app/` (นอก web root)
  - docroot: `/home/dataplc/public_html/flow.dataplc.net/` = สำเนา `public/*` + `index.php` patch ให้ `$base='/home/dataplc/dataflow-app'` + symlink `storage` → `dataflow-app/storage/app/public`
  - **redeploy ที่แก้ asset/public** ต้อง copy `public/*` เข้า docroot ใหม่ (เว้น index.php ที่ patch ไว้) — ใช้ PHP helper หรือ File Manager
- **PHP**: ต้อง 8.4+ (CloudLinux "Select PHP Version") — MultiPHP API ปิด, `.htaccess` handler ไม่มีผล (mod_lsapi)
- บัญชี demo: `staff@demo.test` … `hr@demo.test` (+ `admin@example.com`) รหัส `password`

## 5. ของที่ต้องตั้งเพิ่มถ้าจะเปิดจริง
- **LINE/Email แจ้งเตือน**: ใส่ `LINE_CHANNEL_ACCESS_TOKEN`/`LINE_CHANNEL_SECRET` หรือ `MAIL_*` ใน `.env` (บน server) + ผู้ใช้ link บัญชี LINE หลัง login
- **ปิด escape-hatch**: ลบ `DEPLOY_TOKEN` จาก `.env` หลัง demo เสถียร
- **หมุนรหัส**: เปลี่ยนรหัส cPanel/FTP ถ้าเคยแชร์ออกไป
