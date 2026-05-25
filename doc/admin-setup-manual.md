# คู่มือตั้งค่าระบบ (Admin Setup Manual)

**บทบาทไฟล์นี้:** คู่มือสำหรับผู้ดูแลระบบคนแรก (super-admin) ที่ติดตั้งระบบใหม่ — ตั้งแต่ login ครั้งแรกหลัง `migrate:fresh --seed` ไปจนถึง "ระบบพร้อมให้ end-user เริ่มเข้ามาทำงานได้" ทุกขั้นตอนมี **ผลที่ควรเห็น** เพื่อใช้เป็น UAT walkthrough ไปในตัว

**ผู้ใช้คู่มือนี้คือใคร:**
- Admin ครั้งแรกที่ติดตั้งระบบ (production / staging)
- Tester ที่ verify regression หลัง merge
- ทีมงานที่ทบทวนว่า flow setup ใช้งานได้ถูกต้องจริง

**ไม่ใช่:** คู่มือสำหรับ end-user (requester / approver / evaluator) — ดูคู่มือแยก

**เอกสารที่เกี่ยวข้อง:**
- `doc/uat-by-settings-menu-2026-05-21.md` — UAT checklist เรียงตาม sidebar (delta merge)
- `doc/uat-integration-merge-2026-05-21.md` — UAT checklist เรียงตาม feature/commit (A-M)
- `doc/uat-clean-slate-walkthrough.md` — UAT clean-slate ดั้งเดิม
- `CLAUDE.md` §7 — โดเมนธุรกิจสรุปสั้น
- `doc/erd.md` — ความสัมพันธ์ entity

---

## Prerequisites

### 1. Dev server รัน

```bash
cd /Users/pkreang/work/dataflow-wt-main/backend
composer dev
```
- Web: `http://127.0.0.1:8000`
- Vite: `http://localhost:5173`
- Queue listener + log tail รวมอยู่ด้วย

### 2. DB ผ่าน `migrate:fresh --seed` แล้ว

```bash
php artisan migrate:fresh --seed
```
ผลลัพธ์ที่ควรได้ (อ้างอิง 2026-05-23 หลัง LINE Messaging API migration):
- 1 admin user — `admin@example.com` / `password`
- 4 roles — super-admin, admin, viewer, approver
- 25 permissions
- 35 navigation menus
- 47 settings rows (default ครบ — +4 จาก LINE Messaging API keys ที่ commit `6116d82`/`34f891b`)

### 3. เข้าใจ format ของแต่ละ Step

```
### Step N.M — <ชื่อ action>
**ที่:** /<route>  ·  **เมนู:** "<TH label>"  ·  **Permission:** <perm>

**ทำอะไร:**
  1. คลิก "<button>" → modal/หน้าเปิด
  2. กรอก field:
     - <Field A> : "<value ตัวอย่าง>"
  3. คลิก "<Save button>"

**ผลที่ควรเห็น:** UI feedback + DB state ที่เปลี่ยน

**Pitfall:** (ถ้ามี) ข้อควรระวัง

**UAT check:** ☐
```

---

# Phase 0 — เช็คสถานะเริ่มต้น

ก่อนเริ่ม setup จริง ต้องเห็นว่า seed ทำงานครบ ระบบพร้อมรับ admin

### Step 0.1 — Login admin
**ที่:** `/login`  ·  **Permission:** -

**ทำอะไร:**
1. เปิด `http://127.0.0.1:8000/`
2. ระบบ redirect ไปหน้า `/login`
3. กรอก:
   - Email: `admin@example.com`
   - Password: `password`
4. คลิก "เข้าสู่ระบบ" / Login

**ผลที่ควรเห็น:**
- Redirect ไปหน้า home `/`
- Sidebar แสดงเมนูทั้งหมด (มี Settings group ด้านล่าง)
- มุมขวาบนแสดงชื่อ admin + ปุ่ม density toggle

**Pitfall:** ถ้า login ค้างหรือ redirect loop → ตรวจ session config + `EnforcePasswordChange` middleware (admin@example.com seed ไม่ติด `password_change_required` flag — ถ้าติดต้องเช็ค SettingSeeder)

**UAT check:** ☐

### Step 0.2 — ยืนยัน DB count
**ที่:** Terminal

**ทำอะไร:**
```bash
cd /Users/pkreang/work/dataflow-wt-main/backend
php artisan tinker --execute='
echo "users=".\App\Models\User::count().PHP_EOL;
echo "roles=".\Spatie\Permission\Models\Role::count().PHP_EOL;
echo "perms=".\Spatie\Permission\Models\Permission::count().PHP_EOL;
echo "menus=".\App\Models\NavigationMenu::count().PHP_EOL;
echo "settings=".\App\Models\Setting::count().PHP_EOL;'
```

**ผลที่ควรเห็น:**
```
users=1
roles=4
perms=25
menus=35
settings=47
```

**UAT check:** ☐

### Step 0.3 — สำรวจ sidebar
**ที่:** หน้าใดก็ได้หลัง login

**ทำอะไร:** เลื่อนดู sidebar ทั้งซ้าย — ควรเห็น group หลัก ๆ:
- หน้าหลัก / Dashboard
- ผู้ใช้งาน, บทบาท, สิทธิ์ (ไม่อยู่ใน Settings prefix)
- กลุ่ม "ตั้งค่า" (Settings) — ขยายดู 24 leaves: องค์กร, ฝ่าย/แผนก, ตำแหน่ง, นโยบายรหัสผ่าน, การยืนยันตัวตน, ประเภทเอกสาร, ตั้งค่าฟอร์มเอกสาร, รายการอ้างอิง, Workflow, เลขที่เอกสารอัตโนมัติ, การเลือก workflow อนุมัติ, รอบประเมิน KPI, โลโก้/พื้นหลัง, การแจ้งเตือน, สาขา (Branch scoping), จัดการเมนู, ประวัติการใช้งาน, แดชบอร์ด, Webhook ขาออก, Webhook ขาเข้า, ฟอร์มประเมิน, ฯลฯ

**ผลที่ควรเห็น:** เมนูครบ 35 รายการ (ตรงกับ NavigationMenuSeeder)

**UAT check:** ☐

### Step 0.4 — ดูค่า default ของ singleton settings
**ที่:** หลายเมนู (เปิดดูเฉย ๆ ไม่ต้องบันทึก)

**ทำอะไร:**
1. ไป `/settings/password-policy` → เห็น min length=8, require upper/lower/number/special, force change first login=true
2. ไป `/settings/authentication` → เห็น Local enabled, Entra disabled, LDAP disabled, default role=viewer
3. ไป `/settings/branding` → เห็นค่า default (logo placeholder, สี default)
4. ไป `/settings/notifications` → email + Line enabled, ทุก event เปิด

**ผลที่ควรเห็น:** ทุกหน้า singleton เปิดได้ ไม่ error ค่าเป็น default ตาม `SettingSeeder.php`

**Pitfall:** ถ้าหน้าใด **500 error** → migration ขาด หรือ controller ใช้ key ที่ SettingSeeder ไม่ได้ seed — ตรวจ `storage/logs/laravel.log`

**UAT check:** ☐

### Troubleshooting Phase 0

**อาการ:** หน้าจอทั้งหน้ามี dim/overlay สีเทาเข้มครอบ ทั้ง sidebar และเนื้อหา คลิกอะไรไม่ได้ ไม่มี modal ปรากฏ

**ลำดับแก้:**
1. คลิกในพื้นที่สีเทา — backdrop มี `@click="sidebarOpen = false"` ควรปิด overlay
2. กด F5 / Cmd+R refresh หน้า
3. ขยาย browser window ให้กว้าง ≥ 1024px (ออกจาก mobile responsive mode)
4. ถ้าทั้ง 3 ข้อข้างต้นไม่หาย → **ปิด browser ทั้งหมดแล้วเปิดใหม่** (Alpine.js state ค้างใน bfcache / persisted DOM — เคยเจอ 2026-05-22)

**Root cause:** ยังไม่ได้สรุปแน่ — ต้องสืบเพิ่ม (น่าจะเป็น `sidebarOpen` state ค้าง ไม่ re-evaluate `lg:hidden` ตอน viewport ≥ lg) ถ้าเจอซ้ำในสภาพ window กว้าง > 1024px → log ไว้ใน `doc/backlog.md`

---

# Phase 1 — โครงสร้างองค์กร

ลำดับ: Companies → Branches → Departments → Positions
- Branches ต้องมี company ก่อน (FK required: `company_id`)
- Departments + Positions อิสระ (ไม่ผูก company — confirmed จาก migration)
- **company_mode = 'single'** ใน DB → สร้าง company ได้แค่ **1 ตัว** (constraint ใน `CompanyController` ยังไม่มี UI toggle ใน Phase นี้)

### Step 1.1 — สร้าง Company (องค์กร)
**ที่:** `/profile`  ·  **เมนู:** "องค์กร"  ·  **Permission:** `manage profile`

