# Data Flow — สรุปโปรเจกต์ (สำหรับ Claude Code / ทีม)

**บทบาทของไฟล์นี้:** คู่มือภาษาไทยแบบ **ยาวและเชิงโดเมน** (โฟลเดอร์, events, seed, checklist) — ใช้คู่กับ **`CLAUDE.md`** ซึ่งเป็น **แหล่งความจริงเชิงปฏิบัติการ (ภาษาไทย)** สำหรับคำสั่ง, ลำดับ Auth + Sanctum, RBAC, เมนู/สิทธิ์, และข้อควรระวัง ที่ Cursor/เครื่องมือ AI มักอ่านเป็นหลัก

เมื่อแก้พฤติกรรมสำคัญ (login, middleware, super-admin, overflow ตาราง) ให้อัปเดต **`CLAUDE.md` ก่อน** แล้วปรับสรุปในไฟล์นี้ให้สอดคล้อง

---

## 1. โปรเจกต์คืออะไร

- **ชื่อผลิตภัณฑ์:** Data Flow — ระบบ **CMMS** (Computerized Maintenance Management System) — ตั้งชื่อแสดงผลได้ที่ `APP_NAME`
- **รูปแบบ:** Laravel 12 backend เดียว รองรับทั้ง
  - **Web UI:** Blade + Alpine.js + Tailwind v4 (ไม่ใช่ SPA)
  - **JSON API:** Sanctum (`routes/api.php`) สำหรับ client / mobile
- **โฟลเดอร์หลักของแอป:** `backend/` — คำสั่งทั้งหมดรันจากที่นี่

---

## 2. คำสั่งที่ใช้บ่อย

รันจาก `backend/` — ชุดคำสั่งอยู่ที่ **`CLAUDE.md`** หัวข้อ **§2 คำสั่งที่ใช้บ่อย**

```bash
cd backend

composer setup          # ติดตั้ง + migrate + seed
composer dev            # serve + queue + vite พร้อมกัน
composer test           # PHPUnit
```

ถ้าไม่ใช้ `composer dev`: รัน `php artisan serve` และ `npm run dev` แยกตามต้องการ

**คำสั่ง seed / migrate เต็ม / รีเซ็ต admin:** ดู **§9** ด้านล่าง และ **`backend/README.md`** (ไม่ซ้ำตารางยาวที่นี่)

หลังแก้ลำดับเมนูใน seeder: `php artisan db:seed --class=NavigationMenuSeeder`

---

## 3. Authentication & RBAC

**ลำดับทางเทคนิค (Session + Sanctum, `AuthenticateWeb`, `@can` / Spatie, super-admin DB vs session):** อ่านที่ **`CLAUDE.md`** ส่วน **§3 การยืนยันตัวตน**, **§4 RBAC**, และ **§8 ข้อควรระวัง** — นั่นคือข้อความที่ควร sync กับโค้ดก่อน

**สรุปภาษาไทย:** แอปไม่ใช้ web guard แบบดั้งเดิม; login เก็บ bearer token ใน session; ทุก request ต้องมี `Auth::setUser()` ผ่าน middleware มิฉะนั้นการเช็คสิทธิ์จะเงียบผิด

### โหมด sign-in (ตั้งค่า instance-wide ใน Settings)

- **Local:** email + password
- **Microsoft Entra (OIDC):** redirect → callback → JIT user
- **LDAP:** bind + search + JIT user

คีย์ใน `settings` (ดู `SettingSeeder`, หน้า **Settings → Authentication & SSO**):

- `auth_local_enabled`, `auth_entra_enabled`, `auth_ldap_enabled`
- `auth_local_super_admin_only`, `auth_default_role`, `auth_password_help_url`
- `auth_directory_group_role_map` — JSON map กลุ่ม directory → Spatie roles (substring match)
- Entra/LDAP host ฯลฯ (รายละเอียดใน settings UI)

**Secrets ใน `.env` เท่านั้น:** `ENTRA_CLIENT_SECRET`, `AUTH_LDAP_BIND_PASSWORD` (ดู `config/services.php`)

**ผู้ใช้จาก directory:** ฟิลด์ `users.auth_provider`, `external_id`, `ldap_dn`; เปลี่ยนรหัสในแอปถูกซ่อนตาม `PasswordCapabilityService`

