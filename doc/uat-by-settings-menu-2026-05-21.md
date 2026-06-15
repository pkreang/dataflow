# UAT Checklist — Settings Menu (sidebar-ordered)

**บทบาทไฟล์นี้:** Menu-ordered UAT — เดินตามรายการเมนู "ตั้งค่า" ใน sidebar จากบนลงล่าง 24 รายการ เพื่อไม่ให้พลาดเมนูใด ขนาน (sibling) กับ `doc/uat-integration-merge-2026-05-21.md` ที่เรียงตาม feature/commit (A–M) — ใช้ที่ใดก็ได้แล้วแต่ถนัด เนื้อหาทับซ้อนกันบางส่วน

**ขอบเขต:** 24 leaves ใต้เมนู "ตั้งค่า" + cross-cutting smoke ครอบคลุม 27 commits ระหว่าง `main` (9b4381e) → HEAD ของ `integration`

- **HEAVY (13 รายการ):** มี commit ล่าสุดแตะ — ตรวจ CRUD + flow ต่อท้าย (downstream)
- **SMOKE (11 รายการ):** ไม่มี commit แตะ — แค่เปิด list, save round-trip, ไม่มี error

`composer test` 563/563 + `composer analyse` 0 errors ผ่านแล้ว — UAT manual จับ regression ที่ automated test ไม่ครอบคลุม

---

## Setup

```bash
cd /Users/pkreang/work/dataflow-wt-main/backend
composer dev   # web (127.0.0.1:8000) + queue + vite (5173) + log tail
# login: admin@example.com / password
```

DB ปัจจุบัน clean-slate: 1 admin user, 4 roles, 25 permissions, 35 menus

---

## Conventions

- เครื่องหมาย `[H]` = HEAVY, `[S]` = SMOKE
- "Flow trace" = ทดสอบต่อเนื่องจาก settings menu ไปยัง flow ปลายทาง (workflow/submission/webhook receiver/mobile)
- URL prefix mismatch: `#1`, `#4`, `#5`, `#6` ไม่อยู่ใต้ `/settings/*` แต่เป็นส่วนหนึ่งของกลุ่มเมนู Settings (จัดกลุ่มใน sidebar)

---

## 1. Organizations [H] — `/profile`

**Permission:** `manage profile` · **Recent:** `8c8598b` (simplify address — drop lock + legacy freetext)

- [ ] เปิดหน้า → list company/branch โหลด, ไม่มี error
- [ ] กดแก้ company → ฟอร์ม address **ไม่มี** ปุ่ม lock
- [ ] **ไม่มี** ฟิลด์ freetext address เก่า (เหลือเฉพาะ structured: จังหวัด/อำเภอ/ตำบล/zipcode)
- [ ] เปลี่ยนจังหวัด → อำเภอ cascade reload → ตำบล reload → zipcode autofill
- [ ] Save → reload → ค่าทุก field กลับมาตรง
- [ ] Branch ใต้ company → flow address เดียวกัน

**Flow trace:** สร้าง branch ใหม่ใน company → ไปสร้าง user → เลือก branch นี้ → header user-edit แสดงชื่อ branch ถูก

---

## 2. Departments [S] — `/settings/departments`

**Permission:** `manage_settings` (super-admin only) · **Recent:** `2a4bd19` (auto_code column hidden)

- [ ] เปิด list → ไม่มีคอลัมน์ "รหัสระบบ / auto_code"
- [ ] สร้าง department ใหม่ → field `รหัส` มี prefix auto-fill (เช่น `DEPT-001`); ทับเองได้ → save → ไม่ error
- [ ] auto_code ยังถูก generate ใน DB (ตรวจ detail หรือ tinker)

---

## 3. Positions [S] — `/settings/positions`

**Permission:** `manage_settings` · **Recent:** `2a4bd19`

- [ ] เปิด list → ไม่มีคอลัมน์ auto_code
- [ ] สร้าง position ใหม่ → prefix auto-fill (`POS-001`) → save

---

