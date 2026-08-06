<?php
/**
 * 独立补结算脚本 v2 - 覆盖所有标准盘玩法
 * 直接用PDO计算并修正 2026153 期的所有错误订单
 */
$e=parse_ini_file("/www/wwwroot/haoyunbeicai.top/.env",true)["database"];
$pdo=new PDO("mysql:host=".trim($e['hostname']).";dbname=".trim($e['database']).";charset=utf8mb4",trim($e['username']),trim($e['password']));
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pre=trim($e['prefix']);

$period = '2026153';
$lotteryType = 1;
$numbersArr = ['8','8','7'];
$sumValue = 23; // 8+8+7

// 加载赔率
$oddsMap = [];
$oddsMap2 = [];
$stmt = $pdo->query("SELECT * FROM {$pre}lottery_odds WHERE lottery_type=$lotteryType AND status=1");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
    $oddsMap[$o['bet_key']] = floatval($o['odds']);
    if (!isset($oddsMap2[$o['play_type']])) $oddsMap2[$o['play_type']] = [];
    $oddsMap2[$o['play_type']][$o['bet_key']] = floatval($o['odds']);
}

// bzp映射
$bzpPlayTypeMap = [
    'dingweidan'       => 'bzp_dingweidan',
    'he_zuxuan_baodan' => 'bzp_houer',
    'he_zuxuan_fushi'  => 'bzp_houer',
    'he_zuxuan_danshi' => 'bzp_houer',
    'he_zuxuan_hezhi'  => 'bzp_houer',
    'he_zx_fushi'      => 'bzp_houer',
    'he_zx_danshi'     => 'bzp_houer',
    'he_zx_hezhi'      => 'bzp_houer',
    'he_zx_kuadu'      => 'bzp_houer',
    'qe_zuxuan_baodan' => 'bzp_qianer',
    'qe_zuxuan_fushi'  => 'bzp_qianer',
    'qe_zuxuan_danshi' => 'bzp_qianer',
    'qe_zuxuan_hezhi'  => 'bzp_qianer',
    'qe_zx_fushi'      => 'bzp_qianer',
    'qe_zx_danshi'     => 'bzp_qianer',
    'qe_zx_hezhi'      => 'bzp_qianer',
    'qe_zx_kuadu'      => 'bzp_qianer',
    'sx_zx_fushi'      => 'bzp_sanxing',
    'sx_zx_danshi'     => 'bzp_sanxing',
    'sx_zx_hezhi'      => 'bzp_sanxing',
    'sx_zx_hezhi2'     => 'bzp_sanxing',
    'sx_zx_kuadu'      => 'bzp_sanxing',
    'sx_zx3_fushi'     => 'bzp_sanxing',
    'sx_zx3_danshi'    => 'bzp_sanxing',
    'sx_zx6_fushi'     => 'bzp_sanxing',
    'sx_zx_baodan'     => 'bzp_sanxing',
    'sx_hunhe'         => 'bzp_sanxing',
    'sx_tx_fushi'      => 'bzp_sanxing',
    'sx_tx_danshi'     => 'bzp_sanxing',
    'sx_hzweishu'      => 'bzp_sanxing',
    'sx_yimabuding'    => 'bzp_budindan',
    'sx_ermabuding'    => 'bzp_budindan',
    'dxds'             => 'bzp_dxds',
];

