# Backlog — งานที่ยังไม่ได้ทำ (เก็บไว้ก่อน)

ไฟล์นี้รวม Phase 2+ / "out of scope" / ideas ที่ผ่านการคุยแล้วแต่ยังไม่ได้ลุย — เรียงตามความเกี่ยวข้องเป็นกลุ่ม

## ~~Reports / Dashboards~~ (เสร็จแล้ว 2026-04-20)

- ~~**Per-form data source ใน `DataSourceRegistry`**~~ — done: auto-generate source `form:{form_key}` ต่อ active DocumentForm, aggregate/group_by/filter มาจาก field metadata, fdata_* → direct SQL columns, cache invalidate on form save
- ~~**"สร้างรายงานจากฟอร์มนี้" shortcut**~~ — done: ปุ่ม "📊 สร้างรายงาน" ใน `/settings/document-forms/{form}/edit` → สร้าง dashboard 3 widgets (count + donut by status + recent table)
- **Excel (.xlsx) export** — CSV พอใช้ได้; xlsx ต้อง `spatie/simple-excel` (~10MB) — DEFERRED ถึงลูกค้าขอ

## ~~Submission actions~~ (เสร็จแล้ว)

- **PDF binary engine** — Phase 1 browser print (`window.print()`); Phase 2 Browsershot/DomPDF — DEFERRED (ต้องเลือก library + setup server)
- ~~**Step-specific approver view**~~ — done: `authorizeView` ตรวจ approval_instance_steps (user/role/position) แทน blanket `approval.approve`
- **Email PDF** — DEFERRED (ต้องรอ PDF engine)
- ~~**Bulk actions**~~ — done: bulk-delete drafts พร้อม Alpine checkbox + toolbar (ownership check เข้มงวด)
- ~~**Activity log per action**~~ — done: `submission_activity_log` table + record on created/updated/submitted/printed/duplicated/deleted + แสดงใน show-submission view

## ~~Misc Lookup Management~~ (เสร็จแล้ว)

- ~~**Cascading UI**~~ — done: parent_id picker ใน items editor (cross-list dropdown) + registry filter by parent_id
- ~~**Bulk CSV import/export**~~ — done: export CSV with UTF-8 BOM + import with replace/append mode + validation
- ~~**Per-list permission**~~ — done: required_permission column + `LookupRegistry::accessibleSources()` + LookupController filters by user permission
- ~~**Migrate built-in 9 ตัวเป็น DB-driven**~~ — **WONTFIX 2026-04-29**: built-in 9 ตัว (user/equipment/company/branch/department/position/spare_part/equipment_category/equipment_location) เป็น **FK reference ไปยัง entity จริง** ไม่ใช่ static enum — `LookupListItem` มีแค่ `value`/`label_en`/`label_th` แทน Eloquent model พร้อม relationships/scoping/display logic (`[code] name — location`) ไม่ได้. Cascading ปัจจุบันใช้ FK จริง (`equipment.equipment_category_id` ฯลฯ) — migrate จะต้อง mirror ลง lookup_list_items ทุกครั้งที่สร้าง User/Equipment/etc. = sync hell. Hybrid registry คือ design ที่ถูกต้อง: static lists → DB, entity references → models.

## ~~Demo / Seed data~~ (เสร็จแล้ว)

- ~~**Pre-enable `is_searchable`**~~ — done: NteqPolymer 5 ฟิลด์ (document_date, priority, equipment_id, problem_type, found_date) + Bodindecha 8 ฟิลด์
- ~~**แยก IndustryTemplateSeeder ออกจาก factory demo**~~ — done: ลบจาก base DatabaseSeeder; เพิ่มใน `switch:school` และ `demo:reset` composer scripts
- ~~**Demo data สำหรับ reports**~~ — done: FactoryDashboardSeeder (metric + donut + bar + table widgets), เรียกใน NteqPolymerDemoSeeder; school dashboard ยังอยู่ แต่เรียกผ่าน BodindechaDemoSeeder

## ~~Navigation / UX polish~~ (บางส่วนเสร็จ)

- ~~**Theme persistence ต่อ user**~~ — done: `users.theme` column + profile dropdown + meta tag → Alpine theme store (server > localStorage > OS)
- ~~**Sidebar pinned favorites**~~ — done: `user_pinned_menus` table + toggle API + pinned section ที่ top ของ sidebar
- ~~**Pin toggle ★ button บน menu items**~~ — done 2026-04-20: `<x-sidebar-pin-button>` component + Alpine `pinnedMenus` store; renders on leaf items + group children (not on pinned-section mirrors); star-solid / star-outline icons via `<x-nav-icon>`
- ~~**Density toggle**~~ — done 2026-04-29: `users.density` column + `Alpine.store('density')` (mirrors theme pattern: server `<meta>` > localStorage > `'comfortable'`); `<html class="compact">` flips CSS variable layer in `app.css` (`:root` comfortable / `.compact` overrides); header button + profile select; @apply rules + dynamic-field/sidebar-menu/row-actions/breadcrumb migrated to tokens; 5 tests in `DensityPreferenceTest`
- ~~**Breadcrumb consistency**~~ — done 2026-04-20: `<x-breadcrumb :items="[...]"` component auto-prepends Home; applied to 98 pages; slash separator, slate palette

