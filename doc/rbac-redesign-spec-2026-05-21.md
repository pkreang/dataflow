# RBAC Redesign -- Replace Spatie with Custom RxA Model

**บทบาทไฟล์นี้:** Design spec สำหรับการรื้อ RBAC จาก Spatie Permission v7.2 มาเป็นโมเดล ResourcexAction ของตัวเอง -- V1 (6 สัปดาห์) + V2 (4 สัปดาห์ release ถัดไป) ออกแบบรวมในไฟล์เดียวเพื่อรับประกัน data model coherent กับฟีเจอร์ V2 ที่จะตามมา

**Branch ที่จะ implement:** `rbac-redesign` (สาขาจาก `main` หลัง merge integration เสร็จ -- ไม่ใช่จาก integration)
**Effort estimate:** V1 ~6 สัปดาห์ + V2 ~4 สัปดาห์
**Status:** Spec draft -> รอ user review ก่อนเขียน implementation plan

---

## 1. Context + Goals

**ปัญหาที่กระตุ้นการ redesign:**
- Spatie permissions ปัจจุบัน 28 string-named ตั้งชื่อไม่ consistent: `manage_settings`, `approval.approve`, `manage profile`, `view_purchase_requests` -- มิกซ์ระหว่าง dot, underscore, space
- UI assign permission ต่อ user (`/settings/users/{id}/edit`) ใช้ยาก -- toggle Default<->Custom + matrix table ที่ rigid
- Permission Overview เป็น read-only ไม่ช่วยให้ตั้งค่าได้

**V1 Goals:**
1. **Resource x Action matrix** model -- naming consistent, sparse pivot, ขยายต่อง่าย
2. **UI ใหม่ 4 หน้า:** role editor matrix, user assignment, overview clickable, audit viewer
3. **Time-bound permission** -- สิทธิ์มีวันหมดอายุ (`starts_at`, `expires_at`)
4. **Audit log** -- บันทึกทุก permission change forever (no auto-cleanup)
5. **Field-level unified** -- รวม eForm `editable_by` JSON tokens เข้า RBAC framework

**V2 Goals (เลื่อน, ออกแบบรวมไว้ก่อน):**
6. **Delegation** -- user -> user temporary grant
7. **Branch scoping** -- permission ผูกสาขาได้

**Non-goals (V1 + V2):**
- Row-level data filtering ผ่าน RBAC (ใช้ ownership + branch column check ระดับ controller แทน)
- Conditional permission (e.g. "approve ได้ถ้ายอด<10000") -- เป็น workflow logic ไม่ใช่ RBAC
- Permission group ซ้อน group -- flat 2-level (module -> permission) เท่านั้น

---

## 2. V1 Data Model

9 ตารางใหม่ทั้งหมด prefix `rbac_` (ไม่ชนกับตาราง Spatie เดิม `roles`, `permissions`, `model_has_*` ที่อยู่คู่กันระหว่าง migration period)

| ตาราง | Key columns | บทบาท | จำนวน rows (target) |
|-------|-------------|--------|----------------------|
| `rbac_resources` | id, key, label_en, label_th, description, icon, module, sort_order | "สิ่งของในระบบ" | 25 |
| `rbac_actions` | id, key, label_en, label_th, is_standard | "กริยา" | 18 |
| `rbac_resource_actions` | resource_id, action_id, is_default | sparse RxA pivot -- กำหนดว่า action ไหน apply กับ resource ไหน | ~140 |
| `rbac_permissions` | id, resource_id, action_id, **key** (`equipment.create`), is_legacy | computed permission rows | ~140 |
| `rbac_roles` | id, key, name, name_en, name_th, description, **is_template**, color, icon | 5 templates + user-created | 5+ |
| `rbac_role_permissions` | role_id, permission_id | M:N | -- |
| `rbac_user_roles` | id, user_id, role_id, **starts_at** (nullable), **expires_at** (nullable), granted_by_user_id, timestamps | user -> role (time-bound) | -- |
| `rbac_user_permissions` | id, user_id, permission_id, **granted** (T=grant/F=revoke), starts_at, expires_at, granted_by_user_id, timestamps | direct grant/revoke ทับ role | -- |
| `rbac_audit_log` | id, actor_user_id, action, entity_type, entity_id, before_json, after_json, ip_address, user_agent, created_at | observer-driven, forever retention | -- |
| `rbac_field_permissions` | id, form_id, field_key, permission_type (`editable`/`visible`), role_id (nullable), permission_id (nullable), user_id (nullable) | unified eForm field-level | -- |

