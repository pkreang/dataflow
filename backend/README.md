<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Foundation data (master / พื้นฐาน)

`php artisan db:seed` loads (see `Database\Seeders\DatabaseSeeder`):

| Seeder | Contents |
|--------|----------|
| `PermissionSeeder` / `RolePermissionSeeder` | สิทธิ์ + บทบาท + `admin@example.com` |
| `SettingSeeder` / `NavigationMenuSeeder` | การตั้งค่า + เมนู |
| `DocumentTypeSeeder` | ประเภทเอกสารจัดซื้อ (เบิกอะไหล่, PR, PO) |
| `PositionDemoSeeder` | (ไม่รันจาก `db:seed` หลัก — ย้ายไปอยู่ใน `IndustryTemplateSeeder` เท่านั้น) ตำแหน่งโรงเรียน (SCH\_TEACHER, SCH\_ACAD\_HEAD, SCH\_VICE\_PRINCIPAL, SCH\_ADMIN\_OFFICER, SCH\_FIN\_OFFICER) — โดน seed เมื่อ run `composer setup` หรือ `composer switch:school` เพราะ flow เหล่านั้นเรียก `IndustryTemplateSeeder` |
| `FactoryPositionSeeder` | (ไม่รันจาก `db:seed` หลัก) ตำแหน่ง CMMS สำหรับ `FactoryCmmsTemplateSeeder` / `PurchaseWorkflowSeeder` / `ApprovalWorkflowDemoSeeder` / `NteqPolymerDemoSeeder` |
| `IndustryTemplateSeeder` | **เทมเพลตสองกลุ่มลูกค้า:** โรงงาน (`FactoryCmmsTemplateSeeder`: แจ้งซ่อม + PM/AM, ฟอร์ม, workflow, policy) และโรงเรียน (`SchoolEFormTemplateSeeder`: แผนก SCH\_\*, ประเภท eForm ลา/ขอซื้อ/กิจกรรม, ฟอร์ม, workflow, policy) |
| `DashboardSeeder` | แดชบอร์ดตัวอย่าง |
| `PurchaseWorkflowSeeder` | workflow ใบขอซื้อ/สั่งซื้อ (ต้องมีฟอร์ม `purchase_request_default` / `purchase_order_default` ใน DB — สร้างจาก UI หรือ seeder แยก) |

รันเฉพาะเทมเพลตอุตสาหกรรม: `php artisan db:seed --class=IndustryTemplateSeeder`  
รันเฉพาะแผนกโรงเรียน + eForm: `php artisan db:seed --class=SchoolEFormTemplateSeeder`  
ลบแผนกโรงงานตัวอย่างเก่า (MAINT, PROD, WH, …) ออกจาก DB: `php artisan db:seed --class=DepartmentSeeder` — คำสั่งนี้**ไม่สร้าง**แผนกใหม่ มีแค่ purge; การรัน `SchoolEFormTemplateSeeder` หรือ `IndustryTemplateSeeder` จะเรียก purge นี้ก่อนสร้างแผนก **SCH\_\*** เสมอ

**`DevelopmentDemoSeeder`** — แผนกใน DB เป็น **SCH\_\*** จากเทมเพลตโรงเรียนเท่านั้น (แผนกโรงงานตัวอย่างถูกลบออกจากชุด seed แล้ว)

รัน `php artisan db:seed --class=DevelopmentDemoSeeder` แล้วจะได้ผู้ใช้ทดสอบ eForm / workflow โรงเรียน (รหัส `demo1234`):

| Email | แผนก | บทบาท | หมายเหตุ |
|-------|------|--------|-----------|
| `employee@demo.com` | ฝ่ายวิชาการ | viewer | ผู้ยื่น |
| `admin.staff@demo.com` | ฝ่ายธุรการ | viewer | ผู้ยื่น |
| `finance@demo.com` | ฝ่ายการเงิน | viewer | ผู้ยื่น |
| `facility@demo.com` | ฝ่ายอาคารและสถานที่ | viewer | ผู้ยื่น |
| `manager@demo.com` | ฝ่ายวิชาการ | approver | ขั้นที่ 1 (ตำแหน่งหัวหน้าฝ่ายวิชาการ) |
| `gm@demo.com` | — | approver | ขั้นที่ 2 (รองผู้อำนวยการ) |

