<?php
/**
 * 图片缩略处理脚本
 * 
 * 用法: /thumb.php?src=/uploads/xxx.png&w=400&q=75
 * 
 * 参数:
 *   src  - 原始图片路径（相对 public）
 *   w    - 最大宽度（默认 400）
 *   q    - 质量（默认 75，1-100）
 * 
 * 特性:
 *   - 自动缓存到 /thumbs/ 目录
 *   - 输出 304 缓存头
 *   - 仅处理指定目录内图片，防止路径穿越
 */

$src = isset($_GET['src']) ? $_GET['src'] : '';
$maxW = isset($_GET['w']) ? intval($_GET['w']) : 400;
$quality = isset($_GET['q']) ? intval($_GET['q']) : 75;

// 安全检查
if (empty($src) || strpos($src, '..') !== false) {
    http_response_code(400);
    die('Invalid src');
}

// 允许处理的目录
$allowedPrefixes = ['/uploads/', '/ios/', '/c788', '/wode'];
$allowed = false;
foreach ($allowedPrefixes as $prefix) {
    if (strpos($src, $prefix) === 0) { $allowed = true; break; }
}
// 根目录的logo也允许
if ($src === '/c788ffd04d7bbafa657bd2dcf4c6ecc4.png' || $src === '/wode.png') {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    die('Not allowed');
}

$srcPath = __DIR__ . $src;
if (!file_exists($srcPath)) {
    http_response_code(404);
    die('File not found');
}

// 限制参数范围
$maxW = max(50, min($maxW, 1200));
$quality = max(30, min($quality, 95));

// 缓存路径
$cacheKey = md5($src . '_w' . $maxW . '_q' . $quality);
$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
$cacheDir = __DIR__ . '/thumbs';
$cachePath = $cacheDir . '/' . $cacheKey . '.' . $ext;

// 检查是否需要重新生成
if (file_exists($cachePath) && filemtime($cachePath) >= filemtime($srcPath)) {
    // 缓存有效，检查 304
    $etag = '"' . md5_file($cachePath) . '"';
    $lastMod = gmdate('D, d M Y H:i:s', filemtime($cachePath)) . ' GMT';
    
    header('ETag: ' . $etag);
    header('Last-Modified: ' . $lastMod);
    header('Cache-Control: public, max-age=604800'); // 7天
    
    if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
        (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] === $lastMod)) {
        http_response_code(304);
        exit;
    }
    
    // 输出缓存文件
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($cachePath));
    readfile($cachePath);
    exit;
}

// 需要生成缩略图
if (!extension_loaded('gd')) {
    // 无 GD 扩展，直接输出原图
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    header('Content-Type: ' . $mime);
    readfile($srcPath);
    exit;
}

$info = @getimagesize($srcPath);
if (!$info) {
    http_response_code(400);
    die('Not a valid image');
}

$origW = $info[0];
$origH = $info[1];

// 不需要缩放
if ($origW <= $maxW) {
    $newW = $origW;
    $newH = $origH;
} else {
    $ratio = $maxW / $origW;
    $newW = $maxW;
    $newH = intval($origH * $ratio);
}

// 创建源图像
switch ($info['mime']) {
    case 'image/png':
        $srcImg = @imagecreatefrompng($srcPath);
        break;
    case 'image/jpeg':
    case 'image/jpg':
        $srcImg = @imagecreatefromjpeg($srcPath);
        break;
    default:
        // 不支持的格式，输出原图
        header('Content-Type: ' . $info['mime']);
        readfile($srcPath);
        exit;
}

if (!$srcImg) {
    http_response_code(500);
    die('Image processing failed');
}

$dstImg = imagecreatetruecolor($newW, $newH);

// PNG 透明
if ($info['mime'] === 'image/png') {
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
    imagefill($dstImg, 0, 0, $transparent);
}

imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
imagedestroy($srcImg);

// 确保缓存目录存在
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// 保存到缓存
if ($ext === 'png') {
    $pngLevel = intval((100 - $quality) / 11); // 0-9
    imagepng($dstImg, $cachePath, $pngLevel);
    header('Content-Type: image/png');
} else {
    imagejpeg($dstImg, $cachePath, $quality);
    header('Content-Type: image/jpeg');
}

imagedestroy($dstImg);

// 输出
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . filesize($cachePath));
readfile($cachePath);
