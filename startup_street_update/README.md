# Startup Street — cập nhật theo PDF

## Những gì đã thực hiện
- Bấm vào mặt bằng trên bản đồ để mở bảng thông tin kèm hình minh họa mặt tiền.
- Chọn mô hình kinh doanh ngay trong bảng thông tin.
- Chưa chọn mô hình thì không thể thuê mặt bằng.
- Sau khi thuê, mở màn hình thiết kế gồm hai bước: **Mặt tiền** và **Bên trong**.
- Có thể đổi tên biển hiệu, màu mặt tiền, màu mái hiên, kiểu trang trí và phong cách/nội thất bên trong.
- Lưu trạng thái thuê, số vốn còn lại và thiết kế trong `localStorage` của trình duyệt.
- Sửa lỗi kiểm tra tên cửa hàng trong `create-shop.php` để không truy cập khóa `name` khi bị thiếu.

## Cách đặt vào dự án hiện tại
- `public/map.php`
- `public/business-management.html`
- `public/api/business-types.php`
- `public/api/create-shop.php`
- `public/api/get-shop-products.php`

Các API đang giả định file `db.php` nằm ở thư mục gốc và các endpoint được đặt tại `public/api/`, giống như ghi chú trong mã nguồn. Nếu cấu trúc dự án của bạn khác, cần chỉnh đường dẫn API hoặc `$dbPath`.

## Lưu ý quan trọng
Phần giao diện thuê và tùy chỉnh hiện lưu trạng thái ở trình duyệt bằng `localStorage`, chưa ghi giao dịch thuê/thiết kế xuống database. Muốn đồng bộ cho nhiều tài khoản hoặc nhiều máy, cần bổ sung bảng lưu hợp đồng thuê/thiết kế và ánh xạ mã mặt bằng trên bản đồ (ví dụ A1, B6) với `properties.id` trong database. Mã hiện tại cũng dùng danh sách mô hình mẫu nếu API loại hình kinh doanh không tải được.
