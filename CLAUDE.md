# CLAUDE.md

**บทบาทไฟล์นี้:** คอนเท็กซ์ปฏิบัติการหลักสำหรับ AI / ทีม (workspace rules) — ให้ความถูกต้องของ **คำสั่ง, auth/RBAC, เมนู, ข้อควรระวัง** ที่นี่ก่อน เรื่องเล่ายาว โดเมนละเอียด และ checklist ภาษาไทยอยู่ที่ **`Summary.md`** — เมื่อพฤติกรรมระบบเปลี่ยน ควรอัปเดตทั้งสองไฟล์ให้สอดคล้อง

**ผลิตภัณฑ์:** CMMS + eForm แบบไดนามิกบน **Laravel 12** — Web: **Blade + Alpine.js + Tailwind v4** — API JSON: **Sanctum** (`backend/routes/api.php`) — **รันคำสั่ง shell/composer ทั้งหมดจากโฟลเดอร์ `backend/`** — ชื่อที่แสดงในระบบมาจาก **`APP_NAME`** (`backend/config/app.php` / `.env`)

---

## 1. ภาพรวมที่ต้องรู้

| หัวข้อ | ตำแหน่ง |
|--------|---------|
| โค้ดแอป | `backend/app/`, `backend/routes/`, `backend/resources/` |
| ลำดับ seed หลัก | `backend/database/seeders/DatabaseSeeder.php` |
| สเปก API | `doc/api-spec.md` |
| ERD | `doc/erd.md` |
| รายละเอียด seed / demo | `backend/README.md`, `Summary.md` (ส่วน seed) |

---

## 2. คำสั่งที่ใช้บ่อย

```bash
cd backend

composer setup                    # ติดตั้งแพ็กเกจ + migrate + seed
composer dev                      # เว็บเซิร์ฟเวอร์ + queue + Vite พร้อมกัน
composer test                     # รัน PHPUnit ทั้งชุด

php artisan migrate:fresh --seed  # ล้าง DB แล้ว migrate + seed ใหม่ (โปรดักชันอย่าใช้)
php artisan db:seed --class=NavigationMenuSeeder    # หลังแก้แถวเมนูใน NavigationMenuSeeder
php artisan db:seed --class=IndustryTemplateSeeder   # เทมเพลตโรงเรียน eForm เท่านั้น (ไม่รวม CMMS)
php artisan db:seed --class=FactoryCmmsTemplateSeeder # เทมเพลตโรงงาน CMMS แยกต่างหาก
php artisan forms:backfill-dedicated-table <form_key> # migrate payload เก่า → fdata_* (ใช้หลังเปิด dedicated table ทีหลัง)
php artisan test --filter ExampleTest               # ตัวอย่าง: รันเทสเฉพาะคลาส (เปลี่ยนเป็นชื่อคลาสจริง)
```

กระบวนการพิเศษ / ทางเลือก — ดู **`Summary.md`** และ **`backend/README.md`** (เช่น `DevelopmentDemoSeeder`, `RepairApprovalDemoSeeder`, `PurchaseWorkflowSeeder`, `school:workflow-test-users`, `testing:reset-user-layer`)

---

## 3. การยืนยันตัวตน (Auth)

**ไม่ใช่** web guard แบบดีฟอลต์ของ Laravel

1. `POST /login` เรียก login ภายในผ่าน API แล้วเก็บ bearer token ใน session เป็น `api_token`
2. Middleware **`AuthenticateWeb`** (`auth.web`): ไม่มี token → redirect ไป login; โหลด `User` จาก `session('user')['id']` แล้วเรียก **`Auth::setUser($user)`** ทุก request เพื่อให้ `@can()`, Spatie, `$request->user()` ทำงาน
3. Session เก็บ `user`, `user_permissions`, `user.is_super_admin` (ค่า `is_super_admin` ใน session ใช้ **แสดง UI เท่านั้น**)

**โหมดล็อกอิน (ระดับ instance):** Local, Microsoft Entra (OIDC), LDAP — ผู้ใช้แบบ JIT (สร้างเมื่อล็อกอินครั้งแรก) โค้ดอยู่ที่ `app/Services/Auth/`

---

## 4. RBAC (Spatie)

