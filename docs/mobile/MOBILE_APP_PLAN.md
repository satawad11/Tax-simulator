# แผนพัฒนาแอปมือถือ (Android + iOS) — Flutter

> เอกสารวางแผนสำหรับ mobile client ของ Thai Personal Income Tax Simulator
> โค้ดชุดเดียว (Flutter/Dart) ปล่อยได้ทั้ง Android และ iOS
> สถานะ: **แผน / เริ่ม scaffold แล้ว** — อัปเดตล่าสุด 2026-09-18

---

## 1. ภาพรวมและหลักการ

แอปมือถือเป็น **client ล้วน** ที่ไปเรียก REST API `/api/v1/...` ของ Laravel ที่มีอยู่แล้ว
Backend คือ **source of truth** ทั้งหมด — โดยเฉพาะ **ตรรกะคำนวณภาษี** ต้องเรียกผ่าน API เท่านั้น
ห้ามคำนวณภาษีซ้ำในแอป เพื่อกันตัวเลขเว็บกับแอปไม่ตรงกันเมื่อ tax rule เปลี่ยนปี

**สิ่งที่ backend มีพร้อมแล้ว** (ดู `routes/api.php`):
- Auth ด้วย Sanctum token (`/auth/register`, `/auth/login`, `/auth/me`, `/auth/logout`, reset password, verify email)
- คำนวณ/วางแผนภาษีแบบ guest: `POST /tax/calculate`, `POST /tax/plan`, `POST /tax/forms/recommend`
- ข้อมูลอ้างอิงรายปี: `/tax-years`, `/forms`, `/income-types`, `/allowances`, `/tax-brackets`
- สมาชิก: `/tax-returns/...` CRUD ครบทุก sub-resource (incomes, allowances, dependents, donations, withholdings, income-exemptions, scenarios, calculations, recommendations)
- เนื้อหา public: `/content/articles|featured|faqs|categories|tags`

**หลักการออกแบบ**
1. Offline-tolerant: อ่านข้อมูลอ้างอิง (tax-years, allowances) แล้ว cache ได้; การคำนวณต้องออนไลน์
2. Security-first: token เก็บใน secure storage เท่านั้น, ข้อมูลการเงินเข้ารหัส at rest
3. ธีมตรงกับเว็บ (โทนน้ำเงิน navy + ฟอนต์ Noto Sans Thai)
4. i18n ไทยเป็นหลัก เผื่ออังกฤษภายหลัง

---

## 2. Tech stack

| ชั้น | เครื่องมือ | เหตุผล |
|---|---|---|
| Framework | **Flutter 3.x (stable)** | โค้ดชุดเดียว Android+iOS, ฟอนต์ไทยดี, UI ฟอร์มลื่น |
| ภาษา | Dart 3 | null-safety, records |
| State | **Riverpod** | testable, ไม่มี BuildContext ผูกกับ state |
| Routing | **go_router** | declarative, deep link, auth redirect |
| HTTP | **dio** | interceptor สำหรับแนบ token + จับ error/401 รวมศูนย์ |
| Secure storage | **flutter_secure_storage** | Keychain (iOS) / Keystore (Android) |
| Local cache | **shared_preferences** (เบา) | cache ข้อมูลอ้างอิง, ธง onboarding |
| JSON | model + `fromJson` เขียนมือ (หรือ `freezed`+`json_serializable` ภายหลัง) | คุม contract ชัด |
| Test | flutter_test, mocktail, integration_test | unit + widget + e2e |

---

## 3. สถาปัตยกรรมในแอป

```
UI (screens/widgets)
  └─ Controller/Notifier (Riverpod)        state + orchestration
       └─ Repository (per feature)          แปลง API ↔ domain model
            └─ ApiClient (dio + interceptors)  HTTP + auth + error
                 └─ Laravel /api/v1
TokenStorage (secure) ↔ ApiClient (แนบ Bearer, ล้าง token เมื่อ 401)
```