**ทำอะไร:**
1. คลิกเมนู "องค์กร" ใน sidebar → ไปหน้า list (`/profile`)
2. List ว่าง — คลิกปุ่ม **"เพิ่มองค์กร"** (มุมขวาบน, icon +) → ไปหน้า create
3. กรอก field:
   - **รหัสองค์กร (company_code):** `ABC` *(required, max 50)*
   - **ชื่อองค์กร (company_name):** `บริษัท ABC จำกัด` *(required, max 255)*
   - **เลขประจำตัวผู้เสียภาษี (tax_id):** `0123456789012` *(optional)*
   - **ประเภทธุรกิจ (business_type):** `ก่อสร้าง` *(optional)*
   - **ที่อยู่ (structured):** จังหวัด `กรุงเทพมหานคร` → อำเภอ `บางรัก` → ตำบล `สีลม` → zipcode autofill
   - **โทรศัพท์:** `02-123-4567` *(optional)*
   - **อีเมล:** `contact@abc.co.th` *(optional)*
   - **เว็บไซต์:** `https://abc.co.th` *(optional)*
   - **โลโก้:** อัปโหลด (JPEG/PNG/GIF/WebP, max 2MB) *(optional — ข้ามได้)*
4. คลิก **"บันทึก"** (มุมขวาล่าง)

**ผลที่ควรเห็น:**
- Redirect กลับ `/profile` (list)
- Toast / banner สีเขียว: "องค์กรถูกสร้างเรียบร้อย" (key `company.company_created`)
- ตารางแสดง 1 แถว — ABC, บริษัท ABC จำกัด, สถานะ active
- DB: `companies` row +1 (`php artisan tinker --execute='echo \App\Models\Company::count();'` → 1)

**Pitfall:**
- ระบบอยู่ใน **single mode** (`company_mode=single` ใน settings) — สร้างองค์กรที่ 2 จะ error "company.single_mode_limit"
- ที่อยู่ใช้ structured 4-level (จังหวัด/อำเภอ/ตำบล/zipcode) — ไม่มี freetext แล้ว (commit 8c8598b)
- **ไม่มี** ปุ่ม lock address แล้ว (commit 8c8598b)

**UAT check:** ☐

### Step 1.2 — เพิ่ม Branch (สาขา) เพิ่มเติม (**OPTIONAL — ข้ามได้**)
**ที่:** `/profile` → edit company  ·  **Permission:** `manage profile`

**ทำความเข้าใจก่อน (อัปเดต 2026-05-25 หลัง UAT click-through):**
- **company-level data = สำนักงานใหญ่ (HQ) อยู่แล้ว** โดย convention — section header ในหน้า edit เขียนชัดว่า "ข้อมูลองค์กร (สำนักงานใหญ่)" (key `company.company_info_section`)
- **"Branches" section เอาไว้สำหรับสาขาเพิ่มเติม** ที่ไม่ใช่ HQ — เช่น สาขาภูมิภาค, โรงงาน, สำนักงานสาขา ที่มีที่อยู่/เบอร์โทรต่างจาก HQ
- Hint ในหน้า (`company.branches_section_hint`): "ข้อมูลองค์กรด้านบนคือสำนักงานใหญ่ แต่ละสาขาตั้งที่อยู่และเบอร์โทรแยกได้ ผู้ใช้ที่ผูกสาขาจะเห็นข้อมูลติดต่อของสาขานั้นบนฟอร์มเอกสาร ถ้าไม่กรอกจะใช้ที่อยู่สำนักงานใหญ่"
- **ห้ามสร้าง branch ชื่อ "สำนักงานใหญ่" / "HQ" ซ้ำ** — เพราะ company-level เป็น HQ อยู่แล้ว จะกลายเป็นข้อมูลซ้อน

**ถ้าจะ skip:** กดเข้า Step 1.3 ต่อได้เลย — Phase 2 (Users) ผูก user ที่ "ไม่มี branch" (`branch_id=null`) ได้, ระบบจะ fallback ไปใช้ HQ ข้อมูล

**ทำอะไร (ถ้าต้องการทดสอบหรือมีสาขาจริง):**
1. ที่ `/profile` list → คลิก row action "แก้ไข" (icon ปากกา) บนแถว company → ไปหน้า edit
2. เลื่อนลงล่างหน้า edit → เห็น section "สาขา" / "Branches" ใน dashed-border box
3. ในกรอบ dashed กรอก (ใช้ตัวอย่างสาขาจริง เช่น เชียงใหม่/CMI หรือ โรงงานบางบัวทอง/FTY):
   - **รหัสสาขา (branch_code):** `CMI` *(required, ห้ามใช้ HQ)*
   - **ชื่อสาขา (branch_name):** `สาขาเชียงใหม่` *(required, ห้ามใช้ "สำนักงานใหญ่")*
   - **โทรศัพท์:** *(optional)*
   - **ที่อยู่ (structured):** เลือกจังหวัด/อำเภอ/ตำบล (cascade) *(optional — ถ้าไม่กรอก ระบบ fallback ไปใช้ที่อยู่ HQ)*
   - **สถานะ (is_active):** ☑ *(default true)*
4. คลิกปุ่ม **"เพิ่มสาขา"** (ในกรอบ branches)

**ผลที่ควรเห็น (ถ้าทดสอบ):**
- หน้า reload หรือ AJAX เพิ่มแถวสาขาใหม่ในตารางสาขาของ company
- Toast: "สาขาถูกสร้างเรียบร้อย" (key `company.branch_created`)
- DB: `branches` row +1 พร้อม `company_id` ของ company นั้น
- กรอบ dashed สำหรับเพิ่มสาขาใหม่ ว่างพร้อมกรอกอีก

**Pitfall:**
- Branch ต้องมี company (FK required cascadeOnDelete) — ไม่มีปัญหาที่นี่เพราะอยู่ใต้ edit ของ company
- ถ้าจะเพิ่มสาขาที่ 2 → กรอกในกรอบ dashed อีกรอบ ไม่ต้องออกจากหน้า edit
- **ไม่มีปุ่ม "สร้าง HQ"** — เพราะ company = HQ อยู่แล้ว

**UAT check:** ☐ (☑ ทันทีถ้า skip — single-HQ ก็ valid)

### Step 1.3 — สร้าง Departments (ฝ่าย/แผนก)
**ที่:** `/settings/departments`  ·  **เมนู:** "ฝ่าย/แผนก"  ·  **Permission:** `manage_settings` *(super-admin)*

**ทำอะไร:**
1. ไปเมนู "ฝ่าย/แผนก" → list ว่าง
2. คลิกปุ่ม **"เพิ่มแผนก"** (มุมขวาบน) → ไปหน้า create
3. กรอก field:
   - **รหัส (code):** `IT` *(required, max 100, จะถูก auto-uppercase ตอน save)*
   - **ชื่อ (name):** `เทคโนโลยีสารสนเทศ` *(required, max 255)*
   - **คำอธิบาย (description):** `ดูแลระบบ IT ทั้งหมด` *(optional)*
   - **สถานะใช้งาน (is_active):** ☑ *(default true)*
4. คลิก **"บันทึก"**

**ผลที่ควรเห็น:**
- Redirect กลับ list (`/settings/departments`)
- Toast: "สร้างแผนกเรียบร้อย" (key `common.department_created`)
- ตารางแสดง 1 แถว — IT, เทคโนโลยีสารสนเทศ, active
- **ไม่มี** คอลัมน์ "รหัสระบบ / auto_code" ใน list (commit 2a4bd19 ซ่อน)
- DB: `departments` row +1, `code='IT'` (uppercase)

**ทำซ้ำสร้างอีก 2-3 แผนก** (เช่น `HR` → ทรัพยากรบุคคล, `FIN` → การเงิน, `OPS` → ปฏิบัติการ)

**ผูก workflow ให้แผนก:** sidebar Settings → **"แผนก ↔ workflow"** (`/settings/department-workflow-bindings`) — grid matrix สำหรับ bind หลายแผนก × หลาย document_type ในที่เดียว (เปิดเมนูตั้งแต่ 2026-05-25 — ก่อนหน้านี้ `is_active: false`); หรือเข้าผ่านหน้า **edit ของแผนก** มี Workflow Bindings card ขวามือก็ได้

**Pitfall:**
- **code unique globally** — ไม่ใช่ unique per company; ทั้งระบบมี `IT` ได้ตัวเดียว
- code ถูก **auto-uppercase** เสมอ; กรอก `it` → save → DB เก็บ `IT`
- ไม่มี FK ไป company/branch — แผนกเป็น master data ระดับ system

**UAT check:** ☐

### Step 1.4 — สร้าง Positions (ตำแหน่ง)
**ที่:** `/settings/positions`  ·  **เมนู:** "ตำแหน่ง"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู "ตำแหน่ง" → list ว่าง
2. คลิกปุ่ม **"เพิ่มตำแหน่ง"** (มุมขวาบน) → ไปหน้า create
3. กรอก field:
   - **รหัส (code):** `MGR` *(required, max 100, auto-uppercase)*
   - **ชื่อ (name):** `ผู้จัดการ` *(required, max 255)*
   - **คำอธิบาย:** `ระดับผู้จัดการแผนก` *(optional)*
   - **สถานะใช้งาน:** ☑
4. คลิก **"บันทึก"**