### RBAC (สั้น)

- **Spatie Permission** (`guard_name`: `web`) — รายละเอียด default vs custom role อยู่ `CLAUDE.md` **§4 RBAC**
- **Super-admin จริง:** คอลัมน์ `users.is_super_admin` + `Gate::before` — อย่าสับสนกับ flag session สำหรับ UI (ดู `CLAUDE.md` **§8 ข้อควรระวัง**)

---

## 4. โดเมนธุรกิจที่ควรรู้

### บริษัท / สาขา / ผู้ใช้

- **`companies`:** ที่อยู่หลัก, โลโก้, tax_id ฯลฯ — แก้ที่ **Companies** (ต้องมีสิทธิ์ `manage profile`)
- **`branches`:** สาขาต่อบริษัท — จัดการในหน้าแก้ไขบริษัท (`CompanyController` branches routes)
- **`users.company_id` / `users.branch_id`:** ใช้ผูกผู้ใช้กับบริษัท/สาขา; ฟอร์มเอกสาร (เช่นแจ้งซ่อม) แสดงหัวกระดาษบริษัท/ที่อยู่จากความสัมพันธ์นี้
- **`users.first_name` / `users.last_name`:** ไม่มี `name` column เดี่ยว — migration แยกแล้ว; อย่าอ้าง `users.name`

### แผนก / ตำแหน่ง

- **`departments`:** แผนก — ใช้ routing approval workflow; `users.department_id` FK
- **`positions`:** ตำแหน่ง (`users.position_id`) — ถ้า workflow stage มี `approver_type: position` ผู้ใช้ที่มี position_id ตรงกันทุกคนสามารถอนุมัติได้

### Approval Workflow

ระบบอนุมัติแบบหลายขั้น:

```
approval_workflows
  └─ approval_workflow_stages   (step_no, approver_type, approver_ref, min_approvals)

department_workflow_bindings    (ผูก department + document_type → workflow)

document_form_workflow_policies  (ผูก form + department → policy)
  └─ document_form_workflow_ranges  (amount-based: min/max → workflow)
```

เมื่อมีการ submit เอกสาร:
1. `ApprovalFlowService` หา workflow ที่เหมาะสม (จาก department binding หรือ amount range)
2. สร้าง **`approval_instances`** record (1 ต่อ submission)
3. สร้าง **`approval_instance_steps`** ต่อ stage (snapshot approver_type/ref ณ เวลา submit)
4. `current_step_no` เดิน forward ตาม approval จนถึงขั้นสุดท้าย → status = `approved`

Logic หลักใน `app/Services/ApprovalFlowService.php`

### Equipment / CMMS

- **`equipment_categories`** — ประเภทอุปกรณ์ (code unique)
- **`equipment_locations`** — ตำแหน่งติดตั้ง (building, floor, zone)
- **`equipment`** — ทะเบียนอุปกรณ์ (FK → category, location, company, branch); มี `specifications` JSON, `installed_date`, `warranty_expiry`

### อะไหล่ (Spare Parts)

- **`spare_parts`** — stock อะไหล่ (`current_stock`, `min_stock`, `unit_cost`)
- **`spare_part_transactions`** — ประวัติ stock movement (`receive/issue/adjust/return`); polymorphic `reference_type/id`
- **`spare_part_requisition_items`** — รายการเบิกอะไหล่ ผูกกับ `approval_instances`

### จัดซื้อ (Procurement)

- **`purchase_request_items`** / **`purchase_order_items`** — รายการสินค้า ผูกกับ `approval_instances`; ไม่มีตาราง header แยก (ใช้ `approval_instances` เป็น header)

### ฟอร์มเอกสาร (Document Forms)

- **`document_forms`** → **`document_form_fields`** (รองรับ field-level permissions) → **`document_form_submissions`** (ข้อมูลที่กรอก)
- **`document_form_departments`** — ควบคุมการมองเห็นฟอร์มตามแผนก
- **`document_form_workflow_policies`** → **`document_form_workflow_ranges`** — ผูก form + department → workflow ตาม amount range

### Password Lifecycle

