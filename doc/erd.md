# ERD — Data Flow (CMMS)

> **อัปเดตล่าสุด:** 2026-04-12 · ตรวจสอบจาก `backend/database/migrations/` ทั้งหมด

---

## 1. ภาพรวม Relationships

```
companies ──< branches
companies ──< users >── positions
companies ──< users >── departments
users ──< model_has_roles >── roles ──< role_has_permissions >── permissions
users ──< model_has_permissions >── permissions  (Custom role)
users ──< personal_access_tokens
users ──< sessions

departments ──< department_workflow_bindings >── approval_workflows
approval_workflows ──< approval_workflow_stages
approval_instances ──< approval_instance_steps

document_types ──< document_forms ──< document_form_fields
document_forms ──< document_form_departments >── departments
document_forms ──< document_form_submissions

equipment_categories ──< equipment_locations (ไม่มี FK โดยตรง)
equipment_categories ──< equipment
equipment_locations ──< equipment

spare_parts ──< spare_part_transactions
spare_parts ──< spare_part_requisition_items

report_dashboards ──< report_dashboard_widgets
```

---

## 2. Tables & Fields

---

### `users`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `first_name` | VARCHAR(255) | NOT NULL | |
| `last_name` | VARCHAR(255) | NOT NULL | |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE | Unique คำนึง soft-delete |
| `email_verified_at` | TIMESTAMP | NULLABLE | |
| `auth_provider` | VARCHAR(32) | NULLABLE | `local` / `entra` / `ldap` |
| `external_id` | VARCHAR(191) | NULLABLE | SSO external user ID |
| `ldap_dn` | VARCHAR(512) | NULLABLE | LDAP Distinguished Name |
| `password` | VARCHAR(255) | NOT NULL | bcrypt; ว่างสำหรับ SSO users |
| `password_changed_at` | TIMESTAMP | NULLABLE | ใช้บังคับ password expiry |
| `password_must_change` | BOOLEAN | NOT NULL, DEFAULT false | |
| `company_id` | BIGINT UNSIGNED | NULLABLE, FK → companies | |
| `branch_id` | BIGINT UNSIGNED | NULLABLE, FK → branches | |
| `department` | VARCHAR(255) | NULLABLE | legacy text; ดู `department_id` |
| `department_id` | BIGINT UNSIGNED | NULLABLE, FK → departments | |
| `position` | VARCHAR(255) | NULLABLE | legacy text; ดู `position_id` |
| `position_id` | BIGINT UNSIGNED | NULLABLE, FK → positions | ใช้ workflow approval |
| `phone` | VARCHAR(50) | NULLABLE | |
| `line_notify_token` | VARCHAR(255) | NULLABLE | LINE Notify integration |
| `remark` | TEXT | NULLABLE | |
| `avatar` | VARCHAR(500) | NULLABLE | URL หรือ path |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | soft disable |
| `is_super_admin` | BOOLEAN | NOT NULL, DEFAULT false | DB flag; bypasses all checks |
| `dashboard_config` | JSON | NULLABLE | Home dashboard layout config |
| `last_active_at` | TIMESTAMP | NULLABLE | อัปเดตทุก request |
| `locale` | VARCHAR(8) | NULLABLE | `en` / `th`; override ต่อ user |
| `remember_token` | VARCHAR(100) | NULLABLE | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |
| `deleted_at` | TIMESTAMP | NULLABLE | SoftDeletes |

**Indexes:** `email` (unique), `is_active`, `deleted_at`, `(auth_provider, external_id)`

---

### `sessions`  *(Laravel built-in)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | VARCHAR(255) | PK | session ID |
| `user_id` | BIGINT UNSIGNED | NULLABLE, index | ไม่มี FK constraint |
| `ip_address` | VARCHAR(45) | NULLABLE | |
| `user_agent` | TEXT | NULLABLE | |
| `payload` | LONGTEXT | NOT NULL | serialized session data |
| `last_activity` | INT | NOT NULL, index | Unix timestamp |

> เก็บ `api_token`, `user`, `user_permissions` ที่ใช้ใน `AuthenticateWeb` middleware

---

