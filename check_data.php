<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=fucai3d;charset=utf8mb4','fucai3d','fucai3d');
$p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check table structure
$cols = $p->query("DESCRIBE fa_lottery_draw")->fetchAll(PDO::FETCH_ASSOC);
echo "=== Table Columns ===\n";
foreach ($cols as $c) {
    echo "  {$c['Field']} ({$c['Type']})\n";
}

echo "\n=== Sample Data ===\n";
$r = $p->query("SELECT * FROM fa_lottery_draw ORDER BY id DESC LIMIT 5");
$rows = $r->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

echo "\n\nTotal records: " . $p->query("SELECT COUNT(*) FROM fa_lottery_draw")->fetchColumn();