- ฟิลด์ user: `password_change_required`, `password_expires_at`, `password_last_changed_at`
- **`user_password_histories`** — เก็บประวัติรหัสผ่านเพื่อป้องกันการใช้ซ้ำ
- **`EnforcePasswordChange`** middleware (web) / **`EnforcePasswordChangeForSanctum`** (API) — บังคับเปลี่ยนรหัสก่อนเข้าหน้าอื่น
- Logic ใน `app/Services/Auth/PasswordLifecycleService.php` + `PasswordCapabilityService.php`
- ตั้งค่านโยบายรหัสผ่านในหน้า Settings (key-value ใน `settings`)

### Dashboard / Reports

- **`DataSourceRegistry`** (`app/Support/DataSourceRegistry.php`) — กำหนด data sources ที่ query ได้ (repair_requests, equipment, spare_parts ฯลฯ) พร้อม fields, aggregations, grouping, filtering, date ranges
- **`DashboardWidgetDataController`** — API endpoint สำหรับ widget ดึงข้อมูลตาม data source ที่เลือก

### Branch Scoping

- **`navigation_menus`** รองรับ branch scoping — เมนูบางรายการแสดงเฉพาะสาขาที่กำหนด
- **`BranchScopingController`** — จัดการ isolation ข้อมูลตามสาขาของผู้ใช้

### ปฏิทินวันหยุด & ตารางกะ (เพิ่ม 2026-06-12)

- **ปฏิทินวันหยุด** (`/settings/holidays`, org-wide ชุดเดียว): ตาราง `holidays` → `App\Support\WorkdayCalculator` → ฟังก์ชัน **`WORKDAYS(date_from, date_to)`** ในฟอร์ม = จำนวนวันหักวันหยุด (ไม่หักเสาร์-อาทิตย์ by design) — ฟอร์มใบลาทุกตัวเปลี่ยนมาใช้แล้ว; `HolidaySeeder` ใส่วันหยุดไทย 2026 ให้เริ่มต้น
- **ตารางกะ** (`/settings/shifts`): ทะเบียนกะ (รองรับกะข้ามคืน) + มอบหมายกะต่อ user แบบช่วงวันที่ + วันทำงานต่อสัปดาห์ — `User::currentShift()` แสดงในหน้า Users; roster รายวัน/หมุนเวียนยังเป็น backlog

### Navigation (sidebar)

- ข้อมูลจากตาราง **`navigation_menus`**
- **`NavigationService::getMenus($permissions, $isSuperAdmin)`** — cache tree 1 ชม.; filter ตาม permission ของเมนู
- View composer ใน `AppServiceProvider` ส่ง **`$navigationMenus`** เข้า `layouts.app`
- แก้ลำดับ/โครงสร้างใน **`NavigationMenuSeeder`** แล้วรัน `db:seed --class=NavigationMenuSeeder`

### Layout / UI ที่แก้ล่าสุด (อย่าทำพัง)

แผน migration UI ระดับทั้งแอป: `docs/superpowers/plans/2026-04-09-full-ui-redesign.md` — backlog UX/a11y: `docs/superpowers/specs/2026-04-12-ux-ui-fixes.md`

- **Sidebar vs main:** เคยซ้อน spacer + `padding-left` บน main ทำให้ช่องว่างคู่ — ตอนนี้ใช้แค่ **spacer** กว้างเท่า sidebar; main **ไม่**ใส่ `sidebar-main-expanded` padding
- **`main` ใน layout:** `class="p-6 overflow-auto flex-1"` — ถ้าห่อตารางด้วย **`overflow-hidden`** จะ **ตัด dropdown** (เมนู ⋮ แก้ไข/ลบ) ให้ใช้ **`overflow-visible`** บนการ์ดตารางที่มีเมนูแบบ absolute (สอดคล้อง `CLAUDE.md` §8)
- หน้า **รายการบริษัท** จัดสไตล์ให้ใกล้เคียงรายการผู้ใช้ (หัวข้อ, ตาราง, แถวกระชับ, เมนู actions)

### Settings (key-value)

- โมเดล **`Setting`** + cache; นโยบายรหัสผ่าน, branding, auth, approval routing ฯลฯ

---

## 5. โครงสร้างโฟลเดอร์สำคัญ