## Demo users (repair / approval MVP)

หลัง `migrate --seed` หน้า **แจ้งซ่อม** ใช้ฟอร์ม `repair_request_default` จาก `IndustryTemplateSeeder` แล้ว

Optional: `php artisan db:seed --class=RepairApprovalDemoSeeder` เพิ่มผู้ใช้ `approver@` / `requester@` และตั้งขั้น workflow ชี้ไปที่ผู้ใช้ `approver@` (role `approver` ใช้แค่สิทธิ์หน้าอนุมัติ ไม่ใช่การกำหนดขั้น):


| Email | Password | Role | Use |
|-------|----------|------|-----|
| `requester@example.com` | `password` | viewer | Submit repair requests; track **My submitted requests** |
| `approver@example.com` | `password` | approver | **My Approvals** — approve/reject pending repair requests |
| `admin@example.com` | `password` | super-admin | Full access including Settings |

**ถ้า `admin@example.com` login ไม่ได้:** มักเกิดจากรหัสใน DB ไม่ตรง หรือแถว user ถูก JIT จาก Microsoft/LDAP แล้ว (`auth_provider` ไม่ว่าง)  
- รันคำสั่ง: `php artisan user:reset-bootstrap-admin` (รีเซ็ตรหัสเป็น `password` และล้างฟิลด์ SSO/LDAP)  
- หรือรัน seed ใหม่: `php artisan db:seed --class=RolePermissionSeeder` (ตอนนี้ใช้ `updateOrCreate` สำหรับ admin แล้ว)

- **My Approvals** lives under **Repair Request** in the sidebar (not under Reports).
- Placeholder menu items (maintenance, spare parts, equipment browse, report stubs) require `manage_settings` and are hidden from viewer/approver.

After changing navigation or **Settings submenu order** in `NavigationMenuSeeder`, run: `php artisan db:seed --class=NavigationMenuSeeder`

## Authentication & SSO (optional)

Super-admins configure methods under **Settings → Authentication & SSO**. Toggles and non-secret values are stored in `settings`; **secrets must be in `.env`** only:

| Variable | Used for |
|----------|----------|
| `ENTRA_CLIENT_SECRET` | Microsoft Entra ID (client credentials for token exchange) |
| `AUTH_LDAP_BIND_PASSWORD` | LDAP service account bind password |

**Entra:** Register redirect URI `https://<your-app-host>/auth/entra/callback` (must match `APP_URL`). Delegated scopes include `openid`, `profile`, `email`, `User.Read`, **`GroupMember.Read.All`** (for group → role mapping via Microsoft Graph). Grant admin consent in Entra if required.

**LDAP:** Requires PHP `ext-ldap`. JIT users get role from `auth_default_role` (default `viewer`) unless **directory group mapping** matches (see below). At least one active company is required.

**Group → role mapping:** Super-admins can set JSON **`auth_directory_group_role_map`** on **Authentication & SSO** (array of `{ "pattern": "substring", "role": "spatie_role_name" }`). Patterns match against LDAP `memberOf` DNs and Entra group **id** / **displayName** (case-insensitive substring). When any rule matches on sign-in, the user’s Spatie roles are **replaced** with the matched roles; otherwise the default JIT role applies for new users and existing roles are unchanged when there is no match.

**Local vs AD:** Configuration is **instance-wide** (not per company). Super-admins also see a short reminder on **Companies** linking to Authentication & SSO.

**Directory users:** In-app “change password” is hidden; set optional **`auth_password_help_url`** on the Authentication & SSO page for a link to your org portal (e.g. Microsoft SSPR).

Run `php artisan migrate` after pull for `users.auth_provider`, `external_id`, `ldap_dn`.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