**ผลที่ควรเห็น:**
- Redirect กลับ list, toast "บันทึกเรียบร้อย" (key `common.saved`)
- 1 แถวในตาราง: MGR, ผู้จัดการ, active
- **ไม่มี** auto_code column

**ทำซ้ำ:** เพิ่มอีก 2-3 ตำแหน่ง (เช่น `SUP` → หัวหน้างาน / Supervisor, `STAFF` → พนักงาน, `TECH` → ช่างเทคนิค)

**Pitfall:**
- code unique globally + auto-uppercase (เหมือน departments)
- **ลบไม่ได้ถ้ามี user ผูกอยู่** — ใน Phase 2 ถ้าจะลบตำแหน่งต้อง reassign user ก่อน (error `common.cannot_delete_position_has_users`)

**UAT check:** ☐

### ผลรวมหลัง Phase 1

DB state ที่ควรได้:
```
companies = 1
branches = 1 (หรือมากกว่า)
departments = 3-4
positions = 3-4
```

ตรวจด้วย:
```bash
php artisan tinker --execute='
echo "companies=".\App\Models\Company::count().PHP_EOL;
echo "branches=".\App\Models\Branch::count().PHP_EOL;
echo "departments=".\App\Models\Department::count().PHP_EOL;
echo "positions=".\App\Models\Position::count().PHP_EOL;'
```

พร้อมสำหรับ Phase 2 (Users + Roles) → ตอนนี้มี company/branch/dept/position ให้ assign ตอนสร้าง user แล้ว

---

# Phase 2 — Users & Roles

*(จะเขียนหลังจบ Phase 1 — เพื่อให้ตรงกับ UI จริงที่ user เห็น)*

หัวข้อที่จะ cover:
- ทบทวน 4 seeded roles (super-admin, admin, viewer, approver) + permissions ที่ผูก
- สร้าง test users 3 คน (จะใช้ใน Phase ถัด ๆ)
- ทดสอบ admin password reset (commit 4a878b9)
- ทดสอบ login เป็น non-admin → permission gates 403 + menu hide
- หน้า "ดูภาพรวมสิทธิ์" (`/roles/overview` — commit 79de956)

---

# Phase 3 — Master data: Document Types, Lookups, Running Numbers

หลัง Phase 2 ได้ผู้ใช้และบทบาทพร้อมแล้ว ขั้นนี้สร้าง **master data** ที่ฟอร์ม + workflow จะใช้อ้างอิงต่อ — ทำเรียงตามลำดับ Document Types → Lookups → Running Numbers เพราะมี dependency:
- Running Numbers อ้าง `document_type.code`
- Document Forms (Phase 5) อ้างทั้ง document_type + lookup keys

### Step 3.1 — สร้าง Document Types (ประเภทเอกสาร)
**ที่:** `/settings/document-types`  ·  **เมนู:** "ประเภทเอกสาร"  ·  **Permission:** `manage_settings` *(super-admin)*

**ทำอะไร:**
1. ไปเมนู "ประเภทเอกสาร" → list ว่าง
2. คลิกปุ่ม **"เพิ่มประเภทเอกสาร"** (มุมขวาบน) → ไปหน้า create
3. กรอก field:
   - **รหัส (code):** `repair_request` *(required, max 100, regex `[a-z][a-z0-9_]*`)*
   - **ชื่อ EN (label_en):** `Repair Request` *(required, max 255)*
   - **ชื่อ TH (label_th):** `ใบแจ้งซ่อม` *(required, max 255)*
   - **คำอธิบาย (description):** *(optional)*
   - **ไอคอน (icon):** เลือกจาก dropdown (รายการมาจาก `IconCatalog::names()` เช่น `wrench`, `document-text`) *(optional)*
   - **ลำดับ (sort_order):** `0` *(optional, ใช้เรียงในตัวเลือก)*
   - **สถานะใช้งาน (is_active):** ☑ *(default true)*
4. คลิก **"บันทึก"**

**ผลที่ควรเห็น:**
- Redirect กลับ list, toast "บันทึกเรียบร้อย" (`common.saved`)
- ตารางแสดง 1 แถว — repair_request, Repair Request, ใบแจ้งซ่อม
- DB: `document_types` row +1

**ทำซ้ำสร้างอีก 2-3 ประเภท** (เช่น `spare_part_request` → ใบเบิกอะไหล่, `evaluation` → แบบประเมิน, `pm_work_order` → ใบ PM)

**Pitfall:**
- code **auto-lowercase + space→underscore** ตอน submit; กรอก `Repair Request` → DB เก็บ `repair_request`
- code ต้องขึ้นต้นด้วยตัวพิมพ์เล็ก ตามด้วย `[a-z0-9_]` เท่านั้น — กรอก `1foo` หรือ `-bar` จะ fail
- **ลบไม่ได้** ถ้ามี `document_forms` หรือ `approval_workflows` ใช้ code นี้ — error `common.cannot_delete_document_type`
- ใช้ `evaluation` เป็น code สำรองสำหรับฟอร์มประเมิน (Phase 6.8 จะใช้ filter)

**UAT check:** ☐

### Step 3.2 — สร้าง Lookups (รายการอ้างอิง)
**ที่:** `/settings/lookups`  ·  **เมนู:** "รายการอ้างอิง"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู "รายการอ้างอิง" → list อาจมี lookups ที่ seed มา (`is_system=true`)
2. คลิกปุ่ม **"เพิ่มรายการอ้างอิง"** → ไปหน้า create
3. กรอก field หลัก:
   - **คีย์ (key):** `repair_priority` *(required, alpha_dash, max 64, unique, ห้ามชนกับ built-in source key)*
   - **ชื่อ EN (label_en):** `Repair Priority` *(required, max 100)*
   - **ชื่อ TH (label_th):** `ความเร่งด่วน` *(required, max 100)*
   - **คำอธิบาย:** *(optional)*
   - **ต้องการ permission พิเศษ (required_permission):** *(optional — ถ้าใส่ จะ filter ตอน fetch ใน form)*
   - **ลำดับ + สถานะใช้งาน:** ☑
4. เลื่อนลงไป section **"รายการตัวเลือก"** (items):
   - คลิก **"เพิ่ม item"** หลายครั้งให้ครบ 3-4 ตัว
   - แต่ละ row กรอก:
     - **value:** `low`, `medium`, `high`, `critical` *(required, unique within list)*
     - **label_th:** `ต่ำ`, `ปานกลาง`, `สูง`, `วิกฤต`
     - **label_en:** `Low`, `Medium`, `High`, `Critical`
     - **sort_order:** 0, 1, 2, 3
     - **is_active:** ☑
     - **extra (JSON):** *(optional — เช่น `{"color":"red"}` สำหรับ UI badge)*
5. คลิก **"บันทึก"**

**ผลที่ควรเห็น:**
- Redirect กลับ list, toast `common.saved`
- ตารางแสดง 1 แถว — repair_priority, ความเร่งด่วน, 4 items, active
- DB: `lookup_lists` row +1, `lookup_list_items` rows +4

**Pitfall:**
- key **ห้ามชนกับ built-in source** (เช่น `roles`, `departments`, `positions` — รายการเต็มที่ `LookupRegistry::builtInSourceKeys()`) → error `common.lookup_key_reserved`
- key ของ system lookup (`is_system=true`) **แก้ไม่ได้** หลังสร้าง — controller บังคับ immutable
- ลบไม่ได้ถ้า: `is_system=true` (system protected) **หรือ** มี form field type `lookup` ที่ `options.source` = key นี้ → error ระบุชื่อฟอร์มที่อ้าง
- **CSV import:** column header **ต้องมี `value` อย่างน้อย** (จะ default label_th/label_en = value ถ้าไม่ใส่); mode `replace` ลบทั้งหมดก่อน import, `append` ใช้ updateOrCreate (key = `list_id` + `value`)
- export CSV เริ่มด้วย UTF-8 BOM (`\xEF\xBB\xBF`) เพื่อ Excel ไทย

**UAT check:** ☐

### Step 3.3 — สร้าง Running Numbers (เลขที่เอกสารอัตโนมัติ)
**ที่:** `/settings/running-numbers`  ·  **เมนู:** "เลขที่เอกสารอัตโนมัติ"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู "เลขที่เอกสารอัตโนมัติ" → list ว่าง
2. คลิกปุ่ม **"เพิ่ม Running Number"** → ไปหน้า create
3. กรอก field:
   - **ประเภทเอกสาร (document_type):** เลือก `repair_request` จาก dropdown *(required, unique — ดู Pitfall)*
   - **คำนำหน้า (prefix):** `RR-` *(required, max 20)*
   - **จำนวนหลัก (digit_count):** `5` *(required, 1-10)*
   - **โหมด reset (reset_mode):** เลือก `yearly` *(required: none | yearly | monthly)*
   - **รวมปี (include_year):** ☑ → format จะเป็น `RR-2026-00001`
   - **รวมเดือน (include_month):** *(optional)* → `RR-202605-00001` ถ้าเปิด
   - **สถานะใช้งาน:** ☑
4. คลิก **"บันทึก"**