- แพ็กเกจ **Spatie Permission v7.2**, `guard_name: web`
- **บทบาทปกติ (default):** สิทธิ์ผ่าน `role_has_permissions`
- **กำหนดเองต่อคน (custom):** สิทธิ์ตรงที่ user ผ่าน `model_has_permissions` (ไม่ผ่านชั้น role)
- **Super-admin:** คอลัมน์ **`users.is_super_admin`** ใน DB → `Gate::before` ข้ามการเช็ค permission — **ไม่เทียบเท่า**การมี Spatie role ชื่อ `admin` หรือ `super-admin` อย่างเดียว

---

## 5. เมนู (Navigation)

- Sidebar มาจาก DB: **`navigation_menus`** — `NavigationService::getMenus()` กรองตามสตริง permission + กฎ super-admin สำหรับบาง route; แคชต้นไม้ **3600 วินาที** (ล้างเมื่อ model save/delete)
- คอลัมน์ **`permission`:** ต้อง **ตรงทุกตัวอักษร** กับชื่อ permission ที่ user มี; `null` = ไม่ล็อกสิทธิ์ที่เมนู (ยังอาจถูกจำกัดด้วยกฎ super-admin) — **ไม่ได้** สร้างชื่อสิทธิ์อัตโนมัติจากรูปแบบ `module.action`
- **`permission` กั้น route ด้วย ไม่ใช่แค่ซ่อนเมนู:** middleware **`EnforceMenuPermission`** (alias `menu.permission`, อยู่ใน web auth group) เทียบ path ปัจจุบันกับ `navigation_menus.route` (longest-match ผ่าน `NavigationMenu::routeMatchesPath()`) — ถ้าเมนูที่ตรงมี `permission` แล้ว user ไม่มีสิทธิ์นั้น → **403** (super-admin ข้าม); map route→permission แคช key `navigation_route_permissions` (ล้างพร้อม `navigation_menus_tree`). ตั้ง permission ที่เมนูใน `/settings/navigation` = กั้นทั้ง sidebar **และ** route
- ป้ายกำกับ: `label_en` / `label_th` มี fallback จาก `lang/*/common.php` — จัดการเมนูใน UI: **`/settings/navigation`** (เฉพาะ super-admin)
- Reseed แบบกลุ่มจาก PHP: แก้ `NavigationMenuSeeder` แล้ว `php artisan db:seed --class=NavigationMenuSeeder`

---

## 6. สิทธิ์การเข้าถึง (ใช้งานจริง)

- **ชื่อ permission** เป็นสตริงธรรมดา (`user_access.read`, `manage_settings`, `approval.approve` หรือ `module.action`) — แค่สร้างแถวในเมนู Permissions **ยังไม่** ผูก route หรือเมนูจนกว่าโค้ดจะอ้างชื่อเดียวกัน (`@can`, `middleware('permission:…')`, policy, `navigation_menus.permission`)
- **ลบ permission ไม่ได้** ถ้ายังผูกกับ **role** ใดๆ หรือ **model_has_permissions** — UI จะแสดงว่า **กำลังถูกใช้งาน**; role `admin` จาก seed มักได้สิทธิ์ครบ → หลายแถวลบไม่ได้จนกว่าจะปรับ role
- **ฟอร์มเอกสาร:** flow ทั่วไป **`/forms`** ใช้การมองเห็นตามแผนกบน `DocumentForm` และแบบร่างเช็คเจ้าของ — **ไม่ใช่** รายการสร้าง–อ่าน–แก้–ลบเต็มรูปแบบ + สี่สิทธิ์ต่อฟอร์มโดยอัตโนมัติ ยกเว้นจะเพิ่ม controller/route และผูกเช็คเอง (ดูโมดูลเฉพาะ เช่น repair requests)

---

## 7. โดเมนธุรกิจ (สรุปสั้น)

