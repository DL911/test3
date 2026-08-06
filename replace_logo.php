<?php
$dir = 'e:/fucai/application/index/view/lottery/';
$files = scandir($dir);

$imgHtml = '<img src="/c788ffd04d7bbafa657bd2dcf4c6ecc4.png" style="width:100%;height:100%;object-fit:contain;border-radius:12px;">';

$replacements = [
    'class="logo-icon">北彩</div>' => 'class="logo-icon" style="background:transparent;padding:0;">' . $imgHtml . '</div>',
    'class="m-logo">北彩</div>' => 'class="m-logo" style="background:transparent;padding:0;">' . $imgHtml . '</div>',
    'class="auth-brand-logo">北彩</div>' => 'class="auth-brand-logo" style="background:transparent;width:80px;height:80px;padding:0;">' . $imgHtml . '</div>',
    'class="m-auth-logo">北彩</div>' => 'class="m-auth-logo" style="background:transparent;width:64px;height:64px;padding:0;">' . $imgHtml . '</div>'
];

$count = 0;
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
        $path = $dir . $file;
        $content = file_get_contents($path);
        
        $newContent = str_replace(array_keys($replacements), array_values($replacements), $content);
        
        if ($newContent !== $content) {
            file_put_contents($path, $newContent);
            echo "Updated image logo in: $file\n";
            $count++;
        }
    }
}
echo "Total updated files: $count\n";
