# System Test Playbook — ขั้นตอนทดสอบทั้งระบบ

**บทบาทไฟล์นี้:** ขั้นตอน repeatable สำหรับทดสอบระบบ **ก่อน merge เข้า `main`** หรือก่อน release — ครอบคลุม automated tests, static analysis และ manual UAT 2 vertical. **E2E/browser test อยู่นอกขอบเขต** ของเอกสารนี้

> รันคำสั่ง shell/composer ทั้งหมดจากโฟลเดอร์ **`backend/`**

---

## 0. ภาพรวม 4 ชั้น

| ชั้น | เครื่องมือ | gate | ใครรัน |
|------|-----------|------|--------|
| 1. Automated | `composer test` (PHPUnit, SQLite :memory:) | CI: `tests-sqlite` | dev + CI |
| 2. Static | `composer analyse` (PHPStan/Larastan) + `composer lint` (Pint) | CI: `static-analysis` | dev + CI |
| 3. Integration | `composer test` บน MySQL จริง | CI: `tests-mysql` (continue-on-error) | CI |
| 4. Manual UAT | checklist ต่อ vertical | — | ทีม QA |

CI workflow: `.github/workflows/ci.yml` — รันชั้น 1–3 อัตโนมัติทุก push/PR เข้า `main`

---

## 1. Pre-flight

```bash
cd backend
git status                       # branch สะอาด, อยู่บน branch ที่จะทดสอบ
composer install                 # ติดตั้ง PHP deps ให้ตรง composer.lock
npm ci                           # ติดตั้ง node deps ให้ตรง package-lock.json
npm run build                    # ⚠️ จำเป็น — ดูหมายเหตุด้านล่าง
php artisan migrate:fresh --seed # ตรวจว่า migrate + seed ผ่าน (DB dev เท่านั้น)
```

> ⚠️ **ต้อง `npm run build` ก่อนรันเทส** — feature test หลายตัว render หน้า Blade ที่ใช้ `@vite`
> ถ้าไม่มี `public/build/manifest.json` จะ throw `ViteManifestNotFoundException` → ~25 เทสตอบ 500
> (นี่คือเหตุผลที่ CI job มีขั้น "Build front-end assets" ก่อน "Run test suite")