```
backend/
├── app/Http/Controllers/Api/     # API Sanctum
├── app/Http/Controllers/Web/     # Blade (รวม BranchScopingController, DocumentFormSubmissionController, PasswordResetController)
├── app/Http/Middleware/
│   ├── AuthenticateWeb.php
│   ├── EnforcePasswordChange.php       # บังคับเปลี่ยนรหัส (web)
│   ├── EnforcePasswordChangeForSanctum.php  # บังคับเปลี่ยนรหัส (API)
│   ├── ForceRequestUrl.php
│   ├── SetApiLocale.php
│   ├── SetLocale.php
│   └── SuperAdminOnly.php
├── app/Services/
│   ├── NavigationService.php
│   ├── ApprovalFlowService.php
│   ├── Auth/                     # AuthModeService, EntraOAuthService, LdapAuthService,
│   │                             # PasswordLifecycleService, PasswordCapabilityService, ...
│   └── ...
├── app/Support/
│   └── DataSourceRegistry.php    # Registry สำหรับ dashboard data sources
├── app/Models/
├── database/migrations/
├── database/seeders/
│   └── DatabaseSeeder.php        # ลำดับ: Permission → RolePermission → Setting →
│                                 # NavigationMenu → DocumentType → PositionDemo →
│                                 # IndustryTemplate → DocumentForm → Dashboard
├── resources/views/
│   ├── layouts/app.blade.php
│   ├── companies/
│   └── users/
├── resources/lang/               # แปลหลัก (en/th) — มีบางไฟล์ซ้ำใน lang/
├── routes/web.php
└── routes/api.php
```

---

## 6. Middleware / การอนุญาต route (web)

- **`auth.web`** — ต้อง login (session token)
- **`super-admin`** — เฉพาะ super-admin (หน้า settings หลายอย่าง)
- **`permission:name`** — Spatie

ตัวอย่าง: `companies` resource อยู่ใต้ `auth.web`; การแก้ไข/ลบบริษัทเช็ค **`manage profile`** ใน controller/view

---

## 7. เอกสารอ้างอิงใน repo

| ไฟล์ | เนื้อหา |
|------|---------|
| `CLAUDE.md` | **คอนเท็กซ์หลัก (ไทย)** — คำสั่ง, Auth/RBAC, เมนู/สิทธิ์, ข้อควรระวัง, ดัชนีเอกสาร |
| `doc/api-spec.md` | API endpoints + permission matrix |
| `doc/erd.md` | ERD / ตารางหลัก |
| `doc/uat-repair-request.md` | UAT แจ้งซ่อม: login → master → workflow (เริ่มจาก flow นี้) |
| `doc/uat-reset-testing-layer.md` | รีเซ็ตชั้นทดสอบผู้ใช้ เก็บบริษัท/ฝ่าย/ตำแหน่ง — `php artisan testing:reset-user-layer` |
| `doc/uat-rbac-permissions.md` | ทดสอบสิทธิ์อย่างปลอดภัย — ห้ามลบ permissions ทั้งตาราง; ทางเลือก fresh+seed / reset user |
| `backend/README.md` | seed, demo users, auth/SSO, navigation note |
| `docs/superpowers/plans/2026-04-09-full-ui-redesign.md` | แผน migration Blade + design tokens |
| `docs/superpowers/specs/2026-04-12-ux-ui-fixes.md` | สเปก UX / accessibility แยกตามลำดับความสำคัญ |

---

## 8. Events & Notifications

ไม่มี Queue Jobs แยกต่างหาก — ใช้ Laravel Events + Listeners ส่ง notification ผ่าน queue:

| Event | Listeners | Notification |
|-------|-----------|-------------|
| `Approval\WorkflowStarted` | `SendApprovalPendingNotification` | `ApprovalPendingNotification` → แจ้งผู้ต้องอนุมัติ |
| `Approval\WorkflowStepAdvanced` | `SendPartialApprovalNotification` | แจ้งเมื่อผ่านขั้นกลาง |
| `Approval\WorkflowCompleted` | `SendWorkflowOutcomeNotification` | `WorkflowApprovedNotification` / `WorkflowRejectedNotification` → แจ้ง requester |
| `SparePartStockLow` | `SendStockLowNotification` | `StockLowNotification` → แจ้ง stock ต่ำกว่า min |

**Channels:** `database` (ตาราง `notifications`) + `mail` + LINE Notify (ถ้า user มี `line_notify_token`)

