# UAT แบบ Clean-Slate — ป้อนข้อมูลเองทั้งหมด

**บทบาทไฟล์นี้:** คู่มือ manual UAT แบบ **เริ่มจากศูนย์** — ล้าง DB เหลือแค่ฐานที่จำเป็น แล้ว **ป้อนข้อมูลธุรกิจทุกอย่างผ่าน UI เอง** ตามลำดับการพึ่งพา ใช้ก่อน merge งานก้อนใหญ่เข้า `main` (เช่น integration branch ที่รวมหลาย feature) — ทำตามได้เองจบครบโดยไม่ต้องพึ่ง demo seed

> ทำไมเริ่มจากศูนย์: ป้อนเองทุก entity = ได้ทดสอบ **ทุก create + validation path** จริง, เห็น `auto_code` generate ทุกตัว, และเจอ bug แบบ fresh-install ที่ demo seed มักกลบไว้

รันคำสั่ง shell ทั้งหมดจากโฟลเดอร์ **`backend/`**

---

## 1. Setup — ล้างให้เหลือฐานขั้นต่ำ

```bash
cd backend
php artisan migrate:fresh --seed     # ⚠️ ล้าง DB ที่ .env ชี้อยู่จนหมด
composer dev                          # เว็บเซิร์ฟเวอร์ + queue + vite
```

`migrate:fresh --seed` บน branch นี้รัน `DatabaseSeeder` ซึ่ง seed **แค่ฐาน 4 อย่าง** เท่านั้น:

| Seed | ได้อะไร | ป้อนเองผ่าน UI ได้ไหม |
|------|---------|----------------------|
| `PermissionSeeder` | สิทธิ์ทั้งหมด (สตริงที่โค้ดอ้าง) | ❌ ผูกในโค้ด |
| `RolePermissionSeeder` | บทบาท + ผูกสิทธิ์ + **`admin@example.com`** | ❌ ต้องมี super-admin ถึง login `/settings/*` ได้ |
| `SettingSeeder` | ค่าตั้งระบบ key-value | ❌ โค้ดอ้าง key |
| `NavigationMenuSeeder` | เมนู sidebar | ❌ ไม่มี = ไม่มีเมนูให้คลิก |

> **role vs permission — ป้อนเองได้แค่ไหน:**
> - **role สร้างเองได้เต็มที่** ผ่าน `ตั้งค่า → บทบาท` ใช้งานได้จริงทันที — ที่แถว `RolePermissionSeeder` ติ๊ก ❌ หมายถึงเฉพาะ user bootstrap `admin@example.com` + การผูก permission↔role ตั้งต้น ; role 4 ตัวที่ seed มาเป็นแค่ความสะดวก จะสร้างเอง/เพิ่มเองก็ได้
> - **permission สร้าง "แถว" ได้ แต่ไม่มีผล** ถ้าโค้ดไม่อ้างชื่อนั้น (`@can`, `middleware('permission:…')`, policy, `navigation_menus.permission`) — ชุดที่ใช้ได้จริงคือ 28 ตัวจาก `PermissionSeeder` (fixed) ; การเพิ่ม permission ใหม่ที่ใช้งานได้จริงเป็นงาน dev (แก้ `PermissionSeeder` + เขียนโค้ดอ้างชื่อ) ไม่ใช่งานป้อนผ่าน UI

**ที่เหลือป้อนเองทั้งหมด** — ไม่มี company / department / position / user อื่น / document type / form / workflow / equipment ใด ๆ มาให้

- ⚠️ **ห้ามรัน** `composer switch:school` / `switch:factory` / `db:seed --class=*DemoSeeder` — จะ seed vertical template + demo data ทับ ทำให้ไม่ใช่ clean-slate
- เข้าระบบ: **`admin@example.com`** / `password` (ตรวจที่ `database/seeders/RolePermissionSeeder.php` ถ้ารหัสไม่ตรง)

---

## 2. ลำดับป้อนข้อมูล

ลำดับนี้ **บังคับ** — ของขั้นหลังต้องเลือกของขั้นก่อน (เช่น สร้าง User ต้องมีแผนก/ตำแหน่งให้เลือก, สร้างฟอร์มต้องมีประเภทเอกสาร) ทุกขั้นทำผ่านเมนู sidebar ในฐานะ `admin@example.com`