### `roles`  *(managed by Spatie)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | เช่น `super-admin`, `admin` |
| `guard_name` | VARCHAR(255) | NOT NULL, DEFAULT `'web'` | |
| `display_name` | VARCHAR(255) | NULLABLE | ชื่อแสดงใน UI |
| `description` | TEXT | NULLABLE | |
| `is_system` | BOOLEAN | NOT NULL, DEFAULT false | ถ้า true ห้าม delete |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

<!-- Phase 2 (ยังไม่ implement): เพิ่ม `region VARCHAR(100) NULLABLE` สำหรับ regional-admin scoping -->

**Indexes:** `(name, guard_name)` (unique)

---

### `permissions`  *(managed by Spatie)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | เช่น `sales.create` |
| `guard_name` | VARCHAR(255) | NOT NULL, DEFAULT `'web'` | |
| `module` | VARCHAR(100) | NOT NULL | เช่น `sales` |
| `action` | VARCHAR(50) | NOT NULL | `create/read/update/delete/export` |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(name, guard_name)` (unique), `module`, `action`

---

### `model_has_roles`  *(managed by Spatie)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `role_id` | BIGINT UNSIGNED | FK → roles.id | |
| `model_type` | VARCHAR(255) | NOT NULL | `App\Models\User` |
| `model_id` | BIGINT UNSIGNED | NOT NULL | FK → users.id |

**PK:** `(role_id, model_id, model_type)`

---

### `model_has_permissions`  *(managed by Spatie)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `permission_id` | BIGINT UNSIGNED | FK → permissions.id | |
| `model_type` | VARCHAR(255) | NOT NULL | `App\Models\User` |
| `model_id` | BIGINT UNSIGNED | NOT NULL | FK → users.id |

**PK:** `(permission_id, model_id, model_type)`
> ใช้สำหรับ **Custom role** — assign permission โดยตรงกับ user แทน role

---

### `role_has_permissions`  *(managed by Spatie)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `permission_id` | BIGINT UNSIGNED | FK → permissions.id | |
| `role_id` | BIGINT UNSIGNED | FK → roles.id | |

**PK:** `(permission_id, role_id)`

---

### `personal_access_tokens`  *(Laravel Sanctum)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `tokenable_type` | VARCHAR(255) | NOT NULL | `App\Models\User` |
| `tokenable_id` | BIGINT UNSIGNED | NOT NULL | FK → users.id |
| `name` | VARCHAR(255) | NOT NULL | เช่น `web-session` |
| `token` | VARCHAR(64) | NOT NULL, UNIQUE | SHA-256 hashed |
| `abilities` | TEXT | NULLABLE | JSON array ของ abilities |
| `last_used_at` | TIMESTAMP | NULLABLE | |
| `expires_at` | TIMESTAMP | NULLABLE | token expiry |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(tokenable_type, tokenable_id)`, `token` (unique)

---

### `password_reset_tokens`  *(Laravel built-in)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `email` | VARCHAR(255) | PK | |
| `token` | VARCHAR(255) | NOT NULL | hashed |
| `created_at` | TIMESTAMP | NULLABLE | expires หลัง 60 นาที |

---