| หัวข้อ | หมายเหตุ |
|--------|----------|
| **องค์กร** | Companies → branches → `users.company_id` / `branch_id` — หัวเอกสาร / lookup ใช้ FK เหล่านี้ |
| **แผนก / ตำแหน่ง** | เลือก workflow (`department_workflow_bindings`, `approver_type: position`) |
| **อนุมัติ** | `approval_workflows` → ขั้น → instances + `approval_instance_steps` — policy/range บนฟอร์ม: `ApprovalFlowService` |
| **CMMS** | อุปกรณ์ + อะไหล่ + movement |
| **ฟอร์มเอกสาร** | `document_forms` → `document_form_fields` (field-level permissions) → `document_form_submissions`; การมองเห็นผ่าน `document_form_departments`. **Field types** 23 แบบ รวม `group` (subform repeater), `page_break`, `qr_code` (template tokens ผ่าน `App\Support\QrTemplateResolver`), `signature`. **Field-level rules:** `editable_by` JSON tokens (`requester` / `step_N` / `user:{id}`), `visibility_rules` + `required_rules` (8 operators เหมือนกัน — visibility ชนะ required เสมอ). **Per-submission editors:** `assigned_editor_user_ids` JSON column ให้ owner/super-admin อนุญาตคนอื่นช่วยแก้ draft (lifecycle ยัง owner-only). **ยื่นแทน (on-behalf):** permission `submission.create_for_others` → เลือกเจ้าของใบตอนสร้าง; `user_id` = เจ้าของ (routing/exclusion/overlap/แจ้งเตือนตามเจ้าของ), `created_by_user_id` = คนกรอก (ได้สิทธิ์ lifecycle + editorRole requester + แจ้งเตือนผลคู่กับเจ้าของ); mobile ยังไม่รองรับ |
| **ลายเซ็น approver** | `approval_workflow_stages.require_signature` toggle ต่อขั้น → propagate ไป `approval_instance_steps.require_signature` ตอน `start()`; `users.signature_path` (URL ของรูปลายเซ็นใน profile, ตามแบบ avatar); `ApprovalFlowService::act($id, $userId, $action, $comment, $signatureImage)` เก็บ signature ใน `approved_by[].signature` (approve) หรือ `signature_image` column (reject) — TEXT bumped เป็น MEDIUMTEXT (16MB); `<x-signature-pad>` component shared ระหว่าง requester signature field กับ approval pad |
| **Password lifecycle** | `EnforcePasswordChange` middleware บังคับเปลี่ยนรหัส; `user_password_histories` เก็บประวัติ; `PasswordLifecycleService` + `PasswordCapabilityService` ควบคุม flow |
| **Dashboard / Reports** | `DataSourceRegistry` กำหนด data sources (repair_requests, equipment, spare_parts ฯลฯ) สำหรับ widget; `DashboardWidgetDataController` ให้ API |
| **Branch scoping** | `navigation_menus` มี branch scoping; `BranchScopingController` จัดการ isolation ตามสาขา |
| **วันหยุด / กะ** | `holidays` (org-wide, `/settings/holidays`, `HolidaySeeder` = วันหยุดไทย 2026) → `App\Support\WorkdayCalculator` (cache `holidays_active_dates` 3600s, bust ที่ Holiday model events) → ฟังก์ชัน `WORKDAYS()` ในฟอร์ม; `shifts` + `user_shift_schedules` (`/settings/shifts`, ผูกกะต่อ user มีช่วงวันที่ + work_days JSON, กันช่วงทับ) — `User::currentShift()`; roster รายวัน/rotation/business-day escalation ยังไม่ทำ (backlog) |
| **ตั้งค่า** | ตาราง `settings` แบบ key-value — หลาย route `/settings/*` ใช้ middleware **`super-admin`** (ค่า DB) |
| **ภาษา** | `en` / `th` — ตรวจ **ทั้ง** `lang/{locale}/` และ `resources/lang/{locale}/` — JS: `lang/en.json`, `lang/th.json` |

---

## 8. ข้อควรระวัง (Gotchas)

