<?php
/**
 * 诊断2026159期结算问题
 * 用完请删除
 */
header('Content-Type: text/html; charset=utf-8');

$host = '127.0.0.1';
$dbname = 'fucai3d';
$user = 'fucai3d';
$pass = 'jtxe6465keEi3diF';
$port = 3306;
$prefix = 'fa_';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

$period = isset($_GET['period']) ? $_GET['period'] : '2026159';
$stmt = $pdo->prepare("SELECT numbers FROM {$prefix}lottery_draw WHERE period = ? AND status = 1 LIMIT 1");
$stmt->execute([$period]);
$numbers = $stmt->fetchColumn();
if (!$numbers) {
    die("期号 {$period} 未找到已开奖记录");
}
$numbersArr = explode(',', $numbers);
$sumValue = array_sum($numbersArr);

echo "<h2>{$period}期 开奖: {$numbers} (和值={$sumValue})</h2>";

// 查找该期所有和值/总和相关的订单
$sql = "SELECT id, order_no, user_id, play_type, panel_type, play_sub, bet_content, bet_count, bet_amount, total_amount, win_amount, odds, status
    FROM {$prefix}lottery_bet 
    WHERE period = ? AND status IN (1,2)
    AND (play_type LIKE '%hezhi%' OR play_type = 'zonghe' OR 
         (panel_type = 'shuangmian' AND (bet_content LIKE '%hz_21-27%' OR bet_content LIKE '%hz_0-6%' OR bet_content LIKE '%\"da\"%' OR bet_content LIKE '%\"xiao\"%')))
    ORDER BY id";
$stmt = $pdo->prepare($sql);
$stmt->execute([$period]);
$bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>相关订单 (" . count($bets) . "条)</h3>";

// 加载赔率
$oddsRows = $pdo->query("SELECT * FROM {$prefix}lottery_odds WHERE status = 1")->fetchAll(PDO::FETCH_ASSOC);
$oddsMap = [];
$oddsMap2 = [];
foreach ($oddsRows as $o) {
    $oddsMap[$o['bet_key']] = floatval($o['odds']);
    $oddsMap2[$o['play_type']][$o['bet_key']] = floatval($o['odds']);
}

// 显示hezhi相关赔率
echo "<h3>数据库中和值/总和相关赔率</h3>";
echo "<table border='1' cellpadding='4'><tr><th>play_type</th><th>bet_key</th><th>odds</th></tr>";
foreach ($oddsRows as $o) {
    if (strpos($o['play_type'], 'hezhi') !== false || strpos($o['bet_key'], 'hz_') !== false || 
        in_array($o['bet_key'], ['da','xiao','dan','shuang','zhi','he']) ||
        strpos($o['play_type'], 'zonghe') !== false) {
        echo "<tr><td>{$o['play_type']}</td><td>{$o['bet_key']}</td><td>{$o['odds']}</td></tr>";
    }
}
echo "</table>";

