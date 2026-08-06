<?php
// 排查赔率表 + 包胆判定验证
// 服务器执行: cd /www/wwwroot/haoyunbeicai.top/public && php debug_baodan.php

$envFile = __DIR__ . '/../.env';
$db = ['hostname'=>'127.0.0.1','database'=>'','username'=>'','password'=>'','hostport'=>'3306','prefix'=>'fa_'];
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $section = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        if (preg_match('/^\[(.+)\]$/', $line, $m)) { $section = strtolower($m[1]); continue; }
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = strtolower(trim($k)); $v = trim($v, " \"'");
        if ($section === 'database') {
            if ($k === 'hostname') $db['hostname'] = $v;
            if ($k === 'database') $db['database'] = $v;
            if ($k === 'username') $db['username'] = $v;
            if ($k === 'password') $db['password'] = $v;
            if ($k === 'hostport') $db['hostport'] = $v;
            if ($k === 'prefix')   $db['prefix']   = $v;
        }
    }
}

$dsn = "mysql:host={$db['hostname']};port={$db['hostport']};dbname={$db['database']};charset=utf8mb4";
$pdo = new PDO($dsn, $db['username'], $db['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$oddsTable = $db['prefix'] . 'lottery_odds';

// 1. 查包胆相关赔率
echo "=== 赔率表中包胆/组选相关 bet_key ===\n";
$s = $pdo->query("SELECT lottery_type, bet_key, odds, status FROM {$oddsTable} WHERE bet_key LIKE '%baodan%' OR bet_key LIKE '%zuxuan%' ORDER BY lottery_type, bet_key");
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  lottery_type={$r['lottery_type']} | bet_key={$r['bet_key']} | odds={$r['odds']} | status={$r['status']}\n";
}

// 2. 查 he_zuxuan_baodan 具体赔率
echo "\n=== he_zuxuan_baodan 赔率 ===\n";
$s2 = $pdo->query("SELECT * FROM {$oddsTable} WHERE bet_key='he_zuxuan_baodan'");
$rows = $s2->fetchAll(PDO::FETCH_ASSOC);
if ($rows) { foreach ($rows as $r) { echo "  " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n"; } }
else { echo "  ★ 赔率表中没有 he_zuxuan_baodan！需要确认正确的 bet_key\n"; }