## 4. Users [H] — `/users`  (ไม่อยู่ใต้ /settings/*)

**Permission:** `user_access.read` · **Recent:** `4a878b9` (admin password reset + post-create redirect to edit)

- [ ] เปิด list → ตารางโหลด, ไม่มีคอลัมน์ auto_code
- [ ] **Post-create redirect:** กดสร้าง user ใหม่ → กรอกแค่ required → save → redirect ไปหน้า **edit** (ไม่ใช่ list)
- [ ] หน้า edit ของ super-admin มี section "ตั้งรหัสผ่านใหม่"
- [ ] กรอกรหัสใหม่ + save → user ตัวนั้น login ด้วยรหัสใหม่ได้
- [ ] Non-super-admin เปิดหน้า edit ของผู้อื่น → **ไม่เห็น** password section (หรือ 403)
- [ ] กด "ส่ง link เปลี่ยนรหัส" → email log/queue ออก (ตรวจ `storage/logs/laravel.log` หรือ MAILDEV)

**Flow trace (password lifecycle):**
1. Login เป็น admin → สร้าง user ใหม่ + ตั้งรหัส + ติ๊ก "บังคับเปลี่ยนรหัสเมื่อ login ครั้งแรก" (ถ้ามี toggle)
2. Logout → login เป็น user ใหม่ ด้วยรหัสที่ admin ตั้ง
3. ถ้า flag `password_change_required = true` → ถูก redirect ไปหน้าเปลี่ยนรหัส (middleware `EnforcePasswordChange`)
4. เปลี่ยนรหัส → กลับสู่ flow ปกติ
5. ตรวจ DB: row ใหม่ใน `user_password_histories`

---

## 5. Roles [H] — `/roles`  (ไม่อยู่ใต้ /settings/*)

**Permission:** `role_access.read` · **Recent:** `0962787` (write-guard), `79de956` (RBAC overview)

- [ ] เปิด `/roles` → list role 4 ตัว (admin, manager, staff, employee หรือ seed)
- [ ] กดปุ่ม "ดูภาพรวมสิทธิ์" → ไปหน้า `/roles/overview` → matrix table role × permission
- [ ] ค้นหา permission ใน overview ทำได้
- [ ] Footnote เรื่อง `is_super_admin` ปรากฏ (super-admin ไม่ผูก permission ตรง — bypass ทั้งหมด)
- [ ] กดแก้ role → ปุ่ม "เลือกทั้งกลุ่ม / ล้าง" ต่อ module → ติ๊ก permissions ทีละตัวได้
- [ ] **แถบสรุป "N / M สิทธิ์"** อัปเดต live ตอนติ๊ก/ปลด
- [ ] Save → reload → ค่าตรง

**Flow trace (write-guard):**
1. สร้าง user ใหม่ (non-super-admin) ผ่อนสิทธิ์ → ให้ role ที่ไม่มี `role_access.update`
2. Login เป็น user นั้น → เปิด `/roles/create` ตรง URL → **403**
3. เปิด `/roles/{id}/edit` → **403**
4. Logout

---

## 6. Permissions [H] — `/permissions`  (ไม่อยู่ใต้ /settings/*)

**Permission:** `permission_access.read` · **Recent:** `0962787` (write-guard), `fe0a70b` (menu permission gates route)

- [ ] เปิด list → permissions 25 ตัวทั้งหมดโชว์
- [ ] **ลบ permission ที่ผูก role อยู่:** กดลบ → ระบบบอก "กำลังถูกใช้งาน" → ลบไม่ได้

**Flow trace (menu permission gates route — สำคัญ!):**
1. ไป `/settings/navigation` → หา leaf ใด ๆ ที่ไม่จำเป็น (เช่น "Branding") → ตั้ง `permission` = สตริงสมมติเช่น `branding.manage_super_secret`
2. สร้าง permission `branding.manage_super_secret` ที่ `/permissions/create` แล้วผูกกับ role admin เท่านั้น
3. Login เป็น user non-admin (ไม่มี permission นี้) → เปิด `/settings/branding` ตรง URL → **403** (ไม่ใช่แค่ซ่อนเมนู — middleware `EnforceMenuPermission` ต้องยิง 403)
4. ลบ permission test กลับสู่สภาพเดิม