foreach ($bets as $bet) {
    $betContent = json_decode($bet['bet_content'], true);
    echo "<hr>";
    echo "<h4>订单#{$bet['id']} (order: {$bet['order_no']})</h4>";
    echo "<p>play_type=<b>{$bet['play_type']}</b> | panel_type=<b>{$bet['panel_type']}</b> | play_sub=<b>" . ($bet['play_sub'] ?? '') . "</b></p>";
    echo "<p>bet_count={$bet['bet_count']} | bet_amount={$bet['bet_amount']} | total_amount={$bet['total_amount']} | <b style='color:blue'>win_amount={$bet['win_amount']}</b> | status={$bet['status']}</p>";
    
    echo "<details><summary>bet_content (点击展开)</summary><pre style='max-height:200px;overflow:auto;background:#f0f0f0;padding:8px'>" . json_encode($betContent, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre></details>";
    
    // 逐注分析
    echo "<table border='1' cellpadding='4'><tr><th>#</th><th>key</th><th>sub</th><th>amount</th><th>odds(item)</th><th>应中?</th><th>赔率来源</th><th>DB odds</th><th>应得奖金</th></tr>";
    $calcTotal = 0;
    $idx = 0;
    foreach ($betContent as $item) {
        $idx++;
        $key = isset($item['key']) ? $item['key'] : '';
        $sub = isset($item['sub']) ? $item['sub'] : (isset($bet['play_sub']) ? $bet['play_sub'] : '');
        $amt = isset($item['amount']) ? floatval($item['amount']) : floatval($bet['bet_amount']);
        $itemOdds = isset($item['odds']) ? floatval($item['odds']) : 0;
        
        // 中奖判定
        $isWin = false;
        
        if ($bet['play_type'] === 'hezhi') {
            if ($key === 'hz_0-6') $isWin = ($sumValue >= 0 && $sumValue <= 6);
            elseif ($key === 'hz_21-27') $isWin = ($sumValue >= 21 && $sumValue <= 27);
            elseif (strpos($key, 'hzws_') === 0) $isWin = (intval(substr($key, 5)) === ($sumValue % 10));
            elseif ($key === 'da') $isWin = ($sumValue >= 14);
            elseif ($key === 'xiao') $isWin = ($sumValue < 14);
            elseif ($key === 'dan') $isWin = ($sumValue % 2 === 1);
            elseif ($key === 'shuang') $isWin = ($sumValue % 2 === 0);
            else {
                $kv = $key;
                if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
                $isWin = (intval($kv) === $sumValue);
            }
        } elseif ($bet['panel_type'] === 'shuangmian') {
            // 通用双面盘大小单双
            if ($key === 'da') $isWin = ($sumValue >= 14);
            elseif ($key === 'xiao') $isWin = ($sumValue < 14);
            elseif ($key === 'dan') $isWin = ($sumValue % 2 === 1);
            elseif ($key === 'shuang') $isWin = ($sumValue % 2 === 0);
        }
        
        // 查赔率 - 模拟DrawService的逻辑
        $dbOdds = 0;
        $matchKey = '';
        $dbPlayType = $bet['play_type'];
        $oddsKey = $key;
        
        // 1. 二级精确: oddsMap2[play_type][key]
        if (isset($oddsMap2[$dbPlayType][$oddsKey])) {
            $dbOdds = $oddsMap2[$dbPlayType][$oddsKey];
            $matchKey = "L2:{$dbPlayType}/{$oddsKey}";
        }
        // 2. 扁平: oddsMap[key]
        elseif (isset($oddsMap[$oddsKey])) {
            $dbOdds = $oddsMap[$oddsKey];
            $matchKey = "flat:{$oddsKey}";
        }
        // 3. 扁平: oddsMap[play_type]
        elseif (isset($oddsMap[$dbPlayType])) {
            $dbOdds = $oddsMap[$dbPlayType];
            $matchKey = "flat:{$dbPlayType}";
        }
        // 4. item自带odds
        if ($dbOdds <= 0 && $itemOdds > 0) {
            $dbOdds = $itemOdds;
            $matchKey = "item_odds({$itemOdds})";
        }
        if ($dbOdds <= 0) {
            $matchKey = "<span style='color:red'>⚠️ 未找到赔率!</span>";
        }
        
        $winAmt = $isWin ? round($amt * $dbOdds, 2) : 0;
        if ($isWin) $calcTotal += $winAmt;
        
        $winColor = $isWin ? 'color:green;font-weight:bold' : 'color:gray';
        echo "<tr style='{$winColor}'><td>{$idx}</td><td>{$key}</td><td>{$sub}</td><td>{$amt}</td><td>{$itemOdds}</td><td>" . ($isWin ? '✅' : '❌') . "</td><td>{$matchKey}</td><td>{$dbOdds}</td><td>{$winAmt}</td></tr>";
    }
    echo "</table>";
    $calcTotal = round($calcTotal, 2);
    $diff = round($calcTotal - floatval($bet['win_amount']), 2);
    echo "<p><b>计算应得: ¥{$calcTotal} | 系统实际: ¥{$bet['win_amount']}</b>";
    if (abs($diff) > 0.01) {
        echo " <span style='color:red;font-weight:bold'>⚠️ 差额: ¥{$diff}</span>";
    } else {
        echo " <span style='color:green'>✅ 一致</span>";
    }
    echo "</p>";
}

echo "<hr><p style='color:gray'>诊断完成 - 用完请删除此文件</p>";
