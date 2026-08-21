# ระบบฐานข้อมูลครัวเรือน มรย.พัฒนาท้องถิ่น · ยุทธศาสตร์ที่ 1

เวอร์ชัน **Laravel 12** ของหน้า UI เดิม (ไฟล์ HTML หน้าเดียว) — แปลงเป็นโครงสร้าง Laravel เต็มรูปแบบ
ทุกหน้า render จากฝั่งเซิร์ฟเวอร์ด้วย Blade · เชื่อม **MySQL** แล้ว พร้อม CRUD ทะเบียนครัวเรือน

---

## 1. วิธีรัน (3 คำสั่ง)

ต้องมี **PHP 8.2 ขึ้นไป**, **Composer** และ **Node.js 18+**

```bash
composer install          # ติดตั้งไลบรารี PHP (สร้างโฟลเดอร์ vendor)
npm install && npm run build   # ข้ามได้ ถ้าใช้ไฟล์ใน public/build ที่แนบมาแล้ว
php artisan serve         # เปิด http://127.0.0.1:8000
```

> ไฟล์ `.env` แนบมาให้พร้อม `APP_KEY` แล้ว จึงไม่ต้อง `php artisan key:generate`
> ตั้ง `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` ไว้ (ไม่ต้องมีตาราง session)
> ส่วนฐานข้อมูลของระบบดูขั้นตอนในหัวข้อ 3

ระหว่างพัฒนาหน้าตา (hot reload) ให้เปิดสองหน้าต่าง:

```bash
php artisan serve
npm run dev
```

---

## 2. หน้าเว็บทั้ง 7 หน้า

| URL | หน้า | ไฟล์ Controller |
|---|---|---|
| `/` | ภาพรวมระบบ | `DashboardController` |
| `/households` | ทะเบียนครัวเรือน (ค้นหา · กรอง · เรียง · แบ่งหน้า) | `HouseholdController` |
| `/activities` | โครงการ / กิจกรรม | `ActivityController` |
| `/enrollments` | รายชื่อเข้าร่วมโครงการ | `EnrollmentController` |
| `/areas` | ข้อมูลพื้นที่เป้าหมาย | `AreaController` |
| `/quality` | ตรวจสอบคุณภาพข้อมูล | `DataQualityController` |
| `/import-export` | นำเข้า / ส่งออกข้อมูล | `ImportExportController` |
| `/export/{type}` | ดาวน์โหลด CSV (ใช้งานได้จริง) | `ExportController` |

ฟอร์มเพิ่ม/แก้ไข เปิดเป็นลิ้นชักด้านขวาผ่าน URL จริง เช่น
`/households/create` · `/households/PY671000005/edit` · `/households/PY671000005` · `/enrollments/create?pa=LP69002`

---

## 3. ฐานข้อมูล (MySQL) และ CRUD

### 3.1 ติดตั้งครั้งแรก

```bash
# 1) เปิด MySQL ใน Laragon (คลิกขวาที่ไอคอน → MySQL → Start)
# 2) ตั้งค่าใน .env  (แก้ 6 บรรทัดนี้)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yru_household
DB_USERNAME=root
DB_PASSWORD=

# 3) สร้างตาราง — Laravel จะถามว่าจะสร้างฐานข้อมูล yru_household ให้ไหม ตอบ yes
php artisan migrate

# 4) ใส่ข้อมูลจริงจากไฟล์ใน app/Data (104 ครัวเรือน · 39 กิจกรรม · 104 รายการลงทะเบียน)
php artisan db:seed

# ต้องการล้างแล้วเริ่มใหม่ทั้งหมด
php artisan migrate:fresh --seed
```

### 3.2 โครงสร้างตาราง (แผนภาพอยู่ที่ `docs/schema.mermaid`)

| ตาราง | หน้าที่ | จุดสำคัญ |
|---|---|---|
| `provinces` | จังหวัด | `name` unique |
| `districts` | อำเภอ | unique (province_id, name) |
| `tambons` | ตำบล | unique (district_id, name) · `area_code` ใช้ประกอบรหัส HC |
| `villages` | หมู่บ้าน / ชุมชน | unique (tambon_id, name, moo) |
| `households` | **ทะเบียนครัวเรือน** | `hc` unique · ชื่อแยก 3 ส่วน · lat/lng · soft delete |
| `programs` | โครงการหลักรายปีงบ | unique (fiscal_year, name) |
| `activities` | กิจกรรม | `pa` unique · soft delete |
| `enrollments` | HC ↔ PA | unique (household_id, activity_id) กันลงทะเบียนซ้ำ |

**เหตุผลที่แยกพื้นที่เป็น 4 ตาราง** — ชีตเดิมเก็บชื่ออำเภอ/ตำบลเป็นข้อความในแถวเดียวกับครัวเรือน
ทำให้เกิด «อำเภอไม่ตรงกับตำบล» 18 ครัวเรือน และ «ชื่ออำเภอสะกดต่างกันระหว่างชีต»
เมื่อครัวเรือนอ้าง `tambon_id` แล้วอ่านอำเภอ/จังหวัดผ่านความสัมพันธ์ ปัญหาสองข้อนี้เกิดขึ้นไม่ได้อีก
จำนวนประเด็นในหน้าตรวจคุณภาพข้อมูลจึงลดจาก 15 เหลือ 12 ประเด็นหลัง seed