---

## 7. Password Policy [S] — `/settings/password-policy`

**Permission:** `user_access.update` · **Recent:** ไม่มี

- [ ] เปิด → ฟอร์ม singleton settings โหลด
- [ ] เปลี่ยนค่า min length / require numeric / expire days → save → reload → ค่าตรง

---

## 8. Authentication & SSO [S] — `/settings/authentication`

**Permission:** `manage_settings` · **Recent:** ไม่มี

- [ ] เปิด → ฟอร์ม singleton (Local/Entra/LDAP toggles) โหลด
- [ ] Toggle login mode (ไม่ต้อง save ถ้าจริง ๆ ไม่ได้ใช้ — แค่ดูว่า field ตอบสนอง) → ไม่มี JS error

---

## 9. Document Types [H] — `/settings/document-types`

**Permission:** `manage_settings` (super-admin) · **Recent:** `2a4bd19` (auto_code hidden), `d68255f` (icon picker + whitelist)

- [ ] List → ไม่มีคอลัมน์ auto_code
- [ ] กด create → field "icon" เป็น **picker UI** (ไม่ใช่ text input)
- [ ] เลือก icon จาก IconCatalog whitelist → preview ปรากฏ
- [ ] Field `code` validation: รับเฉพาะ snake_case (`my_doctype` ✓, `MyDoctype` ✗, `my-doctype` ✗)
- [ ] Save → list/badge แสดง icon ที่เลือก

**Flow trace:** ใช้ doctype ที่เพิ่งสร้างไปสร้างฟอร์มใหม่ที่ #10 → badge ใน list ฟอร์มแสดง icon ถูก

---

## 10. Document Forms [H] — `/settings/document-forms`

**Permission:** `manage_settings` · **Recent:** `17e4890` (formula field + KPI Cycles), `2a4bd19` (auto_code hidden)

- [ ] List → ไม่มีคอลัมน์ auto_code
- [ ] กด create form ใหม่ → ตั้งชื่อ + เลือก doctype ที่สร้างใน #9
- [ ] เพิ่ม field type "formula" → editor ขึ้นช่องสูตร
- [ ] ลองสูตร:
  - [ ] `score_a + score_b` (number ปกติ)
  - [ ] `(a*0.4)+(b*0.6)` (weighted)
  - [ ] `if(x>50, "ผ่าน", "ไม่ผ่าน")` (conditional)
- [ ] เพิ่ม field ต้นทาง (number) ให้สูตรอ้างถึง
- [ ] Save form

**Flow trace (submit + JS↔PHP parity):**
1. กรอกฟอร์มที่สร้าง → field ต้นทางใส่ค่า → field formula update ค่าทันที (JS preview)
2. Save → reload submission → ค่าใน formula field ใน DB **เท่ากับ** ค่าใน JS preview (server เป็น authoritative; ดู `tests/Feature/EvaluateRulesPhpTest.php` 17 cases)
3. **Edge:** หารด้วยศูนย์ (`a/0`) → ไม่ crash, แสดง 0 หรือ error message graceful
4. **Edge:** สูตรอ้าง field key ที่ไม่มี → ไม่ crash, fallback 0 หรือ null

---

## 11. Lookups [S] — `/settings/lookups`

**Permission:** `manage_settings` · **Recent:** `2a4bd19` (auto_code hidden)

- [ ] List → ไม่มีคอลัมน์ auto_code
- [ ] เปิด lookup ตัวใดก็ได้ → list values โหลด
- [ ] เพิ่ม value ใหม่ → save → ปรากฏใน dropdown ที่ document-form

---

## 12. Workflow [S] — `/settings/workflow`

**Permission:** `manage_settings` · **Recent:** ไม่มี

