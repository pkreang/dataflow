# UAT Checklist — `integration` → `main` merge (2026-05-21)

**บทบาทไฟล์นี้:** Delta UAT checklist เฉพาะกิจสำหรับ merge `integration` → `main` รอบนี้ — จี้เฉพาะ surface ที่เปลี่ยนใน 27 commits (2026-05-11 ถึง 2026-05-21) ครอบคลุม KPI Cycles, formula field, approval send-back, RBAC overhaul, auto-code expansion, mobile app, webhooks, evaluations, my-reports และ UI cleanups. **ไม่ใช่** clean-slate walkthrough — ถ้าต้องการ flow ครบจากศูนย์ ดู `doc/uat-clean-slate-walkthrough.md`

`composer test` 563/563 + PHPStan 0 errors ผ่านแล้ว — UAT manual เพื่อจับ regression ที่ automated test ไม่ครอบคลุม (visual, flow, UX)

---

## Setup

```bash
cd /Users/pkreang/work/dataflow-wt-main/backend
composer dev   # web + queue + vite
# login: admin@example.com / password
```

ใช้ demo seed ของ vertical ใดก็ได้ (school หรือ factory) — เลือกหนึ่งตามถนัด

---

## A. KPI Cycles + Formula field (commit 17e4890) ★ ใหม่ทั้งหมด

**เมนูใหม่:** `ตั้งค่า → KPI Cycles` (เห็นเฉพาะ super-admin / มีสิทธิ์)

- [ ] เปิดเมนู KPI Cycles → list ว่างหรือมีตัวอย่าง
- [ ] **สร้าง cycle ใหม่:** ตั้งชื่อ + period + assign user → save → กลับมา list เห็นแถว
- [ ] **แก้ cycle:** เพิ่ม assignment, ลบ assignment, เปลี่ยน period
- [ ] **เปิด cycle (open):** กดเปิด → status เปลี่ยน, ฟอร์มสำหรับ assignee ถูกสร้าง
- [ ] **ดูรายงาน:** `/settings/kpi-cycles/{id}/report` → ตารางสรุปแสดง assignee × score
- [ ] **Formula field:** สร้างฟอร์มใหม่ → เพิ่ม field type "formula" → ตั้งสูตร เช่น `score_a + score_b`, `(a*0.4)+(b*0.6)`, `if(x>50, "ผ่าน", "ไม่ผ่าน")`
- [ ] **Submit ฟอร์มที่มี formula:** กรอก field ต้นทาง → formula field อัปเดตค่าทันที (JS) → save → reload → ค่าตรง (PHP eval ต้อง match)
- [ ] **Edge case:** หารด้วยศูนย์ → ไม่ crash, แสดง error/0 graceful
- [ ] **Edge case:** สูตรอ้าง field ที่ไม่มี → ไม่ crash

---

## B. Approval Send-back (commit 5a3f112) ★ ใหม่

**ที่:** `/approvals/my` (รายการรออนุมัติ)

- [ ] มี action ใหม่ "ส่งกลับ" (return) นอกจาก approve/reject
- [ ] **ส่งกลับไปยังผู้ขอ (requester):** เลือก destination = requester + comment → submit → status เปลี่ยนเป็น `returned` → requester เห็น draft กลับมาแก้ได้
- [ ] **ส่งกลับขั้นก่อนหน้า (previous step):** ที่ขั้น ≥2 → เลือก destination = previous → กลับไป approver ขั้นก่อน → approve ใหม่ → flow ดำเนินต่อ
- [ ] ขั้น 1 ไม่ควรมี option "previous step" (หรือ disabled)
- [ ] Comment บังคับ (validation) เมื่อ return
- [ ] Timeline/audit log บันทึก action `returned`

---

## C. Admin Password Reset (commit 4a878b9)

**ที่:** `/users` → edit user

