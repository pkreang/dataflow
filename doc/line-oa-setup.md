# LINE Official Account — คู่มือตั้งค่า (admin)

**บทบาทไฟล์นี้:** คู่มือสำหรับ super-admin ใน DataFlow เพื่อตั้งค่าให้ระบบส่ง LINE notification ผ่าน **LINE Messaging API** หลังจาก **LINE Notify ถูกปิดบริการ 2025-03-31**

**ต้องทำ 2 channel ใน LINE Developers Console:**
1. **Messaging API channel** — ระบบใช้ส่ง push message
2. **LINE Login channel** — ผู้ใช้ใช้กดปุ่ม "เชื่อมบัญชี LINE" ที่หน้า profile

---

## Prerequisites

- บัญชี **LINE Business ID** (ฟรี — สมัครที่ https://account.line.biz/)
- เบราว์เซอร์เข้า https://developers.line.biz/console/

---

## ขั้นที่ 1 — สร้าง LINE Official Account (OA)

1. ไป https://manager.line.biz/ → กด "สร้าง Official Account ใหม่"
2. กรอกชื่อ, ประเภทธุรกิจ, อัปโหลดรูปโปรไฟล์
3. หลังสร้างเสร็จ — จำชื่อ OA ไว้ (ผู้ใช้จะเห็นชื่อนี้เวลาเพิ่มเพื่อน)

---

## ขั้นที่ 2 — สร้าง Provider + Messaging API channel

1. ไป https://developers.line.biz/console/
2. ถ้ายังไม่มี Provider → กด "Create" → ตั้งชื่อ Provider (เช่นชื่อบริษัท)
3. ใต้ Provider → กด "Create a new channel" → เลือก **"Messaging API"**
4. กรอก:
   - Channel name (ใช้เดียวกับ OA ได้)
   - Channel description
   - Category, Subcategory
   - Email address
5. หลังสร้าง → ไปแท็บ **"Messaging API"**:
   - **Channel access token (long-lived)** — กด "Issue" → copy token ยาว ๆ
   - **QR code** — สแกนเพื่อให้ผู้ใช้ "เพิ่มเพื่อน" บัญชี OA (ต้องเพิ่มเพื่อนก่อนระบบส่งข้อความได้)
6. ถ้าต้องการ — ไปแท็บ **"Messaging API"** → ปิด "Auto-reply messages" (ไม่งั้น bot จะตอบกลับเอง)

**Limits ของแพลนฟรี:** 500 push messages / เดือน, 1000 friends

---

## ขั้นที่ 3 — สร้าง LINE Login channel (สำหรับการเชื่อมบัญชี)

1. ใน Provider เดียวกัน → "Create a new channel" → เลือก **"LINE Login"**
2. กรอก:
   - Channel name (เช่น "DataFlow Account Link")
   - Channel icon
   - App types: ติ๊ก **"Web app"**
3. หลังสร้าง → ไปแท็บ **"LINE Login"**:
   - **Callback URL** → ใส่ URL จากระบบ DataFlow: `{APP_URL}/auth/line/callback`
     (ดูค่าจริงได้จากหน้า `/settings/notifications` ในระบบ ส่วน "LINE Login")
   - **Scopes** → ติ๊ก `profile` และ `openid`
4. ไปแท็บ **"Basic settings"**:
   - คัดลอก **Channel ID** (ตัวเลข)
   - คัดลอก **Channel secret** (สตริงสั้น ๆ)

---

## ขั้นที่ 4 — ใส่ค่าใน DataFlow

1. Login เป็น super-admin
2. ไปเมนู **ตั้งค่า → การแจ้งเตือน** (`/settings/notifications`)
3. ในส่วน **"LINE Official Account"**:
   - วาง **Channel Access Token** (จากขั้นที่ 2)
   - ใส่ **Channel ID** ของ Messaging API (optional, ใช้เพื่ออ้างอิง)
   - ติ๊ก **"LINE Official Account"** เปิดใช้งาน
4. ในส่วน **"LINE Login (เชื่อมบัญชี)"**:
   - วาง **LINE Login Channel ID**
   - วาง **LINE Login Channel Secret**
   - **Callback URL** จะแสดงให้ — ใช้ copy ไปใส่ใน LINE Developers (ถ้ายังไม่ใส่)
5. กด **บันทึก**

---

## ขั้นที่ 5 — ทดสอบ

### 5.1 — admin ทดสอบส่ง push (จากหน้า notifications)
1. **ก่อนอื่น admin ต้องเพิ่มเพื่อนของ OA นี้ใน LINE มือถือ** (จาก QR code ของ Messaging API channel) — ไม่งั้นข้อความจะส่งไม่ถึง
2. ไปที่หน้า profile (`/myprofile`) → กดปุ่ม **"เชื่อมบัญชี LINE"** → OAuth → กลับมาเห็น "เชื่อม LINE แล้ว"
3. กลับไปที่ `/settings/notifications` → กดปุ่ม **"ทดสอบส่งไปยัง LINE ของฉัน"** ที่ด้านล่าง
4. เปิดแอป LINE → ควรเห็นข้อความ "ทดสอบส่งจาก {APP_NAME} — LINE Messaging API ใช้งานได้"

### 5.2 — end-user เชื่อมบัญชี
1. login เป็น user ทั่วไป → ไปหน้า profile → กด "เชื่อมบัญชี LINE"
2. ทำ OAuth → กลับมาเห็น badge "เชื่อม LINE แล้ว"
3. รอจน trigger event (เช่น approval) → ระบบจะส่ง LINE ให้

---

## Troubleshooting

| อาการ | สาเหตุ / วิธีแก้ |
|-------|------------------|
| ทดสอบส่งแล้วไม่ได้รับข้อความ | (1) ยังไม่ได้เพิ่มเพื่อน OA → สแกน QR code เพิ่มเพื่อนก่อน; (2) Channel Access Token หมดอายุ → re-issue ใหม่ |
| OAuth callback แล้วได้ error | Callback URL ใน LINE Developers ไม่ตรงกับ `{APP_URL}/auth/line/callback` — ตรวจ allowlist |
| "Cannot test send: your account is not linked" | admin ยังไม่เชื่อมบัญชี LINE ของตัวเอง — ไป `/myprofile` กด "เชื่อมบัญชี LINE" ก่อน |
| Push message ส่งล้มเหลว (silent) | ตรวจ `storage/logs/laravel.log` — `LINE Messaging push failed` พร้อม HTTP status; 401 = token ผิด, 403 = friend ไม่ได้เพิ่ม, 429 = เกินโควต้า |
| ระบบส่ง LINE ไม่ออกแม้ตั้งค่าครบ | ตรวจ toggle: `Settings → Notifications → LINE Official Account` ต้องเปิดด้วย; per-event toggle (approval pending / approved / rejected / stock low) เปิดด้วย |

---

## ข้อแตกต่างจาก LINE Notify (สำหรับ admin ที่เคยตั้ง LINE Notify มาก่อน)

| | LINE Notify (เก่า) | LINE Messaging API (ใหม่) |
|--|---|---|
| ใครต้องตั้งค่า | ผู้ใช้ — gen token ใส่เอง | admin — ตั้ง Channel Access Token + Login channel |
| ผู้ใช้ทำอะไร | เก็บ token ส่วนตัว | เพิ่มเพื่อน OA + กดปุ่ม "เชื่อมบัญชี" |
| Cost | ฟรี ไม่จำกัด | 500 push/เดือนฟรี, เพิ่มได้ตามแพลน |
| Endpoint | `notify-api.line.me/api/notify` (ตายแล้ว) | `api.line.me/v2/bot/message/push` |
| Auth | Per-user Bearer token | Org-level Channel Access Token + per-user LINE userId |

---

## เอกสารอ้างอิงภายนอก

- LINE Messaging API: https://developers.line.biz/en/reference/messaging-api/
- LINE Login: https://developers.line.biz/en/docs/line-login/
- ประกาศปิดบริการ LINE Notify: https://notify-bot.line.me/closing-announce