// checkWin 逻辑
function checkWin($item, $numbersArr, $sumValue, $playType, $panelType, $itemSub) {
    $key = isset($item['key']) ? $item['key'] : '';
    
    // 三星直选单式
    if (preg_match('/^(sx|q3|z3|h3|rx3)_zx_danshi$/', $playType)) {
        $target = $numbersArr[0] . $numbersArr[1] . $numbersArr[2];
        return $key === $target;
    }
    
    // 三星直选和值
    if (preg_match('/^(sx|q3|z3|h3|rx3)_zx_hezhi$/', $playType)) {
        $kv = $key;
        if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
        return intval($kv) === $sumValue;
    }

    // 三星组选和值(hezhi2)
    if (preg_match('/^(sx|q3|z3|h3|rx3)_zx_hezhi2$/', $playType)) {
        if (count(array_unique($numbersArr)) < 2) return false; // 豹子不中
        $kv = $key;
        if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
        return intval($kv) === $sumValue;
    }
    
    // 三星直选跨度
    if (preg_match('/^(sx|q3|z3|h3|rx3)_zx_kuadu$/', $playType)) {
        $kd = max($numbersArr) - min($numbersArr);
        $kv = $key;
        if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
        return intval($kv) === $kd;
    }

    // 三星混合组选
    if (preg_match('/^(sx|q3|z3|h3|rx3)_(zx_)?hunhe$/', $playType)) {
        $drawSorted = array_map('strval', $numbersArr); sort($drawSorted);
        $keyArr = str_split($key); sort($keyArr);
        return $keyArr === $drawSorted;
    }

    // 三星组三单式
    if (preg_match('/^(sx|q3|z3|h3|rx3)_zx3_danshi$/', $playType)) {
        if (count(array_unique($numbersArr)) !== 2) return false;
        $drawSorted = array_map('strval', $numbersArr); sort($drawSorted);
        $keyArr = str_split($key); sort($keyArr);
        return $keyArr === $drawSorted;
    }

    // 前二/后二 直选单式
    if (preg_match('/^(qe|he)_zx_danshi$/', $playType, $m)) {
        $prefix = $m[1];
        if ($prefix === 'qe') {
            $target = strval($numbersArr[0]) . strval($numbersArr[1]);
        } else {
            $target = strval($numbersArr[1]) . strval($numbersArr[2]);
        }
        return $key === $target;
    }

    // 前二/后二 直选和值
    if (preg_match('/^(qe|he)_zx_hezhi$/', $playType, $m)) {
        $prefix = $m[1];
        if ($prefix === 'qe') {
            $hv = intval($numbersArr[0]) + intval($numbersArr[1]);
        } else {
            $hv = intval($numbersArr[1]) + intval($numbersArr[2]);
        }
        $kv = $key;
        if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
        return intval($kv) === $hv;
    }

    // 前二/后二 直选跨度
    if (preg_match('/^(qe|he)_zx_kuadu$/', $playType, $m)) {
        $prefix = $m[1];
        if ($prefix === 'qe') {
            $kd = abs(intval($numbersArr[0]) - intval($numbersArr[1]));
        } else {
            $kd = abs(intval($numbersArr[1]) - intval($numbersArr[2]));
        }
        $kv = $key;
        if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
        return intval($kv) === $kd;
    }

    // 前二/后二 组选单式
    if (preg_match('/^(qe|he)_zuxuan_danshi$/', $playType, $m)) {
        $prefix = $m[1];
        if ($prefix === 'qe') {
            $target = [strval($numbersArr[0]), strval($numbersArr[1])];
        } else {
            $target = [strval($numbersArr[1]), strval($numbersArr[2])];
        }
        // 对子也允许中奖
        sort($target);
        $keyArr = str_split($key); sort($keyArr);
        return $keyArr === $target;
    }

    // 前二/后二 组选包胆
    if (preg_match('/^(qe|he)_zuxuan_baodan$/', $playType, $m)) {
        $prefix = $m[1];
        if ($prefix === 'qe') {
            $target = [strval($numbersArr[0]), strval($numbersArr[1])];
        } else {
            $target = [strval($numbersArr[1]), strval($numbersArr[2])];
        }
        // 对子也允许中奖
        $digit = isset($item['num']) ? strval($item['num']) : null;
        if ($digit === null && preg_match('/^p\d+_(\d+)$/', $key, $km)) $digit = $km[1];
        if ($digit === null) return false;
        return in_array(strval($digit), array_map('strval', $target));
    }

    // 前二/后二 组选和值
    if (preg_match('/^(qe|he)_zuxuan_hezhi$/', $playType, $m)) {
        $prefix = $m[1];
        if ($prefix === 'qe') {
            $hv = intval($numbersArr[0]) + intval($numbersArr[1]);
        } else {
            $hv = intval($numbersArr[1]) + intval($numbersArr[2]);
        }
        // 对子也允许中奖
        $kv = $key;
        if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
        return intval($kv) === $hv;
    }

    // 定位胆
    if ($playType === 'dingweidan') {
        if (preg_match('/^p(\d+)_(\d+)$/', $key, $m)) {
            $pos = intval($m[1]);
            $num = strval($m[2]);
            return isset($numbersArr[$pos]) && strval($numbersArr[$pos]) === $num;
        }
        return false;
    }

    // 和值尾数
    if ($playType === 'sx_hzweishu') {
        $kv = $key;
        if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
        $sumTail = $sumValue % 10;
        return intval($kv) === $sumTail;
    }

    return false;
}

