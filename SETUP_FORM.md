# ONE ERA — Hướng Dẫn Cài Đặt Form

## Cấu trúc files mới
```
OE/
├── api/
│   ├── submit-form.js        ← Vercel serverless function (xử lý form)
│   └── google-apps-script.js ← Code dán vào Google Apps Script
├── assets/js/
│   ├── main.js               ← Logic trang (không đổi)
│   ├── form.js               ← Form handler + loading animation
│   └── form.config.js        ← Tham khảo cấu hình (config thật để ở Vercel)
├── vercel.json               ← Routing config cho Vercel
├── .env.example              ← Mẫu environment variables
└── index.html                ← Không có logic form nào ở đây
```

---

## Bước 1 — Google Sheets

1. Tạo Google Sheets mới, đặt tên tùy ý
2. Vào **Extensions → Apps Script**
3. Xóa code cũ, copy toàn bộ nội dung file `api/google-apps-script.js` dán vào
4. Nhấn **Save** (Ctrl+S), đặt tên project tùy ý
5. Nhấn **Deploy → New Deployment**
   - Type: **Web App**
   - Execute as: **Me**
   - Who has access: **Anyone**
6. Nhấn **Deploy** → Copy URL (dạng `https://script.google.com/macros/s/.../exec`)
7. Dán URL vào bước cài Vercel bên dưới

---

## Bước 2 — EmailJS

1. Đăng ký tại **https://emailjs.com** (miễn phí 200 email/tháng)
2. **Add Email Service** → chọn Gmail → đặt tên → Connect
3. Copy **Service ID** (dạng `service_abc123`)
4. **Email Templates → Create Template**
   - Subject: `[ONE ERA] Lead mới từ {{source}}`
   - Nội dung template:
   ```
   Khách hàng mới đăng ký:

   Họ tên:       {{from_name}}
   Điện thoại:   {{phone}}
   Email:        {{email}}
   Nguồn:        {{source}}
   Thời gian:    {{timestamp}}
   ```
   - To email: `{{to_email}}`
5. Copy **Template ID** (dạng `template_xyz789`)
6. Vào **Account → API Keys** → Copy **Public Key**

---

## Bước 3 — Vercel Environment Variables

Vào **Vercel Dashboard → Project → Settings → Environment Variables**, thêm:

| Key | Value |
|-----|-------|
| `GOOGLE_SHEETS_URL` | URL từ Bước 1 |
| `EMAILJS_PUBLIC_KEY` | Public Key từ Bước 2 |
| `EMAILJS_SERVICE_ID` | Service ID từ Bước 2 |
| `EMAILJS_TEMPLATE_ID` | Template ID từ Bước 2 |
| `RECEIVER_EMAIL` | oneerabykinera@gmail.com |

Sau khi thêm → **Redeploy** project.

---

## Test sau khi deploy

Điền form trên website → kiểm tra:
- ✅ Google Sheets có row mới không?
- ✅ Gmail có email không?
- ✅ Trên web hiện thông báo thành công không?

---

## Lỗi thường gặp

**Form báo lỗi sau khi submit:**
- Kiểm tra Vercel → Functions → Logs để xem lỗi cụ thể
- Đảm bảo đã Redeploy sau khi thêm env vars

**Không nhận email:**
- Kiểm tra EmailJS dashboard → Logs
- Kiểm tra spam folder

**Google Sheets không có data:**
- Mở lại Apps Script → Deploy lại (New Deployment, không phải Update)
- Đảm bảo chọn "Anyone" ở Who has access
