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
2a workflow resolution → org_unit (คง dept fallback) · 2b form visibility → org_unit + document_form_org_units · 2c field visibility → visible_to_org_units · 2d reports filter org_unit · 2e print/display orgUnit relation.

### Phase 3 — seeders + UI → org_units
เขียน vertical seeders ใหม่ (org tree + visibility/binding ผ่าน org_units) · UI selector/admin pages/nav · tests behavior ใหม่ + fixture search-replace.

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
