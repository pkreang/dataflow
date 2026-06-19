# Org Model Consolidation Spec — org_units อย่างเดียว เลิก departments

**Branch:** `feature/org-model-consolidation` (แยกจาก demo track บน main)
**วิธี:** strangler — `composer test` เขียวทุกเฟส, **ห้าม big-bang**
**สร้าง:** 2026-06-18

## เป้าหมาย / ทำไม

ระบบมี 2 โมเดลองค์กรซ้ำซ้อน: `departments` (flat — คุม form visibility + เลือก workflow) กับ `org_units` (tree — routing ตามสายบังคับบัญชา). "ฝ่ายบุคคล" ต้องพิมพ์ 2 ที่. ตลาด SMB ใช้ **org tree เดียว** (department = node ที่มี head). โปรเจกต์นี้รวมให้เหลือ `org_units` ตัวเดียว: ทุก `department_id` → `org_unit_id`, ทิ้งตาราง `departments`.

## Surface (จาก 3 explorer)

**Schema — `department_id`:** users(*มี org_unit_id แล้ว), approval_instances, document_form_submissions, document_form_workflow_policies(unique [form,dept,pos]), **+ ทุก `fdata_*`** (`FormSchemaService` RESERVED_COLUMNS+createTable). pivot/binding: `document_form_departments`[form,dept], `department_workflow_bindings`[dept,doc_type]. config: `DocumentFormField.visible_to_departments`(JSON). **ไม่มี bridge departments↔org_units.**

**Logic readers (~33 ไฟล์):** ApprovalFlowService (resolveWorkflowId/resolveDepartmentBinding/start/previewWorkflow; policy priority pos>dept>global) · DocumentForm::scopeVisibleToUser+departments() · DocumentFormSubmissionController (visibility gate / set-on-create / field visibility / print) · models DocumentFormSubmission/ApprovalInstance/WorkflowPolicy/Binding · DataSourceRegistry+DashboardWidgetDataController · controllers PurchaseRequest/RepairRequest/SpareParts/Maintenance/Evaluation/MobileFormController · UserController+Auth session · BackfillDedicatedTableCommand.

**Rewrite/delete:** seeders NteqPolymerDemoSeeder(674L)+BodindechaDemoSeeder(400L) CRITICAL + UatDemoSeeder + ~25 template + DepartmentSeeder(ลบ) · tests DepartmentsCrudTest(ลบ)+~38 fixture(search-replace)+behavior · views settings/departments/(ลบ)+selectors · DepartmentController web+api(ลบ)+18 routes · NavigationMenu entry.

## เฟส (แต่ละเฟส = commit, เทสต์เขียว)

### Phase 0 — Bridge + additive schema (ไม่เปลี่ยนพฤติกรรม) ← เริ่มตรงนี้
- migration เพิ่ม `org_unit_id` nullable FK: approval_instances, document_form_submissions, document_form_workflow_policies
- migration สร้าง `document_form_org_units` (pivot) + `org_unit_workflow_bindings` + model `OrgUnitWorkflowBinding`
- `FormSchemaService`: เพิ่ม `org_unit_id` ใน RESERVED_COLUMNS + createTable
- bridge: เพิ่ม `org_unit_id` ใน `departments`
- backfill: `*.department_id` → `*.org_unit_id` ผ่าน bridge (migration/command)
- models: เพิ่ม `org_unit_id` fillable + `orgUnit()` relation (คู่กับ department())
- ✅ ไม่มีใครอ่าน org_unit_id → เทสต์เขียวยกชุด

### Phase 1 — Dual-write ✅ (commit ถัดจาก cdb349b)
ทุกจุด create per-submission/per-instance ที่ใส่ department_id → เขียน org_unit_id คู่กัน. ยังอ่าน department_id. 730 เทสต์เขียว (+4 `DualWriteOrgUnitTest`).

**กฎ agreement:** org_unit_id ต้องมาจาก entity เดียวกับ department_id ของแถวนั้น (ไม่งั้น Phase 2 reader จะ route/visibility ผิดเงียบๆ).
- per-user sites (submission create/duplicate/evaluation, mobile submit/saveDraft, restore-copy): dept+org_unit จาก user/owner เดียวกัน → ตรงโดยปริยาย.
- `ApprovalFlowService::start()`: เพิ่ม param `?int $orgUnitId = null` (ท้าย signature — positional callers ไม่กระทบ). ถ้า `departmentId === null` → ดึงทั้ง dept+org_unit จาก requester. เขียน `org_unit_id = $orgUnitId ?? OrgUnit::idForDepartment($instanceDepartmentId)`.
- 4 CMMS callers (SpareParts/Repair/PurchaseRequest/Maintenance) ส่ง `$validated['department_id']` ของ**เอกสาร** (อาจไม่ใช่ของผู้ยื่น) → ส่ง `orgUnitId: OrgUnit::idForDepartment(...)` คู่กัน. PurchaseOrder ส่ง null → default จาก requester.
- bridge helper `OrgUnit::idForDepartment(?int): ?int` อ่าน `departments.org_unit_id` — **คืน null จนกว่า bridge จะถูก populate (Phase 3 re-seed)**. 4 CMMS rows จึงได้ org_unit=null ชั่วคราว (consistent-by-construction).
- session: เพิ่ม `org_unit_id` ใน web/api AuthController + test trait.