### `user_password_histories`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `user_id` | BIGINT UNSIGNED | NOT NULL, FK → users | cascadeOnDelete |
| `password_hash` | VARCHAR(255) | NOT NULL | bcrypt hash |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(user_id, id)`
> ใช้ตรวจสอบ password history เพื่อป้องกันการ reuse

---

## 3. Organization Tables

---

### `companies`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | |
| `code` | VARCHAR(255) | NOT NULL, UNIQUE | |
| `tax_id` | VARCHAR(20) | NULLABLE | เลขประจำตัวผู้เสียภาษี |
| `business_type` | VARCHAR(100) | NULLABLE | |
| `logo` | VARCHAR(255) | NULLABLE | path / URL |
| `address` | TEXT | NULLABLE | legacy text address |
| `address_no` | VARCHAR(50) | NULLABLE | |
| `address_building` | VARCHAR(255) | NULLABLE | |
| `address_street` | VARCHAR(255) | NULLABLE | |
| `address_subdistrict` | VARCHAR(120) | NULLABLE | |
| `address_district` | VARCHAR(120) | NULLABLE | |
| `address_province` | VARCHAR(120) | NULLABLE | |
| `address_postal_code` | VARCHAR(10) | NULLABLE | |
| `phone` | VARCHAR(255) | NULLABLE | |
| `fax` | VARCHAR(20) | NULLABLE | |
| `email` | VARCHAR(255) | NULLABLE | |
| `website` | VARCHAR(255) | NULLABLE | |
| `description` | TEXT | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

---

### `branches`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `company_id` | BIGINT UNSIGNED | NOT NULL, FK → companies | cascadeOnDelete |
| `name` | VARCHAR(255) | NOT NULL | |
| `code` | VARCHAR(255) | NOT NULL | unique ต่อ company |
| `address` | TEXT | NULLABLE | legacy |
| `address_no` | VARCHAR(50) | NULLABLE | |
| `address_building` | VARCHAR(255) | NULLABLE | |
| `address_street` | VARCHAR(255) | NULLABLE | |
| `address_subdistrict` | VARCHAR(120) | NULLABLE | |
| `address_district` | VARCHAR(120) | NULLABLE | |
| `address_province` | VARCHAR(120) | NULLABLE | |
| `address_postal_code` | VARCHAR(10) | NULLABLE | |
| `phone` | VARCHAR(255) | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(company_id, code)` unique

---

### `departments`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | |
| `code` | VARCHAR(255) | NOT NULL, UNIQUE | |
| `description` | TEXT | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

---

### `positions`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | |
| `code` | VARCHAR(100) | NOT NULL, UNIQUE | |
| `description` | TEXT | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

> `users.position_id` → ใช้ตรวจสอบ `approver_type: position` ใน workflow stages

---

## 4. System Tables

---

### `settings`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `key` | VARCHAR(255) | NOT NULL, UNIQUE | เช่น `auth.mode`, `password.min_length` |
| `value` | TEXT | NULLABLE | JSON-encoded สำหรับค่าซับซ้อน |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

> ดู `SettingController` และ `Setting` model สำหรับ key list; ครอบคลุม auth, password policy, branding, notifications

---

### `navigation_menus`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `parent_id` | BIGINT UNSIGNED | NULLABLE, FK → navigation_menus | self-referential tree |
| `label` | VARCHAR(255) | NOT NULL | fallback label |
| `label_en` | VARCHAR(255) | NULLABLE | English label |
| `label_th` | VARCHAR(255) | NULLABLE | Thai label |
| `icon` | VARCHAR(100) | NULLABLE | Heroicon name |
| `route` | VARCHAR(255) | NULLABLE | Named route |
| `permission` | VARCHAR(255) | NULLABLE | ซ่อน menu ถ้าไม่มี permission นี้ |
| `sort_order` | INT | NOT NULL, DEFAULT 0 | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(parent_id, sort_order, is_active)`
> แก้ไขผ่าน `NavigationMenuSeeder` แล้ว reseed; cached 3600s โดย `NavigationService`

---

### `running_number_configs`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `document_type` | VARCHAR(255) | NOT NULL, UNIQUE | เช่น `repair_request` |
| `prefix` | VARCHAR(255) | NOT NULL | เช่น `RR` |
| `digit_count` | TINYINT UNSIGNED | NOT NULL, DEFAULT 5 | จำนวนหลัก running number |
| `reset_mode` | VARCHAR(20) | NOT NULL, DEFAULT `'none'` | `none` / `yearly` / `monthly` |
| `include_year` | BOOLEAN | NOT NULL, DEFAULT true | |
| `include_month` | BOOLEAN | NOT NULL, DEFAULT false | |
| `last_number` | INT UNSIGNED | NOT NULL, DEFAULT 0 | เลข running ล่าสุด |
| `last_reset_at` | DATE | NULLABLE | วันที่ reset ล่าสุด |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

---

## 5. Approval Workflow Tables

```
approval_workflows ──< approval_workflow_stages
                  ──< department_workflow_bindings >── departments
                  ──< approval_instances ──< approval_instance_steps

