// ═══════════════════════════════════════════════════════════
// ONE ERA — Form Handler (assets/js/form.js)
// Validate → Loading animation → POST /api/submit-form
// Dùng cho cả CTA form và Popup form
// ═══════════════════════════════════════════════════════════

(function () {
  "use strict";

  // ── CSS Loading Animation (inject vào <head>) ────────────
  const style = document.createElement("style");
  style.textContent = `
    /* ── Spinner ── */
    .oe-btn-loading {
      position: relative;
      pointer-events: none;
      opacity: 0.85;
    }
    .oe-btn-loading .oe-btn-text { opacity: 0; }
    .oe-btn-loading::after {
      content: '';
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: inherit;
      border-radius: inherit;
    }
    .oe-spinner {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      width: 20px; height: 20px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: oe-spin 0.7s linear infinite;
      pointer-events: none;
    }
    @keyframes oe-spin {
      to { transform: translate(-50%, -50%) rotate(360deg); }
    }

    /* ── Toast notification ── */
    .oe-toast {
      position: fixed;
      bottom: 80px; left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: #1a1535;
      color: #fff;
      font-family: 'Be Vietnam Pro', sans-serif;
      font-size: 13px;
      font-weight: 300;
      letter-spacing: 0.03em;
      padding: 14px 24px;
      border-radius: 4px;
      border-left: 3px solid #c9a0dc;
      box-shadow: 0 8px 32px rgba(0,0,0,0.35);
      z-index: 99999;
      opacity: 0;
      transition: opacity 0.3s ease, transform 0.3s ease;
      max-width: calc(100vw - 40px);
      text-align: center;
      white-space: nowrap;
    }
    .oe-toast.oe-toast-success { border-left-color: #5dcaa5; }
    .oe-toast.oe-toast-error   { border-left-color: #f0997b; }
    .oe-toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    /* ── Input error state ── */
    .oe-input-error {
      border-color: rgba(240,153,123,0.7) !important;
      animation: oe-shake 0.35s ease;
    }
    @keyframes oe-shake {
      0%,100% { transform: translateX(0); }
      20%      { transform: translateX(-6px); }
      40%      { transform: translateX(6px); }
      60%      { transform: translateX(-4px); }
      80%      { transform: translateX(4px); }
    }

    /* ── Success state cho form ── */
    .oe-form-success {
      text-align: center;
      padding: 32px 16px;
      animation: oe-fadeIn 0.4s ease;
    }
    @keyframes oe-fadeIn {
      from { opacity:0; transform:translateY(12px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .oe-success-icon {
      width: 52px; height: 52px;
      border: 1.5px solid #c9a0dc;
      border-radius: 50%;
      display: flex; align-items:center; justify-content:center;
      margin: 0 auto 16px;
      color: #c9a0dc;
    }
    .oe-success-title {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 20px;
      font-weight: 300;
      color: #fff;
      margin-bottom: 8px;
    }
    .oe-success-desc {
      font-family: 'Be Vietnam Pro', sans-serif;
      font-size: 12px;
      font-weight: 300;
      color: rgba(255,255,255,0.5);
      line-height: 1.7;
    }
    /* Success trên nền sáng (CTA section) */
    .oe-form-success.light .oe-success-title { color: #1a1535; }
    .oe-form-success.light .oe-success-desc  { color: #7e6e96; }
  `;
  document.head.appendChild(style);

  // ── Toast helper ────────────────────────────────────────
  let toastEl = null;
  let toastTimer = null;

  function showToast(msg, type = "success") {
    if (!toastEl) {
      toastEl = document.createElement("div");
      toastEl.className = "oe-toast";
      document.body.appendChild(toastEl);
    }
    clearTimeout(toastTimer);
    toastEl.className = `oe-toast oe-toast-${type}`;
    toastEl.textContent = msg;
    requestAnimationFrame(() => {
      requestAnimationFrame(() => toastEl.classList.add("show"));
    });
    toastTimer = setTimeout(() => {
      toastEl.classList.remove("show");
    }, 4500);
  }

  // ── Button loading state ─────────────────────────────────
  function setLoading(btn, loading) {
    if (loading) {
      btn.classList.add("oe-btn-loading");
      // Wrap current content
      if (!btn.querySelector(".oe-btn-text")) {
        btn.innerHTML = `<span class="oe-btn-text">${btn.innerHTML}</span>`;
      }
      // Add spinner if not there
      if (!btn.querySelector(".oe-spinner")) {
        const sp = document.createElement("span");
        sp.className = "oe-spinner";
        btn.appendChild(sp);
      }
      btn.disabled = true;
    } else {
      btn.classList.remove("oe-btn-loading");
      btn.disabled = false;
    }
  }

  // ── Validate helpers ─────────────────────────────────────
  function validateName(input) {
    const ok = input.value.trim().length >= 2;
    input.classList.toggle("oe-input-error", !ok);
    return ok;
  }

  function validatePhone(input) {
    const digits = input.value.replace(/\D/g, "");
    const ok = digits.length >= 9 && digits.length <= 11;
    input.classList.toggle("oe-input-error", !ok);
    return ok;
  }

  // ── Core submit handler ──────────────────────────────────
  async function handleSubmit(btn, fields, sourceLabel, wrapEl) {
    const nameInput  = fields.name;
    const phoneInput = fields.phone;
    const emailInput = fields.email;

    // Clear previous errors
    [nameInput, phoneInput, emailInput].forEach(el => el && el.classList.remove("oe-input-error"));

    // Validate
    let valid = true;
    if (!validateName(nameInput))  valid = false;
    if (!validatePhone(phoneInput)) valid = false;

    if (!valid) {
      const firstErr = [nameInput, phoneInput].find(el => el.classList.contains("oe-input-error"));
      if (firstErr) firstErr.focus();
      return;
    }

    // Loading
    setLoading(btn, true);

    try {
      const res = await fetch("/api/submit-form", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
          name:   nameInput.value.trim(),
          phone:  phoneInput.value.replace(/\D/g, ""),
          email:  emailInput ? emailInput.value.trim() : "",
          source: sourceLabel,
        }),
      });

      const data = await res.json();

      setLoading(btn, false);

      if (data.ok) {
        // Hiện success state trong form wrapper
        const isDark = wrapEl.closest("#cta") === null; // popup = dark
        const safeName  = document.createTextNode(nameInput.value.trim());
        const safePhone = document.createTextNode(phoneInput.value);
        const successDiv = document.createElement("div");
        successDiv.className = `oe-form-success ${!isDark ? 'light' : ''}`;
        successDiv.innerHTML = `
          <div class="oe-success-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
          </div>
          <div class="oe-success-title">Đăng ký thành công!</div>
          <div class="oe-success-desc">
            Cảm ơn <strong class="js-name"></strong>.<br>
            Chuyên viên sẽ liên hệ <strong class="js-phone"></strong><br>
            trong vòng 30 phút làm việc.
          </div>
        `;
        successDiv.querySelector(".js-name").appendChild(safeName);
        successDiv.querySelector(".js-phone").appendChild(safePhone);
        wrapEl.innerHTML = "";
        wrapEl.appendChild(successDiv);
        showToast("✓ " + (data.message || "Đăng ký thành công!"), "success");
      } else {
        showToast(data.error || "Có lỗi xảy ra, vui lòng thử lại.", "error");
      }

    } catch (err) {
      setLoading(btn, false);
      showToast("Mất kết nối, vui lòng thử lại hoặc gọi 0865149461", "error");
    }
  }

  // ── Bind CTA form ────────────────────────────────────────
  function bindCtaForm() {
    const wrap  = document.querySelector(".cta-form");
    if (!wrap) return;

    const nameInput  = wrap.querySelector('input[type="text"]');
    const phoneInput = wrap.querySelector('input[type="tel"]');
    const emailInput = wrap.querySelector('input[type="email"]');
    const btn        = wrap.querySelector("button");
    if (!btn) return;

    btn.addEventListener("click", (e) => {
      e.preventDefault();
      handleSubmit(btn, { name: nameInput, phone: phoneInput, email: emailInput }, "CTA Form", wrap);
    });

    // Clear error on input
    [nameInput, phoneInput].forEach(el => el && el.addEventListener("input", () => el.classList.remove("oe-input-error")));
  }

  // ── Bind Popup form ──────────────────────────────────────
  function bindPopupForm() {
    const wrap  = document.querySelector(".popup-form");
    if (!wrap) return;

    const nameInput  = wrap.querySelector('input[type="text"]');
    const phoneInput = wrap.querySelector('input[type="tel"]');
    const btn        = wrap.querySelector("button");
    if (!btn) return;

    // Remove old onclick (was just closePopup)
    btn.removeAttribute("onclick");

    btn.addEventListener("click", (e) => {
      e.preventDefault();
      handleSubmit(btn, { name: nameInput, phone: phoneInput, email: null }, "Popup - Bảng Giá", wrap);
    });

    [nameInput, phoneInput].forEach(el => el && el.addEventListener("input", () => el.classList.remove("oe-input-error")));
  }

  // ── Init ─────────────────────────────────────────────────
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  function init() {
    bindCtaForm();
    bindPopupForm();
  }

})();
