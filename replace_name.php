<?php
$dir = 'e:/fucai/application/index/view/lottery/';
$files = scandir($dir);

$replacements = [
    'DB多宝彩票' => '好运北彩',
    '多宝彩票' => '好运北彩',
    'DB彩票' => '好运北彩',
    '">DB<' => '">北彩<',
    'DB Lottery' => 'HYBC Lottery'
];

$count = 0;
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
        $path = $dir . $file;
        $content = file_get_contents($path);
        
        $newContent = str_replace(array_keys($replacements), array_values($replacements), $content);
        
        if ($newContent !== $content) {
            file_put_contents($path, $newContent);
            echo "Updated: $file\n";
            $count++;
        }
    }
}
echo "Total updated files: $count\n";
