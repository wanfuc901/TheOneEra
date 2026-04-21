<?php
// =============================================
// ALL PHP LOGIC MUST RUN BEFORE ANY HTML OUTPUT
// =============================================

$configFile = 'assets/config.json';
$imagesDir  = 'assets/images/';

$defaultConfig = [
    "loader"       => ["logo" => ""],
    "hero"         => ["bg" => ""],
    "brand"        => [],
    "masterplan"   => ["img" => ""],
    "lifestyle"    => [],
    "construction" => [],
    "images"       => [],
    "layout"       => []
];

$config = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : $defaultConfig;

// Ensure all keys exist
foreach ($defaultConfig as $k => $v) {
    if (!isset($config[$k])) $config[$k] = $v;
}

$message = '';
$msgType = 'success';

// ---- Helper ----
function detectImageType($src) {
    if (empty($src)) return 'empty';
    if (strpos($src, 'data:image') === 0) return 'base64';
    if (strpos($src, 'http://') === 0 || strpos($src, 'https://') === 0) return 'url';
    if (strpos($src, '<!--') !== false) return 'placeholder';
    if (file_exists($src)) return 'file';
    return 'path';
}

function saveConfig($config, $configFile) {
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function ensureImagesDir($dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

function handleUpload($file, $imagesDir) {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    ensureImagesDir($imagesDir);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','svg'];
    if (!in_array($ext, $allowed)) return false;
    $filename = uniqid() . '.' . $ext;
    $path = $imagesDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $path)) return $path;
    return false;
}

function deleteOldFile($src) {
    if (!empty($src) && file_exists($src) && strpos($src, 'data:') === false && strpos($src, 'http') === false) {
        @unlink($src);
    }
}

// ---- Auto-scan ----
if (isset($_GET['autoscan']) && $_GET['autoscan'] === '1') {
    $indexFile = 'index.html';
    if (file_exists($indexFile)) {
        $html = file_get_contents($indexFile);
        $config['images'] = [];
        $imgCounter = 1;
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $imgs = $dom->getElementsByTagName('img');
        $sections = [
            'loader' => 'Loader Logo','hero' => 'Hero Background','brand-story' => 'Brand Story',
            'brand-img' => 'Brand Images','masterplan' => 'Masterplan','lifestyle' => 'Lifestyle Gallery',
            'construction' => 'Tiến Độ Xây Dựng','events' => 'Events','renders' => 'Renders',
            'location' => 'Location Map','products' => 'Sản Phẩm','tab-canho' => 'Căn Hộ',
            'tab-nhapho' => 'Nhà Phố','tab-bietthu' => 'Biệt Thự'
        ];
        foreach ($imgs as $img) {
            $src = $img->getAttribute('src');
            $imgId = $img->getAttribute('data-img-id');
            $alt = $img->getAttribute('alt');
            if (empty($src)) continue;
            $imgType = detectImageType($src);
            if ($imgType === 'placeholder' || $imgType === 'empty') continue;
            if (empty($imgId)) $imgId = 'auto_' . $imgCounter++;
            if (isset($config['images'][$imgId])) continue;
            $pos = strpos($html, 'data-img-id="' . $imgId . '"');
            if ($pos === false) $pos = strpos($html, $src);
            $offset = $pos !== false ? (int)$pos : 0;
            $before = substr($html, max(0, $offset - 2000), 2000);
            $section = 'Other';
            foreach ($sections as $key => $label) {
                if (stripos($before, 'id="' . $key . '"') !== false) { $section = $label; break; }
            }
            $config['images'][$imgId] = [
                'src' => $src, 'type' => $imgType, 'section' => $section,
                'position' => $imgId, 'alt' => $alt ?: 'Image ' . $imgId, 'status' => 'active'
            ];
        }
        saveConfig($config, $configFile);
        header('Location: admin.php?tab=images&msg=autoscan_ok');
        exit;
    }
}

// ---- Convert Base64 to File ----
if (isset($_GET['convert_base64'])) {
    $converted = 0;
    ensureImagesDir($imagesDir);
    $convertFn = function(&$src) use ($imagesDir, &$converted) {
        if (strpos($src, 'data:image') === 0 && preg_match('/^data:image\/(\w+);base64,(.+)$/', $src, $m)) {
            $data = base64_decode($m[2]);
            if ($data) {
                $path = $imagesDir . uniqid() . '.' . strtolower($m[1]);
                if (file_put_contents($path, $data)) { $src = $path; $converted++; }
            }
        }
    };
    if (isset($config['loader']['logo'])) $convertFn($config['loader']['logo']);
    if (isset($config['hero']['bg'])) $convertFn($config['hero']['bg']);
    if (isset($config['masterplan']['img'])) $convertFn($config['masterplan']['img']);
    foreach ($config['images'] as &$img) { if (isset($img['src'])) $convertFn($img['src']); } unset($img);
    foreach ($config['lifestyle'] as &$item) { if (isset($item['src'])) $convertFn($item['src']); } unset($item);
    foreach ($config['construction'] as &$item) { if (isset($item['src'])) $convertFn($item['src']); } unset($item);
    foreach ($config['brand'] as &$item) { if (isset($item['src'])) $convertFn($item['src']); } unset($item);
    saveConfig($config, $configFile);
    header('Location: admin.php?msg=converted_' . $converted);
    exit;
}

// ---- AJAX: Reorder images ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    $section = $_POST['section'] ?? '';
    $order = json_decode($_POST['order'] ?? '[]', true);
    if ($section === 'images') {
        $new = [];
        foreach ($order as $id) { if (isset($config['images'][$id])) $new[$id] = $config['images'][$id]; }
        $config['images'] = $new;
    } elseif (in_array($section, ['lifestyle','construction','brand'])) {
        $items = $config[$section];
        $new = [];
        foreach ($order as $idx) { if (isset($items[(int)$idx])) $new[] = $items[(int)$idx]; }
        $config[$section] = $new;
    }
    saveConfig($config, $configFile);
    echo 'OK'; exit;
}