- [ ] หน้า edit user ของ super-admin มี section "ตั้งรหัสผ่านใหม่"
- [ ] กรอกรหัสใหม่ → save → user ตัวนั้น login ด้วยรหัสใหม่ได้
- [ ] **Post-create redirect:** สร้าง user ใหม่ → ระบบ redirect ไปหน้า **edit** ของ user นั้น (ไม่ใช่กลับ list) → ผู้ admin ตั้งรหัสได้ทันที
- [ ] User ที่ถูกเปลี่ยนรหัส → ครั้งต่อไป login → ตาม policy (อาจถูกบังคับเปลี่ยน — เช็คตาม `password_change_required` flag)
- [ ] Non-super-admin เปิดหน้า edit user คนอื่น → **ไม่เห็น** password section (หรือ 403)

---

## D. Company/Branch Address (commit 8c8598b)

**ที่:** `/companies` → edit company / branch

- [ ] **ไม่มี** ปุ่ม lock ที่ฟิลด์ address แล้ว
- [ ] **ไม่มี** legacy freetext address field แล้ว (ใช้ structured: จังหวัด/อำเภอ/ตำบล/zipcode)
- [ ] เปลี่ยนจังหวัด → อำเภอ cascade reload, ตำบล reload, zipcode autofill
- [ ] Save → reload → ค่าทุก field กลับมาตรง
- [ ] Branch ใต้ company → flow address เดียวกัน

---

## E. Auto-code column hidden (commit 2a4bd19) — visual

**ทดสอบ:** เปิดหน้า list ของ modules ต่อไปนี้

- [ ] ไม่มีคอลัมน์ `รหัสระบบ / auto_code` ในหน้า list/edit ของ: companies, departments, positions, users, document-types, document-forms, equipment, equipment-categories, equipment-locations, lookups, running-numbers, navigation, dashboards, pm/plans, workflow
- [ ] auto_code ยังถูก generate อยู่ (เช็คใน DB หรือ detail view) — แค่ซ่อนจาก UI

---

## F. RBAC: Permission overview + Write-guard (commits 0962787, 79de956, fe0a70b)

**ที่:** `/roles` (และ navigation menu management)

- [ ] **ปุ่ม "ดูภาพรวมสิทธิ์"** → matrix table role × permission ทั้งระบบ → ค้นหาได้ → footnote เรื่อง `is_super_admin`
- [ ] **แก้ role:** ปุ่ม "เลือกทั้งกลุ่ม / ล้าง" per module → ติ๊กทีละ permission ได้ → **แถบสรุป "N / M สิทธิ์"** อัปเดตทันที (live)
- [ ] **Write-guard:** สร้าง user ที่ไม่ใช่ super-admin → login → เปิด `/settings/roles/create` → ได้ **403** (เช่นเดียวกัน users/create, permissions/create)
- [ ] **Menu permission gates route:** ตั้ง permission ของเมนูใน `navigation_menus` → user ที่ไม่มี permission นั้น → เปิด URL ตรง ๆ → **403** (ไม่ใช่แค่ซ่อนเมนู)

---

## G. Auto-code for masters (commits 6026283, d46ce73, 8a23bb3)

**ทดสอบ:** สร้าง entity ใหม่ใน modules ต่าง ๆ

- [ ] departments, positions, equipment-categories, equipment-locations + 13 entities อื่น ๆ → field `รหัส (code)` มีค่า prefix อัตโนมัติเมื่อเปิดหน้า create (เช่น `DEPT-001`, `POS-001`)
- [ ] User กรอก code เอง override ได้ (manual entry ชนะ auto-generated)
- [ ] Save แล้วซ้ำ → validation บอกว่า code ซ้ำ ไม่ทับเงียบ ๆ

---

## H. Equipment Registry + Branches feature flag (commit 7a94602)

- [ ] Settings → Features → toggle "Branches" **OFF** → equipment registry **ไม่แสดง** คอลัมน์/filter branch
- [ ] Toggle **ON** → กลับมาแสดง
- [ ] ค่า branch ที่บันทึกไว้ยังอยู่ใน DB (ไม่หายตอน toggle off)

---

## I. Document Type icon picker (commit d68255f)

**ที่:** `/settings/document-types/create` หรือ edit