**ผลที่ควรเห็น:**
- Redirect กลับ list, toast `common.saved`
- ตารางแสดง 1 แถว — repair_request, RR-, 5 digits, yearly, last_number=0
- คอลัมน์ **"ใช้โดย"** จะแสดง form ที่จะแชร์ running number lane นี้ (Phase 5 หลังสร้างฟอร์ม)
- DB: `running_number_configs` row +1

**ทำซ้ำ:** สร้างสำหรับ document_type อื่น (1 type = 1 config)

**Pitfall:**
- 1 `document_type` มีได้แค่ 1 config — dropdown "ประเภทเอกสาร" บนหน้า create **ซ่อน type ที่มี config แล้ว** อัตโนมัติ
- **หลายฟอร์มที่ใช้ `document_type` เดียวกัน → แชร์ running number lane** เดียวกัน (ไม่ใช่ per-form) — non-obvious ดู comment ใน controller `index()`
- ปุ่ม **"Reset"** บนแถว: set `last_number=0` + `last_reset_at=วันนี้` — ใช้ตอนต้นปี/เดือนใหม่ (หรือทดสอบ flow); reset_mode ไม่ auto-reset ตอน midnight — runtime ของ workflow จะ check `last_reset_at` ตอน format ref_no
- format ของ ref_no: `prefix[+YYYY[MM]]-NNNN…` ขึ้นกับ include_year/include_month + digit_count

**UAT check:** ☐

### ผลรวมหลัง Phase 3

DB state ที่ควรได้:
```
document_types ≥ 3
lookup_lists ≥ 1 (ที่สร้างเองนอกเหนือจาก seeded)
lookup_list_items ≥ 4
running_number_configs ≥ 1
```

ตรวจด้วย:
```bash
php artisan tinker --execute='
echo "document_types=".\App\Models\DocumentType::count().PHP_EOL;
echo "lookup_lists=".\App\Models\LookupList::count().PHP_EOL;
echo "lookup_items=".\App\Models\LookupListItem::count().PHP_EOL;
echo "running_numbers=".\App\Models\RunningNumberConfig::count().PHP_EOL;'
```

พร้อมสำหรับ Phase 4 (Workflow + Approval Routing) → workflow จะอ้าง document_type ที่สร้างใน 3.1

---

# Phase 4 — Workflow & Approval Routing

ลำดับ: Workflow → Routing mode (per document_type) → Department-Workflow Bindings (เฉพาะ hybrid/department_scoped)
- Workflow ต้องผูกกับ `document_type` ที่สร้างใน Phase 3.1 (FK by string code)
- Routing mode ตัดสินว่าระบบจะหา workflow ตอน submit ฟอร์มยังไง — บันทึกใน `document_types.routing_mode` (ไม่ใช่ตาราง settings)
- Bindings ต้องการ Departments จาก Phase 1.3 + Users assigned to positions (Phase 2.1)

### Step 4.1 — สร้าง Approval Workflow
**ที่:** `/settings/workflow`  ·  **เมนู:** "Workflow"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู "Workflow" → list ว่าง
2. คลิกปุ่ม **"เพิ่ม Workflow"** (มุมขวาบน) → ไปหน้า create
3. กรอก header:
   - **ชื่อ Workflow (name):** `ขั้นตอนอนุมัติการแจ้งซ่อม` *(required, max 255)*
   - **ประเภทเอกสาร (document_type):** เลือก `repair_request` *(required — มาจาก Phase 3.1)*
   - **คำอธิบาย:** *(optional)*
   - **อนุญาตผู้แจ้งเป็นผู้อนุมัติ (allow_requester_as_approver):** ☑ *(default true)*
   - **สถานะใช้งาน (is_active):** ☑
4. เลื่อนลงไป section **"ขั้นตอน (Stages)"**:
   - คลิก **"เพิ่ม Stage"** อย่างน้อย 2 ครั้ง
   - แต่ละ stage กรอก:
     - **ลำดับ (step_no):** `1`, `2`, ... *(required, integer, unique within workflow)*
     - **ชื่อขั้น (name):** `หัวหน้าแผนกอนุมัติ`, `ผู้จัดการอนุมัติ` *(required, max 255)*
     - **ประเภทผู้อนุมัติ (approver_type):** เลือกจาก `role` / `user` / `position`
     - **อ้างอิงผู้อนุมัติ (approver_ref):**
       - ถ้า role: เลือกชื่อ role จาก dropdown (เช่น `approver`)
       - ถ้า user: เลือก user จาก dropdown (ID เก็บเป็น string)
       - ถ้า position: เลือก position จาก dropdown (ID + ระบบจะแสดง list user ใต้ position ให้ดูประกอบ)
     - **จำนวนคนต้องอนุมัติ (min_approvals):** `1` *(required, ≥ 1)*
     - **บังคับลายเซ็น (require_signature):** ☑ ถ้าต้องการให้ approver เซ็นชื่อตอน approve
5. คลิก **"บันทึก"**

**ผลที่ควรเห็น:**
- Redirect กลับ list, toast `common.saved`
- ตารางแสดง 1 แถว — name, document_type, จำนวน stages, active
- DB: `approval_workflows` row +1 + `approval_workflow_stages` rows = จำนวน stages

**Pitfall:**
- step_no **ห้ามซ้ำกัน** ใน workflow เดียว → abort 422 `common.workflow_duplicate_step`
- approver_ref ถูก **validate ว่ามีจริง**: role name ต้องอยู่ใน `roles` table, user id ต้องอยู่ใน `users` table, position id ต้องอยู่ใน `positions` (active หรือ position ที่ใช้ใน stage เดิม)
- **Update workflow → stages ถูกลบทั้งหมดแล้วสร้างใหม่** (delete+recreate) — ใช้ ID `approval_instance_steps` อ้าง stage จะหายตาม **แต่ FK ใช้ `step_no` ไม่ใช่ stage id** ดังนั้น running instances ไม่กระทบ
- **ลบ workflow ไม่ได้** ถ้ามี `approval_instances` (เคยใช้สร้าง flow แล้ว) **หรือ** มี `department_workflow_bindings` ผูกอยู่ → error `common.cannot_delete_workflow`
- **require_signature** propagate ตอน `start()` ของ instance → `approval_instance_steps.require_signature` ใช้ `<x-signature-pad>` ตอน approve (CLAUDE.md §7)

**UAT check:** ☐

### Step 4.2 — ตั้งโหมดการเลือก Workflow (Approval Routing)
**ที่:** `/settings/approval-routing`  ·  **เมนู:** "การเลือก workflow อนุมัติ"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู "การเลือก workflow อนุมัติ" → เห็น list ของ document_types ที่ active (จาก Phase 3.1)
2. แต่ละ row มี radio/select 3 ตัวเลือก:
   - **hybrid** *(default)* — ใช้ binding ของแผนกผู้แจ้งก่อน ถ้าไม่มี fallback ไป workflow ระดับองค์กร
   - **department_scoped** — ใช้ binding ของแผนกเท่านั้น ไม่มี binding = ไม่มี workflow (form submit fail)
   - **organization_wide** — ใช้ workflow ระดับองค์กรเท่านั้น ไม่สน department
3. เลือก mode ของแต่ละ document_type ตามต้องการ
4. คลิก **"บันทึก"**

**ผลที่ควรเห็น:**
- Toast `common.saved`, redirect ที่หน้านี้
- DB: `document_types.routing_mode` ถูก update per row (**ไม่ใช่** ตาราง settings)

**Pitfall:**
- ค่า routing_mode เก็บใน **`document_types.routing_mode`** column ไม่ใช่ `settings` table — ถ้าจะ reset ต้อง update document_types
- ถ้าเลือก `department_scoped` แล้วไม่มี binding ใน Phase 4.3 → submit ฟอร์ม **fail** หาก workflow lookup ไม่เจอ
- `ApprovalFlowService` ใช้ค่านี้ตัดสิน — ดูในโค้ดที่ resolve workflow ตอน start instance

**UAT check:** ☐

### Step 4.3 — ผูก Department ↔ Workflow (สำหรับ hybrid / department_scoped)
**ที่:** `/settings/department-workflow-bindings`  ·  **เมนู:** "แผนก ↔ workflow" *(seeded เป็น `is_active=false` — เข้าผ่าน URL ตรง หรือเปิด menu ใน Phase 6.7)*  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. เปิด URL `/settings/department-workflow-bindings` ตรง (menu อาจซ่อน — ดู NavigationMenuSeeder row id=37 `is_active=false`)
2. หน้าจอแสดง **matrix** — แถว = departments (จาก Phase 1.3), คอลัมน์ = document_types ที่ใช้กับ binding (ดู `WorkflowDocumentTypes::forBindings()`)
3. แต่ละ cell มี dropdown ให้เลือก workflow (filter อัตโนมัติเฉพาะ workflow ที่ `document_type` ตรง cell)
4. เลือก workflow ในเซลล์ที่ต้องการ:
   - **เลือก workflow** → save binding row
   - **เลือก "—" (ค่าว่าง)** → ลบ binding row
5. คลิก **"บันทึก"** (รวมทั้ง matrix ครั้งเดียว)

**ผลที่ควรเห็น:**
- Toast `common.bindings_saved`
- DB: `department_workflow_bindings` rows สอดคล้องกับ matrix (updateOrCreate / delete ตาม cell)

