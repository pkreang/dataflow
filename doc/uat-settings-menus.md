# UAT — Settings menus walkthrough

ใช้ checklist นี้ทดสอบเมนูตั้งค่าทั้ง 21 รายการแบบ end-to-end หลังจาก deploy ใหญ่ หรือเมื่อปั้น vertical/template ใหม่

## Prerequisites

```bash
cd backend
composer setup            # ครั้งแรก
php artisan migrate:fresh --seed
composer dev              # web + queue + vite
```

- เปิด `http://localhost:8000`
- login เป็น **super-admin** (`admin@example.com` / `password` จาก `DatabaseSeeder`)
- toggle ภาษา EN ↔ TH ทดสอบทั้งสอง (icon มุมขวาบน)
- toggle density Comfortable ↔ Compact (icon ข้างๆ ภาษา)
- เปิด devtools console ดู JavaScript error

## Checklist (21 เมนู)

ต่อเมนู ติ๊กถ้าผ่าน — ถ้าไม่ผ่านบันทึก issue ที่ปลาย doc

| # | Menu | URL | (1) เข้าได้ | (2) Render ไม่มี error | (3) Create | (4) Edit | (5) Delete |
|---|------|-----|--|--|--|--|--|
| 1 | Organizations | `/profile` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 2 | Departments | `/settings/departments` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 3 | Positions | `/settings/positions` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 4 | Users | `/users` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 5 | Roles | `/roles` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 6 | Permissions | `/permissions` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 7 | Password Policy | `/settings/password-policy` | ☐ | ☐ | — | ☐ save | — |
| 8 | Authentication & SSO | `/settings/authentication` | ☐ | ☐ | — | ☐ save | — |
| 9 | Document Types | `/settings/document-types` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 10 | Document Forms | `/settings/document-forms` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 11 | Lookups | `/settings/lookups` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 12 | Workflow | `/settings/workflow` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 13 | Running Numbers | `/settings/running-numbers` | ☐ | ☐ | ☐ | ☐ | ☐ |
| 14 | Approval Routing | `/settings/approval-routing` | ☐ | ☐ | — | ☐ save | — |
| 15 | Dept ↔ Workflow | `/settings/department-workflow-bindings` | ☐ | ☐ | — | ☐ save | — |
| 16 | Logo & Background | `/settings/branding` | ☐ | ☐ | — | ☐ upload | — |
| 17 | Notifications | `/settings/notifications` | ☐ | ☐ | — | ☐ save | — |
| 18 | Campus / Branch Scoping | `/settings/branch-scoping` | ☐ | ☐ | — | ☐ save | — |
| 19 | Menu Manager | `/settings/navigation` | ☐ | ☐ | ☐ | ☐ + reorder + toggle | ☐ |
| 20 | Activity History | `/settings/activity-history` | ☐ | ☐ | — read-only | — | — |
| 21 | Dashboards | `/settings/dashboards` | ☐ | ☐ | ☐ + widget | ☐ | ☐ |

**Automated coverage:** Phase 1+2 smoke + Phase 3 CRUD ครอบคลุมข้อ (1) + (2) + (3-5) อยู่แล้ว — manual UAT นี้เน้น JavaScript/Alpine/visual regression ที่ phpunit จับไม่ได้

## ขั้นตอนพิเศษต่อเมนู

### Document Forms (#10)
- สร้างฟอร์มใหม่ → builder UI ต้องโหลด field types ครบ 23 types
- ลอง drag & drop reorder fields → save → reload → order ยังถูก
- ทดสอบ `editable_by` user token, `visibility_rules`, `required_rules` → preview ทำงานทันที (JS evaluator) + submit ทำ enforcement (PHP evaluator)
- ลอง clone form → schema คัดลอกครบ
- ลอง delete → cascade `navigation_menus` row หาย

### Workflow (#12)
- สร้าง workflow with 3 stages — role / user / position approvers
- ทดสอบ `require_signature` toggle per stage
- ใช้ /forms/submit ส่งฟอร์มที่ผูก workflow นี้ → ดู approval flow ทำงาน

### Lookups (#11)
- สร้าง list, เพิ่ม items 5 ตัว, save
- export CSV → ดูคอลัมน์ครบ
- import CSV (modified) → diff คำนวณถูก
- ลอง delete list ที่ผูกกับ form → error toast แสดง

### Menu Manager (#19)
- สร้างเมนูใหม่ที่ root → save → sidebar refresh
- drag reorder → sort_order DB update
- toggle is_active off → sidebar ซ่อน
- ลบ leaf → ลบได้; ลบ parent ที่มี children → error

### Dashboards (#21)
- สร้าง dashboard + 3 widgets (metric, chart, table)
- เปิด `/reports/dashboards/{id}` → widget render data จริง
- ลอง `visibility = permission` ผูก permission → user ที่ไม่มีสิทธิ์ดูไม่เห็น

## Issues found

วันที่ทดสอบ: _________________
ผู้ทดสอบ: _________________

| # | Menu | สิ่งที่พบ | ความรุนแรง | สถานะ |
|---|------|-----------|------------|--------|
|   |      |           |            |        |

## Sign-off

- [ ] ผ่านทั้ง 21 เมนู
- [ ] ไม่มี JS console error
- [ ] EN + TH render ถูกต้อง
- [ ] Comfortable + Compact density render ถูกต้อง

Tester: _________________ Date: _________________
