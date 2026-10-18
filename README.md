# Startup Street

Startup Street là dự án web mô phỏng quản lý kinh doanh/địa điểm kinh doanh, kết hợp giữa bản đồ mặt bằng, lựa chọn mô hình kinh doanh, quản lý kho và dashboard tài chính.

## Tính năng hiện tại

- Bản đồ mặt bằng cho phép xem các lô đất / mặt bằng có sẵn, đã thuê hoặc thuộc sở hữu của người chơi.
- Thuê mặt bằng và chọn mô hình kinh doanh theo từng loại hình như trà sữa, quán ăn, tiệm bánh, shop quần áo, tiệm hoa, tạp hóa.
- Tạo cửa hàng mới dựa trên mặt bằng đã thuê.
- Quản lý kho hàng của từng cửa hàng: xem sản phẩm, giá vốn, giá bán, tồn kho và nhập thêm hàng.
- Cập nhật số dư ví theo các giao dịch mua hàng.
- Dashboard giao dịch/tài chính cho mô phỏng hoạt động kinh doanh.
- Giao diện hiện đại cho các trang `map.php`, `business-management.html` và `startup-street-ui.html`.

## Các trang chính

### 1. Bản đồ và thuê mặt bằng
- `public/map.php`
- Hiển thị các lô trong khu phố Startup với trạng thái: còn trống, đã có chủ, của bạn.
- Có thể chọn mặt bằng, xem giá thuê, diện tích, lưu lượng khách và mô hình phù hợp.

### 2. Quản lý cửa hàng & kho hàng
- `public/business-management.html`
- Chức năng chính:
  - Hiển thị danh sách cửa hàng của người dùng
  - Chọn mô hình kinh doanh
  - Tạo cửa hàng cho mặt bằng đã thuê
  - Xem danh sách sản phẩm và tồn kho
  - Cập nhật giá bán và nhập hàng
  - Tự động kiểm tra khả năng thanh toán theo số dư ví

### 3. Dashboard tài chính / mô phỏng bán hàng
- `public/startup-street-ui.html`
- Mô phỏng xử lý đơn hàng, phân tích doanh thu, chi phí, lợi nhuận và báo cáo tài chính.

## Cấu trúc dự án

```text
startup-street/
├── config/
│   ├── config.example.php
│   └── config.local.php   # cần tạo từ file example
├── public/
│   ├── api/
│   │   ├── business-types.php
│   │   ├── create-shop.php
│   │   ├── get-shops.php
│   │   ├── get-shop-products.php
│   │   ├── purchase-inventory.php
│   │   └── ...
│   ├── css/
│   ├── js/
│   ├── business-management.html
│   ├── index.html
│   ├── map.php
│   └── startup-street-ui.html
├── db.php
├── README.md
├── .gitignore
└── config.local.php.example
```

## Yêu cầu chạy

- PHP 8.x
- MySQL / MariaDB
- Extension `pdo_mysql` phải được bật trong `php.ini`

## Hướng dẫn chạy local

1. Sao chép file cấu hình mẫu:

```bash
copy config\config.example.php config\config.local.php
```

2. Điền thông tin kết nối MySQL vào `config/config.local.php`.

3. Chạy server từ thư mục gốc:

```bash
php -S 127.0.0.1:8000 -t public
```

4. Mở trong trình duyệt:

- `http://127.0.0.1:8000/index.html`
- `http://127.0.0.1:8000/map.php`
- `http://127.0.0.1:8000/business-management.html?user_id=1`

## Lưu ý quan trọng

- Trang `business-management.html` hỗ trợ các tham số truy cập như:
  - `?user_id=1`
  - `?property_id=<id_mặt_bằng>`
  - `?shop_id=<id_cửa_hàng>`
- Một số API yêu cầu cấu hình database và đường dẫn `config/config.local.php` hợp lệ.
- Nếu thiếu extension `pdo_mysql` hoặc thiếu file cấu hình, server sẽ trả lỗi rõ ràng để dễ debug.

## Mục tiêu dự án

Dự án này đang tập trung vào mô phỏng kinh doanh thực tế theo dạng game web, với các module:

- giao dịch đất/nhà
- chọn mô hình kinh doanh
- quản lý kho và giá bán
- tính toán chi phí, lợi nhuận và ngân sách
- dashboard tài chính và báo cáo vận hành
