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
ผลลัพธ์ที่ควรได้ (อ้างอิงตอนเขียนคู่มือ 2026-05-22):
- 1 admin user — `admin@example.com` / `password`
- 4 roles — super-admin, admin, viewer, approver
- 25 permissions
- 35 navigation menus
- 43 settings rows (default ครบ)

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
settings=43
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

### Step 1.2 — เพิ่ม Branch (สาขา) ใต้ Company
**ที่:** `/profile` → edit company  ·  **Permission:** `manage profile`

**ทำอะไร:**
1. ที่ `/profile` list → คลิก row action "แก้ไข" (icon ปากกา) บนแถว ABC → ไปหน้า edit
2. เลื่อนลงล่างหน้า edit → เห็น section "สาขา" / "Branches" ใน dashed-border box
3. ในกรอบ dashed กรอก:
   - **รหัสสาขา (branch_code):** `HQ` *(required)*
   - **ชื่อสาขา (branch_name):** `สำนักงานใหญ่` *(required)*
   - **โทรศัพท์:** `02-123-4568` *(optional)*
   - **ที่อยู่ (structured):** เลือกจังหวัด/อำเภอ/ตำบล (cascade) *(optional)*
   - **สถานะ (is_active):** ☑ เปิดใช้งาน *(default true)*
4. คลิกปุ่ม **"เพิ่มสาขา"** (ในกรอบ branches)

**ผลที่ควรเห็น:**
- หน้า reload หรือ AJAX เพิ่มแถวสาขาใหม่ในตารางสาขาของ company
- Toast: "สาขาถูกสร้างเรียบร้อย"
- DB: `branches` row +1 พร้อม `company_id=1`
- กรอบ dashed สำหรับเพิ่มสาขาใหม่ ว่างพร้อมกรอกอีก

**Pitfall:**
- Branch ต้องมี company (FK required cascadeOnDelete) — ไม่มีปัญหาที่นี่เพราะอยู่ใต้ edit ของ company
- ถ้าจะเพิ่มสาขาที่ 2 → กรอกในกรอบ dashed อีกรอบ ไม่ต้องออกจากหน้า edit

**UAT check:** ☐

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
- Redirect กลับ list
- Toast: "สร้างแผนกเรียบร้อย" (key `common.department_created`)
- ตารางแสดง 1 แถว — IT, เทคโนโลยีสารสนเทศ, active
- **ไม่มี** คอลัมน์ "รหัสระบบ / auto_code" ใน list (commit 2a4bd19 ซ่อน)
- DB: `departments` row +1, `code='IT'` (uppercase)

**ทำซ้ำสร้างอีก 2-3 แผนก** (เช่น `HR` → ทรัพยากรบุคคล, `FIN` → การเงิน, `OPS` → ปฏิบัติการ)

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

*(จะเขียนหลังจบ Phase 2)*

---

# Phase 4 — Workflow & Approval Routing

*(จะเขียนหลังจบ Phase 3)*

---

# Phase 5 — Document Forms

*(จะเขียนหลังจบ Phase 4)*

---

# Phase 6 — Operational: KPI / Dashboards / Notifications / Webhooks / Branding / Menu

*(จะเขียนหลังจบ Phase 5)*

---

# Phase 7 — Final smoke + sign-off

*(จะเขียนหลังจบ Phase 6)*

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