- [ ] List workflows โหลด
- [ ] เปิด workflow ใดก็ได้ → designer/stages โหลด, ไม่ JS error

---

## 13. Running Numbers [S] — `/settings/running-numbers`

**Permission:** `manage_settings` · **Recent:** ไม่มี

- [ ] List running numbers โหลด
- [ ] เปิดตัวใดก็ได้ → ดู prefix/pattern → save no-op ได้

---

## 14. Approval Routing [H] — `/settings/approval-routing`

**Permission:** `manage_settings` · **Recent:** `5a3f112` (send-back), `74144d6` (remove emerald help banner)

- [ ] เปิดหน้า → **ไม่มี** emerald help banner สีเขียวอ่อนแล้ว
- [ ] binding department→workflow ดูถูก

**Flow trace (send-back end-to-end):**
1. สร้าง form ใหม่ที่ผูก workflow 3 stages (หรือใช้ของเดิม)
2. Login เป็น user ทั่วไป → submit form → ไปขั้น approver 1
3. Login เป็น approver 1 → เปิด `/approvals/my` → กด action "ส่งกลับ"
4. ดู destination dropdown:
   - [ ] "กลับไปยังผู้ขอ (requester)" มี
   - [ ] "กลับขั้นก่อนหน้า (previous step)" — ที่ขั้น 1 → **disabled** หรือไม่แสดง; ที่ขั้น ≥2 → แสดงได้
5. ทดสอบส่งกลับ requester:
   - [ ] Comment บังคับ (validation) เมื่อ return
   - [ ] Submit → status เปลี่ยนเป็น `returned`
   - [ ] Login เป็น requester เดิม → เห็น draft กลับมาแก้ได้
6. ทดสอบส่งกลับ previous step (ที่ขั้น ≥2):
   - [ ] เลือก previous → submit → กลับไป approver ขั้นก่อน → approve ใหม่ → flow ต่อไป
7. ตรวจ audit log:
   - [ ] timeline บันทึก action `returned` พร้อม comment

---

## 15. KPI Cycles [H] — `/settings/kpi-cycles`

**Permission:** `manage_settings` · **Recent:** `17e4890`