| # | เมนู | สร้าง / ทำอะไร | จุดตรวจสำคัญ |
|---|------|----------------|--------------|
| 1 | ตั้งค่า → องค์กร | สร้าง company ≥1 + สาขา (branch) ≥1 | ★ คอลัมน์ **รหัสระบบ (`auto_code`)** generate อัตโนมัติ (เช่น `COMP-001`) |
| 2 | ตั้งค่า → แผนก | สร้างแผนก ≥2 (เช่น ฝ่ายวิชาการ, ฝ่ายธุรการ) | ★ `auto_code` + validate code ซ้ำไม่ได้ |
| 3 | ตั้งค่า → ตำแหน่ง | สร้างตำแหน่ง ≥2 (เช่น หัวหน้า, ผอ.) | ★ `auto_code` |
| 4 | ตั้งค่า → ผู้ใช้ | สร้าง user ≥3 ผูกแผนก/ตำแหน่ง + assign role (เช่น employee, approver) | first_name/last_name (ไม่มี `name`), assign role ได้, ★ `auto_code` |
| 5 | ตั้งค่า → ประเภทเอกสาร | สร้าง document type (school: `leave`,`procurement`,`activity` / factory: `repair_request`,`pm_am_plan`) | code เป็น snake_case เท่านั้น, icon picker, ★ `auto_code` |
| 6 | ตั้งค่า → Workflow | สร้าง approval workflow (ผูกกับ document type จากข้อ 5) → ผูก workflow เข้าแผนก | ขั้นอนุมัติแบบ role / position, validate ครบ |
| 7 | ตั้งค่า → Lookup lists | สร้าง lookup list (ถ้าฟอร์มจะใช้ field แบบ lookup) | — |
| 8 | ตั้งค่า → เลขรันนิง | สร้าง running-number config ต่อ document type (ถ้าจะใช้ field `auto_number`) | prefix / รูปแบบ / reset mode |
| 9 | **ตั้งค่า → ฟอร์มเอกสาร** ★★★ | **สร้างฟอร์มใหม่ด้วยตัวสร้างฟอร์ม** — ลาก/เพิ่ม field หลายชนิด, ตั้ง required/visibility rule, **กด Preview**, Save ; แล้วลอง **แก้** ฟอร์มเดิม | ดูหัวข้อ §3 — จุด merge-risk |
| 10 | เอกสาร (`/forms`) | submit เอกสารจากฟอร์มข้อ 9 — กรอก, บันทึกร่าง, แนบไฟล์, group/repeater field, ส่ง | ฟอร์ม dynamic render ถูก, draft เช็คเจ้าของ, reference no generate |
| 11 | รายการรออนุมัติ | login เป็น approver → approve/reject เอกสารข้อ 10 | ★ badge ประเภทเอกสารมี icon (merge-risk), signature pad (ถ้าขั้นนั้นเปิด) |
| 12 | *(CMMS)* อุปกรณ์ / สถานที่ / หมวด / อะไหล่ | สร้าง equipment category → location → equipment → spare part | ★ `auto_code` ทุกตัว, ผูก FK ถูก |
| 13 | *(CMMS)* บำรุงรักษา / แจ้งซ่อม | สร้าง PM plan + work order ; submit แจ้งซ่อม | ทำตาม `doc/uat-repair-request.md` |
| 14 | รายงาน / My Reports ★★ | สร้าง dashboard + widget, เปิดดู, ตั้ง visibility | ดู §3 — จุด merge-risk |

> รายละเอียดระดับ field ของ flow ข้อ 5–11 ดู **`doc/test-full-workflow-via-ui.md`** ; เช็กลิสต์ครบทุกเมนูตั้งค่า (21 รายการ — access + CRUD + ภาษา + density) ดู **`doc/uat-settings-menus.md`**

> **🆕 ระหว่างทำ §2 — ตรวจหน้าจัดการสิทธิ์ (ฟีเจอร์เพิ่มใหม่ใน branch นี้)**
> แวะหน้า `ตั้งค่า → บทบาท` รอบ ๆ ขั้นสร้าง user (ข้อ 4):
> - **ลองสร้าง role ใหม่ 1 อันเอง** → `เพิ่ม role` → ตั้งชื่อ + ติ๊ก permission + บันทึก → นำไป assign ให้ user ในข้อ 4 ได้ (role สร้างเองได้เต็มที่ — ดู §1)
> - ปุ่ม **"ดูภาพรวมสิทธิ์"** → ตาราง role × permission ทั้งระบบในจอเดียว ; ลองช่องค้นหา, เช็ค footnote เรื่อง `is_super_admin`
> - กด **แก้ role** สักอัน → เช็ค **ปุ่มเลือกทั้งกลุ่ม / ล้าง** ต่อ module, **แถบสรุปสด "N / M สิทธิ์"** (อัปเดตทันทีตอนติ๊ก), subtext ชื่อ permission ดิบใต้ป้ายภาษาคน
> - **Write-guard:** create / edit / delete ของ users / roles / permissions = **super-admin เท่านั้น** (หน้า list ยังเปิดให้ทุกคน) — walkthrough นี้เดินเป็น `admin@example.com` จึงไม่ติด ; ถ้าจะตรวจให้ครบ สร้าง user ที่ไม่ใช่ super-admin แล้วเปิด `/settings/roles/create` → ต้องได้ **403**

---

## 3. จุด merge-risk ที่ต้องเพ่งเป็นพิเศษ

