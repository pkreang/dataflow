# LINE notifications (Data Flow)

แจ้งเตือน + อนุมัติผ่าน LINE. โค้ดสร้างเสร็จแล้ว — การเปิดใช้เป็นแค่ **config (DB settings) + ลงทะเบียน URL ใน LINE console**.

## ภาพรวม
- **2 channel ใน LINE Developers console** (developers.line.biz):
  - **Messaging API channel** — push แจ้งเตือน + ปุ่มอนุมัติ/ปฏิเสธ (webhook)
  - **LINE Login channel** — ผูกบัญชี (เก็บ `users.line_user_id`)
- **Config เก็บใน DB `settings`** (ไม่ใช่ .env) → ตั้งที่ **Settings → Notifications** (super-admin)
- **Toggle 3 ชั้น**: system (`line_messaging.enabled`) → event (`notifications.{event}_line`) → user (`notification_preferences`)
- ผู้ใช้ต้อง **ผูก LINE** ก่อน ถึงจะได้ push (ไม่ผูก = ข้าม LINE เงียบๆ)

## 1. สร้าง/ตั้งค่าใน LINE Developers console
**Messaging API channel** → คัดลอก `channel_access_token` (long-lived), `channel_secret`, `channel_id`
- ตั้ง **Webhook URL** = `https://<host>/api/line/webhook` + เปิด **Use webhook**
- (signature verify ด้วย channel secret — ต้องตรงกับที่ตั้งในระบบ)

**LINE Login channel** → คัดลอก `channel_id` + `channel_secret`
- เพิ่ม **Callback URL** (allowlist) = `https://<host>/auth/line/callback` (ใส่ได้หลาย URL)
- scope: `profile`, `openid`

## 2. ตั้งค่าในระบบ — Settings → Notifications (super-admin)
- **LINE Official Account**: เปิด enable + `channel_access_token` + `channel_secret` + `channel_id`
- **LINE Login**: `channel_id` + `channel_secret`

## 3. ผูกบัญชี (ต่อ user)
โปรไฟล์ → **Link LINE** → authorize ด้วย LINE ของตัวเอง → `users.line_user_id` ถูกเซ็ต → พร้อมรับ push

## 4. Cross-environment (local / server)
**config อยู่ DB ของแต่ละ env → ตั้งแยกกัน ไม่ชนกันโดยธรรมชาติ**

| เรื่อง | หมายเหตุ |
|------|----------|
| ส่ง push (แจ้งเตือน) | **ไม่ต้องใช้ webhook** — แค่ token + line_user_id → ทุก env ส่งได้ |
| webhook (ปุ่มอนุมัติใน LINE) | **1 URL/channel** → ชี้ production (`https://flow.dataplc.net/api/line/webhook`); local รับไม่ได้ ต้อง tunnel (ngrok) ถ้าจะเทสปุ่ม |
| LINE Login callback | console ใส่ได้ **หลาย URL** → local + server พร้อมกัน |

ใช้ **channel เดียวร่วม** local+server (webhook→server, callback ทั้งคู่, paste token ใน DB ของแต่ละ env) หรือ **แยก channel ต่อ env** (isolate ขาด) ก็ได้.

## 5. Multi-customer
แต่ละ instance (ลูกค้า) ใช้ **LINE OA ของลูกค้าเอง** → token อยู่ DB ของ instance นั้น → ไม่ต้องแก้โค้ด (เข้ากับ instance-per-customer ใน `doc/git-deploy-workflow.md`).

## 6. Mobile push (FCM) — ยังไม่ครบ (backlog)
- mobile เก็บ FCM token แล้ว (`device_push_tokens` + `/api/v1/devices/push-token`) แต่ **backend ยังไม่มี FCM sender** (ไม่มี Firebase SDK/channel) + แอป**ไม่มี Firebase config files** → push เข้าแอปยังไม่ทำงาน
- **LINE noti เข้าแอป LINE ของ user ได้อยู่แล้ว** ไม่ว่าจะใช้เว็บหรือ mobile (ผูก LINE ครั้งเดียว)
- ทำต่อ: Firebase project + backend FCM channel (`kreait/firebase-php`) + `google-services.json`/`GoogleService-Info.plist` + แก้ platform-detect bug (`mobile/lib/core/push/push_notification_service.dart`) + เพิ่ม push toggle

## ไฟล์อ้างอิง
| ไฟล์ | บทบาท |
|------|-------|
| `app/Channels/LineMessagingChannel.php` | ส่ง push (api.line.me/v2/bot/message/push) |
| `app/Http/Controllers/Api/LineWebhookController.php` | รับปุ่มอนุมัติ/ปฏิเสธ + comment |
| `app/Services/Auth/LineLoginService.php` | OAuth ผูกบัญชี |
| `app/Services/NotificationPreferenceService.php` | เงื่อนไขส่ง (enabled + token + line_user_id + prefs) |
| `database/seeders/SettingSeeder.php` | คีย์ settings ทั้งหมด |
| `resources/views/settings/notifications/index.blade.php` | UI ตั้งค่า |