document_forms ──< document_form_workflow_policies ──< document_form_workflow_ranges
document_form_workflow_policies >── departments
document_form_workflow_ranges >── approval_workflows
```

---

### `approval_workflows`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | |
| `document_type` | VARCHAR(255) | NOT NULL | เช่น `repair_request` |
| `description` | TEXT | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `allow_requester_as_approver` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(document_type, is_active)`

---

### `approval_workflow_stages`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `workflow_id` | BIGINT UNSIGNED | NOT NULL, FK → approval_workflows | cascadeOnDelete |
| `step_no` | SMALLINT UNSIGNED | NOT NULL | ลำดับขั้นตอน |
| `name` | VARCHAR(255) | NOT NULL | ชื่อขั้นตอน |
| `approver_type` | VARCHAR(32) | NOT NULL, DEFAULT `'role'` | `role` / `position` / `user` |
| `approver_ref` | VARCHAR(255) | NOT NULL | role name, position code, หรือ user_id |
| `min_approvals` | SMALLINT UNSIGNED | NOT NULL, DEFAULT 1 | จำนวน approval ขั้นต่ำ |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(workflow_id, step_no)` unique

---

### `department_workflow_bindings`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `department_id` | BIGINT UNSIGNED | NOT NULL, FK → departments | cascadeOnDelete |
| `document_type` | VARCHAR(255) | NOT NULL | เช่น `repair_request` |
| `workflow_id` | BIGINT UNSIGNED | NOT NULL, FK → approval_workflows | cascadeOnDelete |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(department_id, document_type)` unique

---

### `approval_instances`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `workflow_id` | BIGINT UNSIGNED | NOT NULL, FK → approval_workflows | cascadeOnDelete |
| `department_id` | BIGINT UNSIGNED | NULLABLE, FK → departments | nullOnDelete |
| `requester_user_id` | BIGINT UNSIGNED | NULLABLE | ไม่มี FK constraint |
| `document_type` | VARCHAR(255) | NOT NULL | |
| `reference_no` | VARCHAR(255) | NULLABLE | running number |
| `payload` | JSON | NULLABLE | snapshot ข้อมูล document |
| `current_step_no` | SMALLINT UNSIGNED | NOT NULL, DEFAULT 1 | |
| `status` | ENUM | NOT NULL, DEFAULT `pending` | `pending/approved/rejected/cancelled` |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(document_type, status)`, `(requester_user_id, status)`

---

### `approval_instance_steps`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `approval_instance_id` | BIGINT UNSIGNED | NOT NULL, FK → approval_instances | cascadeOnDelete |
| `step_no` | SMALLINT UNSIGNED | NOT NULL | |
| `stage_name` | VARCHAR(255) | NOT NULL | snapshot ชื่อ stage |
| `approver_type` | VARCHAR(255) | NOT NULL | |
| `approver_ref` | VARCHAR(255) | NOT NULL | |
| `acted_by_user_id` | BIGINT UNSIGNED | NULLABLE | ไม่มี FK constraint |
| `action` | ENUM | NOT NULL, DEFAULT `pending` | `pending/approved/rejected` |
| `comment` | TEXT | NULLABLE | |
| `acted_at` | TIMESTAMP | NULLABLE | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(approval_instance_id, step_no)`, `(acted_by_user_id, action)`

---

### `document_form_workflow_policies`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `form_id` | BIGINT UNSIGNED | NOT NULL, FK → document_forms | cascadeOnDelete |
| `department_id` | BIGINT UNSIGNED | NULLABLE, FK → departments | nullOnDelete |
| `use_amount_condition` | BOOLEAN | NOT NULL, DEFAULT false | ใช้ amount-based routing |
| `workflow_id` | BIGINT UNSIGNED | NULLABLE, FK → approval_workflows | nullOnDelete; ใช้ถ้าไม่มี amount condition |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(form_id, department_id)` unique

---

### `document_form_workflow_ranges`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `policy_id` | BIGINT UNSIGNED | NOT NULL, FK → document_form_workflow_policies | cascadeOnDelete |
| `min_amount` | DECIMAL(15,2) | NOT NULL, DEFAULT 0 | |
| `max_amount` | DECIMAL(15,2) | NULLABLE | NULL = ไม่มี upper bound |
| `workflow_id` | BIGINT UNSIGNED | NOT NULL, FK → approval_workflows | cascadeOnDelete |
| `sort_order` | INT UNSIGNED | NOT NULL, DEFAULT 1 | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(policy_id, sort_order)`

