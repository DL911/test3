<?php
// 生成随机 UUID 的函数
function generateUUID()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

// 生成两个随机 UUID
$uuid1 = generateUUID();
$uuid2 = generateUUID();

// 获取当前域名
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $domain;

// 配置文件内容模板
$configTemplate = '<?xml version="1.0" encoding="UTF-8"?> 
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadContent</key>
    <array>
        <!-- Web Clip 配置 -->
        <dict>
            <key>URL</key>
            <string>' . $baseUrl . '/index.php/index/lottery/index</string>
            <key>Label</key>
            <string>gmwxn</string>
            <key>PayloadType</key>
            <string>com.apple.webClip.managed</string>
            <key>PayloadIdentifier</key>
            <string>com.lottery.webclip</string>
            <key>PayloadUUID</key>
            <string>%s</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
            <key>Icon</key>
            <data>' . base64_encode(file_get_contents(__DIR__ . '/logo.png')) . '</data>
            <key>IsRemovable</key>
            <true/>
            <key>PrecomposedIcon</key>
            <true/>
            <key>FullScreen</key>
            <true/>
        </dict>
    </array>
    <key>PayloadDisplayName</key>
    <string>gmwxn</string>
    <key>PayloadIdentifier</key>
    <string>com.lottery.profile</string>
    <key>PayloadRemovalDisallowed</key>
    <false/>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>%s</string>
    <key>PayloadVersion</key>
    <integer>1</integer>
</dict>
</plist>';

// 填充 UUID 到模板
$configContent = sprintf($configTemplate, $uuid1, $uuid2);

// 计算配置文件内容的长度
$contentLength = strlen($configContent);

// 设置响应头
header('Content-Type: application/x-apple-aspen-config; charset=UTF-8');
header('Content-Disposition: attachment; filename="gmwxn.mobileconfig"');
header("Content-Length: $contentLength");

// 输出配置文件内容
echo $configContent;
?>