โครง folder (feature-first) — ดูที่ `mobile/lib/`:
```
core/     config (env), network (api client), storage (token), theme
features/ auth/ (data,state,ui)  tax/ (calculator)  home/
routing/  app_router.dart
```

---

## 4. API contract ที่ยึด (ตรวจสอบจากโค้ดจริงแล้ว)

Response ทุกอันห่อด้วย key `data` + มี `success`, `message`

**Login** — `POST /api/v1/auth/login`
```json
// request
{ "email": "user@x.com", "password": "…", "device_name": "Pixel 8 (Android)" }
// response 200
{ "data": { "user": {…}, "token": "1|abc…", "token_type": "Bearer" }, "success": true }
```

**Register** — `POST /api/v1/auth/register`
```json
{ "name": "…", "email": "…", "password": "…", "password_confirmation": "…", "device_name": "…" }
```

**Me** — `GET /api/v1/auth/me` (Bearer)
**Logout** — `POST /api/v1/auth/logout` (Bearer) → 204

**คำนวณภาษี guest** — `POST /api/v1/tax/calculate` (throttle 30/นาที)
> ตรวจ field ที่ต้องส่งจาก `TaxCalculationController` + FormRequest ก่อนต่อจริง

> ⚠️ `device_name` เป็น field **บังคับ** ตอน login/register → ในแอปให้ generate จากรุ่นเครื่อง (เช่น `device_info_plus`)

---

## 5. รายการหน้าจอ (MVP)

| # | หน้า | API ที่ใช้ | Auth |
|---|---|---|---|
| 1 | Splash / bootstrap | `/health`, โหลด token | - |
| 2 | Onboarding | - | - |
| 3 | Login | `/auth/login` | - |
| 4 | Register | `/auth/register` | - |
| 5 | Forgot/Reset password | `/auth/password/forgot|reset` | - |
| 6 | Home / เมนูหลัก | `/content/featured` | ทั้งคู่ |
| 7 | **เครื่องคำนวณภาษี (guest)** | `/tax/calculate`, `/tax/forms/recommend` | ไม่ต้อง |
| 8 | ผลการคำนวณ | (จาก 7) | - |
| 9 | Dashboard สมาชิก | `/tax-returns` | ต้อง |
| 10 | รายการแบบยื่น + สร้าง/ลบ | `/tax-returns` CRUD | ต้อง |
| 11 | แก้ไขแบบยื่น (รายได้/ลดหย่อน/ผู้ติดตาม…) | sub-resources | ต้อง |
| 12 | เปรียบเทียบ scenario / วางแผน | `/scenarios`, `/tax/plan` | ต้อง |
| 13 | บทความ/FAQ/ข่าว | `/content/*` | ไม่ต้อง |
| 14 | โปรไฟล์ / ออกจากระบบ | `/auth/me`, `/auth/logout` | ต้อง |

---

## 6. Milestones (ประมาณ 10–14 สัปดาห์)

### M0 — Setup (สัปดาห์ 1) ✅ scaffold แล้ว
- สร้างโปรเจกต์ Flutter, pubspec, theme, env config, ApiClient, TokenStorage, routing
- ต่อ `/health`, สร้าง CI (flutter analyze + test)

### M1 — Auth (สัปดาห์ 2–3)
- Login / Register / Logout, secure token, auto-logout เมื่อ 401
- `device_name` จากข้อมูลเครื่อง, forgot/reset password

### M2 — เครื่องคำนวณ guest (สัปดาห์ 4–5) ← จุดขายหลัก
- ฟอร์มกรอกรายได้/ลดหย่อน → `/tax/calculate` → หน้าแสดงผล
- แนะนำแบบฟอร์ม PND90/91

### M3 — สมาชิก & tax returns (สัปดาห์ 6–9)
- CRUD แบบยื่นและทุก sub-resource, sync กับ backend, optimistic UI ระวังตัวเลข

### M4 — วางแผน/scenario + เนื้อหา (สัปดาห์ 10–11)