User ตั้งค่า channel preference ต่อ event_type ได้ในตาราง `notification_preferences`

`composer dev` รัน queue worker คู่กับ server — **อย่าลืมรัน queue** ถ้าทดสอบ notification

## 9. Seed Data & Demo Users

ดูรายละเอียดใน **`backend/README.md`** (ส่วน Foundation data และ Demo users) สำหรับ:
- ลำดับ seeder และ content ของแต่ละ seeder
- Demo user accounts (`requester@example.com`, `approver@example.com`, `admin@example.com`)
- Industry templates (CMMS โรงงาน vs eForm โรงเรียน)
- คำสั่ง reset admin password

**คำสั่งหลัก:**

```bash
composer setup                                              # full seed
php artisan db:seed --class=IndustryTemplateSeeder         # เทมเพลตโรงเรียน eForm เท่านั้น
php artisan db:seed --class=FactoryCmmsTemplateSeeder      # เทมเพลตโรงงาน CMMS (แยกต่างหาก)
php artisan db:seed --class=DevelopmentDemoSeeder          # demo users (eForm โรงเรียน)
php artisan db:seed --class=RepairApprovalDemoSeeder       # demo users (CMMS repair)
php artisan db:seed --class=PurchaseWorkflowSeeder         # demo workflow จัดซื้อ
php artisan user:reset-bootstrap-admin                     # reset admin@example.com
```

## 10. การทดสอบ & ข้อจำกัด

- `php artisan test` — **Feature ExampleTest** ตรวจว่า `GET /` redirect ไปหน้า login
- มี Unit test **`DirectoryGroupRoleMapperTest`** สำหรับ group→role mapping

---

## 11. Checklist เวลาแก้ฟีเจอร์

1. Web ที่ใช้ `@can` ต้องแน่ใจว่า **`AuthenticateWeb` ตั้ง Auth user** แล้ว (อย่าให้มีแค่ session โดยไม่มี User บน guard)
2. Dropdown ใน `<main class="overflow-auto">` + การ์ดตาราง: อย่าใช้ **`overflow-hidden`** บนตัวห่อที่ตัดเมนู
3. แก้เมนู sidebar: อัปเดต seeder + รัน **`NavigationMenuSeeder`**
4. แปล: ตรวจทั้ง **`resources/lang`** และ **`lang`** ถ้ามีคีย์ซ้ำ
5. Auth directory: อย่าลืม scope Entra **`GroupMember.Read.All`** ถ้าใช้ group mapping (ดู README)
6. ทดสอบ login flow: ถ้า user มี `password_change_required` หรือรหัสหมดอายุ — `EnforcePasswordChange` จะ redirect ก่อนเข้าหน้าอื่น
7. ฟอร์มเอกสาร: ตรวจ field-level permissions ใน `document_form_fields` + visibility ผ่าน `document_form_departments`
8. **Seeder ที่ถูกลบ:** `CompanySeeder` และ `ReportDashboardSeeder` ไม่มีแล้ว — อย่าอ้างถึง
9. **Breadcrumb:** หน้าใหม่ต้องใส่ `@section('breadcrumb') <x-breadcrumb :items="[...]" /> @endsection` ต่อหลัง `@section('title')` — component อัตโนมัติ prepend Home, ใช้ `/` separator, รองรับ dark mode. ห้ามเขียน markup เอง
10. **Sidebar pin (★):** ปุ่ม ★ บน leaf menu toggle pin ผ่าน `POST /myprofile/pinned-menus/toggle` (`{menu_key: (string) $menu->id}`). State อยู่ที่ `Alpine.store('pinnedMenus')` — bootstrap จาก `window.__PINNED_MENU_IDS__` ที่ layout inject. Pinned section ที่ top ของ sidebar ใช้ `<x-sidebar-menu :is-pinned-section="true">` เพื่อซ่อน ★ บน mirror. Pinned list refresh บน navigation ถัดไป (ไม่ instant reactive)

---

## 12. เวอร์ชัน / config

- Laravel 12, Vite 7, Tailwind 4, Alpine 3, Spatie Permission v7.2, Sanctum
- Locale: `th`, `en` — middleware `SetLocale`, สลับที่ header

---

*อัปเดตล่าสุด: 2026-04-20 — breadcrumb component + sidebar pin toggle + UI migration pass*