1. **`@can` / Spatie:** ใช้ได้น่าเชื่อถือเมื่อ `AuthenticateWeb` เรียก `Auth::setUser()` แล้ว — ไม่งั้นอาจ **เช็คผิดแบบเงียบๆ** (ไม่ error แต่ได้ผลเป็น false)
2. **`<main>`** ใช้ `overflow-auto`. `.table-wrapper` ใช้ **`overflow-x-auto`** สำหรับตารางกว้าง (เช่น forms list-by-form). `<x-row-actions>` dropdown ใช้ `x-teleport="body"` + `@alpinejs/anchor` (`x-anchor.top-end.offset.8` + auto-flip) → escape overflow ทุก ancestor. ถ้าเพิ่ม dropdown ใหม่ในเซลล์ตาราง ต้อง teleport เช่นกัน ไม่งั้นโดน clip
3. **Sidebar:** spacer กว้างเท่าแถบเมนู; `<main>` **ไม่มี** `padding-left` เพิ่มสำหรับ sidebar
4. **แปลภาษา:** ใช้ต้นไม้เดียว `backend/resources/lang/` (Laravel `lang_path()` ของโปรเจกต์ชี้ที่นี่) — ไฟล์ `lang/` ที่เคยอยู่ root ถูกลบเมื่อ 2026-04-20 เพราะเป็น orphan (Laravel ไม่เคยอ่าน) — school vertical overrides อยู่ที่ `resources/lang/verticals/school/{locale}/`
5. **`ExampleTest`:** `GET /` redirect guest ไป `login`
6. **`super-admin` middleware:** ยึด **`is_super_admin` ใน DB เท่านั้น** — flag ใน session ไม่ให้สิทธิ์เข้า route; sidebar ซ่อน `/settings/*` ส่วนใหญ่จากผู้ที่ไม่ใช่ super-admin จริง — ตัวอย่าง API: `/v1/departments`, `/v1/equipment-categories`, `/v1/equipment-locations` → 403 JSON `auth.super_admin_only`
7. **ผู้ใช้ SSO:** มี `auth_provider`, `external_id`, `ldap_dn` — อย่าพึ่งพา `password`
8. **ผู้ใช้:** ใช้ **`first_name` + `last_name`** — อย่าอ้าง `users.name`
9. **`EnforcePasswordChange` middleware:** ถ้า user มี `password_change_required = true` หรือรหัสหมดอายุ จะ **redirect ไปหน้าเปลี่ยนรหัส** ก่อนเข้าหน้าอื่น — API มี `EnforcePasswordChangeForSanctum` คืน 403 JSON; ทดสอบ flow ต้องตั้งค่าฟิลด์เหล่านี้ด้วย
10. **Seeder ที่ถูกลบ:** `CompanySeeder` และ `ReportDashboardSeeder` ไม่มีแล้ว — อย่าอ้างถึง
11. **Breadcrumb:** ใช้ `<x-breadcrumb :items="[...]">` (ที่ `resources/views/components/breadcrumb.blade.php`) เท่านั้น — **render ตาม items ที่ส่งเข้ามา ไม่ auto-prepend "แดชบอร์ด /" อีกแล้ว** (ลบ logic ตั้งแต่ 2026-05-12) เพราะ Dashboard เข้าถึงได้จาก sidebar อยู่แล้วและ "แดชบอร์ด /" ที่หน้า 3+ levels เป็นภาพรก. ถ้าหน้าใหม่ต้องการให้ "Dashboard" เป็น crumb แรก ใส่เองในอาร์เรย์. **ห้าม** เขียน markup manual ซ้ำ. วาง block `@section('breadcrumb') ... @endsection` ต่อหลัง `@section('title')` ใน layout-extending views
12. **Sidebar pin (★):** แต่ละ leaf menu มีปุ่ม ★ ที่ toggle pin ผ่าน `POST /myprofile/pinned-menus/toggle` (`{menu_key: (string) $menu->id}`). State อยู่ที่ Alpine.store(`pinnedMenus`) — เริ่มต้นจาก `window.__PINNED_MENU_IDS__` ที่ `layouts/app.blade.php` inject ไว้. Pinned section ที่ top ของ sidebar ใช้ component เดียวกันแต่ผ่าน `:is-pinned-section="true"` เพื่อซ่อน ★ บนตัวเอง — pinned section refresh เมื่อ navigate หน้าถัดไป (ไม่ re-render instant)
13. **`<x-data-table>` + `<x-per-page-footer>`:** ถ้าใช้ per-page-footer แยก ให้ตั้ง `:disable-pagination="true"` บน `<x-data-table>` กัน double-render paginator links
14. **`editable_by` user tokens:** format `user:{id}` ผูกใน JSON เดียวกับ role tokens — ปลายทางเช็คผ่าน `DocumentFormSubmissionController::filterPayloadForAssignee()` ที่ filter draft writes ฝั่ง server; non-owner ตอแหลผ่าน devtools ก็เขียนได้แค่ field ที่มี token ตัวเอง — submit/destroy/return-to-draft ยัง **owner-only** (`authorizeOwnerOnlyDraft`) เพื่อไม่ให้ปนตอน workflow ดึง requester
15. **QR template tokens:** ใช้ `App\Support\QrTemplateResolver::resolve($template, $submission)` เท่านั้น — รองรับ `{ref_no}`, `{id}`, `{url}`, `{date}`, `{field:KEY}`. Tokens อื่น **คงไว้ตามตัวอักษร** (ไม่ throw error). ลายเซ็นต์ resolve เก็บไว้ที่ `tests/Feature/QrTemplateResolverTest.php` 8 cases
16. **JS↔PHP rule evaluator parity:** `evaluateRulesPhp` (PHP, ใน `DocumentFormSubmissionController`) กับ `window.evaluateVisibilityRules` (JS, ใน `resources/js/app.js`) ต้อง sync กัน — server เป็น authoritative; ถ้าแก้ฝั่งหนึ่ง ต้องแก้อีกฝั่ง + อัปเดต `tests/Feature/EvaluateRulesPhpTest.php` (17 parity cases). **Quirks ที่จด:** `'0'` ไม่ใช่ empty (อยู่กับ DB convention); array values ใน `equals` เช็ค membership; unknown operator → false (fail-safe). **Formula evaluator มี 3 copy:** PHP `app/Support/FormulaEvaluator` (authoritative — `FormulaFields::recompute()` เรียกทุก write ทั้ง web + mobile API) / JS `resources/js/formula-evaluator.js` (web display) / Dart `mobile/lib/core/utils/formula_evaluator.dart` (app display) — แก้ตัวหนึ่งต้อง sync อีกสอง + เทส `FormulaEvaluatorTest.php`, `MobileApiFormulaTest.php`, `mobile/test/formula_evaluator_test.dart` (quirk: DAYS วันที่กลับด้านใช้ค่าสัมบูรณ์แบบ PHP, JS ให้ค่าติดลบ). **WORKDAYS(a,b)** = DAYS ลบวันหยุด active (ไม่ข้ามเสาร์-อาทิตย์ by design) — PHP รับ holidays ผ่าน constructor (`new FormulaEvaluator($dates)` จาก `WorkdayCalculator::activeDates()`), JS อ่าน `window.__HOLIDAYS__` (inject ใน create/edit), Dart รับ param `holidays` (จาก mobile form schema); client ไม่มี list → เท่า DAYS, server ทับตอน save
17. **Density tokens:** vertical spacing classes ใน high-traffic components (data-table, dynamic-field, sidebar-menu, row-actions, breadcrumb container) ใช้ CSS variable เช่น `--cell-pad-y`, `--btn-pad-y`, `--input-pad-y`, `--header-pad-y`, `--field-gap`, `--field-label-gap`, `--menu-item-pad-y`, `--menu-sub-pad-y`, `--card-pad-x/y` — defined ใน `app.css :root` (comfortable defaults) + override ใน `.compact` block. `<html class="compact">` ถูก toggle ผ่าน `Alpine.store('density').toggle()` (header button + profile select). Persistence: `users.density` (DB) > `localStorage` > `'comfortable'`. **เพิ่ม spacing class ใหม่ที่กระทบ vertical rhythm → ใช้ token ก่อน hardcode** (horizontal padding ส่วนใหญ่ไม่อยู่ใน density scope — เพื่อกัน layout reflow)
18. **`auto_code` (HasAutoCode trait):** 17 domain entities (Department/Position/Eq{Cat,Loc}/User/Company/Branch/DocumentType/DocumentForm/Equipment/SparePart/LookupList/ApprovalWorkflow/RunningNumberConfig/ReportDashboard/NavigationMenu/PmPlan) มี `auto_code` column รูปแบบ `PREFIX-NNN` สร้างใน `creating` event โดย `App\Models\Concerns\HasAutoCode`. Trait ใช้ `DB::table()->where('auto_code','like','PREFIX-%')->max()` + 1 — **raw query, ไม่ผ่าน global scope**. ผลพลอย: (ก) `User` ใช้ SoftDeletes → soft-deleted user ยังนับใน max → **ไม่ reuse code** (audit-stable); (ข) ส่วน entity ที่ใช้ hard-delete (อีก 12 ตัว) ถ้าลบ row ที่มี max auto_code ตัวถัดไป **จะ reuse code นั้น** เพราะ max ลดลง. Mass-assignment: trait guard `if (empty($model->auto_code))` — แปลว่า `Model::create(['auto_code' => 'X'])` ตรงๆ **จะ override** ได้ (โดยตั้งใจ — รองรับ data import). ดังนั้นความปลอดภัยจากการ override ผ่าน request ขึ้นกับ controller ไม่รับ `auto_code` ใน validation rules — มี `AutoCodeTest::test_direct_mass_assignment_with_auto_code_does_overwrite_trait` เป็น tripwire ถ้า trait เปลี่ยนพฤติกรรม

