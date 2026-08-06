<?php
/**
 * 初始化/修复赔率数据
 * 检查并补全 hezhi_ws (三字和数尾数) 的赔率配置
 * 访问: http://域名/init_odds_hzws.php
 */
define('APP_PATH', __DIR__ . '/../application/');
require __DIR__ . '/../thinkphp/base.php';

header('Content-Type: text/plain; charset=utf-8');

$lotteryTypes = [1, 2]; // 福彩3D, 排列三

foreach ($lotteryTypes as $lt) {
    $ltName = $lt === 1 ? '福彩3D' : '排列三';
    echo "=== {$ltName} (lottery_type={$lt}) ===\n";

    // 检查 hezhi_ws 是否已有数据
    $existing = think\Db::name('lottery_odds')
        ->where('lottery_type', $lt)
        ->where('play_type', 'hezhi_ws')
        ->select();

    if (!empty($existing)) {
        echo "[hezhi_ws] 已有 " . count($existing) . " 条记录:\n";
        foreach ($existing as $r) {
            echo "  bet_key={$r['bet_key']}, bet_name={$r['bet_name']}, odds={$r['odds']}\n";
        }
    } else {
        echo "[hezhi_ws] 无数据，正在插入...\n";
        $defaultOdds = 9.85;
        for ($i = 0; $i <= 9; $i++) {
            think\Db::name('lottery_odds')->insert([
                'lottery_type' => $lt,
                'play_type'   => 'hezhi_ws',
                'bet_key'     => 'hzw_' . $i,
                'bet_name'    => '和尾' . $i,
                'odds'        => $defaultOdds,
                'max_odds'    => 0,
                'status'      => 1,
                'createtime'  => time(),
                'updatetime'  => time(),
            ]);
            echo "  插入: hzw_{$i} (和尾{$i}) odds={$defaultOdds}\n";
        }
        echo "  完成! 插入10条\n";
    }

    echo "\n";

    // 同时检查 hezhi (三字和数) 的 bet_key 格式
    $hezhiRows = think\Db::name('lottery_odds')
        ->where('lottery_type', $lt)
        ->where('play_type', 'hezhi')
        ->select();
    echo "[hezhi] 已有 " . count($hezhiRows) . " 条记录:\n";
    foreach ($hezhiRows as $r) {
        echo "  bet_key={$r['bet_key']}, bet_name={$r['bet_name']}, odds={$r['odds']}\n";
    }

    echo "\n";

    // 检查 erzi_heshu 相关
    $ehTypes = ['erzi_heshu_baishi', 'erzi_heshu_baige', 'erzi_heshu_shige',
                'erzi_heshu_baishi_ws', 'erzi_heshu_baige_ws', 'erzi_heshu_shige_ws'];
    foreach ($ehTypes as $ehPt) {
        $cnt = think\Db::name('lottery_odds')
            ->where('lottery_type', $lt)
            ->where('play_type', $ehPt)
            ->count();
        echo "[{$ehPt}] 共 {$cnt} 条\n";
    }

    echo "\n---\n\n";
}

echo "完成。如果显示已有数据但赔率不对，请通过后台赔率管理修改。\n";