// ---- AJAX: Update alt text ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_alt') {
    $section = $_POST['section'] ?? '';
    $id = $_POST['id'] ?? '';
    $alt = $_POST['alt'] ?? '';
    if ($section === 'images' && isset($config['images'][$id])) {
        $config['images'][$id]['alt'] = $alt;
    } elseif (in_array($section, ['lifestyle','construction','brand'])) {
        $idx = (int)$id;
        if (isset($config[$section][$idx])) $config[$section][$idx]['alt'] = $alt;
    }
    saveConfig($config, $configFile);
    echo 'OK'; exit;
}

// ---- AJAX: Update caption ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_caption') {
    $section = $_POST['section'] ?? '';
    $id = $_POST['id'] ?? '';
    $caption = $_POST['caption'] ?? '';
    if (in_array($section, ['lifestyle','construction'])) {
        $idx = (int)$id;
        if (isset($config[$section][$idx])) $config[$section][$idx]['caption'] = $caption;
    }
    saveConfig($config, $configFile);
    echo 'OK'; exit;
}

// ---- Delete image from array sections ----
if (isset($_GET['delete_item'])) {
    $section = $_GET['section'] ?? '';
    $idx = (int)$_GET['delete_item'];
    if (in_array($section, ['lifestyle','construction','brand'])) {
        if (isset($config[$section][$idx])) {
            deleteOldFile($config[$section][$idx]['src'] ?? '');
            array_splice($config[$section], $idx, 1);
            saveConfig($config, $configFile);
        }
    }
    header('Location: admin.php?tab=' . $section . '&msg=deleted');
    exit;
}

// ---- Delete image from images dict ----
if (isset($_GET['delete'])) {
    $imgId = $_GET['delete'];
    if (isset($config['images'][$imgId])) {
        deleteOldFile($config['images'][$imgId]['src'] ?? '');
        unset($config['images'][$imgId]);
        saveConfig($config, $configFile);
    }
    header('Location: admin.php?tab=images&msg=deleted');
    exit;
}

// ---- POST: Upload/Update single image (loader, hero, masterplan) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- Loader logo ---
    if ($action === 'update_loader' && isset($_FILES['image'])) {
        $path = handleUpload($_FILES['image'], $imagesDir);
        if ($path) {
            deleteOldFile($config['loader']['logo'] ?? '');
            $config['loader']['logo'] = $path;
            saveConfig($config, $configFile);
            $message = 'Cập nhật logo loader thành công!';
        } else { $message = 'Lỗi upload ảnh!'; $msgType = 'error'; }
        header('Location: admin.php?tab=loader&msg=' . urlencode($message)); exit;
    }

    // --- Hero bg ---
    if ($action === 'update_hero') {
        $url = trim($_POST['url'] ?? '');
        if (!empty($url)) {
            $config['hero']['bg'] = $url;
            saveConfig($config, $configFile);
            $message = 'Cập nhật hero background thành công!';
            header('Location: admin.php?tab=hero&msg=' . urlencode($message)); exit;
        } elseif (isset($_FILES['image'])) {
            $path = handleUpload($_FILES['image'], $imagesDir);
            if ($path) {
                deleteOldFile($config['hero']['bg'] ?? '');
                $config['hero']['bg'] = $path;
                saveConfig($config, $configFile);
                $message = 'Cập nhật hero background thành công!';
            } else { $message = 'Lỗi upload ảnh!'; $msgType = 'error'; }
            header('Location: admin.php?tab=hero&msg=' . urlencode($message)); exit;
        }
    }

    // --- Masterplan ---
    if ($action === 'update_masterplan') {
        $url = trim($_POST['url'] ?? '');
        if (!empty($url)) {
            $config['masterplan']['img'] = $url;
            saveConfig($config, $configFile);
            header('Location: admin.php?tab=masterplan&msg=' . urlencode('Cập nhật masterplan thành công!')); exit;
        } elseif (isset($_FILES['image'])) {
            $path = handleUpload($_FILES['image'], $imagesDir);
            if ($path) {
                deleteOldFile($config['masterplan']['img'] ?? '');
                $config['masterplan']['img'] = $path;
                saveConfig($config, $configFile);
                $message = 'Cập nhật masterplan thành công!';
            } else { $message = 'Lỗi upload ảnh!'; $msgType = 'error'; }
            header('Location: admin.php?tab=masterplan&msg=' . urlencode($message)); exit;
        }
    }

    // --- Add to lifestyle / construction / brand ---
    if (in_array($action, ['add_lifestyle','add_construction','add_brand'])) {
        $section = str_replace('add_', '', $action);
        $url = trim($_POST['url'] ?? '');
        $alt = trim($_POST['alt'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $src = '';
        if (!empty($url)) {
            $src = $url;
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $path = handleUpload($_FILES['image'], $imagesDir);
            if ($path) $src = $path;
        }
        if (!empty($src)) {
            $item = ['src' => $src, 'alt' => $alt ?: 'Ảnh ' . (count($config[$section]) + 1), 'caption' => $caption];
            $config[$section][] = $item;
            saveConfig($config, $configFile);
        }
        header('Location: admin.php?tab=' . $section . '&msg=added'); exit;
    }

    // --- Update existing image in images dict ---
    if ($action === 'update_image') {
        $imgId = $_POST['imgId'] ?? '';
        $url = trim($_POST['url'] ?? '');
        $alt = trim($_POST['alt'] ?? '');
        if (!empty($imgId) && isset($config['images'][$imgId])) {
            if (!empty($url)) {
                $config['images'][$imgId]['src'] = $url;
                $config['images'][$imgId]['type'] = 'url';
            } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $path = handleUpload($_FILES['image'], $imagesDir);
                if ($path) {
                    deleteOldFile($config['images'][$imgId]['src'] ?? '');
                    $config['images'][$imgId]['src'] = $path;
                    $config['images'][$imgId]['type'] = 'file';
                }
            }
            if (!empty($alt)) $config['images'][$imgId]['alt'] = $alt;
            saveConfig($config, $configFile);
        }
        header('Location: admin.php?tab=images&msg=' . urlencode('Cập nhật ảnh thành công!')); exit;
    }

    // --- Update item in array section ---
    if ($action === 'update_item') {
        $section = $_POST['section'] ?? '';
        $idx = (int)($_POST['idx'] ?? -1);
        $url = trim($_POST['url'] ?? '');
        $alt = trim($_POST['alt'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        if (in_array($section, ['lifestyle','construction','brand']) && isset($config[$section][$idx])) {
            if (!empty($url)) {
                $config[$section][$idx]['src'] = $url;
            } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $path = handleUpload($_FILES['image'], $imagesDir);
                if ($path) {
                    deleteOldFile($config[$section][$idx]['src'] ?? '');
                    $config[$section][$idx]['src'] = $path;
                }
            }
            if (!empty($alt)) $config[$section][$idx]['alt'] = $alt;
            if ($section !== 'brand') $config[$section][$idx]['caption'] = $caption;
            saveConfig($config, $configFile);
        }
        header('Location: admin.php?tab=' . $section . '&msg=' . urlencode('Cập nhật thành công!')); exit;
    }
}

// Read message from GET
$displayMsg = '';
$displayMsgType = 'success';
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'autoscan_ok') $displayMsg = '✓ Auto-scan hoàn tất! Đã import ảnh từ index.html.';
    elseif ($m === 'deleted') $displayMsg = '✓ Đã xoá ảnh.';
    elseif ($m === 'added') $displayMsg = '✓ Đã thêm ảnh.';
    elseif (strpos($m, 'converted_') === 0) $displayMsg = '✓ Đã chuyển ' . substr($m, 10) . ' ảnh base64 thành file.';
    else $displayMsg = htmlspecialchars(urldecode($m));
}