**Super-admin:** `users.is_super_admin` flag คงไว้ใน V1 -- `Gate::before` ตรวจก่อน `canViaRbac()` แล้วผ่านทันทีถ้า true. V2 พิจารณาย้ายเป็น role `super_admin` แทน

**Slot สำหรับ V2:** ไม่ต้องเตรียม column `branch_id` ใน V1 -- V2 migration จะ ADD COLUMN เพิ่มทีหลัง (nullable, default null = global)

**Indexes ที่ต้องการ:**
- `rbac_permissions(key)` UNIQUE -- สำหรับ `Gate::before` lookup
- `rbac_user_roles(user_id, expires_at)` -- สำหรับ permission check
- `rbac_user_permissions(user_id, expires_at)`
- `rbac_audit_log(actor_user_id, created_at)`, `(entity_type, entity_id, created_at)` -- สำหรับ filter
- `rbac_field_permissions(form_id, field_key)` -- form builder lookup

---

## 3. V1 Resources (25 rows)

| key | module | label_en | label_th |
|-----|--------|---------|----------|
| rbac_roles | admin | Roles | บทบาท |
| rbac_permissions | admin | Permissions | สิทธิ์ |
| users | admin | Users | ผู้ใช้ |
| system_settings | admin | System Settings | ตั้งค่าระบบ |
| organization | organization | Organization (Companies+Branches) | องค์กร |
| departments | organization | Departments | แผนก |
| positions | organization | Positions | ตำแหน่ง |
| doctypes | form_platform | Document Types | ประเภทเอกสาร |
| form_designs | form_platform | Form Designs | ออกแบบฟอร์ม |
| forms | form_platform | Documents (Submissions) | เอกสาร |
| approvals | form_platform | Approvals | การอนุมัติ |
| workflows | form_platform | Workflows | เส้นทางอนุมัติ |
| lookups | form_platform | Lookup Lists | รายการ lookup |
| running_numbers | form_platform | Running Numbers | เลขรันนิ่ง |
| equipment | cmms | Equipment | อุปกรณ์ |
| spare_parts | cmms | Spare Parts | อะไหล่ |
| pm_plans | cmms | PM Plans | แผน PM |
| repair_requests | cmms | Repair Requests | แจ้งซ่อม |
| dashboards | reports | Dashboards | แดชบอร์ด |
| my_reports | reports | My Reports | รายงานของฉัน |
| kpi_cycles | reports | KPI Cycles | รอบ KPI |
| webhooks | integrations | Webhooks | Webhooks |
| evaluations | integrations | Evaluations | ประเมิน |
| navigation | integrations | Navigation Menus | เมนูระบบ |
| mobile_app | integrations | Mobile App | แอปมือถือ |

**6 modules** (UI grouping): `admin`, `organization`, `form_platform`, `cmms`, `reports`, `integrations`

---

## 4. V1 Actions (18 rows)