- [ ] เปิดเมนู → list (น่าจะว่างใน clean slate)
- [ ] สร้าง cycle: ตั้งชื่อ + period (start/end) + เลือก form (ที่มี formula field — ใช้ที่สร้างใน #10) + assign users → save
- [ ] แก้ cycle: เพิ่ม/ลบ assignment, เปลี่ยน period → save
- [ ] กด **Open** → status เปลี่ยน "open" → ตรวจ DB: `document_form_submissions` ใหม่ถูกสร้างให้ assignee แต่ละคน

**Flow trace (end-to-end with formula):**
1. Logout admin → login เป็น assignee
2. เปิด `/m/forms` หรือเมนู "งานของฉัน" → เห็นฟอร์ม KPI cycle ที่ถูก assign
3. กรอกค่า number → formula field คำนวณ → save submission
4. Logout → login admin → กดปิด cycle (Close)
5. เปิด `/settings/kpi-cycles/{id}/report` → ตารางแสดง assignee × score → ค่าตรงกับที่ submit

---

## 16. Branding [S] — `/settings/branding`

**Permission:** `manage_settings` · **Recent:** ไม่มี

- [ ] เปิดหน้า → ฟอร์ม logo / favicon / สีหลัก / background โหลด
- [ ] อัพโหลด logo ขนาดเล็ก → save → preview เปลี่ยน → reload → คงไว้

---

## 17. Notifications [S] — `/settings/notifications`

**Permission:** `manage_settings` · **Recent:** `74144d6` (banner removal touched evaluation notifications)

- [ ] เปิด list channel/template → โหลด
- [ ] เปิด template ใดก็ได้ → edit → save no-op

---

## 18. Branch scoping [S] — `/settings/branch-scoping`

**Permission:** `manage_settings` · **Recent:** ไม่มี (commit `7a94602` เก่ากว่า diff range แต่ feature flag ยังต้อง smoke)

- [ ] เปิดหน้า → toggle/setting โหลด
- [ ] Toggle "Branches" **OFF** → ไป `/equipment` → คอลัมน์/filter branch หายไป
- [ ] Toggle **ON** → กลับมาแสดง branch
- [ ] ค่า branch ใน DB ของ equipment เดิมยังอยู่ (ไม่ถูกลบตอน toggle off)

---

## 19. Menu Manager [H] — `/settings/navigation`

**Permission:** `manage_settings` · **Recent:** `fe0a70b` (navigation_menus.permission gates route via EnforceMenuPermission)

- [ ] เปิด → tree เมนู 35 รายการโหลด
- [ ] Drag-reorder leaf หนึ่ง → save → reload หน้าอื่น → sidebar เรียงใหม่
- [ ] เพิ่ม leaf ใหม่ + ตั้ง route + permission → save → cache clear → เห็นใน sidebar (ของ user ที่มีสิทธิ์)

**Flow trace (gating already covered in #6):** ข้าม — ตรวจไปแล้วใน Permissions

---

## 20. Activity History [S] — `/settings/activity-history`

**Permission:** `manage_settings` · **Recent:** ไม่มี

- [ ] เปิด → log read-only โหลด, มี entry จากการกระทำใน UAT ที่ผ่านมา (login/create/update)
- [ ] Filter by user / by action → reload → result กรองถูก

---

## 21. Dashboards [H] — `/settings/dashboards`

**Permission:** `manage_settings` · **Recent:** `2a4bd19` (auto_code hidden), `f0001a1` (configurable home dashboard — outside diff range but feature still UAT)

- [ ] List → ไม่มีคอลัมน์ auto_code
- [ ] สร้าง dashboard ใหม่ + กำหนด visibility = **public**
- [ ] สร้าง dashboard อีกอันด้วย visibility = **owner-only**
- [ ] เปิดหน้า `/` (home) → ไม่ใช่ hardcoded KPI grid แล้ว — แสดง widget configurable

**Flow trace (visibility):**
1. Login เป็น user B (non-super-admin, ไม่ใช่ owner) → เปิด owner-only dashboard URL → **403**
2. Logout → login admin → เปิด owner-only ของ user คนอื่น → **200** (super-admin bypass)
3. Public dashboard → user ใดก็เปิดได้

---

## 22. Outgoing Webhooks [H] — `/settings/integrations`

**Permission:** `manage_settings` · **Recent:** `9a8d343`

- [ ] เปิด list webhook → โหลด
- [ ] เปิดเทอร์มินัล terminal อีกตัวรัน receiver: `nc -l 9999` (หรือใช้ https://webhook.site)
- [ ] สร้าง webhook ใหม่ → URL = `http://127.0.0.1:9999` (หรือ webhook.site URL) → เลือก event = `approval.submitted` (หรือ trigger ใด ๆ) → save
- [ ] กดปุ่ม **Test send** → ตรวจ receiver: เห็น POST request + payload JSON

**Flow trace (trigger from real event):**
1. ไป submit ฟอร์มที่ผูก workflow → ผ่าน approve ขั้นแรก
2. กลับมา receiver terminal → ตรวจว่า webhook ส่ง POST ออกตอน event เกิด
3. Payload มี `event`, `submission_id`, `actor`, `timestamp` (หรือตาม schema ของ system)
4. ตรวจ queue: queue worker ใน `composer dev` จัด job ส่ง webhook ผ่าน → ไม่มี failed_jobs

---

## 23. Incoming Webhooks [H] — `/settings/inbound-webhooks`

**Permission:** `manage_settings` · **Recent:** `9a8d343`

- [ ] เปิด list endpoint → โหลด
- [ ] สร้าง endpoint ใหม่ + ตั้ง secret + ผูก action (เช่น สร้าง submission) → save → ได้ URL endpoint + secret

**Flow trace (receive end-to-end):**
1. คัดลอก URL endpoint + secret
2. `/usr/bin/curl -X POST <endpoint_url> -H "X-Webhook-Secret: <secret>" -H "Content-Type: application/json" -d '{"field_a": "test", "field_b": 42}'`
3. ตอบกลับ 2xx
4. ตรวจ DB / target table → row ใหม่ถูกสร้าง / submission อยู่
5. กดปุ่ม **Test receive** ใน UI → ใช้ payload ตัวอย่าง → ดู log
6. ลอง POST ด้วย secret ผิด → **401/403**

---

## 24. Evaluation Form Settings [H] — `/settings/evaluation-form`

**Permission:** `manage_settings` · **Recent:** `9a8d343` (evaluations), `74144d6` (banner removal)

- [ ] เปิดหน้า → **ไม่มี** emerald help banner
- [ ] List ฟอร์ม `document_type=evaluation` → โหลด
- [ ] เปิด/สร้าง evaluation form → fields เซ็ตได้

**Flow trace (mobile evaluate end-to-end):**
1. ผูก evaluation form กับ doctype/flow ที่เหมาะสม
2. ไป submit request ปกติ → request ปิด job แล้วเข้า evaluation queue
3. Login เป็น assignee/evaluator → เปิด `/m/requests/{submission_id}/evaluate`
4. ดูฟอร์ม evaluation render บน mobile → กรอก rating/comment → submit
5. กลับ desktop → ดู submission → status update + evaluation score ปรากฏ

---

## Cross-cutting smoke

- [ ] **Density toggle:** กดปุ่ม density ที่ header → switch comfortable ↔ compact → DOM update + reload → state persist (ตรวจ `users.density` หรือ localStorage)
- [ ] **Language switch:** EN ↔ TH ที่ profile dropdown → reload → label ใหม่ที่ KPI Cycles / formula / send-back / pw-reset / icon picker ครบ
- [ ] **`composer test`:**
  ```
  cd /Users/pkreang/work/dataflow-wt-main/backend
  composer test
  # expect: 563 passed
  ```
- [ ] **`composer analyse`:**
  ```
  composer analyse
  # expect: 0 errors
  ```

---

## Sign-off

| # | เมนู | Tier | ผ่าน | ผู้ตรวจ | วันที่ | Note |
|--:|------|:----:|:----:|---------|--------|------|
|  1 | Organizations           | H | ☐ | | | |
|  2 | Departments             | S | ☐ | | | |
|  3 | Positions               | S | ☐ | | | |
|  4 | Users                   | H | ☐ | | | |
|  5 | Roles                   | H | ☐ | | | |
|  6 | Permissions             | H | ☐ | | | |
|  7 | Password Policy         | S | ☐ | | | |
|  8 | Auth & SSO              | S | ☐ | | | |
|  9 | Document Types          | H | ☐ | | | |
| 10 | Document Forms          | H | ☐ | | | |
| 11 | Lookups                 | S | ☐ | | | |
| 12 | Workflow                | S | ☐ | | | |
| 13 | Running Numbers         | S | ☐ | | | |
| 14 | Approval Routing        | H | ☐ | | | |
| 15 | KPI Cycles              | H | ☐ | | | |
| 16 | Branding                | S | ☐ | | | |
| 17 | Notifications           | S | ☐ | | | |
| 18 | Branch scoping          | S | ☐ | | | |
| 19 | Menu Manager            | H | ☐ | | | |
| 20 | Activity History        | S | ☐ | | | |
| 21 | Dashboards              | H | ☐ | | | |
| 22 | Outgoing Webhooks       | H | ☐ | | | |
| 23 | Incoming Webhooks       | H | ☐ | | | |
| 24 | Evaluation Form         | H | ☐ | | | |
|  – | Cross-cutting smoke     | – | ☐ | | | |

---

## Merge command (เมื่อผ่านครบ)

```bash
cd /Users/pkreang/work/dataflow
git checkout main
git pull
git merge --no-ff integration -m "merge: integration → main (UAT signed off YYYY-MM-DD)"
git push origin main
```
