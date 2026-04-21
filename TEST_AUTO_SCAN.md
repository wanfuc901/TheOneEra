# Testing Auto-Scan Feature

## Các Bước Để Test:

1. **Truy Cập Admin Dashboard**
   - Mở: http://localhost:8000/admin.php

2. **Nhấn Nút "Auto-Scan Từ index.html"**
   - Bạn sẽ thấy nó quét tất cả ảnh từ index.html và import vào config

3. **Kiểm Tra Các Tab**
   - Bạn sẽ thấy các tabs mới xuất hiện với ảnh:
     - **Loader Logo**: ảnh logo loader (base64)
     - **Hero Background**: ảnh hero section
     - **Brand Images**: 3 ảnh brand
     - **Lifestyle Gallery**: 10+ ảnh gallery
     - **Masterplan**: 1 ảnh masterplan

4. **Kéo Thả & Lưu**
   - Kéo thả các ảnh trong gallery để sắp xếp
   - Nhấn "Lưu Thay Đổi" để lưu thứ tự mới

5. **Xem Trên Trang User**
   - Nhấn nút "Xem Trên Trang User" để mở index.html
   - Tất cả ảnh sẽ hiển thị từ config.json

## Ghi Chú:
- Auto-scan sẽ tự động phát hiện và import tất cả ảnh từ index.html
- Ảnh base64 cũng được hỗ trợ
- Khi bạn upload ảnh mới, chúng sẽ thêm vào config.json
- Bạn có thể xóa ảnh hoặc thay đổi thứ tự bất cứ lúc nào