---

## 6. Document Form Tables

---

### `document_types`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `code` | VARCHAR(100) | NOT NULL, UNIQUE | เช่น `repair_request` |
| `label_en` | VARCHAR(255) | NOT NULL | |
| `label_th` | VARCHAR(255) | NOT NULL | |
| `description` | TEXT | NULLABLE | |
| `icon` | VARCHAR(50) | NULLABLE | |
| `routing_mode` | VARCHAR(30) | NOT NULL, DEFAULT `'hybrid'` | `hybrid` / `department` / `global` |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `sort_order` | INT | NOT NULL, DEFAULT 0 | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

---

### `document_forms`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `form_key` | VARCHAR(255) | NOT NULL, UNIQUE | slug สำหรับ lookup |
| `name` | VARCHAR(255) | NOT NULL | |
| `document_type` | VARCHAR(255) | NOT NULL | ตรงกับ `document_types.code` |
| `description` | TEXT | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `layout_columns` | (ดู migration) | NULLABLE | จำนวน columns ใน form layout |
| `submission_table` | VARCHAR(255) | NULLABLE | legacy custom table name |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(document_type, is_active)`

---

### `document_form_fields`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `form_id` | BIGINT UNSIGNED | NOT NULL, FK → document_forms | cascadeOnDelete |
| `field_key` | VARCHAR(255) | NOT NULL | unique ต่อ form |
| `label` | VARCHAR(255) | NOT NULL | |
| `field_type` | VARCHAR(255) | NOT NULL | `text/select/date/number/…` |
| `is_required` | BOOLEAN | NOT NULL, DEFAULT false | |
| `sort_order` | INT UNSIGNED | NOT NULL, DEFAULT 1 | |
| `placeholder` | VARCHAR(255) | NULLABLE | |
| `options` | JSON | NULLABLE | สำหรับ select/radio fields |
| `col_span` | (ดู migration) | NULLABLE | grid column span |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(form_id, field_key)` unique, `(form_id, sort_order)`

---

### `document_form_departments`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `form_id` | BIGINT UNSIGNED | NOT NULL, FK → document_forms | cascadeOnDelete |
| `department_id` | BIGINT UNSIGNED | NOT NULL, FK → departments | cascadeOnDelete |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(form_id, department_id)` unique
> ควบคุมว่า department ไหนเข้าถึง form ได้

---

### `document_form_submissions`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `form_id` | BIGINT UNSIGNED | NOT NULL, FK → document_forms | cascadeOnDelete |
| `user_id` | BIGINT UNSIGNED | NULLABLE | ไม่มี FK constraint |
| `department_id` | BIGINT UNSIGNED | NULLABLE, FK → departments | nullOnDelete |
| `payload` | JSON | NULLABLE | field values |
| `status` | ENUM | NOT NULL, DEFAULT `draft` | `draft` / `submitted` |
| `approval_instance_id` | BIGINT UNSIGNED | NULLABLE | ไม่มี FK constraint |
| `reference_no` | VARCHAR(255) | NULLABLE | running number |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(user_id, status)`, `(form_id, status)`

---

## 7. Equipment / CMMS Tables

---

### `equipment_categories`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | |
| `code` | VARCHAR(50) | NOT NULL, UNIQUE | |
| `description` | TEXT | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

---

### `equipment_locations`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | |
| `code` | VARCHAR(50) | NOT NULL, UNIQUE | |
| `building` | VARCHAR(255) | NULLABLE | |
| `floor` | VARCHAR(100) | NULLABLE | |
| `zone` | VARCHAR(100) | NULLABLE | |
| `description` | TEXT | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

---