$activeTab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — ONE ERA</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<style>
:root {
  --navy: #1a1535;
  --navy2: #241d4a;
  --gold: #c9a0dc;
  --gold2: #dbbdee;
  --gold-pale: #f3ecfb;
  --white: #fff;
  --bg: #f5f3fb;
  --border: rgba(201,160,220,0.3);
  --text: #2d2450;
  --muted: #7e6e96;
  --success: #1a7a4a;
  --success-bg: #d4f4e4;
  --error: #7a1a2e;
  --error-bg: #fde4eb;
  --radius: 12px;
  --shadow: 0 2px 16px rgba(26,21,53,0.10);
  --shadow-lg: 0 8px 40px rgba(26,21,53,0.16);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Be Vietnam Pro','Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
a{color:inherit;text-decoration:none;}

/* ── SIDEBAR ── */
.layout{display:flex;min-height:100vh;}
.sidebar{width:240px;background:var(--navy);color:#fff;flex-shrink:0;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto;}
.sidebar-logo{padding:28px 24px 20px;border-bottom:1px solid rgba(201,160,220,0.15);}
.sidebar-logo .brand{font-size:22px;font-weight:700;letter-spacing:.12em;color:var(--gold);}
.sidebar-logo .sub{font-size:10px;letter-spacing:.3em;color:rgba(255,255,255,0.35);margin-top:4px;text-transform:uppercase;}
nav{flex:1;padding:16px 0;}
.nav-group-label{font-size:9px;letter-spacing:.3em;color:rgba(255,255,255,0.3);text-transform:uppercase;padding:12px 24px 4px;}
nav a{display:flex;align-items:center;gap:12px;padding:11px 24px;font-size:13.5px;color:rgba(255,255,255,0.65);transition:all .15s;border-left:3px solid transparent;cursor:pointer;}
nav a:hover{background:rgba(201,160,220,0.08);color:#fff;}
nav a.active{background:rgba(201,160,220,0.14);color:var(--gold);border-left-color:var(--gold);font-weight:600;}
nav a i{width:16px;text-align:center;opacity:.7;}
nav a.active i{opacity:1;}
.sidebar-footer{padding:16px 24px;border-top:1px solid rgba(201,160,220,0.15);}
.sidebar-footer a{display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,0.4);transition:color .15s;}
.sidebar-footer a:hover{color:var(--gold);}

/* ── MAIN ── */
.main{flex:1;padding:32px;min-width:0;}
.page-header{margin-bottom:28px;}
.page-header h1{font-size:24px;font-weight:700;color:var(--navy);}
.page-header p{font-size:13px;color:var(--muted);margin-top:4px;}

/* ── FLASH MESSAGE ── */
.flash{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:var(--radius);font-size:13.5px;margin-bottom:24px;font-weight:500;}
.flash.success{background:var(--success-bg);color:var(--success);}
.flash.error{background:var(--error-bg);color:var(--error);}

/* ── CARDS ── */
.card{background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;margin-bottom:24px;}
.card-title{font-size:15px;font-weight:700;color:var(--navy);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.card-title i{color:var(--gold);}

/* ── OVERVIEW STATS ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:24px;}
.stat-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);text-align:center;border-top:3px solid var(--gold);}
.stat-num{font-size:32px;font-weight:800;color:var(--navy);}
.stat-label{font-size:11px;color:var(--muted);margin-top:4px;text-transform:uppercase;letter-spacing:.08em;}

/* ── FORMS ── */
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
.form-group input[type=text],
.form-group input[type=url],
.form-group input[type=file],
.form-group textarea,
.form-group select{width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13.5px;font-family:inherit;background:var(--bg);color:var(--text);transition:border-color .15s;}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:var(--gold);background:#fff;}
.form-group .hint{font-size:11px;color:var(--muted);margin-top:5px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.or-divider{display:flex;align-items:center;gap:10px;margin:8px 0;font-size:12px;color:var(--muted);}
.or-divider::before,.or-divider::after{content:'';flex:1;height:1px;background:var(--border);}

.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border:none;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit;}
.btn-primary{background:var(--gold);color:var(--navy);}
.btn-primary:hover{background:var(--gold2);}
.btn-danger{background:#fee2e2;color:#c0392b;}
.btn-danger:hover{background:#fecaca;}
.btn-secondary{background:var(--navy);color:#fff;}
.btn-secondary:hover{background:var(--navy2);}
.btn-outline{background:transparent;color:var(--navy);border:1.5px solid var(--border);}
.btn-outline:hover{border-color:var(--gold);background:var(--gold-pale);}
.btn-sm{padding:7px 14px;font-size:12px;}
.btn-teal{background:#0ea5e9;color:#fff;}
.btn-teal:hover{background:#0284c7;}

/* ── SINGLE IMAGE PREVIEW ── */
.img-preview-wrap{margin-bottom:16px;}
.img-preview-wrap img{max-width:100%;max-height:300px;border-radius:8px;object-fit:contain;background:#f0eaf8;padding:4px;border:1.5px solid var(--border);}
.no-img{background:var(--gold-pale);border:2px dashed var(--border);border-radius:8px;padding:40px;text-align:center;color:var(--muted);font-size:13px;}

/* ── GALLERY GRID ── */
.gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;}
.gallery-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow:hidden;cursor:grab;transition:all .18s;box-shadow:0 1px 6px rgba(26,21,53,0.06);}
.gallery-card:hover{border-color:var(--gold);box-shadow:var(--shadow);}
.gallery-card.sortable-ghost{opacity:.35;border-style:dashed;}
.gallery-card img{width:100%;height:150px;object-fit:cover;display:block;background:#f0eaf8;}
.gallery-card .card-body{padding:10px 12px;}
.gallery-card .card-pos{font-size:12px;font-weight:700;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.gallery-card .card-section{font-size:10.5px;color:var(--muted);margin-top:2px;}
.gallery-card .card-actions{display:flex;gap:6px;margin-top:8px;}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;}
.badge-file{background:#fef3c7;color:#92400e;}
.badge-url{background:#d1fae5;color:#065f46;}
.badge-base64{background:#ede9fe;color:#5b21b6;}
.badge-other{background:#f1f5f9;color:#475569;}

/* ── DRAG HANDLE HINT ── */
.drag-hint{font-size:10.5px;color:var(--muted);margin-bottom:10px;display:flex;align-items:center;gap:5px;}

/* ── MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(26,21,53,0.55);z-index:1000;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:#fff;border-radius:16px;padding:28px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}
.modal-header h3{font-size:16px;font-weight:700;color:var(--navy);}
.modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);line-height:1;}
.modal-close:hover{color:var(--navy);}

/* ── OVERVIEW ACTIONS ── */
.action-list{display:flex;flex-wrap:wrap;gap:12px;}

/* ── TABLE ── */
.simple-table{width:100%;border-collapse:collapse;font-size:13px;}
.simple-table th{text-align:left;padding:10px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:2px solid var(--border);}
.simple-table td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle;}
.simple-table tr:last-child td{border-bottom:none;}
.simple-table img{width:50px;height:38px;object-fit:cover;border-radius:5px;}

@media(max-width:768px){
  .sidebar{width:60px;}
  .sidebar-logo .brand,.sidebar-logo .sub,.nav-group-label,nav a span,.sidebar-footer a span{display:none;}
  nav a{padding:14px;justify-content:center;}
  .main{padding:20px 16px;}
  .form-row{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<div class="layout">

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="brand">ONE ERA</div>
    <div class="sub">Admin Panel</div>
  </div>
  <nav>
    <div class="nav-group-label">Tổng quan</div>
    <a href="admin.php?tab=overview" class="<?= $activeTab==='overview'?'active':'' ?>"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a>

    <div class="nav-group-label">Ảnh theo section</div>
    <a href="admin.php?tab=loader" class="<?= $activeTab==='loader'?'active':'' ?>"><i class="fas fa-spinner"></i><span>Loader Logo</span></a>
    <a href="admin.php?tab=hero" class="<?= $activeTab==='hero'?'active':'' ?>"><i class="fas fa-image"></i><span>Hero Background</span></a>
    <a href="admin.php?tab=brand" class="<?= $activeTab==='brand'?'active':'' ?>"><i class="fas fa-building"></i><span>Brand Images</span></a>
    <a href="admin.php?tab=masterplan" class="<?= $activeTab==='masterplan'?'active':'' ?>"><i class="fas fa-map"></i><span>Masterplan</span></a>
    <a href="admin.php?tab=lifestyle" class="<?= $activeTab==='lifestyle'?'active':'' ?>"><i class="fas fa-images"></i><span>Lifestyle Gallery</span></a>
    <a href="admin.php?tab=construction" class="<?= $activeTab==='construction'?'active':'' ?>"><i class="fas fa-hard-hat"></i><span>Tiến Độ XD</span></a>

    <div class="nav-group-label">Quản lý chung</div>
    <a href="admin.php?tab=images" class="<?= $activeTab==='images'?'active':'' ?>"><i class="fas fa-th"></i><span>Tất cả ảnh</span></a>
  </nav>
  <div class="sidebar-footer">
    <a href="index.html" target="_blank"><i class="fas fa-external-link-alt"></i><span>Xem trang web</span></a>
  </div>
</aside>

<!-- ── MAIN ── -->
<main class="main">

<?php if ($displayMsg): ?>
<div class="flash success"><i class="fas fa-check-circle"></i> <?= $displayMsg ?></div>
<?php endif; ?>

<?php

// ============================================================
// OVERVIEW
// ============================================================
if ($activeTab === 'overview'):
$totalImages = count($config['images']);
$lifestyleCount = count($config['lifestyle']);
$constructionCount = count($config['construction']);
$brandCount = count($config['brand']);
$hasLoader = !empty($config['loader']['logo']) ? 1 : 0;
$hasHero = !empty($config['hero']['bg']) ? 1 : 0;
$hasMasterplan = !empty($config['masterplan']['img']) ? 1 : 0;
?>
<div class="page-header">
  <h1><i class="fas fa-chart-pie" style="color:var(--gold)"></i> Dashboard</h1>
  <p>Tổng quan quản lý nội dung website ONE ERA</p>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-num"><?= $totalImages ?></div><div class="stat-label">Ảnh Index</div></div>
  <div class="stat-card"><div class="stat-num"><?= $lifestyleCount ?></div><div class="stat-label">Lifestyle</div></div>
  <div class="stat-card"><div class="stat-num"><?= $constructionCount ?></div><div class="stat-label">Tiến Độ XD</div></div>
  <div class="stat-card"><div class="stat-num"><?= $brandCount ?></div><div class="stat-label">Brand Imgs</div></div>
  <div class="stat-card"><div class="stat-num"><?= $hasLoader+$hasHero+$hasMasterplan ?>/3</div><div class="stat-label">Single Imgs</div></div>
</div>

<div class="card">
  <div class="card-title"><i class="fas fa-tools"></i> Công cụ nhanh</div>
  <div class="action-list">
    <a href="admin.php?autoscan=1" class="btn btn-teal" onclick="return confirm('Auto-scan sẽ quét lại tất cả ảnh từ index.html. Tiếp tục?')"><i class="fas fa-search"></i> Auto-Scan index.html</a>
    <a href="admin.php?convert_base64=1" class="btn btn-secondary" onclick="return confirm('Chuyển đổi ảnh base64 thành file? Thao tác này không thể hoàn tác.')"><i class="fas fa-file-export"></i> Convert Base64 → File</a>
    <a href="index.html" target="_blank" class="btn btn-outline"><i class="fas fa-eye"></i> Xem trang user</a>
  </div>
</div>

<div class="card">
  <div class="card-title"><i class="fas fa-info-circle"></i> Trạng thái sections</div>
  <table class="simple-table">
    <tr><th>Section</th><th>Trạng thái</th><th>Hành động</th></tr>
    <tr>
      <td><i class="fas fa-spinner" style="color:var(--gold)"></i> Loader Logo</td>
      <td><?= $hasLoader ? '<span class="badge badge-url">✓ Có ảnh</span>' : '<span class="badge badge-other">Chưa có</span>' ?></td>
      <td><a href="admin.php?tab=loader" class="btn btn-sm btn-outline">Chỉnh sửa</a></td>
    </tr>
    <tr>
      <td><i class="fas fa-image" style="color:var(--gold)"></i> Hero Background</td>
      <td><?= $hasHero ? '<span class="badge badge-url">✓ Có ảnh</span>' : '<span class="badge badge-other">Chưa có</span>' ?></td>
      <td><a href="admin.php?tab=hero" class="btn btn-sm btn-outline">Chỉnh sửa</a></td>
    </tr>
    <tr>
      <td><i class="fas fa-building" style="color:var(--gold)"></i> Brand Images</td>
      <td><span class="badge badge-url"><?= $brandCount ?> ảnh</span></td>
      <td><a href="admin.php?tab=brand" class="btn btn-sm btn-outline">Chỉnh sửa</a></td>
    </tr>
    <tr>
      <td><i class="fas fa-map" style="color:var(--gold)"></i> Masterplan</td>
      <td><?= $hasMasterplan ? '<span class="badge badge-url">✓ Có ảnh</span>' : '<span class="badge badge-other">Chưa có</span>' ?></td>
      <td><a href="admin.php?tab=masterplan" class="btn btn-sm btn-outline">Chỉnh sửa</a></td>
    </tr>
    <tr>
      <td><i class="fas fa-images" style="color:var(--gold)"></i> Lifestyle Gallery</td>
      <td><span class="badge badge-url"><?= $lifestyleCount ?> ảnh</span></td>
      <td><a href="admin.php?tab=lifestyle" class="btn btn-sm btn-outline">Chỉnh sửa</a></td>
    </tr>
    <tr>
      <td><i class="fas fa-hard-hat" style="color:var(--gold)"></i> Tiến Độ Xây Dựng</td>
      <td><span class="badge badge-url"><?= $constructionCount ?> ảnh</span></td>
      <td><a href="admin.php?tab=construction" class="btn btn-sm btn-outline">Chỉnh sửa</a></td>
    </tr>
  </table>
</div>

<?php

// ============================================================
// LOADER
// ============================================================
elseif ($activeTab === 'loader'):
$currentSrc = $config['loader']['logo'] ?? '';
?>
<div class="page-header"><h1><i class="fas fa-spinner" style="color:var(--gold)"></i> Loader Logo</h1><p>Ảnh logo hiển thị khi trang đang tải</p></div>
<div class="card">
  <div class="card-title"><i class="fas fa-image"></i> Ảnh hiện tại</div>
  <?php if ($currentSrc && detectImageType($currentSrc) !== 'empty'): ?>
  <div class="img-preview-wrap"><img src="<?= htmlspecialchars($currentSrc) ?>" alt="Loader Logo" onerror="this.style.display='none'"></div>
  <?php else: ?><div class="no-img"><i class="fas fa-image fa-2x" style="margin-bottom:8px;display:block"></i>Chưa có ảnh loader logo</div><?php endif; ?>
</div>
<div class="card">
  <div class="card-title"><i class="fas fa-upload"></i> Cập nhật Logo</div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_loader">
    <div class="form-group">
      <label>Upload ảnh từ máy tính</label>
      <input type="file" name="image" accept="image/*">
      <div class="hint">Định dạng: JPG, PNG, WebP, SVG. Nên dùng ảnh vuông, nền trong suốt (PNG).</div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Lưu Logo</button>
  </form>
</div>

<?php

// ============================================================
// HERO
// ============================================================
elseif ($activeTab === 'hero'):
$currentSrc = $config['hero']['bg'] ?? '';
?>
<div class="page-header"><h1><i class="fas fa-image" style="color:var(--gold)"></i> Hero Background</h1><p>Ảnh nền phần hero (banner đầu trang)</p></div>
<div class="card">
  <div class="card-title"><i class="fas fa-image"></i> Ảnh hiện tại</div>
  <?php if ($currentSrc && strpos($currentSrc, '<!--') === false): ?>
  <div class="img-preview-wrap"><img src="<?= htmlspecialchars($currentSrc) ?>" alt="Hero BG" onerror="this.style.display='none'"></div>
  <div style="font-size:12px;color:var(--muted);margin-top:6px;word-break:break-all"><?= htmlspecialchars($currentSrc) ?></div>
  <?php else: ?><div class="no-img"><i class="fas fa-image fa-2x" style="margin-bottom:8px;display:block"></i>Chưa có ảnh hero background</div><?php endif; ?>
</div>
<div class="card">
  <div class="card-title"><i class="fas fa-upload"></i> Cập nhật Hero BG</div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_hero">
    <div class="form-group">
      <label>Upload ảnh từ máy tính</label>
      <input type="file" name="image" accept="image/*">
    </div>
    <div class="or-divider">hoặc</div>
    <div class="form-group">
      <label>Nhập URL ảnh</label>
      <input type="url" name="url" placeholder="https://..." value="<?= (strpos($currentSrc,'http')===0)?htmlspecialchars($currentSrc):'' ?>">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Lưu Hero BG</button>
  </form>
</div>

<?php

// ============================================================
// MASTERPLAN
// ============================================================
elseif ($activeTab === 'masterplan'):
$currentSrc = $config['masterplan']['img'] ?? '';
?>
<div class="page-header"><h1><i class="fas fa-map" style="color:var(--gold)"></i> Masterplan</h1><p>Ảnh mặt bằng tổng thể dự án</p></div>
<div class="card">
  <div class="card-title"><i class="fas fa-image"></i> Ảnh hiện tại</div>
  <?php if ($currentSrc && strpos($currentSrc, '<!--') === false): ?>
  <div class="img-preview-wrap"><img src="<?= htmlspecialchars($currentSrc) ?>" alt="Masterplan" onerror="this.style.display='none'"></div>
  <?php else: ?><div class="no-img"><i class="fas fa-map fa-2x" style="margin-bottom:8px;display:block"></i>Chưa có ảnh masterplan</div><?php endif; ?>
</div>
<div class="card">
  <div class="card-title"><i class="fas fa-upload"></i> Cập nhật Masterplan</div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_masterplan">
    <div class="form-group">
      <label>Upload ảnh từ máy tính</label>
      <input type="file" name="image" accept="image/*">
    </div>
    <div class="or-divider">hoặc</div>
    <div class="form-group">
      <label>Nhập URL ảnh</label>
      <input type="url" name="url" placeholder="https://...">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Lưu Masterplan</button>
  </form>
</div>

<?php

// ============================================================
// BRAND
// ============================================================
elseif ($activeTab === 'brand'):
?>
<div class="page-header"><h1><i class="fas fa-building" style="color:var(--gold)"></i> Brand Images</h1><p>Ảnh phần giới thiệu thương hiệu (tối đa 3–4 ảnh, ảnh đầu sẽ to hơn)</p></div>

<!-- Add new -->
<div class="card">
  <div class="card-title"><i class="fas fa-plus"></i> Thêm ảnh Brand</div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_brand">
    <div class="form-row">
      <div class="form-group">
        <label>Upload ảnh</label>
        <input type="file" name="image" accept="image/*">
      </div>
      <div class="form-group">
        <label>hoặc URL ảnh</label>
        <input type="url" name="url" placeholder="https://...">
      </div>
    </div>
    <div class="form-group">
      <label>Mô tả ảnh (alt)</label>
      <input type="text" name="alt" placeholder="Ví dụ: Phối cảnh dự án ONE ERA">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Thêm ảnh</button>
  </form>
</div>

<!-- Gallery -->
<div class="card">
  <div class="card-title"><i class="fas fa-images"></i> Danh sách ảnh Brand (<?= count($config['brand']) ?>)</div>
  <?php if (empty($config['brand'])): ?>
  <div class="no-img">Chưa có ảnh brand nào</div>
  <?php else: ?>
  <div class="drag-hint"><i class="fas fa-grip-vertical"></i> Kéo thả để sắp xếp lại thứ tự. Nhấn <b>Lưu thứ tự</b> để áp dụng.</div>
  <div class="gallery-grid" id="brand-gallery" data-section="brand">
    <?php foreach ($config['brand'] as $idx => $img): ?>
    <div class="gallery-card" data-idx="<?= $idx ?>">
      <img src="<?= htmlspecialchars($img['src']) ?>" alt="<?= htmlspecialchars($img['alt'] ?? '') ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22150%22><rect fill=%22%23f0eaf8%22 width=%22200%22 height=%22150%22/><text x=%22100%22 y=%2275%22 text-anchor=%22middle%22 fill=%22%23c9a0dc%22 font-size=%2212%22>Lỗi ảnh</text></svg>'">
      <div class="card-body">
        <div class="card-pos">Ảnh <?= $idx+1 ?><?= $idx===0?' <span style="color:var(--gold)">(Ảnh to)</span>':'' ?></div>
        <div class="card-section"><?= htmlspecialchars($img['alt'] ?? '') ?></div>
        <div class="card-actions">
          <button class="btn btn-sm btn-outline" onclick="openEditModal('brand','<?= $idx ?>','<?= htmlspecialchars(addslashes($img['src'])) ?>','<?= htmlspecialchars(addslashes($img['alt']??'')) ?>','')"><i class="fas fa-edit"></i> Sửa</button>
          <a href="admin.php?tab=brand&delete_item=<?= $idx ?>&section=brand" class="btn btn-sm btn-danger" onclick="return confirm('Xoá ảnh này?')"><i class="fas fa-trash"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <br>
  <button class="btn btn-secondary" onclick="saveOrder('brand')"><i class="fas fa-save"></i> Lưu thứ tự</button>
  <?php endif; ?>
</div>

<?php

// ============================================================
// LIFESTYLE
// ============================================================
elseif ($activeTab === 'lifestyle'):
?>
<div class="page-header"><h1><i class="fas fa-images" style="color:var(--gold)"></i> Lifestyle Gallery</h1><p>Bộ ảnh lifestyle hiển thị dạng lưới trên trang web</p></div>

<div class="card">
  <div class="card-title"><i class="fas fa-plus"></i> Thêm ảnh Lifestyle</div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_lifestyle">
    <div class="form-row">
      <div class="form-group">
        <label>Upload ảnh</label>
        <input type="file" name="image" accept="image/*">
      </div>
      <div class="form-group">
        <label>hoặc URL ảnh</label>
        <input type="url" name="url" placeholder="https://...">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Mô tả ảnh (alt)</label>
        <input type="text" name="alt" placeholder="Ví dụ: Không gian sống xanh">
      </div>
      <div class="form-group">
        <label>Caption hiển thị</label>
        <input type="text" name="caption" placeholder="Ví dụ: Hồ bơi vô cực">
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Thêm ảnh</button>
  </form>
</div>

<div class="card">
  <div class="card-title"><i class="fas fa-images"></i> Gallery (<?= count($config['lifestyle']) ?> ảnh)</div>
  <?php if (empty($config['lifestyle'])): ?>
  <div class="no-img">Chưa có ảnh lifestyle nào</div>
  <?php else: ?>
  <div class="drag-hint"><i class="fas fa-grip-vertical"></i> Kéo thả để sắp xếp. Nhấn <b>Lưu thứ tự</b> để áp dụng.</div>
  <div class="gallery-grid" id="lifestyle-gallery" data-section="lifestyle">
    <?php foreach ($config['lifestyle'] as $idx => $img): ?>
    <div class="gallery-card" data-idx="<?= $idx ?>">
      <img src="<?= htmlspecialchars($img['src']) ?>" alt="<?= htmlspecialchars($img['alt'] ?? '') ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22150%22><rect fill=%22%23f0eaf8%22 width=%22200%22 height=%22150%22/><text x=%22100%22 y=%2275%22 text-anchor=%22middle%22 fill=%22%23c9a0dc%22 font-size=%2212%22>Lỗi ảnh</text></svg>'">
      <div class="card-body">
        <div class="card-pos">Ảnh <?= $idx+1 ?></div>
        <div class="card-section"><?= htmlspecialchars($img['caption'] ?? '') ?></div>
        <div class="card-actions">
          <button class="btn btn-sm btn-outline" onclick="openEditModal('lifestyle','<?= $idx ?>','<?= htmlspecialchars(addslashes($img['src'])) ?>','<?= htmlspecialchars(addslashes($img['alt']??'')) ?>','<?= htmlspecialchars(addslashes($img['caption']??'')) ?>')"><i class="fas fa-edit"></i> Sửa</button>
          <a href="admin.php?tab=lifestyle&delete_item=<?= $idx ?>&section=lifestyle" class="btn btn-sm btn-danger" onclick="return confirm('Xoá ảnh này?')"><i class="fas fa-trash"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <br>
  <button class="btn btn-secondary" onclick="saveOrder('lifestyle')"><i class="fas fa-save"></i> Lưu thứ tự</button>
  <?php endif; ?>
</div>

<?php

// ============================================================
// CONSTRUCTION
// ============================================================
elseif ($activeTab === 'construction'):
?>
<div class="page-header"><h1><i class="fas fa-hard-hat" style="color:var(--gold)"></i> Tiến Độ Xây Dựng</h1><p>Ảnh cập nhật tiến độ công trình</p></div>

<div class="card">
  <div class="card-title"><i class="fas fa-plus"></i> Thêm ảnh tiến độ</div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_construction">
    <div class="form-row">
      <div class="form-group">
        <label>Upload ảnh</label>
        <input type="file" name="image" accept="image/*">
      </div>
      <div class="form-group">
        <label>hoặc URL ảnh</label>
        <input type="url" name="url" placeholder="https://...">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Mô tả ảnh (alt)</label>
        <input type="text" name="alt" placeholder="Ví dụ: Tiến độ tháng 4/2025">
      </div>
      <div class="form-group">
        <label>Caption hiển thị</label>
        <input type="text" name="caption" placeholder="Ví dụ: Tháng 04/2025">
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Thêm ảnh</button>
  </form>
</div>

<div class="card">
  <div class="card-title"><i class="fas fa-images"></i> Ảnh tiến độ (<?= count($config['construction']) ?>)</div>
  <?php if (empty($config['construction'])): ?>
  <div class="no-img">Chưa có ảnh tiến độ nào</div>
  <?php else: ?>
  <div class="drag-hint"><i class="fas fa-grip-vertical"></i> Kéo thả để sắp xếp. Nhấn <b>Lưu thứ tự</b> để áp dụng.</div>
  <div class="gallery-grid" id="construction-gallery" data-section="construction">
    <?php foreach ($config['construction'] as $idx => $img): ?>
    <div class="gallery-card" data-idx="<?= $idx ?>">
      <img src="<?= htmlspecialchars($img['src']) ?>" alt="<?= htmlspecialchars($img['alt'] ?? '') ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22150%22><rect fill=%22%23f0eaf8%22 width=%22200%22 height=%22150%22/><text x=%22100%22 y=%2275%22 text-anchor=%22middle%22 fill=%22%23c9a0dc%22 font-size=%2212%22>Lỗi ảnh</text></svg>'">
      <div class="card-body">
        <div class="card-pos"><?= htmlspecialchars($img['caption'] ?? 'Ảnh '.($idx+1)) ?></div>
        <div class="card-section"><?= htmlspecialchars($img['alt'] ?? '') ?></div>
        <div class="card-actions">
          <button class="btn btn-sm btn-outline" onclick="openEditModal('construction','<?= $idx ?>','<?= htmlspecialchars(addslashes($img['src'])) ?>','<?= htmlspecialchars(addslashes($img['alt']??'')) ?>','<?= htmlspecialchars(addslashes($img['caption']??'')) ?>')"><i class="fas fa-edit"></i> Sửa</button>
          <a href="admin.php?tab=construction&delete_item=<?= $idx ?>&section=construction" class="btn btn-sm btn-danger" onclick="return confirm('Xoá ảnh này?')"><i class="fas fa-trash"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <br>
  <button class="btn btn-secondary" onclick="saveOrder('construction')"><i class="fas fa-save"></i> Lưu thứ tự</button>
  <?php endif; ?>
</div>

<?php

// ============================================================
// ALL IMAGES
// ============================================================
elseif ($activeTab === 'images'):
// Group by section
$grouped = [];
foreach ($config['images'] as $id => $img) {
    $s = $img['section'] ?? 'Other';
    $grouped[$s][$id] = $img;
}
?>
<div class="page-header">
  <h1><i class="fas fa-th" style="color:var(--gold)"></i> Tất cả ảnh từ index.html</h1>
  <p>Ảnh được quét từ index.html. Kéo thả để sắp xếp trong cùng section.</p>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
  <a href="admin.php?autoscan=1" class="btn btn-teal" onclick="return confirm('Scan lại? Dữ liệu cũ sẽ bị ghi đè.')"><i class="fas fa-search"></i> Auto-Scan lại</a>
  <a href="admin.php?convert_base64=1" class="btn btn-secondary" onclick="return confirm('Chuyển base64 thành file?')"><i class="fas fa-file-export"></i> Convert Base64</a>
</div>

<?php if (empty($config['images'])): ?>
<div class="card"><div class="no-img"><i class="fas fa-search fa-2x" style="margin-bottom:10px;display:block"></i>Chưa có ảnh nào. Nhấn <b>Auto-Scan</b> để quét từ index.html.</div></div>
<?php else: ?>

<!-- Upload form for images dict -->
<div class="card">
  <div class="card-title"><i class="fas fa-upload"></i> Thay ảnh theo vị trí</div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_image">
    <div class="form-row">
      <div class="form-group">
        <label>Chọn vị trí ảnh</label>
        <select name="imgId" required>
          <option value="">-- Chọn vị trí --</option>
          <?php foreach ($config['images'] as $id => $img): ?>
          <option value="<?= htmlspecialchars($id) ?>">[<?= htmlspecialchars($img['section']??'') ?>] <?= htmlspecialchars($img['position']??$id) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Mô tả ảnh (alt, tuỳ chọn)</label>
        <input type="text" name="alt" placeholder="Mô tả ảnh">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Upload ảnh mới</label>
        <input type="file" name="image" accept="image/*">
      </div>
      <div class="form-group">
        <label>hoặc URL ảnh</label>
        <input type="url" name="url" placeholder="https://...">
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Cập nhật ảnh</button>
  </form>
</div>

<?php foreach ($grouped as $sectionName => $imgs): ?>
<div class="card">
  <div class="card-title">
    <i class="fas fa-layer-group"></i> <?= htmlspecialchars($sectionName) ?>
    <span class="badge badge-url" style="margin-left:8px"><?= count($imgs) ?> ảnh</span>
  </div>
  <div class="drag-hint"><i class="fas fa-grip-vertical"></i> Kéo thả để sắp xếp. Nhấn <b>Lưu thứ tự</b> sau khi sắp xếp xong.</div>
  <div class="gallery-grid" id="gallery-<?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9]/','-',strtolower($sectionName))) ?>" data-section="images">
    <?php foreach ($imgs as $id => $img): ?>
    <?php $src = $img['src']; $safeSrc = strpos($src,'data:image')===0 ? $src : htmlspecialchars($src); ?>
    <div class="gallery-card" data-img-id="<?= htmlspecialchars($id) ?>">
      <img src="<?= $safeSrc ?>" alt="<?= htmlspecialchars($img['alt']??'') ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22150%22><rect fill=%22%23f0eaf8%22 width=%22200%22 height=%22150%22/><text x=%22100%22 y=%2275%22 text-anchor=%22middle%22 fill=%22%23c9a0dc%22 font-size=%2212%22>Lỗi ảnh</text></svg>'">
      <div class="card-body">
        <div class="card-pos"><?= htmlspecialchars($img['position']??$id) ?></div>
        <div class="card-section">
          <?php $t = $img['type']??'other'; echo "<span class='badge badge-$t'>".strtoupper($t)."</span>"; ?>
        </div>
        <div class="card-actions">
          <a href="admin.php?tab=images&delete=<?= htmlspecialchars($id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xoá ảnh này khỏi danh sách?')"><i class="fas fa-trash"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <br>
  <button class="btn btn-secondary btn-sm" onclick="saveOrderImages(this.closest('.card').querySelector('.gallery-grid'))"><i class="fas fa-save"></i> Lưu thứ tự section này</button>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>

</main>
</div>

<!-- ── EDIT MODAL ── -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fas fa-edit" style="color:var(--gold)"></i> Chỉnh sửa ảnh</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div id="modalImgPreview" style="margin-bottom:16px;"></div>
    <form method="post" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action" value="update_item">
      <input type="hidden" name="section" id="modalSection">
      <input type="hidden" name="idx" id="modalIdx">
      <div class="form-group">
        <label>Upload ảnh mới</label>
        <input type="file" name="image" accept="image/*">
      </div>
      <div class="or-divider">hoặc</div>
      <div class="form-group">
        <label>URL ảnh</label>
        <input type="url" name="url" id="modalUrl" placeholder="https://...">
      </div>
      <div class="form-group">
        <label>Mô tả ảnh (alt)</label>
        <input type="text" name="alt" id="modalAlt" placeholder="Mô tả ảnh">
      </div>
      <div class="form-group" id="captionGroup">
        <label>Caption hiển thị</label>
        <input type="text" name="caption" id="modalCaption" placeholder="Caption">
      </div>
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Lưu</button>
        <button type="button" class="btn btn-outline" onclick="closeModal()">Huỷ</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── SORTABLE init ──
document.querySelectorAll('.gallery-grid').forEach(function(el) {
  Sortable.create(el, { animation: 150, ghostClass: 'sortable-ghost' });
});

// ── Save order for array sections (lifestyle, construction, brand) ──
function saveOrder(section) {
  var el = document.getElementById(section + '-gallery');
  if (!el) return;
  var order = Array.from(el.querySelectorAll('.gallery-card')).map(c => c.dataset.idx);
  fetch('admin.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=reorder&section=' + section + '&order=' + encodeURIComponent(JSON.stringify(order))
  }).then(r => r.ok ? (alert('✓ Đã lưu thứ tự!'), location.reload()) : alert('Lỗi lưu!'));
}

// ── Save order for images dict sections ──
function saveOrderImages(el) {
  var order = Array.from(el.querySelectorAll('.gallery-card')).map(c => c.dataset.imgId);
  fetch('admin.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=reorder&section=images&order=' + encodeURIComponent(JSON.stringify(order))
  }).then(r => r.ok ? alert('✓ Đã lưu thứ tự!') : alert('Lỗi lưu!'));
}

// ── Edit Modal ──
function openEditModal(section, idx, src, alt, caption) {
  document.getElementById('modalSection').value = section;
  document.getElementById('modalIdx').value = idx;
  document.getElementById('modalUrl').value = src.startsWith('http') ? src : '';
  document.getElementById('modalAlt').value = alt;
  document.getElementById('modalCaption').value = caption;
  // Caption only for lifestyle & construction
  document.getElementById('captionGroup').style.display = section === 'brand' ? 'none' : 'block';
  // Preview
  var preview = document.getElementById('modalImgPreview');
  if (src) {
    preview.innerHTML = '<img src="' + src + '" style="max-width:100%;max-height:200px;border-radius:8px;object-fit:contain;background:#f0eaf8;padding:4px">';
  } else {
    preview.innerHTML = '';
  }
  document.getElementById('editModal').classList.add('open');
}

function closeModal() {
  document.getElementById('editModal').classList.remove('open');
}

document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>