// ═══════════════════════════════════════════════════════════
// ONE ERA — Vercel Serverless API: /api/submit-form.js
// Xử lý form submission: validate → Google Sheets → Email
// ═══════════════════════════════════════════════════════════

export default async function handler(req, res) {
  // Handle CORS preflight
  if (req.method === "OPTIONS") {
    return res.status(200).end();
  }

  if (req.method !== "POST") {
    return res.status(405).json({ ok: false, error: "Method not allowed" });
  }

  try {
    const body = req.body || {};
    const { name, phone, email, source } = body;

    // ── Validate ──────────────────────────────────────────
    if (!name || name.trim().length < 2) {
      return res.status(400).json({ ok: false, error: "Vui lòng nhập họ tên (ít nhất 2 ký tự)" });
    }

    const cleanPhone = (phone || "").replace(/\D/g, "");
    if (!cleanPhone || cleanPhone.length < 9 || cleanPhone.length > 11) {
      return res.status(400).json({ ok: false, error: "Số điện thoại không hợp lệ" });
    }

    // ── Đọc config từ environment variables (Vercel Dashboard) ──
    const SHEETS_URL   = process.env.GOOGLE_SHEETS_URL;
    const EJS_KEY      = process.env.EMAILJS_PUBLIC_KEY;
    const EJS_SERVICE  = process.env.EMAILJS_SERVICE_ID;
    const EJS_TEMPLATE = process.env.EMAILJS_TEMPLATE_ID;
    const RECEIVER_RAW = process.env.RECEIVER_EMAIL || "oneerabykinera@gmail.com";
    // Hỗ trợ nhiều email cách nhau bằng dấu phẩy
    const RECEIVERS    = RECEIVER_RAW.split(",").map(e => e.trim()).filter(Boolean);

    const timestamp = new Date().toLocaleString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh" });
    const leadData  = {
      name:      name.trim(),
      phone:     cleanPhone,
      email:     email ? email.trim() : "",
      source:    source || "Website",
      timestamp,
    };

    const results = { sheets: null, email: null };
    const errors  = [];

    // ── Ghi Google Sheets ─────────────────────────────────
    if (SHEETS_URL && SHEETS_URL.includes("script.google.com")) {
      try {
        const sheetsRes = await fetch(SHEETS_URL, {
          method:  "POST",
          headers: { "Content-Type": "application/json" },
          body:    JSON.stringify(leadData),
        });
        const sheetsJson = await sheetsRes.json().catch(() => ({}));
        results.sheets = sheetsJson.result === "success" ? "ok" : "error";
        if (results.sheets === "error") errors.push("sheets");
      } catch (e) {
        results.sheets = "error";
        errors.push("sheets");
      }
    } else {
      results.sheets = "skipped";
    }

    // ── Gửi Email qua EmailJS REST API (hỗ trợ nhiều người nhận) ───────────────────
    if (EJS_KEY && EJS_SERVICE && EJS_TEMPLATE) {
      try {
        // Gửi đồng thời tới tất cả email trong danh sách
        const sendAll = RECEIVERS.map(toEmail =>
          fetch("https://api.emailjs.com/api/v1.0/email/send", {
            method:  "POST",
            headers: { "Content-Type": "application/json" },
            body:    JSON.stringify({
              service_id:  EJS_SERVICE,
              template_id: EJS_TEMPLATE,
              user_id:     EJS_KEY,
              template_params: {
                to_email:  toEmail,
                from_name: leadData.name,
                phone:     leadData.phone,
                email:     leadData.email || "(không có)",
                source:    leadData.source,
                timestamp: leadData.timestamp,
                reply_to:  leadData.email || RECEIVERS[0],
              },
            }),
          })
        );
        const responses = await Promise.all(sendAll);
        const allOk = responses.every(r => r.ok);
        results.email = allOk ? "ok" : "partial";
        if (!allOk) errors.push("email");
      } catch (e) {
        results.email = "error";
        errors.push("email");
      }
    } else {
      results.email = "skipped";
    }

    // ── Response ──────────────────────────────────────────
    // Thành công nếu ít nhất 1 kênh ghi được
    const success = results.sheets === "ok" || results.email === "ok"
                 || results.sheets === "skipped" && results.email === "skipped"; // dev mode

    return res.status(200).json({
      ok:      success,
      message: success
        ? "Đăng ký thành công! Chúng tôi sẽ liên hệ trong 30 phút."
        : "Có lỗi xảy ra, vui lòng gọi hotline 0559239553.",
      results,
    });

  } catch (err) {
    console.error("[submit-form] Unexpected error:", err);
    return res.status(500).json({ ok: false, error: "Server error" });
  }
}
