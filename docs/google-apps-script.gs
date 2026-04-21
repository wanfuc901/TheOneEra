// ═══════════════════════════════════════════════════════════
// ONE ERA — Google Apps Script
// Hướng dẫn:
//   1. Mở Google Sheets mới, đặt tên "ONE ERA Leads"
//   2. Extensions → Apps Script → Xóa code cũ, dán code này vào
//   3. Nhấn Save (Ctrl+S)
//   4. Deploy → New Deployment → Web App
//      - Execute as: Me
//      - Who has access: Anyone
//   5. Nhấn Deploy → Copy URL
//   6. Dán URL vào Vercel Dashboard:
//      Settings → Environment Variables → GOOGLE_SHEETS_URL
// ═══════════════════════════════════════════════════════════

const SHEET_NAME = "Leads";
const HEADERS    = ["STT", "Thời gian", "Họ tên", "Số điện thoại", "Email", "Nguồn"];

function doPost(e) {
  try {
    const ss    = SpreadsheetApp.getActiveSpreadsheet();
    let sheet   = ss.getSheetByName(SHEET_NAME);

    // Tạo sheet + header nếu chưa có
    if (!sheet) {
      sheet = ss.insertSheet(SHEET_NAME);
      const headerRow = sheet.getRange(1, 1, 1, HEADERS.length);
      headerRow.setValues([HEADERS]);
      headerRow.setFontWeight("bold");
      headerRow.setBackground("#1a1535");
      headerRow.setFontColor("#c9a0dc");
      sheet.setFrozenRows(1);
    }

    // Parse data
    const data = JSON.parse(e.postData.contents);
    const lastRow = sheet.getLastRow();
    const stt     = lastRow; // header là row 1, nên stt = lastRow

    // Append row
    sheet.appendRow([
      stt,
      data.timestamp || new Date().toLocaleString("vi-VN"),
      data.name      || "",
      data.phone     || "",
      data.email     || "",
      data.source    || "Website",
    ]);

    // Auto-resize columns
    sheet.autoResizeColumns(1, HEADERS.length);

    return ContentService
      .createTextOutput(JSON.stringify({ result: "success", row: lastRow + 1 }))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ result: "error", message: err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

// Test function - chạy trong Apps Script editor để kiểm tra
function testDoPost() {
  const mockEvent = {
    postData: {
      contents: JSON.stringify({
        name:      "Test User",
        phone:     "0901234567",
        email:     "test@example.com",
        source:    "Website - Test",
        timestamp: new Date().toLocaleString("vi-VN"),
      })
    }
  };
  const result = doPost(mockEvent);
  Logger.log(result.getContent());
}