- [ ] Field "icon" มี picker UI (ไม่ใช่ text input) → เลือกได้จาก IconCatalog whitelist
- [ ] เลือก icon → preview ปรากฏ → save → list/badge แสดง icon นั้น
- [ ] Field "code" validation: รับเฉพาะ snake_case (`my_doctype` ผ่าน, `MyDoctype` หรือ `my-doctype` ไม่ผ่าน)

---

## J. Items-per-page persistence (commit 1cff8d6)

**ทดสอบ:** หน้า list ใดก็ได้ที่มี pagination

- [ ] เปลี่ยน items-per-page เป็น 50 → reload หน้า → ยังเป็น 50 (persist)
- [ ] เปลี่ยนเป็น 100 ที่ list อื่น → list แรกยังเป็น 50 (per-list ไม่ใช่ global)
- [ ] Settings/index ดู layout ใหม่

---

## K. Configurable Home Dashboard (commit f0001a1)

**ที่:** หน้า `/` (dashboard)

- [ ] ไม่ใช่ hardcoded KPI grid แล้ว — แสดง widget configurable
- [ ] Settings → Dashboards → สร้าง dashboard ใหม่ → ตั้ง visibility (public/owner-only)
- [ ] Owner-only dashboard: เจ้าของเปิดได้, user อื่นเปิดได้ **403**, super-admin เปิดได้ทุกอัน

---

## L. Mobile App + Webhooks + My Reports + Evaluations (commit 9a8d343)

**Mobile app:**
- [ ] `/m/login` (guest) → login → redirect ไป `/m/me` → mobile-optimized UI โหลด
- [ ] `/m/forms`, `/m/requests`, `/m/approvals`, `/m/reports`, `/m/write` เข้าถึงได้
- [ ] Submit ฟอร์มจากมือถือ → save ใน DB ปกติ

**Webhooks:**
- [ ] **Outbound:** `/settings/integrations` → สร้าง webhook → trigger event (เช่น approval) → webhook ส่ง POST → ตรวจ payload ใน receiver
- [ ] **Inbound:** `/settings/inbound-webhooks` → สร้าง endpoint → test → ดู payload

**My Reports:**
- [ ] `/my-reports` → list dashboard ที่ user เข้าถึงได้
- [ ] เปิด report → render widget ถูก

**Evaluations:**
- [ ] เมนู Evaluations (ดูจาก sidebar) → flow assessment ทำได้

---

## M. Cross-cutting smoke

- [ ] **Density toggle:** กดปุ่ม density ที่ header → switch comfortable/compact → DOM update + persist หลัง reload
- [ ] **Language switch:** EN ↔ TH → label ทุกหน้าใหม่แปลครบ (KPI Cycles, formula, send-back, password reset)
- [ ] **Navigation Menu management:** `/settings/navigation` → drag-reorder → save → sidebar reflect ใหม่
- [ ] **Help banners (emerald):** หน้า evaluation + approval-routing **ไม่มี** emerald help banner แล้ว (commit 74144d6)
- [ ] **`composer test` หลัง pull ล่าสุด:** เขียว 563/563

---

## Sign-off

| ส่วน | ผ่าน | ผู้ตรวจ | วันที่ | Note |
|------|:----:|---------|--------|------|
| A. KPI Cycles + Formula | ☐ | | | |
| B. Approval Send-back | ☐ | | | |
| C. Admin Password Reset | ☐ | | | |
| D. Company/Branch Address | ☐ | | | |
| E. Auto-code column hidden | ☐ | | | |
| F. RBAC overview + write-guard | ☐ | | | |
| G. Auto-code masters | ☐ | | | |
| H. Equipment Branches flag | ☐ | | | |
| I. Doctype icon picker | ☐ | | | |
| J. Items-per-page persistence | ☐ | | | |
| K. Home dashboard configurable | ☐ | | | |
| L. Mobile/Webhooks/Reports/Eval | ☐ | | | |
| M. Cross-cutting smoke | ☐ | | | |

---

## Merge command (เมื่อผ่านครบ)

```bash
cd /Users/pkreang/work/dataflow
git checkout main
git pull
git merge --no-ff integration -m "merge: integration → main (UAT signed off YYYY-MM-DD)"
git push origin main
```