**Pitfall:**
- ระบบ **bulk save** ทั้ง matrix — เซลล์ที่ workflow.document_type ไม่ตรงกับคอลัมน์จะถูก **silently skip** (loop `continue;` ใน `bulkBindWorkflows`)
- ลบ department ไม่ได้ถ้ามี binding อยู่ → error `common.cannot_delete_department` (แต่ถ้าลบ binding ออกก่อน แล้วลบได้ — ถ้ายังไม่มี user ผูกแผนกนั้น)
- เมนูถูก seed `is_active=false` เพราะใช้ทั่วไปไม่บ่อย — ถ้าจะให้ end-user เห็น เปิดใน Phase 6.7 (Menu Manager)

**UAT check:** ☐

### ผลรวมหลัง Phase 4

DB state ที่ควรได้:
```
approval_workflows ≥ 1
approval_workflow_stages ≥ 2 (รวมทุก workflow)
document_types.routing_mode ตั้งค่าครบทุก type
department_workflow_bindings ≥ 1 (ถ้าใช้ hybrid/department_scoped)
```

ตรวจด้วย:
```bash
php artisan tinker --execute='
echo "workflows=".\App\Models\ApprovalWorkflow::count().PHP_EOL;
echo "stages=".\App\Models\ApprovalWorkflowStage::count().PHP_EOL;
echo "bindings=".\App\Models\DepartmentWorkflowBinding::count().PHP_EOL;
foreach (\App\Models\DocumentType::all() as $dt) {
    echo "  ".$dt->code." → ".$dt->routing_mode.PHP_EOL;
}'
```

พร้อมสำหรับ Phase 5 (Document Forms) → ฟอร์มจะอ้าง document_type + workflow ที่สร้างแล้ว

---

# Phase 5 — Document Forms

นี่คือ **หัวใจของระบบ eForm** — สร้างฟอร์มไดนามิกที่ผูกกับ document_type, workflow, running number, และ lookup ที่สร้างใน Phase 3-4.

ลำดับ: สร้างฟอร์ม → กำหนด fields → ตั้ง field-level rules → ตั้ง workflow policy (ถ้ามี range-based)

### Step 5.1 — สร้าง Document Form
**ที่:** `/settings/document-forms`  ·  **เมนู:** "ตั้งค่าฟอร์มเอกสาร"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู "ตั้งค่าฟอร์มเอกสาร" → list มี Search box + paginator
2. คลิกปุ่ม **"เพิ่มฟอร์ม"** (มุมขวาบน) → ไปหน้า create (form builder)
3. กรอก section **"ข้อมูลฟอร์ม"**:
   - **คีย์ฟอร์ม (form_key):** `repair_request_form` *(required, alpha_dash, max 100, unique)*
   - **ชื่อฟอร์ม (name):** `ใบแจ้งซ่อม` *(required, max 255)*
   - **ประเภทเอกสาร (document_type):** เลือก `repair_request` *(required, Rule::exists document_types code where is_active=true)*
   - **คำอธิบาย:** *(optional)*
   - **ตาราง submission (table_name):** `fdata_repair_request` *(required, regex `[a-z][a-z0-9_]*`, max 64, unique)* — ระบบจะสร้างตาราง physical ตามนี้ผ่าน `FormSchemaService::createTable()`
   - **จำนวนคอลัมน์ layout (layout_columns):** เลือก 1, 2, 3, หรือ 4
   - **เปิดใช้งานการประเมิน (evaluation_enabled):** ☑ (ถ้าฟอร์มนี้รับ feedback หลัง close)
   - **สถานะใช้งาน (is_active):** ☑
4. ไป section **"Fields"** → เพิ่ม fields (ดู Step 5.2)
5. คลิก **"บันทึก"**

**ผลที่ควรเห็น:**
- Redirect กลับ list, toast `common.saved`
- ถ้ามี `auto_number` field แต่ไม่มี `RunningNumberConfig` ตรง document_type → toast เพิ่มข้อความ warning `common.document_form_auto_number_no_config` (กลับไปทำ Phase 3.3 ก่อน)
- DB: `document_forms` row +1, `document_form_fields` rows = จำนวน fields, ตาราง physical `fdata_repair_request` ถูกสร้าง
- ดูตารางได้: `php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("fdata_repair_request") ? "yes" : "no";'`

**Pitfall:**
- `table_name` **collision check**: ถ้ามี physical table ชื่อนี้อยู่แล้ว **และ** ไม่ใช่ของฟอร์มอื่นในระบบ → error `validation.document_form.table_name_conflicts_system` (กัน overwrite ตารางระบบเช่น `users`)
- **Update ฟอร์ม → fields ทั้งหมดถูกลบแล้วสร้างใหม่** (delete+recreate); ถ้ามี `submission_table` แล้ว `FormSchemaService::syncTable()` จะ ALTER table (add/drop column ตาม field diff)
- ฟอร์มที่ยังไม่มี dedicated table (legacy) → save ครั้งแรกที่ใส่ `table_name` ระบบสร้างให้ + backfill payload เก่าผ่าน `php artisan forms:backfill-dedicated-table <form_key>` (CLAUDE.md §2)
- **target_document_types** ใช้ได้เฉพาะตอน `document_type='evaluation'` — สำหรับฟอร์มประเมินที่ผูกกับ document_type อื่น (Phase 6.8)
- evaluation_enabled toggle ใช้ตอน close cycle → trigger evaluation flow

**UAT check:** ☐

### Step 5.2 — เพิ่ม Fields (24 field types)
**ที่:** หน้า edit/create form builder

**ทำอะไร:** ในแต่ละ field row คลิก **"เพิ่ม field"** แล้วเลือก type จาก dropdown:

| Type | ใช้ตอนไหน | Options พิเศษ |
|------|-----------|---------------|
| `text` | ข้อความบรรทัดเดียว | placeholder, default_value |
| `textarea` | ข้อความหลายบรรทัด | placeholder |
| `number` | ตัวเลข | decimals (0-8) |
| `currency` | จำนวนเงิน | decimals |
| `date` / `time` / `datetime` | วันเวลา | default_value |
| `email` / `phone` | format validation | placeholder |
| `select` / `radio` | เลือก 1 จาก list | `options_raw` (1 ตัวเลือกต่อบรรทัด) |
| `checkbox` | flag / multi pick | `options_raw` |
| `multi_select` | เลือกหลายค่า | `options_raw` **หรือ** `lookup_source` |
| `lookup` | dropdown จาก master | `lookup_source` (จาก Phase 3.2 หรือ built-in) + `depends_on` + `foreign_key` ถ้า cascading |
| `file` / `multi_file` / `image` | upload | (validation_rules max:N) |
| `signature` | ลายเซ็น (`<x-signature-pad>`) | - |
| `auto_number` | ref_no อัตโนมัติ | ใช้ `RunningNumberConfig` ของ document_type — preview จะแสดงข้าง field |
| `qr_code` | QR code แสดงผล | `qr_options` (template tokens `{ref_no}`, `{id}`, `{url}`, `{date}`, `{field:KEY}`) |
| `formula` | คำนวณ runtime | `expression` (max 500) |
| `table` | ตารางย่อย (matrix) | `table_columns` JSON (max 40 cols, type: text/number/select/checkbox/date/lookup) |
| `group` | repeater (subform) | `group_options` JSON; inner fields จำกัด `GROUP_INNER_FIELD_TYPES` (ไม่มี upload/signature/nested group) |
| `section` | หัวข้อแบ่งส่วน | - (ไม่สร้าง DB column) |
| `page_break` | แบ่งหน้าฟอร์ม | - (ไม่สร้าง DB column) |

แต่ละ field กรอก field หลัก:
- **field_key:** snake_case unique within form (alpha_dash, max 100)
- **label_en / label_th:** ป้ายชื่อ
- **field_type:** เลือกจากรายการข้างต้น
- **is_required / is_searchable / is_readonly:** ☑ ตามต้องการ (is_searchable เฉพาะ type ที่อยู่ใน `DocumentFormField::SEARCHABLE_TYPES`)
- **placeholder / default_value:** optional
- **col_span:** 0-4 (0 = ใช้ค่า layout_columns)

**Pitfall:**
- **field_key reserved**: ห้ามใช้ key ที่อยู่ใน `FormSchemaService::RESERVED_COLUMNS` (เช่น `id`, `created_at`, `user_id`, `status`) **ยกเว้น** field_type อยู่ใน `SKIP_TYPES` (`section`, `auto_number`, `page_break`, `qr_code`) — auto_number ใช้ key `reference_no` ได้เพราะไม่สร้างคอลัมน์
- **lookup cascade**: ตั้ง `depends_on` = field_key ของ lookup parent + `foreign_key` (snake_case `[a-z_]+`) — เด็กจะ filter ตามค่าที่ parent เลือก
- **เพิ่ม field type ใหม่** ต้องแตะ **5 จุด** (CLAUDE.md §10 ตาราง)
- **table_columns:** JSON array, แต่ละ col มี `key` + `type` + ใน TABLE_COLUMN_TYPES; keys ห้ามซ้ำ; max 40 cols

**UAT check:** ☐