**เลื่อนไป Phase 3:** config policy/binding dual-write (DocumentFormWorkflowPolicyController/SettingController/DepartmentController binding) — bridge ยังว่าง เขียนได้แค่ null ไม่มีประโยชน์; จะ populate ตอน UI เปลี่ยนเป็น org_unit selector + re-seed.

### Phase 2 — ย้าย reader ทีละ subsystem (commit ละตัว)

**2a workflow resolution ✅ (commit 513bfb9 + demo seeder)** — `ApprovalFlowService` อ่าน org_unit ก่อน dept (fallback):
- `resolveWorkflowId` +param `orgUnitId`; policy priority **position > org_unit > department > global** (org match + orderBy)
- `resolveOrgUnitBindingWorkflowId()`: org_unit binding ชนะ dept binding ใน `start()`/`previewWorkflow()`
- `previewWorkflow` +param `orgUnitId` (resolve จาก requester parity กับ start); `resolveOverridePicker` thread org_unit (create=session/user, editDraft=submission)
- non-breaking: org config ว่าง → fallback dept. tests: `OrgUnitWorkflowResolutionTest` (binding/policy/org-beats-dept/dept-fallback).
- **demo data authored** (`OrgStructureDemoSeeder`, `db:seed --class=OrgStructureDemoSeeder`): demo มีแค่ Company/Dept/User → สร้าง org tree mirror (root + 8 ฝ่าย), bridge `departments.org_unit_id`, assign `users.org_unit_id`, ตั้ง head, ผูก org-routed workflow (repair_request). พิสูจน์ resolve org path บน data จริง (0 dept binding → ผ่าน org). transitional — Phase 3 vertical seeders แทนที่.
- **bonus:** bridge populated → Phase 1 CMMS dual-write (`OrgUnit::idForDepartment`) เลิกคืน null.

**2b form visibility ✅ (4fb355b)** — `DocumentForm::scopeVisibleToUser(orgUnitId, departmentId)` org-first; thread org ผ่าน 12 callers + NavigationService chain. `FormVisibilityOrgUnitTest`.

**2c field visibility ✅ (5a2635a)** — migration `visible_to_org_units` JSON; `fieldVisibleToUser(org, dept)` + `dynamic-field.blade` org-first; thread org ผ่าน 8 includes + 4 CMMS controllers. `FieldVisibilityOrgUnitTest`.

**2d reports ✅ (2569d4f)** — `DataSourceRegistry` +org_unit_id dimension (guard fdata เก่า); `DashboardWidgetDataController` +org_unit_id filter + label lookup. `ReportOrgUnitFilterTest`.

**2e print/display ✅ (d160829)** — print/pdf + 4 CMMS show แสดง org-first; eager-load orgUnit คู่ department; th lang หน่วยงาน; `ApprovalInstance::orgUnit():BelongsTo` (larastan).

**Phase 2 จบ — reader ทุก subsystem อ่าน org_unit ก่อน department (fallback). 744 tests, analyse 97.**

### Phase 3 — UI → org_units (admin authoring)
- **keystone (0a6b002)** user create/edit: org_unit selector + UserController save
- **3a (27553f1)** form-level visibility admin: allowed_org_units (DocumentFormController + _form.blade)
- **3b (f2e6d3e)** field-level visibility admin: visible_to_org_units (formBuilder Alpine + checkboxes)
- **3c (e0b443e)** approval-routing policy: scope 'org_unit' exception (SettingController + Alpine)
- **3d (ข้าม)** org binding matrix admin แยก — org_unit_workflow_bindings จัดผ่าน OrgStructureDemoSeeder/programmatic (advanced/low-traffic; form policy 3c คุม routing ส่วนใหญ่แล้ว) → follow-up
- vertical seeders (NteqPolymer/Bodindecha) ยังเป็น department-based — รันได้ผ่าน bridge; rewrite เป็น Phase 4 หรือ follow-up

**ระบบ org-capable เต็ม: org ขับเคลื่อนทุก reader + admin authoring (user/form/field/policy) ครบ. department = fallback. 747 tests.**

### Phase 4 — ทิ้ง department (coordinated removal — ต้องทำพร้อมกันกัน test แดง)
**Interdependency:** user form require department_id · readers มี dept fallback · ~40 test fixtures set department_id → drop ต้อง coordinated.

