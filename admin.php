<?php
// =============================================
// ALL PHP LOGIC MUST RUN BEFORE ANY HTML OUTPUT
// =============================================

$configFile = 'assets/config.json';
$imagesDir  = 'assets/images/';

// Default config structure - now using flexible key-based system
$defaultConfig = [
    "images" => [],
    "layout" => []
];

$config = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : $defaultConfig;

// Ensure structure
if (!isset($config['images'])) $config['images'] = [];
if (!isset($config['layout'])) $config['layout'] = [];

$uploadMessage = '';

// ---- Auto-scan ----
if (isset($_GET['autoscan']) && $_GET['autoscan'] === '1') {
    $indexFile = 'index.html';
    if (file_exists($indexFile)) {
        $html = file_get_contents($indexFile);
        $config['images'] = [];
        
        // Find all img tags with data-img-id attribute
        if (preg_match_all('/data-img-id=["\']([^"\']+)["\'].*?src=["\']([^"\']+)["\']', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $imgId = $match[1];
                $src = $match[2];
                
                // Skip placeholder/template patterns
                if (strpos($src, '<!--') !== false || empty($src)) continue;
                
                $config['images'][$imgId] = [
                    'src' => $src,
                    'position' => $imgId,
                    'alt' => 'Image ' . $imgId,
                    'status' => 'active'
                ];
            }
        }
        
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        header('Location: admin.php?message=autoscan_success');
        exit;
    }
}

// ---- Delete ----
if (isset($_GET['delete'])) {
    $imgId = $_GET['delete'] ?? '';
    if ($imgId && isset($config['images'][$imgId])) {
        $img = $config['images'][$imgId];
        // Delete physical file if it's not a data URI
        if (isset($img['src']) && file_exists($img['src']) && strpos($img['src'], 'data:') === false) {
            unlink($img['src']);
        }
        unset($config['images'][$imgId]);
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        header('Location: admin.php');
        exit;
    }
}

// ---- Reorder (AJAX) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
    $order = json_decode($_POST['order'], true);
    $newImages = [];
    foreach ($order as $imgId) {
        if (isset($config['images'][$imgId])) {
            $newImages[$imgId] = $config['images'][$imgId];
        }
    }
    $config['images'] = $newImages;
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo 'OK';
    exit;
}

