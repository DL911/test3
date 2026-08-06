<?php
// Android APK 下载脚本
$apkFile = __DIR__ . '/app.apk';

if (!file_exists($apkFile)) {
    http_response_code(404);
    echo '文件不存在';
    exit;
}

$fileSize = filesize($apkFile);

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="gmwxn.apk"');
header("Content-Length: $fileSize");
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($apkFile);
exit;
?>