### M5 — Polish + ขึ้นสโตร์ (สัปดาห์ 12–14)
- error/empty/loading states, i18n, ทดสอบเครื่องจริง, store assets, privacy labels

---

## 7. งานฝั่ง Backend ที่ต้องเสริม

- [ ] ตรวจ **CORS / Sanctum** ให้รองรับ mobile (token flow ใช้ได้เลย ไม่ต้องพึ่ง SPA cookie)
- [ ] ตรวจ **rate limit** (`throttle:30,1`, `member-write`, `member-create`) ให้เข้ากับ pattern ของแอป
- [ ] (ถ้าต้องการแจ้งเตือน) เพิ่ม endpoint รับ **push token** (FCM/APNs) + เตือนกำหนดยื่นภาษี
- [ ] คง **API v1 backward-compatible**; เพิ่มของใหม่ใน field เสริม อย่าลบ/เปลี่ยน type เดิม
- [ ] จัดทำ/อัปเดต **API docs** (OpenAPI) ให้ mobile ยึดเป็น contract

---

## 8. การทดสอบ

- **Unit:** repositories + model `fromJson` (mock dio)
- **Widget:** ฟอร์ม login, calculator (validation, error state)
- **Integration/e2e:** flow login→calculate→logout กับ API จริงบน staging
- **Contract:** ทดสอบว่า model ตรงกับ response จริง (กัน 500/parse error)

---

## 9. การปล่อยขึ้นสโตร์

**iOS**
- Apple Developer Program ($99/ปี), build ด้วย `flutter build ipa`
- TestFlight สำหรับ beta, กรอก **App Privacy** (แอปเก็บข้อมูลการเงิน → ประกาศชัด)

**Android**
- Google Play Console ($25 ครั้งเดียว), `flutter build appbundle`
- Internal testing → Closed → Production, กรอก Data safety form

---

## 10. ความปลอดภัย / PDPA (สำคัญ — แอปการเงิน)

- Token ใน **secure storage เท่านั้น** (ห้าม SharedPreferences/plain)
- **HTTPS บังคับ** ทุก environment ที่ไม่ใช่ dev; พิจารณา cert pinning
- ข้อมูลรายได้/ภาษี: เข้ารหัส at rest, ไม่ log ข้อมูลอ่อนไหว, ไม่ใส่ใน URL query
- มี **นโยบายความเป็นส่วนตัว (PDPA)** และหน้าจัดการ/ลบบัญชี ก่อนปล่อยจริง
- Logout ต้องล้าง token + cache ที่อ่อนไหวทั้งหมด

---

## 11. ความเสี่ยงหลัก

| ความเสี่ยง | การจัดการ |
|---|---|
| ฟอร์มภาษีซับซ้อนหลาย sub-resource | ลง state management (Riverpod) ให้ดีตั้งแต่ต้น |
| ตัวเลขเงินคลาดเคลื่อน (float) | ใช้ integer สตางค์ / Decimal; ให้ backend คำนวณ |
| ตรรกะภาษีไม่ตรงเว็บ | ไม่คำนวณในแอป — เรียก API เดียวกับเว็บ |
| tax rule เปลี่ยนปี | ยึด `/tax-years` เป็น dynamic ไม่ hardcode |
| Flutter ยังไม่ติดตั้ง | ติดตั้ง SDK + รัน `flutter create .` ตาม README ในโฟลเดอร์ mobile |

---

## 12. ขั้นตอนถัดไปทันที

1. ติดตั้ง Flutter SDK (Windows) → `flutter doctor`
2. `cd mobile && flutter create . --platforms=android,ios --org com.taxsimulator` (เติมโฟลเดอร์ platform)
3. `flutter pub get`
4. รัน backend: `docker compose up -d --wait` แล้วตั้ง `API_BASE_URL` ให้ชี้ backend
   - Android emulator ใช้ `http://10.0.2.2:8088`, iOS simulator ใช้ `http://localhost:8088`
5. `flutter run` — ทดสอบ health → login → calculate
