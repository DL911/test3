<?php
// 查询一字组合相关赔率配置
$e = parse_ini_file(dirname(__DIR__).'/.env', true);
$db = $e['database'];
$p = new PDO('mysql:host='.trim($db['hostname']).';dbname='.trim($db['database']).';charset=utf8mb4', trim($db['username']), trim($db['password']));
$pre = trim($db['prefix']);

echo "=== lottery_odds 中 yizi 相关记录 ===\n";
$sql = "SELECT id,lottery_type,play_type,bet_key,odds,status FROM {$pre}lottery_odds WHERE bet_key LIKE '%yizi%' OR play_type LIKE '%yizi%'";
foreach($p->query($sql) as $r) {
    echo "id={$r['id']} type={$r['lottery_type']} play={$r['play_type']} key={$r['bet_key']} odds={$r['odds']} st={$r['status']}\n";
}

echo "\n=== lottery_odds 中 num_ 相关记录 ===\n";
$sql2 = "SELECT id,lottery_type,play_type,bet_key,odds,status FROM {$pre}lottery_odds WHERE bet_key LIKE 'num_%' LIMIT 20";
foreach($p->query($sql2) as $r) {
    echo "id={$r['id']} type={$r['lottery_type']} play={$r['play_type']} key={$r['bet_key']} odds={$r['odds']} st={$r['status']}\n";
}

echo "\n=== 快捷玩法 play_sub 示例 (lottery_bet) ===\n";
$sql3 = "SELECT id,play_type,panel_type,play_sub,bet_content FROM {$pre}lottery_bet WHERE panel_type='kuaijie' ORDER BY id DESC LIMIT 3";
foreach($p->query($sql3) as $r) {
    echo "id={$r['id']} play={$r['play_type']} panel={$r['panel_type']} sub={$r['play_sub']}\n";
    echo "  content: ".substr($r['bet_content'],0,200)."\n\n";
}

echo "\n=== 一字组合 play_sub 示例 ===\n";
$sql4 = "SELECT id,play_type,panel_type,play_sub,bet_content FROM {$pre}lottery_bet WHERE play_type='yizi_zuhe' ORDER BY id DESC LIMIT 3";
foreach($p->query($sql4) as $r) {
    echo "id={$r['id']} play={$r['play_type']} panel={$r['panel_type']} sub={$r['play_sub']}\n";
    echo "  content: ".substr($r['bet_content'],0,200)."\n\n";
}
