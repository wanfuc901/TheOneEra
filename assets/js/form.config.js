// ═══════════════════════════════════════════════════════════
// ONE ERA — Form Configuration
// Chỉnh sửa file này để thay đổi cấu hình form
// KHÔNG cần sửa index.html hay logic xử lý
// ═══════════════════════════════════════════════════════════

const FORM_CONFIG = {

  // ── GOOGLE SHEETS ──────────────────────────────────────────
  // 1. Mở Google Sheets mới
  // 2. Extensions → Apps Script → Dán code từ google-apps-script.js
  // 3. Deploy → Web App → Copy URL dán vào đây
  googleSheets: {
    enabled: true,
    webhookUrl: "https://script.google.com/macros/s/PASTE_YOUR_APPS_SCRIPT_URL_HERE/exec",
    sheetName: "ONE ERA Leads"
  },

  // ── EMAIL (EmailJS) ────────────────────────────────────────
  // 1. Đăng ký tại https://emailjs.com (miễn phí 200 email/tháng)
  // 2. Add Email Service (Gmail) → Copy Service ID
  // 3. Create Email Template → Copy Template ID
  // 4. Account → API Keys → Copy Public Key
  emailjs: {
    enabled: true,
    publicKey: "PASTE_YOUR_EMAILJS_PUBLIC_KEY",
    serviceId: "PASTE_YOUR_SERVICE_ID",
    templateId: "PASTE_YOUR_TEMPLATE_ID"
  },

  // ── CẤU HÌNH CHUNG ────────────────────────────────────────
  project: {
    name: "ONE ERA",
    receiverEmail: "oneerabykinera@gmail.com",
    receiverName: "Team ONE ERA",
    hotline: "0559239553"
  }

};

// Export cho Node.js (Vercel API) hoặc dùng trực tiếp trong browser
if (typeof module !== "undefined") module.exports = FORM_CONFIG;