### 3.3 CRUD ที่ใช้งานได้จริงแล้ว (ทะเบียนครัวเรือน)

| การทำงาน | เส้นทาง | รายละเอียด |
|---|---|---|
| เพิ่ม | `POST /households` | ตรวจข้อมูล → ออกรหัส HC อัตโนมัติจากรหัสตำบล → บันทึก |
| แก้ไข | `PUT /households/{hc}` | อัปเดตพร้อมสร้างหมู่บ้านใหม่ให้เองถ้ายังไม่มีในตำบลนั้น |
| ลบ | `DELETE /households/{hc}` | soft delete — กู้คืนได้ด้วย `restore()` |
| เพิ่มเข้ากิจกรรมเป็นชุด | `POST /households/bulk/enroll` | ข้ามรายการที่ลงทะเบียนไว้แล้วอัตโนมัติ |
| แก้พื้นที่เป็นชุด | `POST /households/bulk/area` | ย้ายหลายครัวเรือนไปตำบลใหม่พร้อมกัน |
| ลบเป็นชุด | `POST /households/bulk/delete` | soft delete หลายรายการ |
| รวมรายการซ้ำ | `POST /households/bulk/merge` | เก็บรายการแรกเป็นหลัก ย้ายกิจกรรมมารวม เติมค่าที่ว่าง แล้วลบที่เหลือ (อยู่ใน transaction) |

หน้าโครงการ/กิจกรรม และรายชื่อเข้าร่วม **อ่านจากฐานข้อมูลแล้ว** แต่ปุ่มบันทึก/ลบยังไม่เขียนลงฐานข้อมูล
(ตามที่ตกลงว่ารอบนี้เน้นทะเบียนครัวเรือนก่อน) จุดที่ต้องเติมมี `/* TODO */` กำกับไว้

### 3.4 ชั้นสถาปัตยกรรม

```
Blade (ไม่ต้องแก้)  →  Controller  →  Repository  →  Eloquent Model  →  MySQL
                                        ↑
                          คืนค่าอาร์เรย์รูปแบบเดิม ['hc','name','tam',...]
                          จึงเปลี่ยนจากไฟล์ไปเป็นฐานข้อมูลได้โดยหน้าเว็บไม่ต้องแก้เลย
```

`app/Data/*.php` ยังอยู่ในฐานะ **ต้นทางของ seeder** เท่านั้น ระบบไม่ได้อ่านตอนใช้งานแล้ว

---

## 4. โครงสร้างโปรเจกต์

```
app/
├── Data/                     ← ชุดข้อมูลชั่วคราว (แทนฐานข้อมูล)
│   ├── households.php        104 ครัวเรือน
│   ├── activities.php        39 กิจกรรม ปีงบ 2567–2569
│   ├── enrollments.php       104 รายการลงทะเบียน (HC ↔ PA)
│   ├── areas.php             34 พื้นที่เป้าหมาย
│   └── programs.php          ชื่อโครงการหลักตามปีงบ
├── Repositories/             ← ชั้นเข้าถึงข้อมูล (จุดเดียวที่ต้องแก้เมื่อต่อฐานข้อมูล)
│   ├── HouseholdRepository.php    กรอง / เรียง / ออกรหัส HC / ความสมบูรณ์
│   ├── ActivityRepository.php     งบประมาณรายปี / ออกรหัส PA
│   ├── EnrollmentRepository.php   join HC ↔ PA / สถานะ
│   ├── AreaRepository.php         จังหวัด → อำเภอ → ตำบล
│   └── DataQualityAnalyzer.php    กฎตรวจคุณภาพข้อมูลทั้งหมด
├── Support/
│   ├── Thai.php              จัดรูปแบบตัวเลข/วันที่ไทย · ตัดสระซ้ำ · แยกชื่อ · รหัสตำบล
│   ├── Icon.php              ชุดไอคอน SVG
│   ├── Nav.php               นิยามเมนู/ชื่อหน้า/ตัวเลขท้ายเมนู
│   ├── Paginate.php          แบ่งหน้าสำหรับอาร์เรย์
│   └── helpers.php           ฟังก์ชัน qs() ทำลิงก์ตัวกรอง
└── Http/Controllers/         7 หน้า + ส่งออก CSV

resources/
├── views/
│   ├── layouts/app.blade.php         โครงหน้าหลัก
│   ├── partials/                     sidebar · topbar · overlays
│   ├── components/                   icon · th (หัวตารางเรียงลำดับ) · pager · issue
│   ├── dashboard.blade.php
│   ├── households/  index · form · show
│   ├── activities/  index · form
│   ├── enrollments/ index · picker
│   ├── areas/index.blade.php
│   ├── quality/index.blade.php
│   └── io/index.blade.php
├── css/app.css               Tailwind + design tokens เดิมทั้งชุด
└── js/app.js                 ธีม · ย่อเมนู · ลิ้นชัก · แจ้งเตือน · เลือกหลายรายการ · ทูลทิป
```

