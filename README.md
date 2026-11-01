# 🏙️ Startup Street

**Startup Street** là game web mô phỏng khởi nghiệp / quản lý kinh doanh. Người chơi đăng ký tài khoản với vốn ban đầu **1.000.000.000đ**, đi dạo trên bản đồ phố, **thuê mặt bằng**, **mở cửa hàng** theo 6 mô hình kinh doanh, **nhập hàng – định giá – mở cửa**, xem khách tự động ghé mua, **chốt ca** qua từng ngày chơi, trả tiền thuê mặt bằng hằng tháng và theo dõi báo cáo doanh thu (ngày / tuần / tháng) cùng tài chính toàn chuỗi cửa hàng.

> Nguyên tắc cốt lõi: **tiền, kho hàng, khách và giá đều do server (PHP + MySQL) quyết định**. Trình duyệt chỉ gọi API và vẽ kết quả, không bao giờ gửi số tiền / giá / số khách lên server.

---

## 📚 Mục lục

1. [Tổng quan công nghệ](#1-tổng-quan-công-nghệ)
2. [Kiến trúc hệ thống](#2-kiến-trúc-hệ-thống)
3. [Frontend](#3-frontend)
4. [Backend](#4-backend)
5. [Cơ sở dữ liệu](#5-cơ-sở-dữ-liệu)
6. [Danh sách API](#6-danh-sách-api)
7. [Các cơ chế game quan trọng](#7-các-cơ-chế-game-quan-trọng)
8. [Bảo mật](#8-bảo-mật)
9. [Kiểm thử](#9-kiểm-thử)
10. [Cấu trúc thư mục](#10-cấu-trúc-thư-mục)
11. [Cài đặt & chạy local](#11-cài-đặt--chạy-local)
12. [Các trang chính](#12-các-trang-chính)
13. [Phân công module](#13-phân-công-module)
14. [Hạn chế hiện tại](#14-hạn-chế-hiện-tại)

---

## 1. Tổng quan công nghệ

| Lớp | Công nghệ | Ghi chú |
|---|---|---|
| **Frontend** | HTML5, CSS3, **JavaScript thuần (ES6+)** | Không dùng framework (React/Vue), không cần build step |
| Đồ họa | **SVG** (bản đồ, nhân vật, cửa hàng), CSS animation, `requestAnimationFrame` | Bản đồ 2440×1240 vẽ bằng SVG sinh từ JS |
| Biểu đồ | **Chart.js 4.4.7** (CDN jsDelivr) | Biểu đồ doanh thu/chi phí, cơ cấu chi phí, so sánh cửa hàng |
| Font | **Google Fonts**: Baloo 2, Nunito | Phong cách "cozy" hoạt hoạ |
| **Backend** | **PHP 8.x thuần** (không framework, không Composer) | Mỗi file trong `public/api/` là một endpoint |
| Truy cập DB | **PDO + `pdo_mysql`** | Prepared statements, `ATTR_EMULATE_PREPARES = false` |
| Xác thực | **PHP Session** (cookie `PHPSESSID`) | `password_hash` / `password_verify` (bcrypt) |
| Giao thức API | **REST-style JSON** qua `fetch()` | Định dạng chung `{ success, message, data }` |
| **Database** | **MySQL / MariaDB** (InnoDB, `utf8mb4_unicode_ci`) | Hỗ trợ kết nối SSL (`ssl_ca`) |
| Migration | Script PHP CLI `tools/run_migration.php` + file `.sql` idempotent | 10 file migration `00` → `09` |
| Test | Integration test viết bằng PHP + **cURL** + PDO | `tests/shop_test.php` |
| Công cụ dev | PhpStorm (`.idea/`), Postman (`.postman/`), Git | Server dev: `php -S` |

---

## 2. Kiến trúc hệ thống

```mermaid
flowchart LR
    subgraph Browser["Trình duyệt (Vanilla JS)"]
        UI["HTML / CSS / JS<br/>login · map · business-management<br/>startup-street-ui · shop-counter · profile"]
    end

    subgraph Server["PHP 8 (public/api/*.php)"]
        API["REST API (JSON)"]
        LIB["lib/<br/>shop_helpers · auth_session<br/>shop_simulation · shop_reports"]
        DBP["db.php (PDO)"]
    end

    DB[("MySQL / MariaDB<br/>InnoDB")]

    UI -- "fetch() + cookie PHPSESSID" --> API
    API --> LIB --> DBP --> DB
```

**Luồng xử lý một request điển hình** (ví dụ nhập hàng):

1. Frontend gọi `POST api/purchase-inventory.php` với `{shop_id, product_id, quantity, selling_price?}`.
2. Server lấy người chơi từ **session** (`getCurrentUserId()`), **không** nhận `user_id` từ client.
3. Mở transaction → khóa ví (`FOR UPDATE`) → kiểm tra chủ cửa hàng → kiểm tra sản phẩm, sức chứa kho, đủ tiền.
4. Trừ ví nguyên tử → cộng kho → ghi sổ `transactions` → `commit`.
5. Trả JSON `{success, message, data}`; frontend cập nhật giao diện từ dữ liệu server trả về.

**Luồng chơi của người chơi:**

```mermaid
flowchart LR
    A[Đăng ký / Đăng nhập] --> B[Bản đồ: chọn mặt bằng]
    B --> C[Thuê mặt bằng<br/>rent-property]
    C --> D[Chọn mô hình + mở cửa hàng<br/>create-shop]
    D --> E[Nhập hàng + định giá<br/>purchase-inventory / restock-bulk]
    E --> F[Mở cửa quán<br/>shop-open]
    F --> G[Khách tự động ghé mua<br/>shop-tick]
    G --> H[Chốt ca → sang ngày mới<br/>end-day]
    H --> I[Báo cáo ngày / tuần / tháng<br/>get-reports]
    H -. mỗi 30 ngày chơi .-> J[Tự trừ tiền thuê mặt bằng]
    G --> E
```

**Quan hệ giữa các bảng chính:**

```mermaid
erDiagram
    users ||--|| player_profiles : "có"
    users ||--o{ personal_expenses : "ghi"
    users ||--o{ rentals : "thuê"
    properties ||--o| rentals : "được thuê"
    users ||--o{ shops : "sở hữu"
    properties ||--o| shops : "đặt tại"
    business_types ||--o{ shops : "loại hình"
    business_types ||--o{ products : "có sản phẩm"
    shops ||--o{ products : "món riêng"
    shops ||--o{ inventory : "tồn kho"
    products ||--o{ inventory : "của"
    shops ||--|| shop_runtime : "trạng thái vận hành"
    shops ||--o{ shop_orders : "khách vào quán"
    shops ||--o{ shop_daily_reports : "tổng kết ngày"
    users ||--o{ transactions : "sổ cái"
    shops ||--o{ transactions : "sổ cái"
```

---

## 3. Frontend

### 3.1. Công nghệ

- **HTML5 + CSS3 + JavaScript thuần (ES6+)**: `async/await`, `fetch`, arrow function, template literal, `Map/Set`, `URLSearchParams`, `Promise` — tất cả chạy trực tiếp trên trình duyệt, **không npm, không bundler**.
- **CSS hiện đại**: CSS Variables (design token màu gỗ/giấy), CSS Grid + Flexbox, `clamp()`, `position: sticky`, keyframe animation, media query responsive, tôn trọng `prefers-reduced-motion`.
- **SVG động**: toàn bộ bản đồ phố (nhà, mái ngói, cây, đèn đường, ghế, người đi bộ) và nhân vật/avatar được **sinh bằng JavaScript** thành chuỗi SVG.
- **Chart.js 4.4.7** (nạp qua CDN `cdn.jsdelivr.net`) cho biểu đồ đường, cột và doughnut.
- **Google Fonts** (Baloo 2, Nunito) cho phong cách giao diện game vui mắt.
- **localStorage**: lưu *thiết kế cửa hàng* (màu mặt tiền, kệ hàng, trang trí) với khóa `startupStreetDesigns` và bản sao hồ sơ avatar (`playerProfile`) để vẽ nhanh. **Không** lưu tiền hay kho.

### 3.2. Các trang & script

| Trang | Mục đích | Script / thư viện |
|---|---|---|
| `public/index.html` | Chuyển hướng sang `login.html` | — |
| `public/login.html` | Đăng nhập / đăng ký, nền thị trấn hoạt hình | JS inline, `fetch` tới `api/auth/*` |
| `public/map.php` | **Bản đồ phố** (có guard session phía PHP), thuê mặt bằng, tùy chỉnh mặt tiền | JS inline, `js/avatar.js`, SVG, game loop |
| `public/business-management.html` | Quản lý cửa hàng & kho: nhập hàng, đổi giá, thêm mặt hàng, đóng cửa vĩnh viễn | JS inline |
| `public/shop-counter.html` | Bên trong cửa hàng: khách vào mua, mở/đóng cửa quán | `js/shop-scene.js` |
| `public/startup-street-ui.html` | Dashboard vận hành: nhật ký, kho, **báo cáo doanh thu**, tài chính, chốt ca | `js/game.js`, `js/wallet-api.js`, `js/shop-scene.js`, `js/shop-live.js`, `js/reports.js`, `css/reports.css`, Chart.js |
| `public/profile.html` | Hồ sơ, **tùy chỉnh avatar**, **tài khoản** (đổi tên / email / mật khẩu), **tài chính toàn chuỗi cửa hàng** | JS inline, Chart.js |

### 3.3. Các module JavaScript dùng chung (`public/js/`)

| File | Vai trò |
|---|---|
| `game.js` | Động cơ **demo cục bộ**: `GameState`, menu mẫu, tính doanh thu/chi phí, vẽ biểu đồ Chart.js. Ở chế độ thật nhiều hàm của file này bị các file sau ghi đè |
| `wallet-api.js` | **Cầu nối game.js ↔ MySQL**: hiển thị số dư thật, nạp kho thật từ `get-shop-products.php`, nhập hàng loạt qua `restock-bulk.php`; có 2 chế độ *thật* (có `?shop_id=`) và *demo* (không có) |
| `shop-live.js` | Chế độ chạy "thật": vòng lặp **polling 4 giây** gọi `shop-tick.php`, nạp lại nhật ký đã lưu (`get-activity-log.php`), nút mở/đóng cửa, **hộp thoại chốt ca** (`end-day.php`) |
| `shop-scene.js` | Cảnh trong quán: khách (SVG) bước vào, nói món, nhận hàng, "+tiền" nổi lên rồi ra về; chỉ **vẽ**, không quyết định tiền |
| `reports.js` | Bảng **"Hôm nay"** và tab **Báo cáo doanh thu** (theo ngày / tuần / tháng) + mục Tài chính; gọi `get-reports.php`, tự làm mới mỗi 15 giây |
| `avatar.js` | Bộ vẽ avatar SVG dùng chung (người chơi và người đi bộ NPC trên bản đồ) |

### 3.4. Điểm kỹ thuật đáng chú ý ở frontend

- **Game loop trên bản đồ**: `requestAnimationFrame`, di chuyển bằng **WASD / phím mũi tên**, va chạm với nhà (AABB), camera mượt (lerp), 22 NPC đi bộ theo làn đường.
- **Sinh bản đồ xác định (deterministic)**: bộ random **Mulberry32** với seed `20260117`, nên mã lô / giá / diện tích trên bản đồ **khớp đúng** với bảng `properties` trong migration `03`. Mặt bằng được khớp với server theo **ID**, không theo chỉ số mảng.
- **Avatar procedural**: avatar, NPC và khách trong quán được vẽ bằng SVG từ các tham số (màu da, kiểu tóc, mắt, trang phục, mũ, kính, nhóm tuổi); khách dùng random có hạt giống nên cùng một đơn luôn ra cùng một gương mặt.
- **Chống XSS ở UI**: dữ liệu từ server được escape (`esc()`), tên cửa hàng / khách gán qua `textContent`.
- **Xử lý 401 tập trung**: mọi `fetch` gặp HTTP 401 đều chuyển về `login.html`.
- **Hàng đợi** (`chain = Promise`) trong `wallet-api.js` để các thao tác liên tiếp được áp dụng đúng thứ tự.
- **Polling thông minh**: chỉ hỏi server khi quán đang mở và tab đang hiển thị (`document.hidden`); quay lại tab thì lấy ngay khách đã đến trong lúc vắng.
- **Accessibility cơ bản**: `aria-label`, `role="dialog"`, `aria-live`, `:focus-visible`, `[hidden]`.

---

## 4. Backend

### 4.1. Công nghệ

- **PHP 8.x thuần**, không framework, không Composer. Mỗi endpoint là một file PHP độc lập trong `public/api/`, dùng chung thư viện trong `lib/`.
- **PDO (`pdo_mysql`)** với prepared statements, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, tắt emulate prepares.
- Tương thích **PHP ≥ 8.4** (`\Pdo\Mysql::ATTR_SSL_CA`) và PHP < 8.4 (`PDO::MYSQL_ATTR_SSL_CA`).
- Máy chủ phát triển: **PHP built-in server** (`php -S 127.0.0.1:8000 -t public`).

### 4.2. Thư viện nội bộ (`lib/`)

| File | Chức năng |
|---|---|
| `db.php` (gốc dự án) | Kết nối PDO dùng chung, đọc `config/config.local.php`, trả lỗi dạng JSON, bắt lỗi fatal |
| `lib/shop_helpers.php` | `apiRespond`, `ApiException`, `requireMethod`, `readJsonBody`, `toPositiveInt/Number`, `getCurrentUserId`, `requireOwnedShop`, `getWalletBalance`, `deductWallet`, `creditWallet`, `deductShopInventoryAndAddBalance` + các hằng số cân bằng game |
| `lib/auth_session.php` | Khởi tạo / huỷ session an toàn (không phụ thuộc DB nên logout vẫn chạy khi MySQL lỗi) |
| `lib/shop_simulation.php` | **Động cơ khách tự động** (`simulateCustomers`, `shopRuntimeTick`) và **chốt ca** (`shopEndDay`): phân phối Poisson, hệ số giờ cao điểm, hệ số giá, uy tín, ngày chơi, trừ tiền thuê |
| `lib/shop_reports.php` | Ngày chơi, tiền thuê tháng, tổng hợp báo cáo ngày → tuần → tháng |
| `lib/shop_billing.php` | *(Cũ)* Phí cố định theo tháng thu "lười" theo thời gian thật. **Hiện không còn được gọi**: phí tháng đã chuyển sang thu lúc chốt ca |

### 4.3. Quy ước API

- Mọi response có dạng:

```json
{ "success": true, "message": "Lấy số dư thành công", "data": { "...": "..." } }
```

- Mã HTTP có ý nghĩa: `200/201` thành công, `400` dữ liệu sai, `401` chưa đăng nhập, `403` không phải chủ, `404` không tồn tại, `405` sai method, `409` xung đột (hết hàng, trùng, kho đầy, không đủ tiền thuê…), `410` tính năng đã tắt, `500` lỗi server.
- Lỗi nghiệp vụ ném `ApiException(message, httpStatus, data)`, được bắt ở mỗi endpoint rồi chuyển thành JSON.

### 4.4. Xác thực & phiên

- **PHP Session** với cấu hình: `use_strict_mode`, `use_only_cookies`, `cookie_httponly`, `cookie_samesite=Lax`, `cookie_secure` (tự bật khi chạy HTTPS).
- `session_regenerate_id(true)` khi đăng nhập / đăng ký / đổi mật khẩu để chống **session fixation**.
- Mật khẩu băm bằng **`password_hash(PASSWORD_DEFAULT)`** (bcrypt), có `password_needs_rehash`.
- Đăng nhập luôn chạy `password_verify` với **hash giả** khi không tìm thấy user, để thời gian phản hồi như nhau (chống dò tài khoản).
- `getCurrentUserId()` dùng `read_and_close` để nhả khóa file session ngay, các request song song không bị xếp hàng.

### 4.5. Giao dịch & đồng thời (concurrency)

- Mọi thao tác động tới tiền/kho chạy trong **transaction InnoDB** với `SELECT ... FOR UPDATE`.
- **Thứ tự khóa cố định**: `ví → cửa hàng → shop_runtime → kho` để tránh deadlock.
- Trừ tiền **nguyên tử**: `UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?` (kiểm tra số dư ngay trong câu lệnh).
- Ràng buộc **UNIQUE** ở tầng DB là chốt chặn cuối cùng: `rentals.property_id`, `shops.property_id`, `inventory(shop_id, product_id)`, `shop_daily_reports(shop_id, day_number)`.
- Sổ cái `transactions` là **tùy chọn**: lỗi ghi sổ không làm hỏng giao dịch chính.

---

## 5. Cơ sở dữ liệu

**MySQL / MariaDB**, engine **InnoDB**, bảng mã **`utf8mb4_unicode_ci`** (hỗ trợ tiếng Việt và emoji).

### 5.1. Các bảng

| Nhóm | Bảng | Mô tả |
|---|---|---|
| Tài khoản | `users` | Tài khoản, `balance` (nguồn sự thật duy nhất của tiền), `password_hash` |
| | `player_profiles` | Avatar (tuổi, da, tóc, mắt, trang phục, mũ, kính), tên hiển thị |
| | `personal_expenses` | Chi tiêu cá nhân theo danh mục / tháng |
| Mặt bằng | `properties` | 48 mặt bằng (A1–D12): lượng khách, diện tích, giá thuê / tháng, trạng thái `available` / `npc` |
| | `rentals` | Ai đang thuê mặt bằng nào (UNIQUE theo `property_id`), `price_paid` |
| Cửa hàng | `business_types` | 6 mô hình: trà sữa, quán ăn, tiệm bánh, shop quần áo, tiệm hoa, tạp hóa |
| | `products` | Sản phẩm theo mô hình; `shop_id` NULL = món có sẵn, có giá trị = món do chủ quán tự thêm; `emoji`, `default_price` |
| | `shops` | Cửa hàng (1 mặt bằng ↔ 1 cửa hàng) |
| | `inventory` | Tồn kho + giá bán từng món của từng cửa hàng; cột `max_stock` (mặc định 40) |
| Vận hành | `shop_runtime` | Trạng thái mở/đóng, mốc tick, uy tín, bộ đếm, **ngày chơi hiện tại** (`day_number`, `day_started_ts`), **ngày đã trả tiền thuê tới** (`rent_paid_until_day`) |
| | `shop_orders` | Mỗi khách vào quán (`served` / `missed`), kèm `cost` (vốn mặt hàng) và `day_number`; chỉ giữ 7 ngày chơi gần nhất |
| | `shop_daily_reports` | Tổng kết từng ngày chơi (đơn bán, đơn lỡ, doanh thu, vốn, tiền thuê) — **giữ vĩnh viễn** |
| Tài chính | `transactions` | Sổ cái thu/chi (sale, material, rent, salary, utilities…) |
| | `cycle_reports`, `cycle_sales_detail` | Bảng báo cáo chu kỳ từ migration `01` (hiện chưa được code sử dụng) |

> Ghi chú: các cột `till_balance`, `last_settled_order_id`, `last_billed_ts` của `shop_runtime` (migration `06`) là di sản của cơ chế "két quán" cũ; doanh thu hiện được cộng thẳng vào ví khi khách mua.

### 5.2. Migration

Chạy theo thứ tự, **idempotent** (chạy lại nhiều lần vẫn an toàn nhờ `CREATE TABLE IF NOT EXISTS`, kiểm tra `information_schema` + `PREPARE/EXECUTE`, `INSERT IGNORE`):

| File | Nội dung |
|---|---|
| `00_users_auth.sql` | `users`, `player_profiles`, `personal_expenses` |
| `01_simulation_finance.sql` | `transactions`, `cycle_reports`, `cycle_sales_detail` |
| `02_shops_inventory.sql` | `business_types`, `products`, `shops`, `inventory` + dữ liệu mẫu (6 loại hình × 4 sản phẩm) |
| `03_properties_rentals.sql` | `properties` (48 lô), `rentals` |
| `04_shop_runtime.sql` | `shop_runtime`, `shop_orders` |
| `05_custom_products.sql` | Thêm `products.shop_id` (món riêng của cửa hàng) |
| `06_settlement.sql` | Cột két quán / mốc chốt ca / mốc thu phí (cơ chế cũ) |
| `07_product_emoji.sql` | `products.emoji`, `products.default_price` |
| `08_game_days_reports.sql` | Ngày chơi, `shop_daily_reports`, `shop_orders.cost` + `day_number`, `rent_paid_until_day` |
| `09_inventory_max_stock.sql` | `inventory.max_stock` |

Chạy tất cả: `php tools/run_migration.php`

---

## 6. Danh sách API

### Xác thực & hồ sơ — `public/api/auth/`

| Endpoint | Method | Chức năng |
|---|---|---|
| `register.php` | POST | Đăng ký, cấp vốn ban đầu, tạo profile mặc định, mở session |
| `login.php` | POST | Đăng nhập bằng username hoặc email |
| `logout.php` | POST | Huỷ session + xoá cookie (không cần DB) |
| `me.php` | GET | Thông tin người dùng hiện tại + profile + số dư |
| `update-profile.php` | POST | Cập nhật tên hiển thị, avatar |
| `update-account.php` | POST | Đổi tên đăng nhập / email / tên hiển thị / mật khẩu (đổi email hoặc mật khẩu phải nhập mật khẩu hiện tại) |
| `expenses.php` | GET / POST / DELETE | Chi tiêu cá nhân theo tháng |

### Mặt bằng & ví — `public/api/`

| Endpoint | Method | Chức năng |
|---|---|---|
| `get-balance.php` | GET | Số dư thật của người đang đăng nhập |
| `get-occupied-properties.php` | GET | Mặt bằng đã có chủ / NPC, `is_mine` (không lộ chủ là ai) |
| `rent-property.php` | POST | Thuê mặt bằng (giá lấy từ server, idempotent) |

### Cửa hàng & kho

| Endpoint | Method | Chức năng |
|---|---|---|
| `business-types.php` | GET | Danh mục mô hình kinh doanh |
| `create-shop.php` | POST | Mở cửa hàng trên mặt bằng đã thuê (kiểm tra vốn yêu cầu) |
| `get-shops.php` | GET | Cửa hàng của người chơi |
| `get-shop-products.php` | GET | Sản phẩm + tồn kho + giá bán + số dư |
| `purchase-inventory.php` | POST | Nhập 1 món (trừ ví + cộng kho trong 1 transaction) |
| `restock-bulk.php` | POST | Nhập nhiều món, **all-or-nothing** |
| `add-product.php` | POST | Thêm mặt hàng riêng (icon, giá vốn, giá bán) |
| `update-shop-name.php` | POST | Đổi tên cửa hàng |
| `close-shop.php` | POST | **Đóng cửa vĩnh viễn**: thanh lý kho 50% giá vốn, trả mặt bằng |
| `sell-product.php` | POST | Bán thủ công — **đã tắt** (trả `410`), khách giờ mua tự động |

### Mô phỏng, báo cáo & tài chính

| Endpoint | Method | Chức năng |
|---|---|---|
| `shop-open.php` | POST | Mở / đóng cửa quán |
| `shop-tick.php` | POST | Cho khách ghé từ lần tick trước, trả trạng thái để vẽ |
| `end-day.php` | POST | **Chốt ca**: lưu báo cáo ngày, sang ngày mới, tự trừ tiền thuê khi sang tháng mới, xóa nhật ký quá 7 ngày chơi |
| `get-activity-log.php` | GET | Nhật ký khách đã lưu (7 ngày chơi gần nhất) |
| `get-reports.php` | GET | Báo cáo doanh thu theo ngày / tuần / tháng + tổng cộng của một cửa hàng |
| `get-all-shops-finance.php` | GET | Tài chính tổng hợp toàn bộ cửa hàng của người chơi (tick các quán đang mở trước khi tổng hợp) |

---

## 7. Các cơ chế game quan trọng

### 7.1. Khách tự động (server-side simulation)

- Không dùng cron: server tính khách **"lười"** mỗi khi có request `shop-tick` (và cả khi mở trang tài chính tổng hợp).
- Số khách ≈ **Poisson** (thuật toán Knuth). Tốc độ (khách / phút) = `(0,4 + 1,6 × traffic/100) × (0,6 + 0,8 × uy tín/100) × hệ số giờ`, trong đó `traffic` là lượng khách qua lại của mặt bằng, hệ số giờ cao điểm: sáng 7–8h ×1,4; trưa 11–13h ×1,5; chiều tối 17–20h ×1,5; khuya ×0,5.
- **Hệ số giá**: `r = giá bán / (giá vốn × 1,8)`. `r = 1` → mọi khách chịu mua; `r = 1,5` → khoảng 40%; `r ≥ 1,83` → không ai mua (khách "chê đắt" và chỉ đi ngang qua cửa kính); bán rẻ hơn thị trường thì khách mua nhiều hơn.
- Hết hàng → khách bỏ về, **uy tín −1**. Phục vụ thành công → **uy tín +0,2** (uy tín giới hạn 0–100).
- Mỗi đơn lưu **giá vốn** (`cost`) và **ngày chơi** (`day_number`) vào `shop_orders`. **Doanh thu được cộng thẳng vào ví** trong cùng transaction với việc trừ kho.
- Người chơi vắng mặt vẫn được **bù khách** (tối đa 10 phút); mỗi tick tối đa 40 khách.
- Gọi tick nhiều lần / nhiều tab **không nhân đôi khách** nhờ khóa `shop_runtime` và cập nhật `last_tick_ts` trong cùng transaction.

### 7.2. Ngày chơi & chốt ca

- "Ngày" của quán là **ngày chơi**, không phải ngày lịch: bắt đầu ở Ngày 1 và chỉ sang ngày mới khi người chơi bấm **Chốt ca** (`end-day.php`). Dữ liệu lưu ở MySQL nên tải lại trang / đổi máy vẫn đúng ngày.
- Chốt ca chạy trong **một transaction** (lỗi giữa chừng = không đổi gì):
  1. Quán đang mở thì chốt nốt khách đến lúc này.
  2. Lưu tổng kết ngày vào `shop_daily_reports`.
  3. Sang ngày mới; nếu bước sang tháng mới thì **tự trừ tiền thuê mặt bằng** (xem 7.3).
  4. Xóa nhật ký khách (`shop_orders`) cũ hơn **7 ngày chơi** (sang Ngày 8 thì Ngày 1 bị xóa). Báo cáo trong `shop_daily_reports` **không** bị xóa.

### 7.3. Tiền thuê mặt bằng

- Giá thuê lấy từ `properties.price`, tính theo **tháng = 30 ngày chơi**.
- Tháng đầu trả ngay khi thuê (`rent-property.php`, tính vào Ngày 1). Các tháng sau **tự trừ vào ví khi chốt ca bước sang tháng mới** và ghi sổ `transactions` (category `rent`).
- Ví không đủ tiền → chốt ca trả `409`, ngày **không** sang (tiền khách vừa chốt không bị đổi). Người chơi cần bán thêm hàng rồi chốt lại.

### 7.4. Báo cáo doanh thu

- **Lợi nhuận = Doanh thu − Vốn mặt hàng** (giá vốn của hàng đã bán; tiền nhập hàng đã bị trừ ví lúc nhập nên không trừ hai lần). **Lãi ròng = Lợi nhuận − Tiền thuê mặt bằng.**
- Tuần = 7 ngày chơi, tháng = 30 ngày chơi. Báo cáo **chỉ hiển thị kỳ đã kết thúc trọn vẹn** (ngày đã chốt ca, tuần đủ 7 ngày, tháng đủ 30 ngày).
- Bảng "theo ngày" trả tối đa 60 ngày gần nhất, còn tuần / tháng / tổng cộng được tính từ **toàn bộ** ngày đã chốt.

### 7.5. Đóng cửa vĩnh viễn

`close-shop.php` (phải gõ đúng tên cửa hàng để xác nhận): đóng quán và chốt khách → trả nốt két (nếu còn) vào ví → **thanh lý kho = 50% giá vốn** → xóa đơn, trạng thái vận hành, báo cáo ngày, kho, món riêng, cửa hàng → **gỡ quyền thuê** để mặt bằng quay lại thị trường (tiền thuê đã trả không được hoàn).

### 7.6. Các hằng số cân bằng game (chỉnh trong code, không cần sửa logic)

| Hằng số | Giá trị | File |
|---|---|---|
| Vốn ban đầu | 1.000.000.000đ | `00_users_auth.sql` |
| Sức chứa kho mỗi món | 40 | `shop_helpers.php` |
| Hệ số giá bán mặc định | ×1,5 giá vốn | `shop_helpers.php` |
| Hệ số giá thị trường | ×1,8 giá vốn | `shop_simulation.php` |
| Bù khách tối đa khi vắng mặt | 600 giây | `shop_simulation.php` |
| Số khách tối đa mỗi tick | 40 | `shop_simulation.php` |
| 1 tháng / 1 tuần (ngày chơi) | 30 / 7 | `shop_reports.php` |
| Giữ nhật ký khách | 7 ngày chơi | `shop_reports.php` |
| Hoàn thanh lý khi đóng cửa | 50% giá vốn | `close-shop.php` |
| Số món riêng tối đa / cửa hàng | 20 | `add-product.php` |

---

## 8. Bảo mật

- **Không tin client**: user id luôn lấy từ session; tiền, giá, số khách do server tính.
- **SQL Injection**: 100% truy vấn dùng prepared statements, tắt emulate prepares.
- **Phân quyền theo chủ sở hữu**: `requireOwnedShop()` trả 404 / 403; `UPDATE` còn kèm `owner_user_id` làm lớp bảo vệ thứ hai.
- **Chống lộ thông tin**: `get-occupied-properties.php` không tiết lộ ai là chủ mặt bằng; `create-shop.php` trả chung 403 cho "không tồn tại" và "của người khác".
- **Session**: HttpOnly, SameSite=Lax, Secure khi HTTPS, strict mode, regenerate id; trang `map.php` kiểm tra session ngay phía PHP và `Cache-Control: no-store`.
- **Mật khẩu**: bcrypt, giới hạn 72 byte, chống dò tài khoản bằng timing; đổi email / mật khẩu bắt buộc nhập mật khẩu hiện tại.
- **Validate đầu vào chặt**: `toPositiveInt`, `toPositiveNumber`, kiểu dữ liệu chuỗi bắt buộc, giới hạn độ dài, regex `/D`.
- **XSS**: escape dữ liệu khi render HTML ở frontend; emoji chỉ nhận ký tự ngoài ASCII (tối đa 8 ký tự).
- **Chống race condition**: `FOR UPDATE`, UNIQUE key, UPDATE có điều kiện.
- **Bí mật**: `config/config.local.php`, `*.pem`, `.env` nằm trong `.gitignore`.

---

## 9. Kiểm thử

`tests/shop_test.php` là **integration test dạng black-box**:

- Gọi API thật qua **cURL** (mỗi người chơi một cookie jar = một session).
- Đối chiếu **sự thật từ MySQL** (đọc thẳng bằng PDO), không tin vào chính API đang test.
- Kịch bản kiểm tra: tạo cửa hàng trùng mặt bằng (409), không đủ tiền nhập hàng, sản phẩm khác loại hình (404), truy cập cửa hàng của người khác (403), số lượng không hợp lệ (400), mặt bằng không tồn tại (403/404).
- Dữ liệu test dùng tiền tố `TESTSUITE-` và `property_id` giả `9900001/9900002`, **tự dọn và khôi phục số dư** khi kết thúc (kể cả khi lỗi).

```bash
php -S 127.0.0.1:8000 -t public     # terminal 1
php tests/shop_test.php             # terminal 2   (thêm -v để xem từng request)
```

> ⚠️ Test **ghi vào database** — chỉ chạy trên DB phát triển, không chạy trên production. Test dùng một endpoint đăng nhập dev (`dev-login.php`, chỉ cho `127.0.0.1`); hãy xoá nó khi triển khai thật hoặc truyền `--login-url` khác.

---

## 10. Cấu trúc thư mục

```text
startup-street/
├── .idea/                          # Cấu hình PhpStorm
├── .postman/                       # Workspace Postman
├── certs/                          # Chứng chỉ SSL CA cho MySQL (ca.pem, không commit)
├── config/
│   ├── config.example.php          # File mẫu
│   └── config.local.php            # Cấu hình thật (tự tạo, không commit)
├── database/
│   └── migrations/                 # 00 → 09 (.sql, idempotent)
├── lib/
│   ├── auth_session.php            # Session đăng nhập / đăng xuất
│   ├── shop_helpers.php            # Helper API, ví, quyền sở hữu, hằng số game
│   ├── shop_simulation.php         # Động cơ khách tự động + chốt ca
│   ├── shop_reports.php            # Ngày chơi, báo cáo ngày/tuần/tháng
│   └── shop_billing.php            # (Cũ) phí tháng theo thời gian thật, không còn được gọi
├── public/                         # Document root của web server
│   ├── api/
│   │   ├── auth/                   # login, register, logout, me, update-profile, update-account, expenses
│   │   ├── rent-property.php  get-occupied-properties.php  get-balance.php
│   │   ├── create-shop.php  get-shops.php  get-shop-products.php  business-types.php
│   │   ├── purchase-inventory.php  restock-bulk.php  add-product.php
│   │   ├── update-shop-name.php  close-shop.php  sell-product.php
│   │   ├── shop-open.php  shop-tick.php  end-day.php
│   │   └── get-activity-log.php  get-reports.php  get-all-shops-finance.php
│   ├── css/
│   │   └── reports.css
│   ├── js/
│   │   ├── game.js  wallet-api.js  shop-live.js  shop-scene.js  reports.js  avatar.js
│   ├── index.html  login.html  map.php  profile.html
│   ├── business-management.html  shop-counter.html  startup-street-ui.html
│   └── logo.png  dat.png           # Logo và ảnh mảnh đất trống
├── tests/
│   └── shop_test.php               # Integration test
├── tools/
│   └── run_migration.php           # Chạy migration bằng CLI
├── db.php                          # Kết nối PDO dùng chung
├── .gitignore
└── README.md
```

---

## 11. Cài đặt & chạy local

### Yêu cầu

- **PHP 8.x** với extension **`pdo_mysql`** (và `curl` nếu chạy test, `mbstring` được khuyến nghị)
- **MySQL / MariaDB**
- Trình duyệt hiện đại (có kết nối Internet để nạp Chart.js và Google Fonts từ CDN)

### Các bước

1. **Tạo file cấu hình**

   ```bash
   # Windows
   copy config\config.example.php config\config.local.php
   # macOS / Linux
   cp config/config.example.php config/config.local.php
   ```

2. **Điền thông tin MySQL** vào `config/config.local.php`:

   ```php
   return [
       'host'     => 'YOUR_MYSQL_HOST',
       'port'     => 3306,
       'database' => 'street_business_game',
       'username' => 'YOUR_MYSQL_USERNAME',
       'password' => 'YOUR_MYSQL_PASSWORD',
       'ssl_ca'   => __DIR__ . '/../certs/ca.pem', // bỏ trống nếu DB không dùng SSL
   ];
   ```

3. **Chạy migration** (tạo bảng + dữ liệu mẫu):

   ```bash
   php tools/run_migration.php
   ```

4. **Khởi động server** từ thư mục gốc dự án:

   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

5. **Mở trình duyệt**: <http://127.0.0.1:8000/login.html> → đăng ký tài khoản → vào game.

> Các trang sau khi đăng nhập dùng **session**, không còn truyền `?user_id=` trên URL. Tham số URL còn dùng: `?shop_id=<id>` (trang quản lý kho, trong quán, vận hành) và `?property_id=<id>` (trang quản lý cửa hàng khi mở cửa hàng mới).

### Xử lý sự cố nhanh

| Lỗi | Cách xử lý |
|---|---|
| "Thiếu extension pdo_mysql" | Bật `extension=pdo_mysql` trong `php.ini` rồi chạy lại server |
| "Thiếu file config/config.local.php" | Làm lại bước 1–2 |
| "Không tìm thấy file chứng chỉ SSL CA" | Đặt đúng `certs/ca.pem` hoặc để `ssl_ca` rỗng |
| Lỗi thiếu bảng / cột (`42S02`, `42S22`), "Chưa cập nhật database vận hành quán" | Chạy lại `php tools/run_migration.php` (cần đủ migration `04` và `08`) |
| Chốt ca báo "Không đủ tiền trả tiền thuê mặt bằng" | Bán thêm hàng / chờ có đủ tiền trong ví rồi chốt ca lại |

---

## 12. Các trang chính

### 🗺️ Bản đồ & thuê mặt bằng — `public/map.php`
Bản đồ SVG 48 mặt bằng (4 dãy A–D × 12 lô), đi lại bằng WASD, nhấn **E** để xem mặt bằng. Trạng thái: *Còn trống / Đã có chủ / Của bạn*. Thuê mặt bằng (giá tính theo tháng) → tạo cửa hàng → tùy chỉnh mặt tiền, nội thất, hàng hóa, trang trí.

### 📦 Quản lý cửa hàng & kho — `public/business-management.html`
Danh sách cửa hàng, tìm kiếm / lọc sản phẩm (còn hàng, sắp hết, hết hàng), nhập hàng từng món hoặc **bổ sung đầy kho** hàng loạt, chỉnh giá bán (hiển thị % lãi/lỗ), **thêm mặt hàng riêng**, **đóng cửa vĩnh viễn**.

### 🏪 Trong cửa hàng — `public/shop-counter.html`
Cảnh nội thất sống động: khách bước vào, nói món, nhận hàng, trả tiền rồi ra về; hết hàng thì khách buồn bỏ đi; chê giá thì chỉ đi ngang qua cửa kính. Nút mở/đóng cửa quán.

### 📊 Dashboard vận hành & tài chính — `public/startup-street-ui.html`
Bốn tab: **Hoạt động** (nhật ký khách, lưu 7 ngày chơi), **Kho hàng** (tồn kho thật), **Báo cáo doanh thu** (theo ngày / tuần / tháng), **Tài chính** (tổng doanh thu, vốn mặt hàng, tiền thuê, lợi nhuận, biểu đồ). Thanh bên có bảng "Hôm nay", nút mở/đóng cửa, nhập đầy kho và **Chốt ca**; có ô chọn cửa hàng.

### 👤 Hồ sơ cá nhân — `public/profile.html`
Ba tab: **Tùy chỉnh avatar** (nhóm tuổi, da, tóc, mắt, trang phục, mũ, kính), **Tài khoản** (tên hiển thị, tên đăng nhập, email, đổi mật khẩu), **Quản lý tài chính** (báo cáo tổng hợp toàn bộ cửa hàng: so sánh doanh thu, cơ cấu chi phí, bảng chi tiết từng cửa hàng, vốn tồn kho; tự làm mới mỗi 10 giây).

---

## 13. Phân công module

| Module | Phạm vi |
|---|---|
| **Thành viên 1** | Tài khoản, đăng nhập / đăng ký, session, hồ sơ & avatar, chi tiêu cá nhân |
| **Thành viên 2** | Bản đồ, mặt bằng, thuê mặt bằng |
| **Thành viên 3** | Cửa hàng, mô hình kinh doanh, kho hàng, nhập hàng |
| **Thành viên 4** | Mô phỏng khách, ngày chơi & chốt ca, tiền thuê hằng tháng, báo cáo, sổ cái & dashboard tài chính |

---

## 14. Hạn chế hiện tại

- `game.js` còn **menu demo cứng** (4 món trà sữa); phần "Kho hàng" của dashboard đã hiển thị kho thật của mọi mô hình, nhưng các nút gọi món / giờ cao điểm demo chỉ chạy ở chế độ demo (không có `shop_id`). Thông báo trên dashboard vẫn ghi menu chỉ hỗ trợ "Trà sữa & đồ uống".
- `wallet-api.js` còn một số đường code cũ không còn dùng ở chế độ thật (gọi `sell-product.php` đã tắt, xử lý `end-day.php` theo định dạng cũ); chúng bị `shop-live.js` ghi đè nên không ảnh hưởng.
- Một số phần di sản chưa dọn: `lib/shop_billing.php`, các cột két quán trong `shop_runtime`, bảng `cycle_reports` / `cycle_sales_detail`.
- Cột `inventory.max_stock` (migration `09`) đã có nhưng server vẫn dùng hằng số `MAX_STOCK_PER_PRODUCT = 40`.
- `tools/run_migration.php` và một số thông báo lỗi trong code vẫn nhắc tới `05_game_days_reports.sql`, trong khi file thực tế là `08_game_days_reports.sql`.
- `tests/shop_test.php` còn kiểm tra `sell-product.php` trả `403` cho người lạ, nhưng endpoint này hiện trả `410` trước bước kiểm tra chủ sở hữu nên bước đó cần cập nhật.
- Thiết kế mặt tiền lưu ở **localStorage** của trình duyệt (chỉ tên cửa hàng được đồng bộ lên server).
- Khách được mô phỏng bằng **polling 4 giây** (chưa dùng WebSocket / SSE).
- Cần Internet để nạp **Chart.js** và **Google Fonts** từ CDN.
- Chưa có CSRF token riêng (đang dựa vào `SameSite=Lax`), chưa có giới hạn tần suất đăng nhập (rate limiting).

---

## 🎯 Mục tiêu dự án

Mô phỏng kinh doanh thực tế dưới dạng game web, gồm các module: giao dịch mặt bằng, chọn mô hình kinh doanh, quản lý kho & giá bán, mô phỏng khách hàng, tính toán chi phí – lợi nhuận, và dashboard tài chính.