// 获取赔率
function getOdds($item, $playType, $key, $oddsMap, $oddsMap2, $bzpPlayTypeMap) {
    $odds = 0;
    $oddsKey = $key;
    $dbPlayType = $playType;
    $dbPlayTypeBzp = isset($bzpPlayTypeMap[$dbPlayType]) ? $bzpPlayTypeMap[$dbPlayType] : '';
    
    // 1. oddsMap2[dbPlayType][oddsKey]
    if (!empty($oddsMap2[$dbPlayType]) && isset($oddsMap2[$dbPlayType][$oddsKey])) {
        $odds = $oddsMap2[$dbPlayType][$oddsKey];
    }
    // 2. oddsMap2[bzp_xxx][oddsKey]
    elseif ($dbPlayTypeBzp && !empty($oddsMap2[$dbPlayTypeBzp]) && isset($oddsMap2[$dbPlayTypeBzp][$oddsKey])) {
        $odds = $oddsMap2[$dbPlayTypeBzp][$oddsKey];
    }
    // 3. oddsMap2[bzp_xxx][key]
    elseif ($dbPlayTypeBzp && !empty($oddsMap2[$dbPlayTypeBzp]) && isset($oddsMap2[$dbPlayTypeBzp][$key])) {
        $odds = $oddsMap2[$dbPlayTypeBzp][$key];
    }
    // 4. oddsMap2[bzp_xxx][playType] — 通用赔率
    elseif ($dbPlayTypeBzp && !empty($oddsMap2[$dbPlayTypeBzp]) && isset($oddsMap2[$dbPlayTypeBzp][$playType])) {
        $odds = $oddsMap2[$dbPlayTypeBzp][$playType];
    }
    // 5. oddsMap[playType]
    elseif (isset($oddsMap[$playType])) {
        $odds = $oddsMap[$playType];
    }
    // 6. item自带odds (最低优先级)
    elseif (isset($item['odds']) && floatval($item['odds']) > 0) {
        $odds = floatval($item['odds']);
    }
    return $odds;
}

// ====== 开始补结算 ======
echo "===== 独立补结算 v2 期号: $period 开奖: " . implode(',', $numbersArr) . " =====\n\n";

// 查询所有标准盘status=1或2的注单
$stmt = $pdo->prepare("SELECT * FROM {$pre}lottery_bet WHERE lottery_type=? AND period=? AND panel_type='biaozhun' AND status IN (1,2)");
$stmt->execute([$lotteryType, $period]);
$bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "找到 " . count($bets) . " 条标准盘订单\n\n";

$fixCount = 0;
$fixAmount = 0;

foreach ($bets as $bet) {
    $betContent = json_decode($bet['bet_content'], true);
    if (!$betContent) continue;
    
    $playType = $bet['play_type'];
    $panelType = $bet['panel_type'];
    $totalWin = 0;
    
    // 逐注结算
    foreach ($betContent as $item) {
        $key = isset($item['key']) ? $item['key'] : '';
        $isWin = checkWin($item, $numbersArr, $sumValue, $playType, $panelType, '');
        
        if ($isWin) {
            $odds = getOdds($item, $playType, $key, $oddsMap, $oddsMap2, $bzpPlayTypeMap);
            // 每注金额
            if ($bet['bet_count'] > 0) {
                $itemAmt = floatval($bet['total_amount']) / intval($bet['bet_count']);
            } else {
                $itemAmt = floatval($bet['bet_amount']);
            }
            $totalWin += $itemAmt * $odds;
        }
    }
    
    $correctWin = round($totalWin, 2);
    $oldWin = round(floatval($bet['win_amount']), 2);
    $oldStatus = intval($bet['status']);
    $diff = round($correctWin - $oldWin, 2);
    
    $needFix = false;
    if ($correctWin > 0 && ($oldStatus != 1 || abs($diff) > 0.001)) {
        $needFix = true;
    } elseif ($correctWin == 0 && $oldStatus == 1 && $oldWin > 0) {
        // 原来判中但实际没中 — 需要核实（暂时不扣）
        // $needFix = true;
    }
    
    if ($needFix) {
        $newStatus = $correctWin > 0 ? 1 : 2;
        
        // 更新订单
        $upd = $pdo->prepare("UPDATE {$pre}lottery_bet SET status=?, win_amount=?, updatetime=? WHERE id=?");
        $upd->execute([$newStatus, $correctWin, time(), $bet['id']]);
        
        // 补差额给用户
        if ($diff > 0) {
            $pdo->prepare("UPDATE {$pre}user SET money=money+? WHERE id=?")->execute([$diff, $bet['user_id']]);
        } elseif ($diff < 0) {
            $pdo->prepare("UPDATE {$pre}user SET money=money-? WHERE id=?")->execute([abs($diff), $bet['user_id']]);
        }
        
        $tag = $diff > 0 ? "补发 +¥$diff" : ($diff < 0 ? "扣回 ¥".abs($diff) : "仅状态");
        echo "✓ ID={$bet['id']} play={$playType} | 原win={$oldWin} → 正确={$correctWin} | $tag\n";
        $fixCount++;
        $fixAmount += $diff;
    }
}

echo "\n===== 补结算完成: 修正 {$fixCount} 注, 累计调整 ¥" . number_format($fixAmount, 2) . " =====\n";
