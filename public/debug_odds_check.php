<?php
/**
 * 赔率对照检查脚本
 * 对比数据库 lottery_odds 的 play_type / bet_key 与前端 getItemOdds 调用的 key 是否匹配
 */
define('APP_PATH', __DIR__ . '/../application/');
require __DIR__ . '/../thinkphp/base.php';

$db = think\Db::connect();

// 查询福彩3D (lottery_type=1) 全部赔率
$rows = think\Db::name('lottery_odds')
    ->where('lottery_type', 1)
    ->where('status', 1)
    ->field('play_type, bet_key, bet_name, odds')
    ->order('play_type asc, id asc')
    ->select();

// 按 play_type 分组
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['play_type']][] = $r;
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== 福彩3D 双面盘赔率表 (lottery_type=1) ===\n\n";

// 前端使用的 play_type 列表（双面盘相关）
$frontendPlayTypes = [
    'baiwei', 'shiwei', 'gewei',           // 百位/十位/个位 (号码+属性)
    'shuangmian',                            // 总和属性
    'shuangmian_combo',                      // 两两组合 (百十/百个/十个)
    'shuangmian_total',                      // 总和扩展(百十个)
    'longhu',                                // 龙虎和
    'xingtai',                               // 形态
    'yizi_zuhe', 'erzi_zuhe', 'sanzi_zuhe',  // 组合
    'yizi_dingwei', 'erzi_dingwei', 'sanzi_dingwei', // 定位
    'erzi_heshu_baishi', 'erzi_heshu_baige', 'erzi_heshu_shige', // 二字和数
    'erzi_heshu_baishi_ws', 'erzi_heshu_baige_ws', 'erzi_heshu_shige_ws', // 二字和数尾数
    'hezhi', 'hezhi_ws',                     // 三字和值 / 三字和值尾数
    'zusan', 'zuliu', 'kuadu',               // 组三/组六/跨度
];

foreach ($grouped as $pt => $items) {
    $label = $pt;
    echo "--- [{$pt}] ({$label}) ---\n";
    foreach ($items as $item) {
        echo "  bet_key: {$item['bet_key']}  |  bet_name: {$item['bet_name']}  |  odds: {$item['odds']}\n";
    }
    echo "\n";
}

echo "\n=== 前端调用对照 ===\n\n";

// 关键对照点：
echo "前端 renderHezhi() 三字和数: getItemOdds('hezhi', 'hz_' + bk)\n";
echo "前端 renderHezhi() 三字和数尾数: getItemOdds('hezhi_ws', 'hzw_' + i)\n";
echo "  → 注意: 前端用 'hzw_' 前缀，检查数据库是否匹配\n\n";

echo "前端 renderErziHeshu() 二字和数: getItemOdds(dbKey, 'ehs_' + subTab + '_' + bk)\n";
echo "前端 renderErziHeshu() 二字和数尾数: getItemOdds(dbWsKey, 'ehsw_' + subTab + '_' + i)\n\n";

// 检查 hezhi_ws 的 bet_key 格式
if (isset($grouped['hezhi_ws'])) {
    echo "[hezhi_ws] 数据库中的 bet_key:\n";
    foreach ($grouped['hezhi_ws'] as $item) {
        echo "  {$item['bet_key']}\n";
    }
    echo "  前端查找: 'hzw_0' ~ 'hzw_9'\n";
    $dbKeys = array_column($grouped['hezhi_ws'], 'bet_key');
    for ($i = 0; $i <= 9; $i++) {
        $frontKey = 'hzw_' . $i;
        $found = in_array($frontKey, $dbKeys) ? '✓ 匹配' : '✗ 不匹配';
        echo "  前端 {$frontKey} → {$found}\n";
    }
} else {
    echo "[hezhi_ws] 数据库中无此 play_type!\n";
}

echo "\n";

// 检查 hezhi 的 bet_key 格式
if (isset($grouped['hezhi'])) {
    echo "[hezhi] 数据库中的 bet_key:\n";
    foreach ($grouped['hezhi'] as $item) {
        echo "  {$item['bet_key']}\n";
    }
    echo "  前端查找: 'hz_0' ~ 'hz_27' (其中 hz_0 代表 0-6, hz_21 代表 21-27)\n";
}

echo "\n";

// 检查 shuangmian_combo 的 bet_key
if (isset($grouped['shuangmian_combo'])) {
    echo "[shuangmian_combo] 数据库中的 bet_key:\n";
    foreach ($grouped['shuangmian_combo'] as $item) {
        echo "  {$item['bet_key']} | {$item['bet_name']} | {$item['odds']}\n";
    }
    echo "  前端查找: 'baishi_hedan', 'baishi_heshuang', 'baishi_heweida', 'baishi_heweixiao' 等\n";
}

echo "\n";

// 检查 kuadu 的 bet_key
if (isset($grouped['kuadu'])) {
    echo "[kuadu] 数据库中的 bet_key:\n";
    foreach ($grouped['kuadu'] as $item) {
        echo "  {$item['bet_key']} | odds: {$item['odds']}\n";
    }
    echo "  前端查找: 'kd_0' ~ 'kd_9' 或 '0' ~ '9'\n";
}

echo "\n完成\n";