| key | label_en | label_th | apply to | is_standard |
|-----|---------|----------|----------|:---:|
| read | Read | ดู | ทุก resource | [x] |
| create | Create | สร้าง | ทุก resource | [x] |
| update | Update | แก้ไข | ทุก resource | [x] |
| delete | Delete | ลบ | ทุก resource | [x] |
| manage | Manage (=CRUD) | จัดการทั้งหมด | ทุก resource (auto-derived) | [x] |
| export | Export | ส่งออก | opt-in: users, forms, equipment, ... | -- |
| import | Import | นำเข้า | opt-in | -- |
| approve | Approve | อนุมัติ | approvals | -- |
| reject | Reject | ไม่อนุมัติ | approvals | -- |
| return | Return (send-back) | ส่งกลับ | approvals | -- |
| reassign | Reassign | มอบหมายใหม่ | approvals | -- |
| submit | Submit | ส่ง | forms | -- |
| return_to_draft | Return to Draft | คืนเป็นร่าง | forms | -- |
| view_others | View Others' | ดูของคนอื่น | forms | -- |
| transfer | Transfer | โอน | equipment | -- |
| assign | Assign | มอบหมาย | equipment, repair_requests | -- |
| requisition | Requisition | เบิก | spare_parts | -- |
| issue | Issue | จ่าย | spare_parts | -- |
| execute | Execute | ดำเนินการ | pm_plans | -- |

`rbac_resource_actions` pivot ระบุว่า resource ไหน support action ไหน -- sparse, ~140 cells ทั้งหมด

---

## 5. Permission Check Algorithm

```php
// User::canViaRbac(string $permissionKey): bool
1. If $this->is_super_admin: return true                        // Gate::before bypass

2. $perm = RbacPermission::byKey($permissionKey);
   If null -> return false                                        // unknown permission

3. Check direct rbac_user_permissions
   WHERE user_id = $this->id AND permission_id = $perm->id AND granted = TRUE
     AND (starts_at IS NULL OR starts_at <= NOW())
     AND (expires_at IS NULL OR expires_at > NOW())
   If exists -> return true

4. Check direct rbac_user_permissions (revoke)
   WHERE user_id = $this->id AND permission_id = $perm->id AND granted = FALSE
     AND time window valid
   If exists -> return false                                      // revoke ชนะ role

5. Check via roles
   JOIN rbac_user_roles ON user_id = $this->id (time window valid)
   JOIN rbac_role_permissions ON role_id
   WHERE permission_id = $perm->id
   If exists -> return true

6. Return false
```

**Backwards compat shim (AppServiceProvider::boot):**
```php
Gate::before(function ($user, $ability) {
    if ($user->is_super_admin) return true;
    return $user->canViaRbac($ability) ? true : null;
    // return null = continue to other Gate definitions; ไม่ block
});
```

ผลที่ได้:
- `@can('equipment.create')` ใน 6 จุด blade -- ทำงานต่อโดยไม่ต้องแก้
- `middleware('permission:user_access.read')` ใน 29 จุด routes -- ทำงานต่อผ่าน middleware ใหม่ที่ alias ชื่อเดียว
- 19 ที่ใช้ Spatie API ใน `app/` -- เปลี่ยนเป็น `$user->canViaRbac()` หรือ `$user->hasRoleViaRbac()` ใน V1

---

## 6. Role Templates (5 รายการ seed ที่ `is_template=TRUE`)

| key | label_en | label_th | Permission set โดยย่อ |
|-----|---------|----------|----------------------|
| super_admin | Super Admin | ผู้ดูแลระบบสูงสุด | ทุก permission (~140) -- ปกติใช้ `is_super_admin` flag แทน, template นี้ไว้กรณีแยก super-admin scope ในอนาคต |
| manager | Manager | ผู้จัดการ | `manage` บน organization, departments, equipment, forms + `approve` + `export` |
| officer | Officer | เจ้าหน้าที่ | `forms.create/update/submit` + `approvals.read` + `equipment.read` |
| approver | Approver | ผู้อนุมัติ | `approvals.approve/reject/return` + `forms.read` + `view_others` |
| viewer | Viewer | ผู้ดูเท่านั้น | `read` บนทุก resource ที่ apply ได้ |

