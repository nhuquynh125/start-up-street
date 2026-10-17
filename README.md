# 🏙️ STARTUP STREET — Cửa Hàng & Tài Chính (Member 4)

Dự án Web Game mô phỏng kinh doanh khởi nghiệp **STARTUP STREET**.
Phần phụ trách: **Thành viên 4 — Simulation & Finance (Mô phỏng bán hàng & Quản lý tài chính)**.

---

## 📂 Cấu Trúc Thư Mục Chuẩn

```text
web/
├── .gitignore
├── db.php                                  # Kết nối CSDL MySQL bằng PDO + SSL
├── test-db.php                             # File test kết nối CSDL online
├── certs/
│   ├── .gitkeep
│   └── ca.pem                              # Chứng chỉ CA tải từ Aiven (tự tải về)
├── config/
│   ├── config.example.php                  # File mẫu cấu hình DB (commit lên Git)
│   └── config.local.php                    # File cấu hình thật (được .gitignore bỏ qua)
├── database/
│   └── migrations/
│       └── 01_simulation_finance.sql       # Script tạo bảng giao dịch, chu kỳ, đơn hàng
└── public/                                 # Document Root cho Web Server
    ├── index.html                          # Giao diện chính của game demo
    ├── css/
    │   └── style.css                       # Giao diện Cozy Warm Pastel chuẩn art guidelines
    ├── js/
    │   └── game.js                         # Engine mô phỏng bán hàng, tính toán & Chart.js
    └── assets/                             # Hình ảnh, âm thanh, icon
```

---

## 🚀 Cách Chạy Demo

### Cách 1: Mở trực tiếp (Frontend Demo)
- Nhấp đúp chuột vào file: `public/index.html` hoặc mở bằng trình duyệt Chrome / Edge.

### Cách 2: Chạy qua PHP Built-in Server (Theo chuẩn hướng dẫn)
Mở Terminal tại thư mục `web/` và chạy lệnh:
```bash
php -S 127.0.0.1:8000 -t public
```
Sau đó truy cập trình duyệt tại: [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## 🎨 Quy Chuẩn Đồ Họa Đã Áp Dụng (Art Guidelines)
1. **Màu nét:** Dùng `#2D1E18` và `#3C2A21` (không dùng đen `#000000`).
2. **Bảng màu:** Cozy Warm Pastel với tông nền kem `#FFF8F0`, màu gỗ ấm `#C4956A`, màu kim loại xám ấm `#B8ADA0`.
3. **Bo góc:** Toàn bộ component, nút bấm, bảng card đều được bo tròn (`border-radius: 8px - 20px`).
4. **Nguồn sáng:** Đổ bóng nhẹ theo hướng góc trên - bên trái chiếu xuống.

---

## 📊 Chức Năng Của Thành Viên 4
- [x] **Vận hành cửa hàng:** Bật/tắt mở quán, chạy chu kỳ kinh doanh 1 lượt hoặc 5 lượt liên tiếp.
- [x] **Mô phỏng bán hàng:** Tính lượng khách dựa trên vị trí, tính sản phẩm bán ra theo tỷ lệ ưa thích (popularity), tự động trừ kho.
- [x] **Hệ thống tài chính:** Tính doanh thu, trừ chi phí nguyên liệu & chi phí cố định (thuê mặt bằng, nhân viên, điện nước, marketing), tính lợi nhuận ròng và biên lợi nhuận (profit margin).
- [x] **Biểu đồ trực quan (Chart.js):** Biểu đồ cột/đường doanh thu vs lợi nhuận; Biểu đồ tròn (Doughnut) cơ cấu chi phí.
- [x] **Lịch sử giao dịch (Transactions):** Ghi nhận chi tiết từng dòng tiền ra vào kèm số dư sau giao dịch.
- [x] **Báo cáo kinh doanh:** Thống kê hiệu quả hoạt động, món bán chạy nhất/bán chậm, cảnh báo tồn kho và phân tích tỷ lệ chi phí.
- [x] **Cơ sở dữ liệu:** Sẵn sàng kết nối MySQL Online (Aiven) qua PDO SSL và file migration SQL.