---

## 9. ดัชนีเอกสาร

| ไฟล์ | ใช้ทำอะไร |
|------|-----------|
| `Summary.md` | ภาพรวมไทย, seed, checklist — sync กับ Auth/RBAC และข้อควรระวังที่นี่ |
| `doc/api-spec.md` | เส้นทาง REST + middleware สิทธิ์ |
| `doc/erd.md` | ความสัมพันธ์ entity |
| `doc/uat-repair-request.md` | UAT แจ้งซ่อม + workflow |
| `doc/uat-reset-testing-layer.md` | รีเซ็ต user ทดสอบ (`testing:reset-user-layer`) |
| `doc/uat-rbac-permissions.md` | ทดสอบ RBAC อย่างปลอดภัย |
| `doc/backlog.md` | งาน Phase 2+ / out-of-scope ที่คุยแล้วแต่ยังไม่ได้ลุย |
| `doc/example-maintenance-request-form.md` | Playbook ฟอร์มแจ้งซ่อม enterprise-grade (36 fields, 7 sections) — ใช้เป็น reference สำหรับสร้างฟอร์มแจ้งซ่อมใหม่ |
| `doc/system-test-playbook.md` | ขั้นตอนทดสอบทั้งระบบก่อน merge — automated (`composer test`) + static (`composer analyse`/`lint`) + UAT 2 vertical |
| `doc/uat-settings-menus.md` | UAT checklist สำหรับเมนูตั้งค่า 21 รายการ (access + CRUD + visual regression) |
| `doc/uat-clean-slate-walkthrough.md` | UAT แบบ clean-slate — `migrate:fresh --seed` + ป้อนข้อมูลเองทุก entity ตามลำดับ dependency (ใช้ก่อน merge ก้อนใหญ่) |
| `doc/uat-integration-merge-2026-05-21.md` | Delta UAT checklist สำหรับ merge `integration` → `main` (KPI Cycles, formula field, send-back, RBAC, auto-code, mobile, webhooks) |
| `doc/rbac-redesign-spec-2026-05-21.md` | Design spec — รื้อ Spatie → Resource×Action model + time-bound + audit + field-level (V1 6 สัปดาห์ + V2 4 สัปดาห์) |
| `backend/README.md` | Seed, demo user, SSO |