### `equipment`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | |
| `code` | VARCHAR(100) | NOT NULL, UNIQUE | |
| `serial_number` | VARCHAR(255) | NULLABLE | |
| `equipment_category_id` | BIGINT UNSIGNED | NOT NULL, FK → equipment_categories | cascadeOnDelete |
| `equipment_location_id` | BIGINT UNSIGNED | NOT NULL, FK → equipment_locations | cascadeOnDelete |
| `company_id` | BIGINT UNSIGNED | NULLABLE, FK → companies | nullOnDelete |
| `branch_id` | BIGINT UNSIGNED | NULLABLE, FK → branches | nullOnDelete |
| `status` | VARCHAR(30) | NOT NULL, DEFAULT `'active'` | `active/inactive/under_repair/…` |
| `installed_date` | DATE | NULLABLE | |
| `warranty_expiry` | DATE | NULLABLE | |
| `specifications` | JSON | NULLABLE | specs ของอุปกรณ์ |
| `notes` | TEXT | NULLABLE | |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

---

## 8. Spare Parts Tables

---

### `spare_parts`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `code` | VARCHAR(100) | NOT NULL, UNIQUE | |
| `name` | VARCHAR(255) | NOT NULL | |
| `description` | TEXT | NULLABLE | |
| `unit` | VARCHAR(50) | NOT NULL, DEFAULT `'ชิ้น'` | หน่วยนับ |
| `equipment_category_id` | BIGINT UNSIGNED | NULLABLE, FK → equipment_categories | nullOnDelete |
| `min_stock` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | stock ขั้นต่ำ |
| `current_stock` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | stock ปัจจุบัน |
| `unit_cost` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | ราคาต่อหน่วย |
| `company_id` | BIGINT UNSIGNED | NULLABLE, FK → companies | nullOnDelete |
| `branch_id` | BIGINT UNSIGNED | NULLABLE, FK → branches | nullOnDelete |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

---

### `spare_part_transactions`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `spare_part_id` | BIGINT UNSIGNED | NOT NULL, FK → spare_parts | cascadeOnDelete |
| `transaction_type` | VARCHAR(20) | NOT NULL | `receive/issue/adjust/return` |
| `quantity` | DECIMAL(12,2) | NOT NULL | + รับเข้า, - จ่ายออก |
| `unit_cost` | DECIMAL(12,2) | NULLABLE | ราคาต่อหน่วย ณ เวลานั้น |
| `reference_type` | VARCHAR(100) | NULLABLE | polymorphic type |
| `reference_id` | BIGINT UNSIGNED | NULLABLE | polymorphic id |
| `note` | TEXT | NULLABLE | |
| `performed_by_user_id` | BIGINT UNSIGNED | NULLABLE, FK → users | nullOnDelete |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(spare_part_id, transaction_type)`, `(reference_type, reference_id)`

---

### `spare_part_requisition_items`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `approval_instance_id` | BIGINT UNSIGNED | NOT NULL, FK → approval_instances | cascadeOnDelete |
| `spare_part_id` | BIGINT UNSIGNED | NOT NULL, FK → spare_parts | cascadeOnDelete |
| `quantity_requested` | DECIMAL(12,2) | NOT NULL | |
| `quantity_issued` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | จ่ายจริง |
| `unit_cost` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | |
| `note` | TEXT | NULLABLE | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `approval_instance_id`

---

## 9. Procurement Tables

---

### `purchase_request_items`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `approval_instance_id` | BIGINT UNSIGNED | NOT NULL, FK → approval_instances | cascadeOnDelete |
| `item_name` | VARCHAR(255) | NOT NULL | |
| `qty` | DECIMAL(12,2) | NOT NULL | |
| `unit` | VARCHAR(255) | NOT NULL | |
| `unit_price` | DECIMAL(12,2) | NOT NULL | |
| `total_price` | DECIMAL(12,2) | NOT NULL | |
| `notes` | TEXT | NULLABLE | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `approval_instance_id`

---

### `purchase_order_items`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `approval_instance_id` | BIGINT UNSIGNED | NOT NULL, FK → approval_instances | cascadeOnDelete |
| `item_name` | VARCHAR(255) | NOT NULL | |
| `qty` | DECIMAL(12,2) | NOT NULL | |
| `unit` | VARCHAR(255) | NOT NULL | |
| `unit_price` | DECIMAL(12,2) | NOT NULL | |
| `total_price` | DECIMAL(12,2) | NOT NULL | |
| `notes` | TEXT | NULLABLE | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `approval_instance_id`

---

## 10. Notification Tables

---

### `notifications`  *(Laravel built-in)*

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | UUID | PK | |
| `type` | VARCHAR(255) | NOT NULL | notification class name |
| `notifiable_type` | VARCHAR(255) | NOT NULL | polymorphic type |
| `notifiable_id` | BIGINT UNSIGNED | NOT NULL | polymorphic id |
| `data` | TEXT | NOT NULL | JSON notification data |
| `read_at` | TIMESTAMP | NULLABLE | NULL = ยังไม่อ่าน |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(notifiable_type, notifiable_id)` (morphs)

