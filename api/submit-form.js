// ═══════════════════════════════════════════════════════════
// ONE ERA — Vercel Serverless API: /api/submit-form.js
// Validate → Google Sheets → Resend (email)
// ═══════════════════════════════════════════════════════════

export default async function handler(req, res) {
  if (req.method === "OPTIONS") return res.status(200).end();
  if (req.method !== "POST") {
    return res.status(405).json({ ok: false, error: "Method not allowed" });
  }

  try {
    const body = req.body || {};
    const { name, phone, email, source } = body;

    if (!name || name.trim().length < 2) {
      return res.status(400).json({ ok: false, error: "Vui lòng nhập họ tên (ít nhất 2 ký tự)" });
    }
    const cleanPhone = (phone || "").replace(/\D/g, "");
    if (!cleanPhone || cleanPhone.length < 9 || cleanPhone.length > 11) {
      return res.status(400).json({ ok: false, error: "Số điện thoại không hợp lệ" });
    }

    const SHEETS_URL   = process.env.GOOGLE_SHEETS_URL;
    const RESEND_KEY   = process.env.RESEND_API_KEY;
    const RECEIVER_RAW = process.env.RECEIVER_EMAIL || "oneerabykinera@gmail.com";
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

    // ── Google Sheets ─────────────────────────────────────
    if (SHEETS_URL && SHEETS_URL.includes("script.google.com")) {
      try {
        const r = await fetch(SHEETS_URL, {
          method:  "POST",
          headers: { "Content-Type": "application/json" },
          body:    JSON.stringify(leadData),
        });
        const j = await r.json().catch(() => ({}));
        results.sheets = j.result === "success" ? "ok" : "error";
      } catch {
        results.sheets = "error";
      }
    } else {
      results.sheets = "skipped";
    }

    // ── Resend Email ──────────────────────────────────────
    if (RESEND_KEY) {
      try {
        const r = await fetch("https://api.resend.com/emails", {
          method:  "POST",
          headers: {
            "Content-Type":  "application/json",
            "Authorization": `Bearer ${RESEND_KEY}`,
          },
          body: JSON.stringify({
            from: "ONE ERA <noreply@hoangphuc.space>",
            to:      RECEIVERS,
            subject: `[ONE ERA] Lead mới — ${leadData.name} — ${leadData.phone}`,
            html:    buildEmailHtml(leadData),
          }),
        });
        const j = await r.json().catch(() => ({}));
        results.email = r.ok ? "ok" : "error";
        if (!r.ok) console.error("[Resend error]", j);
      } catch (e) {
        results.email = "error";
        console.error("[Resend exception]", e);
      }
    } else {
      results.email = "skipped";
    }

    const success = results.sheets === "ok" || results.email === "ok"
                 || (results.sheets === "skipped" && results.email === "skipped");

    return res.status(200).json({
      ok: success,
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

function buildEmailHtml(d) {
  return `<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#f4f1eb;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1eb;padding:32px 16px;">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e8e0d0;">
  <tr><td style="background:#1a1a1a;padding:20px 28px;">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td><div style="font-size:10px;letter-spacing:3px;color:#b8975a;font-weight:700;">ONE ERA</div>
          <div style="font-size:9px;letter-spacing:1.5px;color:#666;">BY KINERA</div></td>
      <td align="right"><span style="background:#b8975a;color:#1a1a1a;font-size:10px;font-weight:700;padding:5px 14px;border-radius:3px;">LEAD MỚI</span></td>
    </tr></table>
  </td></tr>
  <tr><td style="height:3px;background:#b8975a;font-size:0;">&nbsp;</td></tr>
  <tr><td style="background:#fffbf2;border-bottom:1px solid #e8d9b0;padding:13px 28px;">
    <span style="font-size:13px;color:#7a5c1e;font-weight:500;">Có khách hàng mới vừa đăng ký tư vấn tại website</span>
  </td></tr>
  <tr><td style="padding:28px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eaeaea;border-radius:6px;overflow:hidden;margin-bottom:20px;">
      <tr><td colspan="2" style="background:#f9f9f7;padding:10px 16px;border-bottom:1px solid #eaeaea;">
        <span style="font-size:11px;font-weight:700;color:#888;letter-spacing:1px;text-transform:uppercase;">Thông tin khách hàng</span>
      </td></tr>
      <tr>
        <td style="padding:11px 16px;color:#888;font-size:13px;width:130px;border-bottom:1px solid #f0f0f0;">Họ tên</td>
        <td style="padding:11px 16px;color:#1a1a1a;font-size:13px;font-weight:700;border-bottom:1px solid #f0f0f0;">${d.name}</td>
      </tr>
      <tr>
        <td style="padding:11px 16px;color:#888;font-size:13px;border-bottom:1px solid #f0f0f0;">Điện thoại</td>
        <td style="padding:11px 16px;color:#b8975a;font-size:15px;font-weight:700;border-bottom:1px solid #f0f0f0;">${d.phone}</td>
      </tr>
      <tr>
        <td style="padding:11px 16px;color:#888;font-size:13px;border-bottom:1px solid #f0f0f0;">Email</td>
        <td style="padding:11px 16px;color:#1a1a1a;font-size:13px;border-bottom:1px solid #f0f0f0;">${d.email || "(không có)"}</td>
      </tr>
      <tr>
        <td style="padding:11px 16px;color:#888;font-size:13px;border-bottom:1px solid #f0f0f0;">Nguồn</td>
        <td style="padding:11px 16px;border-bottom:1px solid #f0f0f0;">
          <span style="background:#e8f0fb;color:#1a5fa8;font-size:11px;font-weight:700;padding:3px 10px;border-radius:3px;">${d.source}</span>
        </td>
      </tr>
      <tr>
        <td style="padding:11px 16px;color:#888;font-size:13px;">Thời gian</td>
        <td style="padding:11px 16px;color:#aaa;font-size:12px;">${d.timestamp}</td>
      </tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#faf8f4;border-left:3px solid #b8975a;margin-bottom:24px;">
      <tr><td style="padding:14px 16px;font-size:13px;color:#5c4a1e;line-height:1.7;">
        <strong>Lưu ý:</strong> Vui lòng liên hệ khách hàng trong vòng <strong>24 giờ</strong> để tư vấn và giới thiệu dự án ONE ERA.
      </td></tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #eaeaea;">
      <tr><td style="padding-top:20px;font-size:12px;color:#aaa;line-height:1.8;">
        Email này được gửi tự động từ hệ thống website ONE ERA.<br/>Không trả lời email này.
      </td></tr>
    </table>
  </td></tr>
  <tr><td style="background:#f9f9f7;border-top:1px solid #eaeaea;padding:14px 28px;">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td style="font-size:11px;color:#aaa;">© 2025 ONE ERA by Kinera</td>
      <td align="right" style="font-size:11px;color:#aaa;">Hệ thống tự động</td>
    </tr></table>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>`;
}