Custom role ที่ user สร้างผ่าน UI -> `is_template=FALSE`

---

## 7. UI Design (V1 -- 4 หน้าหลัก + 1 in-form)

### 7.1 Role editor -- `/settings/rbac/roles/{id}/edit`

ResourcexAction matrix per module (collapsible), live counter, select-all per module:

```
[Role: Manager]                                       [ Save]

[-] Organization                            8/15  [All|Clear]
              | Read |Create|Update|Delete|Manage|Export|
  Organization|  [x]   |  [x]   |  [x]   |  [ ]   |  [ ]   |  [ ]   |
  Departments |  [x]   |  [x]   |  [x]   |  [ ]   |  [ ]   |  [ ]   |
  Positions   |  [x]   |  [x]   |  [ ]   |  [ ]   |  [ ]   |  [ ]   |

[-] Form Platform                          22/35  [All|Clear]
  ...

[+] CMMS                                   0/20  [All|Clear]
[+] Reports                                0/15  [All|Clear]
[+] Integrations                           0/10  [All|Clear]
[+] Admin                                  0/20  [All|Clear]

Total: 32 / 140 permissions selected
```

- Cell ที่ resource ไม่ support action นั้น -> greyed out (ไม่ใช่ [ ] -- ต่างกัน)
- [x]/[ ] คือ explicit grant/empty; greyed = "not applicable"
- `manage` = auto-derived (เลือก manage = เลือก CRUD ทั้ง 4)

### 7.2 User assignment -- `/settings/rbac/users/{id}`

```
[User: john@example.com]                                [ Save]

ROLES (2)                                              [+ Add Role]
+-------------------------------------------------------------+
| Manager      No expiry                            Edit  Remove|
| Approver     Until 2026-12-31                     Edit  Remove|
+-------------------------------------------------------------+

DIRECT PERMISSIONS (2)                            [+ Grant] [+ Revoke]
+-------------------------------------------------------------+
| [x] Grant   equipment.export       Forever                      |
| [ ] Revoke  users.delete           Until 2026-06-30             |
+-------------------------------------------------------------+

 Audit history -> /settings/rbac/audit?user=12
```

Modal "Add Role": select role + (optional) `starts_at` + `expires_at` (datetime pickers)
Modal "Grant Permission": select permission (search/autocomplete + module filter) + time window
Modal "Revoke Permission": เหมือน Grant แต่ `granted=FALSE`

### 7.3 Permission overview -- `/settings/rbac/overview` (clickable matrix)

```
                  Roles ->
Permissions ↓     SuperAd  Manager  Officer  Approver  Viewer  Custom₁
---------------------------------------------------------------------
[-] ADMIN
  users.read       [x]        [x]         [ ]         [ ]         [x]       [ ]
  users.create     [x]        [x]         [ ]         [ ]         [ ]       [ ]
  users.update     [x]        [x]         [ ]         [ ]         [ ]       [ ]
  ...
[-] FORM PLATFORM
  forms.submit     [x]        [x]         [x]         [x]         [ ]       [ ]
  approvals.approve [x]       [x]         [ ]         [x]         [ ]       [ ]
  ...

[Search permission...] [Filter module: All [-]]
Click cell -> toggle (with confirmation modal ถ้าเป็น super_admin role)
Click column header -> bulk action ("Select all in column" / "Clear column")
Click row label -> "Where is this permission used?" (list roles + direct grants)
```

### 7.4 Audit viewer -- `/settings/rbac/audit`

```
[Filter: actor | target user | action type | date range]

2026-05-21 13:45  admin@example.com  ASSIGNED role "Manager" to john@example.com
                                     expires=null                              [diff]
2026-05-21 13:30  admin@example.com  REVOKED users.delete from john@example.com
                                     expires=2026-06-30                        [diff]
2026-05-21 13:10  admin@example.com  CREATED role "QA Tester" (5 permissions)  [diff]
2026-05-21 12:45  system            BACKFILLED 28 Spatie permissions           [diff]
...
[1] [2] [3] ... [Last]
```