## ~~Data model cleanup~~ (เสร็จแล้ว 2026-04-20)

- ~~`users.department` + `users.position` text columns → drop, ใช้ FK relation เท่านั้น~~ — done

## ~~Profile / Security~~ (เสร็จแล้ว)

- ~~Avatar upload~~ — done
- ~~Locale self-service toggle~~ — done
- ~~Notification preferences UI~~ — done (4 events × 2 channels matrix)
- ~~Phone number field~~ — done
- ~~Login history / last_active_at display~~ — done (LoginHistoryRecorder service + /myprofile/login-history page)
- ~~**Active sessions / revoke other devices**~~ — done (extended personal_access_tokens with ip_address/user_agent + `/myprofile/sessions` with per-token revoke + revoke-others)
- ~~**Connected SSO providers display**~~ — done (card on /myprofile showing Microsoft Entra / LDAP / Local + password-change hint + email_verified_at)
- ~~**Personal API tokens**~~ — done (`/myprofile/api-tokens` — create with name + optional expiry + one-time display + revoke)

## งานที่ยังคง DEFERRED (ต้องตัดสินใจเพิ่มเติม)

1. **PDF binary engine** (Browsershot vs DomPDF) — ต้องเลือก library + ยอมรับ Chrome Docker infra หรือ font setup ของ DomPDF
2. **Email PDF** — รอ PDF engine
3. **Excel xlsx export** — ต้อง install `spatie/simple-excel` (~10MB)
4. **Sidebar auto-refresh หลัง navigation manager actions** (added 2026-06-04, Phase 6.7 UAT) — เปลี่ยน menu (reorder/toggle/create/edit/delete) ผ่าน `/settings/navigation` ตอนนี้ DB + cache ถูก update ทันที (`NavigationMenuController` Cache::forget) แต่ sidebar DOM ไม่ re-render → ต้อง F5 manually เพื่อเห็นลำดับใหม่. Improvement: หลัง JSON success ของ reorder/toggle → JS fetch partial `/partials/sidebar` แล้ว swap DOM (ต้องเพิ่ม route + view partial + extract sidebar markup จาก `layouts/app.blade.php`). เหตุผลที่ deferred: ไม่ block UAT, ระบบทำงานปกติ, แค่ UX polish
5. **Inbound webhook → dedicated `fdata_*` table sync gap** (added 2026-06-04, Phase 6.5 UAT) — `InboundController::receive()` (`backend/app/Http/Controllers/Api/InboundController.php:13-50`) บันทึก payload ลง `document_form_submissions.payload` (JSON column legacy) สำเร็จ + update `last_payload` + counter. **แต่ไม่เขียนลง dedicated table `fdata_<form_key>`** → submission row #4 ใน DB มีข้อมูลใน `payload` แต่ row ใน `fdata_repair_request` มีแต่คอลัมน์ระบบ (title/description/report_status ว่าง). ผลกระทบ: query/dashboard/widget ที่อ้างคอลัมน์ใน fdata จะไม่เห็น submission ที่มาจาก webhook inbound. Fix: ใน `receive()` หลัง create submission + ถ้า `$form->hasDedicatedTable()` → `$schemaService->insertRow($form, $filtered, ['user_id' => ..., 'status' => ...])` (reuse `FormSchemaService` insert method ที่ DocumentFormSubmissionController ใช้). เหตุผลที่ deferred: ระบบทำงานปกติ (payload เก็บ), แต่ analytics ขาด — ทำตอนเริ่มใช้ webhook inbound กับ form ที่มี dashboard widget

6. **Mobile ยังไม่รองรับ "ยื่นแทน" (on-behalf)** (added 2026-06-12) — ฟีเจอร์ยื่นเอกสารแทนคนอื่น (`submission.create_for_others` + `created_by_user_id`) ทำเฉพาะ web. Mobile API (`MobileFormController::submit/saveDraft`) ยังตั้ง `user_id = ผู้ login` เสมอ. Fix เมื่อต้องการ: รับ `on_behalf_of_user_id` ใน mobile submit/draft + เช็ค permission เดียวกัน + Flutter เพิ่ม picker ใน form_create_screen (เฉพาะ user ที่มีสิทธิ์ — ส่ง flag มากับ `/mobile/forms/{key}` schema)

---

> เพิ่มได้เรื่อยๆ — PR ที่ pick งานจาก backlog ให้ cross ออก (ใส่ ~~strike~~) แทนการลบแถว เพื่อเก็บ trail ของการตัดสินใจ