### Step 5.3 — Field-level Permissions & Rules
**ที่:** ในแต่ละ field row (expand "Advanced" panel)

**ทำอะไร:**

**3.1 editable_by** (JSON list of tokens) — กำหนดว่าใครแก้ field นี้ได้:
- `requester` — เจ้าของ submission (ตอน draft)
- `step_N` — approver ที่ step N (เช่น `step_1`, `step_2`)
- `user:{id}` — user เฉพาะคน (เช่น `user:5`)
- ค่าว่าง → ใช้ default คือ `requester` only

**3.2 visibility_rules** + **required_rules** (JSON, ใช้ rule engine เดียวกัน):
- 8 operators: `equals`, `not_equals`, `in`, `not_in`, `greater_than`, `less_than`, `is_empty`, `is_not_empty`
- แต่ละ rule: `{field: "<key>", operator: "<op>", value: "<v>"}`
- **visibility ชนะ required เสมอ** (ถ้าซ่อนแล้วไม่ต้อง require)

**3.3 visible_to_departments** (JSON array of department IDs):
- field จะแสดงเฉพาะคนใน department ที่ระบุ
- ค่าว่าง = แสดงทุก department

**3.4 validation_rules** (JSON):
- Laravel-style rules (เช่น `{max: 100, regex: ...}`)

**Pitfall:**
- **JS↔PHP parity**: `evaluateRulesPhp` (server) vs `window.evaluateVisibilityRules` (JS) ต้อง sync — ดู `tests/Feature/EvaluateRulesPhpTest.php` (17 parity cases) (CLAUDE.md §8 ข้อ 16)
- Quirks: `'0'` ไม่ใช่ empty; array values ใน `equals` เช็ค membership; unknown operator → false
- non-owner ที่ใส่ `user:{id}` token เขียน draft ได้ (ผ่าน `DocumentFormSubmissionController::filterPayloadForAssignee()`) แต่ **submit/destroy/return-to-draft ยัง owner-only**

**UAT check:** ☐

### Step 5.4 — Workflow Policy (Range-based selection)
**ที่:** `/settings/document-forms/{form}/policy`  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. จากหน้า edit ฟอร์ม → คลิก **"Workflow Policy"** (หรือไป URL ตรง)
2. กรอก:
   - **กลุ่ม department:** เลือก specific department หรือ `null` (global)
   - **กลุ่ม ranges:** ตั้ง min/max ของ numeric field (เช่น `amount` < 10000 → workflow A, ≥ 10000 → workflow B)
   - **workflow_id per range:** เลือก workflow ที่ใช้ในช่วงนั้น
3. บันทึก

**ผลที่ควรเห็น:** `document_form_workflow_policies` row + `..._ranges` row ตามจำนวน

**Pitfall:**
- Range-based workflow ใช้ตอน routing_mode ของ document_type เป็น `hybrid` หรือเฉพาะกรณี — `ApprovalFlowService` resolve ตอน submit
- ถ้าไม่ใช้ range → ข้าม step นี้ได้

**UAT check:** ☐

### Step 5.5 — Clone + Create Report (optional)
**ที่:** จาก list หน้า `/settings/document-forms`

**ทำอะไร:**
- **Clone:** คลิก row action "Clone" → สร้างฟอร์มใหม่ที่ copy fields + structure (form_key + table_name + name ถูก suffix `_copy`)
- **Create report:** คลิก "สร้างรายงาน" → ระบบสร้าง dashboard อัตโนมัติ 3 widgets (total count, breakdown by status, recent submissions table) ที่ data source `form:<form_key>` → ไปแก้ใน `/settings/dashboards` (Phase 6.2)

**UAT check:** ☐

### ผลรวมหลัง Phase 5

DB state ที่ควรได้:
```
document_forms ≥ 1
document_form_fields ≥ N (ตามจำนวน field ในฟอร์ม)
ตาราง physical fdata_<key> ถูกสร้าง
```

ตรวจด้วย:
```bash
php artisan tinker --execute='
$form = \App\Models\DocumentForm::first();
echo "form_key=".$form->form_key.PHP_EOL;
echo "fields=".$form->fields()->count().PHP_EOL;
echo "submission_table=".$form->submission_table.PHP_EOL;
echo "physical=".(\Illuminate\Support\Facades\Schema::hasTable($form->submission_table) ? "yes" : "no").PHP_EOL;'
```

พร้อมสำหรับ Phase 6 (Operational: KPI / Dashboards / Notifications / Webhooks / Branding / Menu / Evaluation)

---

# Phase 6 — Operational: KPI / Dashboards / Notifications / Webhooks / Branding / Menu

หมวด operational ทำหลังจาก master data + forms พร้อมแล้ว — แต่ละ section อิสระต่อกัน ทำตามลำดับสมเหตุสมผลก็ได้ ไม่บังคับ

### Step 6.1 — KPI Cycles
**ที่:** `/settings/kpi-cycles`  ·  **เมนู:** "รอบประเมิน KPI"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู "รอบประเมิน KPI" → list ว่าง
2. คลิก **"เพิ่มรอบ"** → กรอก:
   - **ชื่อรอบ (name):** `รอบประเมิน Q1 2026` *(required, max 255)*
   - **ฟอร์มประเมิน (form_id):** เลือกฟอร์ม `document_type='evaluation'` *(required, exists document_forms)*
   - **ช่วงเวลา (period_start / period_end):** *(optional, end ≥ start)*
3. คลิก **"บันทึก"** → ระบบสร้างรอบใน **status=draft**, redirect ไปหน้า edit
4. ที่หน้า edit เพิ่ม **assignments**:
   - แต่ละ assignment = pair (evaluee: target, evaluator: evaluator)
   - เลือก user สำหรับ evaluator + target ที่ active
   - บันทึก assignments
5. คลิก **"เปิดรอบ"** (`POST /open`) — `KpiCycleOpener` จะ spawn draft `document_form_submissions` 1 ใบ per assignment (evaluator เป็น owner) + ตั้ง status=open
6. หลังประเมินครบ คลิก **"ปิดรอบ"** (`POST /close`) → status=closed → trigger reporting (Phase 3 ของ KPI)
7. คลิก **"รายงาน"** (`GET /report`) → ดู KPI report

**ผลที่ควรเห็น:**
- DB: `kpi_cycles` row + `kpi_cycle_assignments` rows + draft submissions (หลัง open)
- list แสดง status + ปุ่ม open/close/report ตาม status

**Pitfall:**
- form_id ใช้ `restrictOnDelete` — ลบฟอร์มที่ผูก cycle ไม่ได้
- เปิดรอบแล้ว assignment list **ลบไม่ได้** ตรง ๆ — ต้อง close แล้ว clean up
- Phase 6.8 (Evaluation Form) ต้องสร้างฟอร์มก่อนถึงจะใช้ใน 6.1 ได้

**UAT check:** ☐

### Step 6.2 — Dashboards
**ที่:** `/settings/dashboards`  ·  **เมนู:** "แดชบอร์ด"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู "แดชบอร์ด" → list
2. คลิก **"เพิ่ม Dashboard"** → กรอก:
   - **ชื่อ:** `Repair Request Overview`
   - **คำอธิบาย:** *(optional)*
   - **scope:** admin / user-scoped
3. ใน edit page → เพิ่ม **widgets**:
   - แต่ละ widget เลือก **data source** จาก `DataSourceRegistry` (เช่น `repair_requests`, `equipment`, `spare_parts`, หรือ `form:<form_key>` ที่สร้างจาก Phase 5.5 createReport)
   - เลือก type: `metric` / `chart` (bar/line/pie) / `table`
   - กรอก aggregation: count / sum / avg / group_by
4. คลิก **"บันทึก"** → preview ใน dashboard view

**ผลที่ควรเห็น:** `report_dashboards` + widget rows; ดูจริงที่ `/reports/dashboards/{id}`

**Pitfall:**
- Data source ต้องมีอยู่ใน `App\Support\DataSourceRegistry` — ถ้า source ใหม่ต้องเพิ่มใน registry ก่อน
- widget ของ form data source อ้าง `form:<form_key>` — ถ้าเปลี่ยน form_key dashboard จะพัง

**UAT check:** ☐

### Step 6.3 — Notifications (Email + LINE)
**ที่:** `/settings/notifications`  ·  **เมนู:** "การแจ้งเตือน"  ·  **Permission:** `manage_settings`

**ทำอะไร:**

**6.3.1 Email (SMTP):**
1. เลือก **"ใช้ค่า DB"** (`mail.use_db_settings`) → form unlock
2. กรอก:
   - **mailer:** smtp / log / sendmail / array
   - **smtp_host / smtp_port / smtp_username / smtp_password**
   - **smtp_encryption:** tls / ssl / none
   - **from_address / from_name**
3. คลิก **"บันทึก"** → settings table updated, `ApplyDatabaseMailConfig::apply()` รัน live, cache cleared