---

## 5. สิ่งที่ใช้งานได้แล้วในเวอร์ชันนี้

- ค้นหา / กรอง / เรียงลำดับ / แบ่งหน้า — ทำงานฝั่งเซิร์ฟเวอร์ผ่าน query string (แชร์ลิงก์ได้)
- ตรวจคุณภาพข้อมูลอัตโนมัติ (ซ้ำซ้อน · ตำบลว่าง · อำเภอไม่ตรงตำบล · สะกดผิด · ข้อมูลขาด)
- ส่งออก CSV ทั้ง 5 ชุด (UTF-8 BOM เปิดใน Excel ได้ทันที) — **ทำงานจริง**
- ตรวจความถูกต้องของฟอร์มด้วย Laravel Validation + แสดงข้อความใต้ช่องที่ผิด
- สลับโหมดสว่าง/มืด · ย่อเมนู (จำค่าไว้ในเบราว์เซอร์) · แจ้งเตือน · ทูลทิป
- มุมมองการ์ดสำหรับจอเล็กอัตโนมัติด้วย CSS (ไม่ต้องรอ JS)

## 6. งานที่เหลือ (ต่อยอดได้)

1. เปิดเขียนฐานข้อมูลให้หน้าโครงการ/กิจกรรม และรายชื่อเข้าร่วม (จุด `/* TODO */` ใน Controller)
2. หน้าจัดการพื้นที่ (เพิ่ม/แก้ตำบล·หมู่บ้าน) — ตอนนี้อ่านได้อย่างเดียว
3. ระบบล็อกอิน + สิทธิ์ผู้ใช้ แล้วเก็บ `created_by` / `updated_by` อัตโนมัติ
4. ถังขยะ (รายการที่ soft delete) พร้อมปุ่มกู้คืน
5. นำเข้าไฟล์ CSV/XLSX จริงในหน้านำเข้า (ปัจจุบันเป็นขั้นตอนตัวอย่าง)
6. ตาราง `household_members` เก็บสมาชิกในครัวเรือน ถ้าต้องการวิเคราะห์รายบุคคล

---

## 7. หมายเหตุข้อมูล

ข้อมูลทั้งหมดมาจาก Google Sheet «ฐานข้อมูลครัวเรือน 2570» (4 ชีต)
ชีต «รายชื่อเข้าร่วมโครงการ» อ่านผ่านลิงก์แชร์ไม่ได้ จึงจำลองการจับคู่ HC ↔ PA ไว้ให้ครบทุกฟิลด์

---

## 8. การใช้งานร่วมกันผ่าน Git

> **repo นี้ต้องเป็น Private เท่านั้น**
> `app/Data/households.php` มีชื่อจริง เบอร์โทร และรายได้ของครัวเรือน 104 ราย
> และตารางกิจกรรมมีเลขประจำตัวประชาชนของอาจารย์ผู้รับผิดชอบ
> ถ้าเผลอตั้งเป็น Public ข้อมูลจะค้างอยู่ใน git history ลบทีหลังไม่หมด

### สิ่งที่ไม่ขึ้น git (ตั้งไว้ใน .gitignore แล้ว)

| ไม่ขึ้น | เหตุผล |
|---|---|
| `.env` | มีรหัสผ่านฐานข้อมูลและ APP_KEY ของแต่ละเครื่อง |
| `/vendor` · `/node_modules` | ติดตั้งใหม่ได้ด้วย composer / npm |
| `/public/build` | ไฟล์ที่ build แล้ว สร้างใหม่ได้ด้วย `npm run build` |
| `*.sql` · `*.csv` · `*.zip` | ไฟล์ export ที่มีข้อมูลส่วนบุคคล |
| `storage/logs` · `storage/framework` | ไฟล์ชั่วคราวของแต่ละเครื่อง |

`public/css/app.css` และ `public/js/app.js` **ขึ้น git** โดยตั้งใจ เพราะเป็นไฟล์สำรอง
ที่ทำให้หน้าเว็บมีสไตล์ถูกต้องแม้ยังไม่ได้สั่ง `npm run build` (ดูหัวข้อ 1)

### ขั้นตอนของคนที่ clone ไปใหม่

```
composer install
copy .env.example .env        # macOS/Linux ใช้ cp
php artisan key:generate
```

แก้ `DB_DATABASE` ใน `.env` ให้ตรงกับฐานข้อมูลที่สร้างไว้ แล้วเปิดเว็บ
กดปุ่ม **«อัปเดตฐานข้อมูลให้เลย»** ที่แถบเหลืองด้านบน (เท่ากับ `php artisan migrate`)
ถ้าต้องการข้อมูลตัวอย่าง กดปุ่ม **«ใส่ข้อมูลตัวอย่างทั้งชุด»** ในหน้าข้อมูลพื้นที่

> ปุ่ม «ใส่ข้อมูลตัวอย่างทั้งชุด» **ล้างทุกตารางก่อน** อย่ากดบนเครื่องที่มีข้อมูลจริง
