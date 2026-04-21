// ═══════════════════════════════════════════════════════════
// ONE ERA — Form Configuration (client-side reference only)
//
// Backend API (/api/submit-form.js) sử dụng biến môi trường
// được cấu hình trong Vercel Dashboard → Settings → Environment Variables:
//
//   GOOGLE_SHEETS_URL  — URL của Google Apps Script Web App
//   RESEND_API_KEY     — API key của Resend.com
//   RECEIVER_EMAIL     — Email nhận lead (có thể nhiều, cách nhau dấu phẩy)
//
// ── CÁCH CÀI ĐẶT GOOGLE SHEETS ──────────────────────────────
// 1. Mở Google Sheets mới
// 2. Extensions → Apps Script → Dán code từ /docs/google-apps-script.gs
// 3. Deploy → New Deployment → Web App
//    - Execute as: Me
//    - Who has access: Anyone
// 4. Copy URL → dán vào Vercel env GOOGLE_SHEETS_URL
//
// ── CÁCH CÀI ĐẶT RESEND ────────────────────────────────────
// 1. Đăng ký tại https://resend.com (miễn phí 100 email/ngày)
// 2. Add domain hoặc dùng Resend test domain
// 3. API Keys → Create → Copy key → dán vào Vercel env RESEND_API_KEY
//
// ═══════════════════════════════════════════════════════════

const FORM_CONFIG = {
  project: {
    name: "ONE ERA",
    receiverEmail: "oneerabykinera@gmail.com",
    receiverName: "Team ONE ERA",
    hotline: "0865149461"
  }
};

// Export cho Node.js nếu cần
if (typeof module !== "undefined") module.exports = FORM_CONFIG;