**6.3.2 LINE Messaging API:**
1. กรอก **Channel Access Token** (จาก LINE Developers — ดู `doc/line-oa-setup.md` 5 ขั้น)
2. กรอก **LINE Login Channel ID** + **Channel Secret** (ถ้าให้ user link LINE ผ่าน OAuth ที่ `/auth/line/callback`)
3. คลิก **"บันทึก"**
4. **ทดสอบ:** คลิก **"ส่งข้อความทดสอบ"** (`POST /settings/notifications/test-line`)
   - ต้องมี token + user (admin คนนี้) ต้อง link LINE แล้วก่อน (`users.line_user_id` ไม่ว่าง — ทำผ่าน `/myprofile`)
   - error keys: `notifications.line_test_send_no_token`, `notifications.line_test_send_no_user_id`
5. ดูข้อความเข้า LINE ของ admin

**6.3.3 Notification toggles** (10 keys):
- `email_enabled`, `approval_pending_email`, `workflow_approved_email`, `workflow_rejected_email`
- `line_messaging.enabled`, `approval_pending_line`, `workflow_approved_line`, `workflow_rejected_line`
- `stock_low_email`, `stock_low_line`
- ☑ ตามต้องการ; บันทึกพร้อมกัน

**ผลที่ควรเห็น:**
- DB: `settings` rows updated (ตรวจ `Setting::get('mail.smtp_host')`)
- LINE test → ข้อความเข้า OA ที่ admin link ไว้

**Pitfall:**
- `mail.smtp_password_enc` ถูก `encrypt()` ก่อนเก็บ — กรอก password ใหม่เท่านั้นจะ update; เว้นว่าง = ใช้ password เดิม (UI แสดง smtpPasswordConfigured=true)
- LINE Notify (api.notify-bot.line.me) **ถูกยกเลิก 2025-03-31** — ระบบใช้ Messaging API แทน (`api.line.me/v2/bot/message/push`) ตั้งแต่ commit `6116d82`
- การตั้ง LINE Login ครบ — ดู `doc/line-oa-setup.md` 5 ขั้น (ต้องมี LINE Business account จริง)
- user คนที่จะรับ LINE notification ต้อง link OA แล้ว (`users.line_user_id` ไม่ว่าง) — ทำที่ `/myprofile` ปุ่ม "ผูก LINE"

**UAT check:** ☐

### Step 6.4 — Outgoing Webhooks
**ที่:** `/settings/integrations`  ·  **เมนู:** "Webhook ขาออก"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู → list
2. คลิก **"เพิ่ม Webhook"** → กรอก:
   - **ชื่อ + URL** (endpoint ปลายทาง)
   - **events:** เลือกจาก list (เช่น `submission.created`, `approval.completed`)
   - **field allowlist:** จำกัด field ที่ส่งออก (ถ้าเลือก specific form)
   - **secret/header:** สำหรับ HMAC sig (ถ้ามี)
3. บันทึก
4. **ทดสอบ:** ปุ่ม **"Test"** (`POST /settings/integrations/{webhook}/test`) → ส่ง payload ตัวอย่างไป URL

**Pitfall:** หาก endpoint ปลายทางตอบ non-2xx → webhook log แสดง error; outbound retry policy ดู `App\Jobs\DispatchWebhook` (ถ้ามี)

**UAT check:** ☐

### Step 6.5 — Incoming Webhooks
**ที่:** `/settings/inbound-webhooks`  ·  **เมนู:** "Webhook ขาเข้า"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. คลิก **"เพิ่ม Endpoint"** → กรอก:
   - **slug** (path segment, unique)
   - **bind form:** เลือก document_form ที่จะรับ payload เป็น submission
   - **token:** auto-generate (สำหรับ Bearer header)
   - **field mapping:** map payload JSON → form fields
2. บันทึก → endpoint live ที่ `/api/inbound/<slug>` (ดู api routes)
3. **ทดสอบรับ:** ปุ่ม **"Test"** (`POST /settings/inbound-webhooks/{id}/test`) — ใช้ sample payload

**Pitfall:** Form fields ที่ map ต้อง active; ถ้าฟอร์มเปลี่ยน field_key หลังตั้ง mapping → fail แบบ silent (ดู log)

**UAT check:** ☐

### Step 6.6 — Branding (โลโก้/พื้นหลัง)
**ที่:** `/settings/branding`  ·  **เมนู:** "โลโก้และพื้นหลัง"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู → ฟอร์ม singleton
2. อัปโหลด:
   - **logo** (header logo)
   - **login_background** (รูปพื้นหลังหน้า login)
   - **login_illustration** (รูป illustration ข้าง form login)
3. ตั้ง **background_color** (hex)
4. คลิก **"บันทึก"** → settings updated, file ใน `storage/app/public/branding/`

**ผลที่ควรเห็น:** หน้า login + sidebar logo แสดงตามที่อัปโหลด (อาจต้อง refresh ด้วย Ctrl+Shift+R เพื่อ bypass browser cache)

**Pitfall:** ต้อง `php artisan storage:link` ครั้งเดียวก่อน เพื่อให้ public access; CLAUDE.md ไม่ระบุ — ถ้า login bg ไม่ขึ้นให้ตรวจตรงนี้

**UAT check:** ☐

### Step 6.7 — Menu Manager
**ที่:** `/settings/navigation`  ·  **เมนู:** "จัดการเมนู"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู → list 35 menus (จาก seed)
2. **เพิ่ม menu:** คลิก "เพิ่ม" → กรอก label_en + label_th + icon + route + permission + parent_id + sort_order + is_active
3. **แก้ menu:** คลิก row → form เดิม
4. **ลำดับ:** drag rows ใน list (PATCH `/settings/navigation/reorder`)
5. **toggle active:** สวิตช์ใน list (PATCH `/settings/navigation/{id}/toggle`)

**ผลที่ควรเห็น:**
- Sidebar refresh (ผ่าน cache invalidation ที่ model save/delete) — อาจต้อง refresh page เพื่อเห็น
- `navigation_menus` row updated

**Pitfall:**
- **permission column ต้องตรง string** กับ permission ที่ user มี — ถ้าใส่ไม่ตรง menu จะถูกซ่อน (CLAUDE.md §5)
- เมนูที่ permission=`null` = ไม่ล็อกสิทธิ์ที่ menu (อาจถูกจำกัดด้วย super-admin route middleware)
- cache 3600s — ล้างเมื่อ model save/delete (event listener)
- super-admin จะเห็นเมนูบางตัวที่คนอื่นไม่เห็น (เช่น `/settings/*`)

**UAT check:** ☐

### Step 6.8 — Evaluation Form
**ที่:** `/settings/evaluation-form`  ·  **เมนู:** "ฟอร์มประเมิน"  ·  **Permission:** `manage_settings`

**ทำอะไร:**
1. ไปเมนู → list ของฟอร์มประเมิน (filter `document_type='evaluation'`)
2. คลิก **"เพิ่ม"** → redirect ไปหน้า `/settings/document-forms/create` พร้อม preset `document_type=evaluation`
3. สร้างฟอร์มเหมือน Phase 5.1 แต่ document_type=evaluation
4. หลังบันทึก → ฟอร์มจะใช้ใน Phase 6.1 (KPI Cycles)

**Pitfall:**
- หน้านี้เป็นแค่ **listing** ของฟอร์มประเมิน — CRUD จริงผ่าน `/settings/document-forms` route ปกติ
- ตั้ง `evaluation_enabled=true` ใน form หากต้องการให้ลิงก์กับ approval workflow (ตอน close cycle)
- ฟอร์มประเมินมี `target_document_types` (array) เลือกได้ว่าฟอร์มประเมินตัวนี้ใช้ประเมินใคร — เฉพาะเมื่อ document_type=evaluation

**UAT check:** ☐

### ผลรวมหลัง Phase 6

```bash
php artisan tinker --execute='
echo "kpi_cycles=".\App\Models\KpiCycle::count().PHP_EOL;
echo "dashboards=".\App\Models\ReportDashboard::count().PHP_EOL;
echo "outgoing_webhooks=".\App\Models\Webhook::count().PHP_EOL;
echo "incoming_webhooks=".\App\Models\IncomingWebhook::count().PHP_EOL;
echo "navigation_menus=".\App\Models\NavigationMenu::count().PHP_EOL;
echo "mail_use_db=".\App\Models\Setting::get("mail.use_db_settings").PHP_EOL;
echo "line_token_set=".(\App\Models\Setting::get("line_messaging.channel_access_token") ? "yes" : "no").PHP_EOL;'
```

ระบบพร้อมใช้งานครบ — ไป Phase 7 รัน smoke test

---

# Phase 7 — Final smoke + sign-off

ปิดท้ายด้วยการรันชุดทดสอบ + smoke flow end-to-end + cross-cutting check + sign-off

### Step 7.1 — Run automated tests
**ที่:** Terminal in `backend/`

**ทำอะไร:**
```bash
cd /Users/pkreang/work/dataflow-wt-main/backend
composer test    # ควรผ่าน 576/576 (อ้างอิง 2026-05-23)
composer analyse # ควร 0 errors (PHPStan level ใน phpstan.neon)
```

**ผลที่ควรเห็น:**
- `Tests: 576 passed`
- `[OK] No errors`