**ทดสอบหลาย branch พร้อมกัน:** ใช้ `git worktree` แยก checkout — แต่ละ worktree ต้องมี **`vendor/` จริง** (รัน `composer install` หรือ copy — **ห้าม symlink** เพราะ Composer resolve `__DIR__` ผ่าน symlink ทำให้ autoload `App\` ชี้ checkout ผิด branch) และต้อง `npm run build` ของตัวเอง

---

## 2. ชั้น Automated

```bash
composer test                       # ทั้งชุด (= config:clear + artisan test)
php artisan test --filter SomeTest   # เฉพาะคลาส
```

**เกณฑ์ผ่าน:** `Tests: N passed` ไม่มี failed/errored. ค่าอ้างอิงปัจจุบัน (อาจเพิ่มเมื่อมีเทสใหม่):
`main` ~266 · `mobile-redesign` ~462 · เลขจะต่างกันตาม branch

**เมื่อไหร่ต้องรันบน MySQL จริง:** เทสปกติใช้ SQLite `:memory:` (เร็ว) ซึ่ง **ไม่** ทดสอบฟีเจอร์เฉพาะ MySQL (generated columns, JSON path index, FULLTEXT) — ถ้าแก้ migration/คอลัมน์ที่ใช้ของพวกนี้ ให้รันบน MySQL local หรือพึ่ง CI job `tests-mysql`:

```bash
DB_CONNECTION=mysql DB_DATABASE=testing DB_USERNAME=root DB_PASSWORD=... composer test
```

---

## 3. ชั้น Static Analysis

```bash
composer analyse   # PHPStan + Larastan (level 4) — เทียบกับ phpstan-baseline.neon
composer lint      # Pint --test (เช็ค style, ไม่แก้ไฟล์)
composer format    # Pint แก้ style จริง (ใช้ตอน dev)
```

**PHPStan baseline:** error เดิม 237 รายการถูก freeze ไว้ใน `phpstan-baseline.neon` — `composer analyse` จะ **ผ่าน** ถ้าไม่มี error **ใหม่**. เมื่อแก้ error เดิมหมดบางส่วนแล้ว regenerate:
```bash
vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon --memory-limit=512M
```
เป้าหมายระยะยาว: baseline หด → ดัน `level` ขึ้น (4 → 5 → 6) ใน `phpstan.neon`

**Pint:** ยังมี style debt เดิม ~48 ไฟล์ที่ **ยังไม่ reformat** (เลื่อนไว้กัน conflict กับ feature branch ที่ค้าง). CI lint **เฉพาะไฟล์ที่ PR/push แก้** — โค้ดใหม่ต้องผ่าน Pint. **TODO:** เมื่อ feature branch ที่ค้างทั้งหมด merge แล้ว ให้รัน `composer format` ทั้ง repo เป็น commit "style: apply pint" ครั้งเดียว แล้วเปลี่ยน CI ให้ lint ทั้ง repo

---

## 4. ชั้น Manual UAT (ต่อ vertical)

ระบบมี 2 vertical — ทดสอบทั้งคู่เมื่อเปลี่ยนของที่กระทบ workflow/seed/permission

### 4a. School vertical
```bash
composer switch:school                       # migrate:fresh + seed school + Bodindecha demo
php artisan school:workflow-test-users        # demo users (employee@demo.com … password demo1234)
```
เดินตาม checklist: **`doc/uat-repair-request.md`** (login → master data → submit → multi-step approval), **`doc/uat-rbac-permissions.md`**, **`doc/uat-settings-menus.md`** (21 รายการ — UI/CRUD/ภาษา/density)

### 4b. Factory/CMMS vertical
```bash
composer switch:factory                       # migrate:fresh + seed factory + NTEQ demo
```
เดินตาม: **`doc/test-nteq-maintenance-manual.md`** (visibility rules, validation, equipment linking), **`doc/test-full-workflow-via-ui.md`** (สร้าง doctype → dept → position → user → workflow → submit → approve)

### รีเซ็ตระหว่างรอบ
```bash
php artisan testing:reset-user-layer --dry-run   # ดูก่อน
php artisan testing:reset-user-layer --force     # ลบ test users เก็บ master data
```
รายละเอียด: `doc/uat-reset-testing-layer.md`

---

## 5. Branch-merge regression

ก่อน merge feature branch เข้า `main`:

1. รันชั้น 1–2 บน feature branch → เขียว
2. หา **ไฟล์ที่ branch อื่นแตะร่วมกัน**: `git diff --name-only main..<branch>` แล้วเทียบกับ branch อื่นที่ยังค้าง — จุดที่ซ้อนกันคือ conflict risk
3. merge/rebase แล้วรันชั้น 1–2 อีกครั้งบน integration branch → เขียว ก่อนเข้า `main`
4. ตรวจ CI เขียวบน PR

---

## 6. CI gate

`.github/workflows/ci.yml` รันทุก push/PR → `main`:

| Job | บังคับ? | หมายเหตุ |
|-----|---------|----------|
| `tests-sqlite` | ✅ required | gate หลัก |
| `static-analysis` | ✅ required | PHPStan baselined + Pint ไฟล์ที่แก้ |
| `tests-mysql` | ⚠️ continue-on-error | เลื่อนเป็น required เมื่อเขียวสม่ำเสมอ |

**CI แดง = ห้าม merge**

---

## 7. Sign-off checklist

| รายการ | ผ่าน | ผู้ตรวจ | วันที่ |
|--------|------|---------|--------|
| ชั้น 1 — `composer test` เขียว | ☐ | | |
| ชั้น 2 — `composer analyse` + `composer lint` เขียว | ☐ | | |
| ชั้น 3 — `tests-mysql` เขียว (CI) | ☐ | | |
| ชั้น 4 — UAT school vertical | ☐ | | |
| ชั้น 4 — UAT factory/CMMS vertical | ☐ | | |
| CI ทุก job เขียวบน PR | ☐ | | |