---

### `notification_preferences`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `user_id` | BIGINT UNSIGNED | NOT NULL, FK → users | cascadeOnDelete |
| `event_type` | VARCHAR(100) | NOT NULL | เช่น `approval.requested` |
| `channel` | VARCHAR(50) | NOT NULL | `email` / `line` / `database` |
| `enabled` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(user_id, event_type, channel)` unique

---

## 11. Reporting Tables

---

### `report_dashboards`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `name` | VARCHAR(255) | NOT NULL | |
| `description` | TEXT | NULLABLE | |
| `layout_columns` | TINYINT UNSIGNED | NOT NULL, DEFAULT 2 | |
| `visibility` | ENUM | NOT NULL, DEFAULT `'all'` | `all` / `permission` |
| `required_permission` | VARCHAR(100) | NULLABLE | ตรวจสอบถ้า visibility=permission |
| `is_active` | BOOLEAN | NOT NULL, DEFAULT true | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users | nullOnDelete |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `is_active`

---

### `report_dashboard_widgets`

| Column | Type | Constraint | หมายเหตุ |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK | |
| `dashboard_id` | BIGINT UNSIGNED | NOT NULL, FK → report_dashboards | cascadeOnDelete |
| `title` | VARCHAR(255) | NOT NULL | |
| `widget_type` | ENUM | NOT NULL | `metric` / `chart` / `table` |
| `data_source` | VARCHAR(100) | NOT NULL | data source identifier |
| `config` | JSON | NULLABLE | chart config, filters ฯลฯ |
| `col_span` | TINYINT UNSIGNED | NOT NULL, DEFAULT 0 | 0 = full width |
| `sort_order` | INT UNSIGNED | NOT NULL, DEFAULT 1 | |
| `created_at` | TIMESTAMP | | |
| `updated_at` | TIMESTAMP | | |

**Indexes:** `(dashboard_id, sort_order)`

---

## 12. Relationship Diagram (Auth/RBAC — ย่อ)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                 users                                    │
│  id, first_name, last_name, email, company_id, branch_id,               │
│  department_id, position_id, is_active, is_super_admin, auth_provider   │
└──┬──────────────────┬───────────────────────┬────────────────┬──────────┘
   │ 1:N              │ 1:N                   │ 1:N            │ 1:N
   ▼                  ▼                       ▼                ▼
model_has_roles  model_has_permissions  sessions     personal_access_tokens
   │ N:1              │ N:1
   ▼                  ▼
 roles            permissions
   │ N:M (role_has_permissions)
   └──────────────────┘
```

```
departments ──< department_workflow_bindings >── approval_workflows
                                                       │ 1:N
                                               approval_workflow_stages

approval_instances (1 per submission)
       │ 1:N
approval_instance_steps (1 per stage)
```

---

## 13. Default Roles (Seeder)

| Role name | is_system | หมายเหตุ |
|---|---|---|
| `super-admin` | true | Bypass ทุก permission check (`Gate::before`) |
| `admin` | true | Full access ทุก module |
| `regional-admin` | false | **Phase 2 — ยังไม่ implement:** region scoping ยังไม่มีใน DB |
| `employee` | false | Read-only โมดูลธุรกิจ (ไม่รวม users/roles/permissions) — default role ของ user ใหม่ |

---

## 14. Custom Role Flow

```
User ─── Default role ──► ใช้ permission จาก role_has_permissions
User ─── Custom role  ──► ใช้ permission จาก model_has_permissions (direct)
                           (ไม่ assign role ใดๆ หรือ assign role = 'custom')
```