**Pitfall:**
- ถ้า test fail → ตรวจว่า DB state สอดคล้อง (test ใช้ SQLite in-memory หรือ test DB แยก ดู `phpunit.xml`)
- ถ้า test count ต่างจาก 576 — มี test ใหม่หลัง 2026-05-23; update ตัวเลขนี้ใน manual

**UAT check:** ☐

### Step 7.2 — Smoke flow end-to-end
**ที่:** Browser

**ทำอะไร:**
1. **Login** เป็น admin (`admin@example.com` / `password`)
2. **สร้าง submission** ของ form ที่สร้างใน Phase 5:
   - ไป `/forms` → เลือก `ใบแจ้งซ่อม` → คลิก "สร้างใหม่"
   - กรอก field ที่ required + auto_number field จะ preview ref_no
   - **Save draft** → ดู submission ใน list
3. **Submit** draft → ระบบ:
   - assign workflow ตาม routing_mode + bindings (Phase 4)
   - generate ref_no จาก RunningNumberConfig (Phase 3.3) — ตรวจ format `RR-2026-00001`
   - trigger notification (Phase 6.3) — admin/approver ได้ email/LINE
4. **Login เป็น approver** (test user จาก Phase 2.1, ตำแหน่ง = ที่ผูก stage 1)
5. **Approve** stage 1:
   - ดู submission ใน inbox (`/approvals`)
   - ถ้า `require_signature=true` ที่ stage นี้ → ต้องเซ็นชื่อใน `<x-signature-pad>` ก่อน submit
   - กด **อนุมัติ** + ใส่ comment
6. **Login เป็น approver stage 2** → approve อีกครั้ง (ถ้ามีหลาย stage)
7. หลัง approve ครบทุก stage → status=approved + trigger notification

**ผลที่ควรเห็น:**
- ref_no auto-generated ตาม format Phase 3.3
- workflow log แสดงทุก step + signature ภาพถูกเก็บ (`approved_by[].signature` JSON)
- notification ส่งเข้า email + LINE ของ requester
- DB: `document_form_submissions` row + `fdata_<key>` row + `approval_instances` + `approval_instance_steps` ครบ

**Pitfall:**
- ถ้า workflow ไม่หา → ตรวจ Phase 4.2 (routing_mode) + Phase 4.3 (binding) ก่อน
- LINE notification ไม่เข้า → ดู `users.line_user_id` ของ requester/approver + LINE token Phase 6.3

**UAT check:** ☐

### Step 7.3 — Cross-cutting checks

**7.3.1 Density toggle persist:**
1. คลิก density icon ใน header → toggle comfortable ↔ compact
2. Refresh page → ค่าต้องคงเดิม (อ่านจาก `users.density` column DB > localStorage > 'comfortable')
3. ตรวจ DB: `php artisan tinker --execute='echo \App\Models\User::find(1)->density;'`

**7.3.2 EN/TH language switch:**
1. คลิก language switcher (ปกติบน profile menu)
2. ลองสลับ EN ↔ TH บนหน้าใหม่ ๆ:
   - **KPI Cycles** (`/settings/kpi-cycles`)
   - **Formula field** (ในฟอร์มที่มี field type `formula`)
   - **Send-back action** (commit `5a3f112`) — workflow approver กด "ส่งกลับ" ทั้งไป requester หรือ stage ก่อนหน้า
   - **LINE settings** + LINE link button ที่ `/myprofile`
3. ทุกหน้าควรแสดงภาษาที่ถูกต้อง ไม่มี key หลุด เช่น `notifications.line_test_send_message`

**7.3.3 Permission gates:**
1. Logout admin
2. Login เป็น viewer user (ไม่มี super-admin)
3. ลองเข้า URL ตรง:
   - `/settings/document-forms` → 403 หรือ redirect
   - `/users` → 403
4. Sidebar ต้องซ่อนเมนู settings ทั้งหมด (อาจเห็น dashboard + my forms เท่านั้น)

**UAT check:** ☐

### Step 7.4 — Sign-off Checklist

นับ UAT check ของทุก Phase:

| Phase | Topic | UAT count |
|------:|-------|----------:|
| 0 | สถานะเริ่มต้น | 4 |
| 1 | โครงสร้างองค์กร | 4 |
| 2 | Users & Roles | 6 |
| 3 | Master data | 3 |
| 4 | Workflow & Approval | 3 |
| 5 | Document Forms | 5 |
| 6 | Operational | 8 |
| 7 | Final smoke | 3 |
| **รวม** | | **36** |

**ถ้า ☑ ครบ 36 → ระบบพร้อม production:**
- ☐ คู่มือ admin ผ่านครบทุก Phase
- ☐ ทดสอบ smoke flow end-to-end ไม่มี error
- ☐ Email + LINE notification ทำงานจริง (ถ้าตั้ง)
- ☐ Webhook ขาออก (ถ้ามี) ตอบ 2xx
- ☐ ผู้ใช้งาน 3 บทบาทเทสต์ผ่านได้ตามคาด (admin, approver, requester)

**ลายเซ็น admin:** ____________________  **วันที่:** ____________

---

# ภาคผนวก

## A. FK dependency graph

```
companies ─┬─→ branches (company_id required, cascade)
           │
departments (standalone, no FK)
positions (standalone, no FK)
           │
           ↓
users (all FKs nullable: company_id, branch_id, department_id, position_id)

document_types (standalone)
           │
           ↓
document_forms (document_type matched by code string, not FK)
           │
           ↓
kpi_cycles (form_id required, restrictOnDelete)
           │
           ↓
kpi_cycle_assignments (cycle_id + user_id required)

approval_workflows (document_type matched by code string)
           │
           ↓
approval_workflow_stages (workflow_id required)
           │
           ↓
approval_instances (workflow_id + submission_id)
           │
           ↓
approval_instance_steps (instance_id + step_index)

settings/document-types ← document_form_fields.lookup_list_id (Lookup ref)
running_numbers ← document_form_fields.running_number_id (RN ref)
```

## B. 24 settings menus + classification

| # | TH | EN | Route | Type | Heavy/Smoke |
|--:|----|----|-------|------|:----:|
| 1 | องค์กร | Organizations | /profile | CRUD nested | H |
| 2 | ฝ่าย/แผนก | Departments | /settings/departments | CRUD | S |
| 3 | ตำแหน่ง | Positions | /settings/positions | CRUD | S |
| 4 | ผู้ใช้งาน | Users | /users | CRUD | H |
| 5 | บทบาท | Roles | /roles | CRUD + overview | H |
| 6 | สิทธิ์การเข้าถึง | Permissions | /permissions | LIST | H |
| 7 | นโยบายรหัสผ่าน | Password Policy | /settings/password-policy | SINGLETON | S |
| 8 | การยืนยันตัวตน | Auth & SSO | /settings/authentication | SINGLETON | S |
| 9 | ประเภทเอกสาร | Document Types | /settings/document-types | CRUD | H |
| 10 | ตั้งค่าฟอร์มเอกสาร | Document Forms | /settings/document-forms | CRUD + builder | H |
| 11 | รายการอ้างอิง | Lookups | /settings/lookups | CRUD | S |
| 12 | Workflow | Workflow | /settings/workflow | CRUD nested | S |
| 13 | เลขที่เอกสารอัตโนมัติ | Running Numbers | /settings/running-numbers | CRUD | S |
| 14 | การเลือก workflow อนุมัติ | Approval Routing | /settings/approval-routing | SINGLETON | H |
| 15 | รอบประเมิน KPI | KPI Cycles | /settings/kpi-cycles | CRUD + actions | H |
| 16 | dept↔workflow | Bindings | /settings/department-workflow-bindings | SINGLETON-MATRIX | S |
| 17 | โลโก้/พื้นหลัง | Branding | /settings/branding | SINGLETON | S |
| 18 | การแจ้งเตือน | Notifications | /settings/notifications | SINGLETON | S |
| 19 | สาขา | Branch scoping | /settings/branch-scoping | SINGLETON | S |
| 20 | จัดการเมนู | Menu Manager | /settings/navigation | CRUD + reorder | H |
| 21 | ประวัติการใช้งาน | Activity History | /settings/activity-history | LIST | S |
| 22 | แดชบอร์ด | Dashboards | /settings/dashboards | CRUD + widgets | H |
| 23 | Webhook ขาออก | Outgoing | /settings/integrations | CRUD | H |
| 24 | Webhook ขาเข้า | Incoming | /settings/inbound-webhooks | CRUD | H |
| 25 | ฟอร์มประเมิน | Evaluation Form | /settings/evaluation-form | mixed | H |

## C. เอกสาร UAT อ้างอิง

| ไฟล์ | ใช้เมื่อไหร่ |
|------|-------------|
| `doc/admin-setup-manual.md` *(ไฟล์นี้)* | ติดตั้งระบบใหม่ตั้งแต่ศูนย์ — manual + UAT รวมกัน |
| `doc/uat-by-settings-menu-2026-05-21.md` | UAT เรียงตาม sidebar 24 menus — focus delta merge |
| `doc/uat-integration-merge-2026-05-21.md` | UAT เรียงตาม feature (A-M) — focus 27 commits ก่อน merge |
| `doc/uat-clean-slate-walkthrough.md` | UAT clean-slate ดั้งเดิม — full workflow ทั้ง school + factory |