**มาตรฐานทีม (เมนู + list + CRUD + audit):** เมื่อตกลงแล้ว ให้สรุปเป็น playbook ไฟล์เดียวใต้ `doc/` (เช่น `doc/menu-permissions-and-forms.md`) แล้วเพิ่ม **หนึ่งแถว** ในตารางนี้ — อย่าให้ไฟล์นี้ยาวเกินจำเป็น

---

## 10. เมื่อแก้จุดที่ชนกันบ่อย

| การเปลี่ยนแปลง | จำไว้ |
|----------------|--------|
| ลำดับเมนู / seed เมนู | `NavigationMenuSeeder` + `db:seed` หรือหน้าจัดการเมนู + cache จาก model events |
| permission ใหม่ที่ใช้ในโค้ด | เพิ่มใน `PermissionSeeder` (หรือ UI) + มอบให้ role + อาจต้อง `PermissionRegistrar::forgetCachedPermissions()` |
| route API ใหม่ | อัปเดต `doc/api-spec.md` + middleware ใน `routes/api.php` |
| เพิ่ม/แก้ฟิลด์ฟอร์มเอกสาร | ตรวจ field-level permissions ใน `document_form_fields` + `document_form_departments` visibility |
| **เพิ่ม field type ใหม่** | 5 จุดต้องแตะ: (1) `DocumentFormController::allowedFieldTypes()` (2) `FormSchemaService::SKIP_TYPES` ถ้าไม่มี payload column / `addColumn()` match arm ถ้ามี (3) `DocumentFormController::parseOptions()` branch สำหรับ field-specific config (4) `dynamic-field.blade.php` render branch (5) `_form.blade.php` field-type `<select>` option + per-type editor block + `ensureFieldRowId` defaults |
| password policy เปลี่ยน | ตรวจ `PasswordLifecycleService`, `EnforcePasswordChange` middleware, `user_password_histories` |