"[diff]" -> modal แสดง before/after JSON snapshot side-by-side

Action types ที่ log:
- `role.created`, `role.updated`, `role.deleted`
- `role.permission_added`, `role.permission_removed`
- `user.role_assigned`, `user.role_revoked`
- `user.permission_granted`, `user.permission_revoked`
- `user.super_admin_flipped`
- `field_permission.granted`, `field_permission.revoked`
- `system.backfill_completed`, `system.parity_test_mismatch`

### 7.5 Field-level (in form builder)

ใน form builder field editor เพิ่ม tab "Permissions":

```
Field: amount_total                              [+ Add]
---------------------------------------------------------
Edit: Editable by:
  ☑ Role: Manager           [Remove]
  ☑ Role: Officer           [Remove]
  ☐ User: jane@... (direct) [Remove]

View: Visible to:
  ☑ All authenticated (default)
  - Override: only Role: Approver?  [Add Override]
```

แทน `editable_by` JSON tokens เดิม:
- `requester` -> ไม่ map (handled by ownership check ใน controller -- submission.requester_id = current user)
- `step_N` -> ไม่ map (handled by workflow step assignment -- current step's approver_id = current user)
- `user:{id}` -> `rbac_field_permissions(user_id={id}, permission_type='editable')`

UI แสดง role/user options จาก lookup; JSON token semantic (`requester`, `step_N`) ยังคงทำงานคู่ขนานใน controller -- ไม่กระทบ

---

## 8. Migration Plan (Spatie -> V1, ~6 weeks)

### Week 1: Schema + Backfill

**Deliverables:**
- Migrations: 9 `rbac_*` tables + indexes (1 file ต่อ table)
- Seeders: 
  - `RbacResourceSeeder` (25 rows)
  - `RbacActionSeeder` (18 rows)
  - `RbacResourceActionSeeder` (~140 rows)
  - `RbacPermissionSeeder` (computed จาก resource_actions)
  - `RbacRoleSeeder` (5 templates: super_admin, manager, officer, approver, viewer)
- `BackfillSpatieToRbacCommand` (artisan):
  - Map 28 Spatie permissions -> `rbac_permissions.key`
  - **Skip 3 legacy:** `view_purchase_requests`, `view_purchase_orders`, `purchase_order.create` (orphan, ไม่มี route active)
  - Iterate ALL Spatie `roles` (4 seeded + user-created) -> `rbac_roles`
  - `role_has_permissions` -> `rbac_role_permissions`
  - `model_has_roles` -> `rbac_user_roles` (starts_at=null, expires_at=null)
  - `model_has_permissions` -> `rbac_user_permissions` (granted=true)
- **Pre-flight diff script:** dump effective permissions ของทุก user ก่อน + หลัง backfill -> compare -> fail loud ถ้าต่าง
- Old Spatie tables ยังอยู่คู่กันใน DB

### Week 2: Auth layer swap

**Deliverables:**
- `User` model: เพิ่ม `HasRbacRoles` trait method (`canViaRbac`, `assignRbacRole`, `revokeRbacRole`, `grantRbacPermission`, `revokeRbacPermission`, `hasRoleViaRbac`)
- `User` model: ลบ Spatie `HasRoles` trait -> bridge methods (เรียก Rbac แทน) สำหรับ test backwards compat
- `AppServiceProvider::boot()`: register `Gate::before` shim
- New `RbacPermissionMiddleware` ที่ `app/Http/Middleware/RbacPermission.php` -- Kernel alias `permission` (แทน Spatie's)
- Existing 29 `middleware('permission:foo')` + 6 `@can('foo')` ทำงานต่อโดยไม่แก้
- **Parity test:** เปิด feature flag `RBAC_PARITY_LOG=true` -- ทุก permission check log mismatch ระหว่าง Spatie answer vs Rbac answer -> 1 สัปดาห์ shadow mode -> ดู log -> fix discrepancies ก่อน swap จริง

### Week 3: UI scaffold + Role editor + Overview

**Deliverables:**
- Routes `/settings/rbac/*` (controller: `RbacRoleController`, `RbacPermissionController`, `RbacUserAccessController`, `RbacAuditController`)
- Role editor blade -- matrix per module + select-all + live counter
- Permission overview blade -- clickable matrix + search + filter
- Old `/settings/roles`, `/settings/permissions` -> redirect ไปเส้นใหม่
- Sidebar menu: rename "บทบาท" -> "บทบาท (RBAC)" (หรือเก็บชื่อเดิม)
- ลบ view files เก่าใน `resources/views/roles/`, `resources/views/permissions/` (commit แยก เพื่อ rollback ง่าย)

### Week 4: User assignment + Audit viewer

**Deliverables:**
- User assignment page (`/settings/rbac/users/{id}`):
  - Roles section (list + Add modal + time-window picker)
  - Direct permissions section (Grant + Revoke modals)
- Audit log viewer (`/settings/rbac/audit`):
  - Paginated list with filters
  - Diff modal (before/after JSON)
- Audit observers wired:
  - `RbacRoleObserver`, `RbacRolePermissionObserver`, `RbacUserRoleObserver`, `RbacUserPermissionObserver`, `UserObserver` (`is_super_admin` flip)
- หน้า `/settings/users/{id}/edit` link ไปยัง `/settings/rbac/users/{id}` ใน section "สิทธิ์การเข้าถึง"

### Week 5: Field-level migration

**Deliverables:**
- Table + migration: `rbac_field_permissions`
- Form builder field editor: tab "Permissions" (Alpine.js component)
- Migration script: parse `document_form_fields.editable_by` JSON -> populate `rbac_field_permissions` rows
- Controller: `DocumentFormSubmissionController::filterPayloadForAssignee` -> ใช้ทั้ง JSON tokens เดิม (`requester`, `step_N`) + RBAC field permissions ใหม่ คู่กัน 1 release
- Tests: parity ระหว่างวิธีเดิม vs RBAC field-level

### Week 6: Test rewrite + Cleanup

**Deliverables:**
- Rewrite 19 Spatie test usages -> new API (`->givePermissionTo` -> `->grantRbacPermission`, ฯลฯ)
- Helper: `actAsUserWithPermissions($user, [...])` ลด boilerplate
- New tests:
  - `RbacServiceTest` -- check algorithm 6 steps
  - `TimeBoundPermissionTest` -- starts_at/expires_at edge cases
  - `AuditLogTest` -- observer fires for all RBAC mutations
  - `BackfillSpatieToRbacTest` -- parity before/after
  - `FieldPermissionRbacTest` -- dual-mode (JSON tokens vs RBAC)
- Remove `spatie/laravel-permission` from `composer.json`
- Drop Spatie tables (`roles`, `permissions`, `model_has_*`, `role_has_permissions`) -- defer 1 release ถ้าไม่มั่นใจ
- Cleanup orphan `PurchaseRequestController` + dead seed rows (separate commit)
- Update CLAUDE.md: section §4 (RBAC) rewrite ทั้ง section
- Update `doc/api-spec.md` ถ้ามี route ใหม่

### V2 (release ถัดไป, ~4 weeks)

- Week 7: Migration เพิ่ม `branch_id` ใน `rbac_user_roles`, `rbac_user_permissions`, + ตาราง `rbac_delegations`
- Week 8: Delegation UI (`/settings/rbac/delegations`) + permission check update (step 6 ใน algorithm)
- Week 9: Branch scoping permission check + UI branch picker ใน Add Role / Grant modal
- Week 10: Test + UAT V2 features + cleanup `editable_by` JSON tokens (ถ้า field-level RBAC ทำงานครบ)

---

## 9. Risks + Mitigation

| Risk | Mitigation |
|------|-----------|
| `Gate::before` shim ตอบผิด -> silent permission grant/deny | Parity test 1 สัปดาห์ shadow mode -- log mismatches ทุก auth check; fix discrepancies ก่อน swap จริง |
| Backfill ลืม permission -> user สูญสิทธิ์ที่เคยมี | Pre-flight diff: dump effective permissions every user before + after, fail loud ถ้าต่าง |
| Cache invalidation ตอน assign role -> permission stale | Cache versioning per user (`user:{id}:rbac:v{N}`); flush cache key เมื่อ user_roles/user_permissions/role_permissions mutation ผ่าน observer |
| `users.is_super_admin` ลืม revoke ตอน user ออกจากบทบาท admin | UI ใน user edit แสดง warning + audit log ทุก flag flip + email notify super-admins ทุก instance ที่เกิด flip |
| Test rewrite (19 places) ใช้เวลา | Helper `actAsUserWithPermissions($user, [...])` + bridge methods ใน HasRbacRoles trait (Spatie-like API) ลด boilerplate |
| Field-level migration ทำลาย `editable_by` ที่ใช้อยู่จริง | Dual-run 1 release: controller check ทั้งสองวิธี -- RBAC ใหม่ และ JSON tokens เดิม; deprecated log ถ้า fall-back ไป JSON; remove JSON support ใน V2 |
| V2 retrofit `branch_id` ลำบาก | V1 spec บอก slot ไว้แล้ว -- V2 แค่ ADD COLUMN ที่ nullable default null + update algorithm step 5 ให้รับ $branchId parameter |
| Performance: 140 permissions x N users -> check ทุก request | Eager-load effective permissions ต่อ user แล้ว cache 5 นาที; invalidate ผ่าน observer; ทำ benchmark ก่อน ship |
| Backwards compat shim leak -> Spatie code path active หลัง remove package | Grep `Spatie\\Permission` หลัง cleanup; CI lint rule reject new usage |
| Migration ระหว่าง dev (มี data) vs production (มี customer data) ต่างกัน | Backfill script idempotent -- รันซ้ำได้; ทดสอบบน DB clone ก่อน production |

---

## 10. Implementation Sequencing (V1 = 6 weeks)

```
Week 1: Schema + Seed + Backfill           (*) Foundation
Week 2: Auth swap + Gate::before shim       (*) Compat layer
Week 3: UI -- Role editor + Overview
Week 4: UI -- User assignment + Audit viewer
Week 5: Field-level migration
Week 6: Test rewrite + Cleanup
```

**Critical path:** Week 1 -> Week 2 (sequential, ห้ามข้าม). Week 3-5 parallelable ถ้ามีคนทำหลายคน. Week 6 ทำตอนท้าย

**Branch strategy:**
- `rbac-redesign` ตัวหลัก แตกจาก main
- Sub-branches ต่อ week: `rbac-redesign/wk1-schema`, `rbac-redesign/wk2-auth-swap`, ... -> merge เข้า main branch แต่ละสัปดาห์
- ทุก merge -> CI ต้องเขียว + parity test ผ่าน

---

## 11. Next Steps

1. ✅ Spec saved ที่ไฟล์นี้ -- commit on `integration` branch (เพราะอยู่ใน worktree dataflow-wt-main ที่ checkout integration; เมื่อ merge เข้า main แล้วจะอยู่ที่ main ตามด้วย)
2. **(ตอนนี้)** User review spec -> confirm or request changes
3. หลัง user confirm -> invoke `writing-plans` skill เพื่อสร้าง implementation plan แยกย่อยตาม 6 weeks
4. Execute plan ใน session แยก, branch `rbac-redesign` ที่แตกจาก main (หลัง integration -> main merge เสร็จก่อน)