**สถานะ Phase 4 (ทำแล้ว):**
- **4a ✅ (47d084c)** ลบ department admin UI: routes(web+api) + DepartmentController(web+api) + settings/departments views + nav entry + DepartmentsCrudTest; user form department_id required→nullable; phpstan-baseline ล้าง stale entries
- **4b-1 ✅ (1ace24a)** CMMS (แจ้งซ่อม/จัดซื้อ/เบิกอะไหล่/PM) ตัด department dropdown → route ตาม org_unit ของผู้ยื่น (start() resolve เอง)
- **4b-2 ✅ (3d86c92)** user form ตัด department selector + UserController store/update เลิก save department_id
- **→ department หายจาก UI ทุกหน้าแล้ว. column/table/model + dual-write + fallback ยังอยู่ (internal). 737 tests, analyse 96.**

**4c เหลือ (internal drop — mechanical sweep, ทุกชิ้น cascade เข้า fixtures):**
- เอา dual-write department_id ออก (submission/instance/eval/mobile/inbound/auth-session/start()) + dept fallback ออกจาก readers (scopeVisibleToUser ลด param→12 callers, fieldVisibleToUser, resolveDepartmentBinding, NavigationService threading, DataSourceRegistry, DashboardWidget filter, print/pdf/CMMS-show display) + OrgUnit::idForDepartment ลบ
- migration drop department_id (users/approval_instances/document_form_submissions/document_form_workflow_policies) + fdata_* (FormSchemaService) + drop tables departments/department_workflow_bindings/document_form_departments + bridge
- ลบ Department + DepartmentWorkflowBinding models + imports 8 models; DepartmentSeeder; **OrgStructureDemoSeeder rewrite ให้สร้าง org tree ตรงๆ ไม่ mirror Department**; UserController import/index dept refs
- ~40 test fixtures (group A `Department::create`→`OrgUnit`, group B 4 rewrite assertions, group C ~25 ลบ department_id) — ใช้ test failures เป็น checklist
- verify: `grep -rn department_id app/ database/` = 0

**OLD 4a/4b/4c plan ด้านล่าง (อ้างอิง):**

**4a UI/admin teardown:** ลบ web routes 327-338 + api routes 70-74 (DepartmentController web+api) · ลบ `resources/views/settings/departments/*` · NavigationMenuSeeder:87 entry · user form department_id required→ลบ (org_unit required แทน) · DepartmentsCrudTest ลบ
**4b reader/writer teardown:** เอา dual-write department_id ออก (submissions/instances/6 controllers/Mobile/Eval/Inbound) · เอา dept fallback ออกจาก ApprovalFlowService (resolveDepartmentBinding) + DocumentForm::scopeVisibleToUser + fieldVisibleToUser + DataSourceRegistry + display · OrgUnit::idForDepartment ลบ
**4c schema drop:** migration drop `department_id` (users/approval_instances/document_form_submissions/document_form_workflow_policies) + fdata_* (FormSchemaService RESERVED_COLUMNS+createTable) · drop tables departments + department_workflow_bindings + document_form_departments + bridge column · ลบ Department + DepartmentWorkflowBinding models + imports ใน 8 models · DepartmentSeeder
**4d test fixtures (~40 ไฟล์):** delete DepartmentsCrudTest · group A (~10) `Department::create`→`OrgUnit::create` · group B (~4) rewrite assertions (ApprovalRoutingPolicy/ProfileExtended/UsersCrud/OrgUnitWorkflowResolution) · group C (~25) ลบ `department_id` ออกจาก fixture arrays
**verify:** `grep -rn department_id app/ database/` = 0 · เทสต์เขียว · analyse

### Phase 4 — ทิ้ง department
เอา dual-write ออก · drop department_id (6 ตาราง + fdata_*) + department_workflow_bindings + old document_form_departments + departments table + bridge · ลบ Department model/Controller/routes/views/nav + DepartmentsCrudTest · เทสต์เขียว + analyse.

## Verification (ทุกเฟส)
- `composer test` เขียว (baseline ~726 บน main; branch นี้เริ่มเท่า main) ก่อนไปเฟสถัดไป
- `composer analyse` ไม่เพิ่ม error
- หลัง Phase 3: re-seed `dataflow_uat` → demo school/factory เดินได้ (UAT)
- หลัง Phase 4: `grep -rn department_id app/ database/` = 0

## Risk
- จุดเสี่ยงสุด: workflow resolution (2a) + `fdata_*` dynamic tables (migrate ตาราง runtime ด้วย sync/`forms:backfill-dedicated-table`)
- แต่ละเฟส shippable แยก — merge เข้า main ทีละเฟสได้
- demo บน main ไม่กระทบจน Phase 3