3 จุดนี้ตอน merge ถูก resolve ด้วย **ดุลพินิจ** (รวมโค้ดจากหลาย branch) — automated test ผ่านแล้วแต่ครอบคลุมไม่หมด ให้ทดสอบด้วยตามือละเอียด:

1. **ตัวสร้างฟอร์ม (`/settings/document-forms` → สร้าง/แก้)** — ไฟล์ `_form.blade.php` ถูกรวมจาก mobile-redesign (preview modal เป็น partial, fixed action bar) + home-dashboard (running-number info). ตรวจ: เพิ่ม/ลบ/ลาก field ทุกชนิด, Preview modal เปิด-ปิด, ปุ่ม Save/บันทึก, เลือก document type ที่มี running-number แล้วดูว่าโชว์ "เลขถัดไป", field แบบ lookup/group/qr/signature

2. **การเข้าถึง dashboard (`/reports`, My Reports)** — `ReportDashboard` access ถูกรวม owner-visibility. ตรวจ: สร้าง dashboard ตั้ง visibility = "เฉพาะเจ้าของ" → **เจ้าของเปิดดูได้**, **ผู้ใช้อื่นเปิดแล้วได้ 403**, super-admin เปิดได้ทุกอัน

3. **ช่องค้นหาหน้า list ฟอร์มเอกสาร (`/settings/document-forms`)** — เปลี่ยนเป็น server-side search. ตรวจ: พิมพ์คำค้น → กดค้นหา/submit → ผลกรองถูก, ล้างคำค้นแล้วกลับมาครบ

---

## 4. ทดสอบ 2 vertical แยกกัน

ระบบมี 2 vertical — ทำทีละรอบ อย่าปนข้อมูล:

- **รอบ 1 — School (eForm):** ทำ §1–§2 โดยข้อ 5 สร้าง document type แนวโรงเรียน (`leave`, `procurement`, `activity`) ; ข้อ 12–13 (CMMS) ข้ามได้
- **รอบ 2 — Factory/CMMS:** รัน `php artisan migrate:fresh --seed` **ใหม่** เพื่อล้างข้อมูลรอบ 1 → ทำ §1–§2 อีกครั้ง โดยข้อ 5 สร้าง document type แนวโรงงาน (`repair_request`, `pm_am_plan`) และทำข้อ 12–13 (CMMS) ให้ครบ

---

## 5. Sign-off checklist

| รายการ | School | Factory | ผู้ตรวจ | วันที่ |
|--------|:------:|:-------:|---------|--------|
| §1 Setup — `migrate:fresh --seed` + login ผ่าน | ☐ | ☐ | | |
| §2 ข้อ 1–4 — องค์กร/แผนก/ตำแหน่ง/ผู้ใช้ (auto_code ครบ) | ☐ | ☐ | | |
| หน้าจัดการสิทธิ์ — ภาพรวมสิทธิ์ + หน้าแก้ role (select-all / แถบสรุป) | ☐ | ☐ | | |
| §2 ข้อ 5–8 — doctype/workflow/lookup/running-number | ☐ | ☐ | | |
| §2 ข้อ 9 — ตัวสร้างฟอร์ม (merge-risk #1) | ☐ | ☐ | | |
| §2 ข้อ 10–11 — submit เอกสาร + อนุมัติ | ☐ | ☐ | | |
| §2 ข้อ 12–13 — CMMS (อุปกรณ์ + แจ้งซ่อม) | — | ☐ | | |
| §2 ข้อ 14 — รายงาน/dashboard (merge-risk #2) | ☐ | ☐ | | |
| §3 — ตรวจ merge-risk ครบ 3 จุด | ☐ | ☐ | | |
| เมนูรอบนอก — webhooks, mobile app, density, จัดการเมนู | ☐ | ☐ | | |

ผ่านทั้ง 2 vertical → พร้อม promote เข้า `main`

---

## 6. เอกสารอ้างอิง

| ไฟล์ | ใช้เมื่อ |
|------|----------|
| `doc/test-full-workflow-via-ui.md` | รายละเอียดระดับ field ของ flow สร้าง doctype→dept→position→user→workflow→submit→approve |
| `doc/uat-settings-menus.md` | เช็กลิสต์ครบ 21 เมนูตั้งค่า (access + CRUD + ภาษา + density) |
| `doc/uat-repair-request.md` | UAT แจ้งซ่อม + workflow (ข้อ 13) |
| `doc/test-nteq-maintenance-manual.md` | สถานการณ์ factory/CMMS แบบ NTEQ (visibility rules, conditional fields) |
| `doc/system-test-playbook.md` | ภาพรวมการทดสอบ 4 ชั้น (automated + static + integration + UAT) |
| `doc/uat-reset-testing-layer.md` | ล้าง test user ระหว่างรอบโดยไม่ล้าง master data |