// ---- Upload ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $imgId = $_POST['imgId'] ?? '';
    $file = $_FILES['image'];
    
    if ($file['error'] === UPLOAD_ERR_OK && !empty($imgId)) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $path = $imagesDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $path)) {
            // Delete old file if exists
            if (isset($config['images'][$imgId]['src']) && file_exists($config['images'][$imgId]['src']) && strpos($config['images'][$imgId]['src'], 'data:') === false) {
                unlink($config['images'][$imgId]['src']);
            }
            
            $config['images'][$imgId] = [
                'src' => $path,
                'position' => $imgId,
                'alt' => $_POST['alt'] ?? 'Image ' . $imgId,
                'status' => 'active'
            ];
            
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $uploadMessage = '<div class="message success"><i class="fas fa-check"></i> Cập nhật ảnh thành công!</div>';
        } else {
            $uploadMessage = '<div class="message error"><i class="fas fa-times"></i> Lỗi upload ảnh!</div>';
        }
    } else {
        $uploadMessage = '<div class="message error"><i class="fas fa-times"></i> Vui lòng chọn vị trí ảnh!</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ONE ERA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Be Vietnam Pro', sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #1a1535; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section { margin-bottom: 30px; }
        .section h2 { border-bottom: 2px solid #c9a0dc; padding-bottom: 10px; margin-bottom: 20px; }
        .upload-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .upload-form input[type="file"],
        .upload-form input[type="text"],
        .upload-form select { padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: white; }
        .upload-form button { padding: 10px 20px; background: #c9a0dc; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .upload-form button:hover { background: #dbbdee; }
        .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .gallery-item { position: relative; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; cursor: move; }
        .gallery-item img { width: 100%; height: 150px; object-fit: cover; }
        .gallery-item .caption { padding: 10px; font-size: 12px; background: rgba(0,0,0,0.7); color: white; position: absolute; bottom: 0; left: 0; right: 0; }
        .gallery-item .delete-btn { position: absolute; top: 5px; right: 5px; background: red; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer; }
        .gallery-item .edit-btn { position: absolute; top: 5px; left: 5px; background: #007bff; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #ddd; flex-wrap: wrap; }
        .tab-btn { padding: 10px 20px; background: none; border: none; cursor: pointer; border-bottom: 2px solid transparent; }
        .tab-btn.active { border-bottom-color: #c9a0dc; color: #1a1535; font-weight: bold; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .save-btn { padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .save-btn:hover { background: #5a6268; }
        .preview-btn { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; margin-left: 10px; display: inline-block; }
        .preview-btn:hover { background: #218838; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .auto-scan-btn { padding: 10px 20px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; font-size: 14px; text-decoration: none; display: inline-block; }
        .auto-scan-btn:hover { background: #138496; }
        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 14px; line-height: 1.6; }
        .empty-notice { color: #999; font-style: italic; padding: 20px 0; }
    </style>
</head>
<body>
<div class="header">
    <h1><i class="fas fa-cog"></i> Admin Dashboard - ONE ERA</h1>
    <p>Quản lý nội dung trang web</p>
</div>
<div class="container">

    <?php if (isset($_GET['message']) && $_GET['message'] === 'autoscan_success'): ?>
    <div class="message success"><i class="fas fa-check"></i> Auto-scan hoàn tất! Đã tìm thấy và import các ảnh từ index.html</div>
    <?php endif; ?>

    <?php echo $uploadMessage; ?>

    <div class="info-box">
        <strong><i class="fas fa-info-circle"></i> Thông tin:</strong> Nhấn nút "Auto-Scan" để tự động quét và import tất cả ảnh từ index.html vào dashboard.
        <br>
        <a href="admin.php?autoscan=1" class="auto-scan-btn"><i class="fas fa-search"></i> Auto-Scan Từ index.html</a>
    </div>

    <div class="section">
        <h2><i class="fas fa-upload"></i> Cập Nhật Ảnh</h2>
        <form method="post" enctype="multipart/form-data" class="upload-form">
            <select name="imgId" required>
                <option value="">-- Chọn vị trí ảnh --</option>
                <?php foreach ($config['images'] as $imgId => $imgData): ?>
                <option value="<?= htmlspecialchars($imgId) ?>"><?= htmlspecialchars($imgData['position'] ?? $imgId) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="file" name="image" accept="image/*" required>
            <input type="text" name="alt" placeholder="Mô tả ảnh (alt)">
            <button type="submit"><i class="fas fa-upload"></i> Cập Nhật</button>
        </form>
    </div>

    <div class="section">
        <h2><i class="fas fa-images"></i> Quản Lý Tất Cả Ảnh</h2>
        <p>Kéo thả để thay đổi vị trí. Nhấn "Lưu Thay Đổi" để áp dụng.</p><br>
        <div class="gallery" id="gallery-all">
            <?php if (empty($config['images'])): ?>
            <p class="empty-notice">Chưa có ảnh nào. Vui lòng nhấn "Auto-Scan Từ index.html" để tìm tất cả ảnh.</p>
            <?php else: ?>
                <?php foreach ($config['images'] as $imgId => $imgData): ?>
                <div class="gallery-item" data-img-id="<?= htmlspecialchars($imgId) ?>">
                    <img src="<?= htmlspecialchars($imgData['src']) ?>" alt="<?= htmlspecialchars($imgData['alt'] ?? 'Image') ?>" style="width:100%;height:150px;object-fit:cover;">
                    <div class="caption"><?= htmlspecialchars($imgData['position'] ?? $imgId) ?></div>
                    <button class="delete-btn" onclick="deleteImage('<?= htmlspecialchars($imgId) ?>')" title="Xóa" type="button"><i class="fas fa-trash"></i></button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <br>
        <button class="save-btn" onclick="saveOrder()"><i class="fas fa-save"></i> Lưu Thay Đổi</button>
        <a href="index.html" target="_blank" class="preview-btn"><i class="fas fa-eye"></i> Xem Trang User</a>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var g = document.getElementById('gallery-all');
    if (g) Sortable.create(g, { animation: 150 });
});

function saveOrder() {
    var gallery = document.getElementById('gallery-all');
    if (!gallery) { alert('Không tìm thấy gallery'); return; }
    var items = gallery.querySelectorAll('.gallery-item');
    if (items.length === 0) { alert('Không có ảnh để lưu'); return; }
    var order = Array.from(items).map(function (item) { return item.dataset.imgId; });
    fetch('admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order=' + encodeURIComponent(JSON.stringify(order))
    }).then(function (r) {
        if (r.ok) { alert('Lưu thay đổi thành công!'); location.reload(); }
        else { alert('Lỗi lưu thay đổi!'); }
    });
}

function deleteImage(imgId) {
    if (confirm('Bạn có chắc muốn xóa ảnh này?')) {
        window.location.href = 'admin.php?delete=' + encodeURIComponent(imgId);
    }
}
</script>
</script>
</body>
</html>