<?php

namespace app\common\service;

use think\Db;
use think\Log;

/**
 * 开奖数据采集服务
 * 支持多数据源: huiniao(API) / cz89(HTML解析) / manual(手动)
 */
class DrawService
{
    // 彩种映射
    protected static $typeMap = [
        'fc3d' => ['id' => 1, 'name' => '福彩3D', 'draw_time' => '21:15:00'],
        'pl3'  => ['id' => 2, 'name' => '排列三', 'draw_time' => '21:25:00'],
    ];

    // 数据源映射: huiniao接口的type参数
    protected static $huiniaoMap = [
        'fc3d' => 'fcsd',
        'pl3'  => 'pls',
    ];

    /**
     * 从 huiniao API 获取最新开奖
     * @param string $type fc3d / pl3
     * @return array|false ['period'=>'2026102', 'numbers'=>'4,2,0', 'draw_time'=>'2026-04-22 21:15:00']
     */
    public static function fetchFromHuiniao($type = 'fc3d')
    {
        $apiType = isset(self::$huiniaoMap[$type]) ? self::$huiniaoMap[$type] : 'fcsd';
        $url = "http://api.huiniao.top/interface/home/lotteryHistory?type={$apiType}&page=1&limit=1";

        try {
            $response = self::httpGet($url, 10);
            if (!$response) return false;

            $data = json_decode($response, true);
            if (!$data || $data['code'] != 1 || empty($data['data']['last'])) return false;

            $last = $data['data']['last'];
            return [
                'period'    => $last['code'],
                'numbers'   => $last['one'] . ',' . $last['two'] . ',' . $last['three'],
                'draw_time' => $last['open_time'],
                'source'    => 'huiniao',
            ];
        } catch (\Exception $e) {
            Log::error("DrawService::fetchFromHuiniao failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 从 cz89.com (牛彩网) HTML页面解析开奖号码
     * @param string $type fc3d / pl3
     * @return array|false
     */
    public static function fetchFromCz89($type = 'fc3d')
    {
        $path = $type === 'pl3' ? 'p3' : '3d';
        $url = "https://www.cz89.com/kaijiang/{$path}";

        try {
            $html = self::httpGet($url, 15);
            if (!$html) return false;

            // 从页面中解析期号和开奖号码
            // 页面标题格式: "福彩3D 102期开奖结果" 或类似
            // 期号格式: 2026102
            if ($type === 'fc3d') {
                // 搜索 class="ball" 或者从文本中匹配
                // 页面文本中有: "2026102" 和 开奖号码
                if (preg_match('/(\d{7})期开奖结果/', $html, $m)) {
                    $period = $m[1];
                } elseif (preg_match('/第(\d{5,7})期/', $html, $m)) {
                    $period = $m[1];
                } else {
                    return false;
                }

                // 匹配3个独立数字球 (0-9)
                // cz89页面中会有类似 <span class="ball">4</span> 的结构
                if (preg_match_all('/<span[^>]*class="[^"]*ball[^"]*"[^>]*>(\d)<\/span>/', $html, $balls)) {
                    if (count($balls[1]) >= 3) {
                        $numbers = $balls[1][0] . ',' . $balls[1][1] . ',' . $balls[1][2];
                    } else {
                        return false;
                    }
                } elseif (preg_match('/开奖号码[^\d]*(\d)\s*[,，\s]\s*(\d)\s*[,，\s]\s*(\d)/', $html, $nm)) {
                    $numbers = $nm[1] . ',' . $nm[2] . ',' . $nm[3];
                } else {
                    return false;
                }
            } else {
                // 排列三
                if (preg_match('/(\d{5,7})期/', $html, $m)) {
                    $period = $m[1];
                } else {
                    return false;
                }
                if (preg_match_all('/<span[^>]*class="[^"]*ball[^"]*"[^>]*>(\d)<\/span>/', $html, $balls)) {
                    if (count($balls[1]) >= 3) {
                        $numbers = $balls[1][0] . ',' . $balls[1][1] . ',' . $balls[1][2];
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            }

            $drawDate = date('Y-m-d');
            $drawHour = self::$typeMap[$type]['draw_time'];

            return [
                'period'    => $period,
                'numbers'   => $numbers,
                'draw_time' => $drawDate . ' ' . $drawHour,
                'source'    => 'cz89',
            ];
        } catch (\Exception $e) {
            Log::error("DrawService::fetchFromCz89 failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 自动获取开奖结果 (多源自动容灾)
     * 按优先级轮询所有数据源，直到成功
     * 还会验证数据新鲜度（必须是今天的开奖）
     */
    public static function fetchLatestDraw($type = 'fc3d', $requireToday = false)
    {
        // 数据源列表，按优先级排序，可随时新增
        $sources = [
            ['name' => 'huiniao', 'method' => 'fetchFromHuiniao'],
            ['name' => 'cz89',    'method' => 'fetchFromCz89'],
        ];

        $today = date('Y-m-d');
        $errors = [];

        foreach ($sources as $src) {
            Log::info("DrawService: 尝试数据源 [{$src['name']}] 获取 {$type} ...");
            try {
                $result = call_user_func([__CLASS__, $src['method']], $type);
                if ($result) {
                    // 验证数据新鲜度：开奖日期必须是今天
                    if ($requireToday && isset($result['draw_time'])) {
                        $drawDate = substr($result['draw_time'], 0, 10);
                        if ($drawDate !== $today) {
                            $errors[] = "[{$src['name']}] 数据不是今天的(返回日期: {$drawDate})";
                            Log::info("DrawService: [{$src['name']}] 数据非今日, 跳过 (日期: {$drawDate})");
                            continue;
                        }
                    }
                    Log::info("DrawService: [{$src['name']}] 成功! 期号={$result['period']}, 号码={$result['numbers']}");
                    return $result;
                } else {
                    $errors[] = "[{$src['name']}] 返回空数据";
                    Log::info("DrawService: [{$src['name']}] 返回空数据");
                }
            } catch (\Exception $e) {
                $errors[] = "[{$src['name']}] 异常: " . $e->getMessage();
                Log::error("DrawService: [{$src['name']}] 异常: " . $e->getMessage());
            }
        }

        Log::error("DrawService: 所有数据源均失败 [{$type}] - " . implode('; ', $errors));
        return false;
    }

    /**
     * 执行自动开奖流程
     * 1. 获取远程开奖号码
     * 2. 更新本地 lottery_draw 表
     * 3. 创建下一期
     * 4. 结算投注
     */
    public static function processAutoDraw($type = 'fc3d')
    {
        $typeInfo = self::$typeMap[$type];
        $lotteryType = $typeInfo['id'];

        Log::info("===== 开始自动开奖: {$typeInfo['name']} =====");

        // 1. 获取远程开奖数据
        $drawData = self::fetchLatestDraw($type);
        if (!$drawData) {
            Log::error("自动开奖失败: 无法获取 {$typeInfo['name']} 开奖数据");
            return ['code' => 0, 'msg' => '获取开奖数据失败，所有数据源不可用'];
        }

        Log::info("获取到开奖数据: 期号={$drawData['period']}, 号码={$drawData['numbers']}, 来源={$drawData['source']}");

        // 2. 查找本地对应期号
        $localDraw = Db::name('lottery_draw')
            ->where('lottery_type', $lotteryType)
            ->where('period', $drawData['period'])
            ->find();

        if (!$localDraw) {
            // 本地无此期号，创建并标记已开奖
            $numbersArr = explode(',', $drawData['numbers']);
            $sumValue = array_sum($numbersArr);
            Db::name('lottery_draw')->insert([
                'lottery_type' => $lotteryType,
                'period'       => $drawData['period'],
                'numbers'      => $drawData['numbers'],
                'sum_value'    => $sumValue,
                'draw_time'    => $drawData['draw_time'],
                'status'       => 1,
                'createtime'   => time(),
                'updatetime'   => time(),
            ]);
            $drawId = Db::name('lottery_draw')->getLastInsID();
            Log::info("创建新开奖记录 ID={$drawId}");
        } elseif ($localDraw['status'] == 1) {
            // 已开奖 — 但要检查是否还有更新的待开奖期号
            // 如果存在更新的 status=0 期号，说明 API 还没更新到最新，需要重试
            $pendingNewer = Db::name('lottery_draw')
                ->where('lottery_type', $lotteryType)
                ->where('status', 0)
                ->where('period', '>', $drawData['period'])
                ->find();

            if ($pendingNewer) {
                Log::info("期号 {$drawData['period']} 已开奖，但存在更新的待开奖期号 {$pendingNewer['period']}，等待API更新...");
                self::ensureNextPeriod($type, $drawData);
                return ['code' => 0, 'msg' => "API尚未更新到最新期号(当前返回: {$drawData['period']}, 待开: {$pendingNewer['period']})"];
            }

            Log::info("期号 {$drawData['period']} 已开奖，无更新待开期号，跳过");
            // 确保下一期存在
            self::ensureNextPeriod($type, $drawData);
            return ['code' => 1, 'msg' => "期号 {$drawData['period']} 已开奖"];
        } else {
            // 未开奖(status=0)，更新为已开奖
            $numbersArr = explode(',', $drawData['numbers']);
            $sumValue = array_sum($numbersArr);
            Db::name('lottery_draw')
                ->where('id', $localDraw['id'])
                ->update([
                    'numbers'    => $drawData['numbers'],
                    'sum_value'  => $sumValue,
                    'status'     => 1,
                    'updatetime' => time(),
                ]);
            $drawId = $localDraw['id'];
            Log::info("更新开奖记录 ID={$drawId}, 号码={$drawData['numbers']}");
        }

        // 3. 结算该期投注
        $settleResult = self::settleBets($lotteryType, $drawData['period'], $drawData['numbers']);

        // 4. 确保下一期存在
        self::ensureNextPeriod($type, $drawData);

        $msg = sprintf(
            "%s 第%s期 开奖号码: %s (来源: %s) | 结算: %d注中奖, %d注未中",
            $typeInfo['name'], $drawData['period'], $drawData['numbers'], $drawData['source'],
            $settleResult['win_count'], $settleResult['lose_count']
        );
        Log::info($msg);

        return ['code' => 1, 'msg' => $msg, 'data' => $drawData];
    }

    /**
     * 确保下一期存在
     */
    protected static function ensureNextPeriod($type, $drawData)
    {
        $typeInfo = self::$typeMap[$type];
        $lotteryType = $typeInfo['id'];

        // 计算下一期期号
        $currentPeriod = $drawData['period'];
        $nextPeriod = self::calcNextPeriod($currentPeriod);
        $nextDrawTime = date('Y-m-d', strtotime('+1 day')) . ' ' . $typeInfo['draw_time'];

        $exists = Db::name('lottery_draw')
            ->where('lottery_type', $lotteryType)
            ->where('period', $nextPeriod)
            ->find();

        if (!$exists) {
            Db::name('lottery_draw')->insert([
                'lottery_type' => $lotteryType,
                'period'       => $nextPeriod,
                'numbers'      => '',
                'sum_value'    => 0,
                'draw_time'    => $nextDrawTime,
                'status'       => 0,
                'createtime'   => time(),
                'updatetime'   => time(),
            ]);
            Log::info("创建下一期: {$nextPeriod}, 开奖时间: {$nextDrawTime}");
        }
    }

    /**
     * 计算下一期期号
     * 福彩3D: 2026102 → 2026103
     * 排列三: 26102 → 26103
     */
    protected static function calcNextPeriod($period)
    {
        return (string)(intval($period) + 1);
    }

    /**
     * 结算投注
     */
    public static function settleBets($lotteryType, $period, $numbers)
    {
        $numbersArr = explode(',', $numbers);
        $sumValue = array_sum($numbersArr);

        // 预加载该彩种所有赔率到内存（避免逐条查询）
        $oddsMap = [];      // 扁平: bet_key => odds (fallback)
        $oddsMap2 = [];     // 二级: play_type => bet_key => odds (精确)
        $oddsRows = Db::name('lottery_odds')
            ->where('lottery_type', $lotteryType)
            ->where('status', 1)
            ->select();
        foreach ($oddsRows as $o) {
            $oddsMap[$o['bet_key']] = floatval($o['odds']);
            // 二级映射：按play_type分组
            if (!isset($oddsMap2[$o['play_type']])) $oddsMap2[$o['play_type']] = [];
            $oddsMap2[$o['play_type']][$o['bet_key']] = floatval($o['odds']);
        }

        // 获取该期所有待结算投注
        $bets = Db::name('lottery_bet')
            ->where('lottery_type', $lotteryType)
            ->where('period', $period)
            ->where('status', 0)  // 待结算
            ->select();

        $winCount = 0;
        $loseCount = 0;

        foreach ($bets as $bet) {
            $betContent = json_decode($bet['bet_content'], true);
            if (!$betContent) {
                // 无效投注
                Db::name('lottery_bet')->where('id', $bet['id'])->update(['status' => 2, 'updatetime' => time()]);
                $loseCount++;
                continue;
            }

            $playType = $bet['play_type'];
            $panelType = $bet['panel_type'];

            // 派奖金额计算（与补结算脚本共用同一算法，避免偏差）
            $totalWin = self::calcWinAmount($bet, $betContent, $numbersArr, $sumValue, $oddsMap, $oddsMap2);

            if ($totalWin > 0) {
                // 中奖
                Db::startTrans();
                try {
                    Db::name('lottery_bet')->where('id', $bet['id'])->update([
                        'status'      => 1,
                        'win_amount'  => $totalWin,
                        'updatetime'  => time(),
                    ]);
                    // 加回余额
                    \app\common\model\User::where('id', $bet['user_id'])->setInc('money', $totalWin);
                    Db::commit();
                    $winCount++;
                    Log::info("中奖: 用户{$bet['user_id']}, 订单{$bet['order_no']}, 赢 ¥{$totalWin}");
                } catch (\Exception $e) {
                    Db::rollback();
                    Log::error("结算失败: 订单{$bet['order_no']}, " . $e->getMessage());
                }
            } else {
                // 未中奖
                Db::name('lottery_bet')->where('id', $bet['id'])->update([
                    'status'     => 2,
                    'win_amount' => 0,
                    'updatetime' => time(),
                ]);
                $loseCount++;
            }

            // 奖励在投注时生成 xima_record 待领取记录，开奖结算不再发放佣金
        }

        return ['win_count' => $winCount, 'lose_count' => $loseCount];
    }

    /**
     * 计算单注派奖金额（中奖返回金额，未中奖返回0）
     * 抽离自 settleBets，供正式结算与补结算脚本共用同一套算法
     * @param array $bet         lottery_bet 行
     * @param array $betContent  已解码的投注内容
     * @param array $numbersArr  开奖号数组 ['2','8','6']
     * @param int   $sumValue    和值
     * @param array $oddsMap     bet_key => odds 映射 (扁平fallback)
     * @param array $oddsMap2    play_type => bet_key => odds 映射 (精确)
     * @return float 派奖金额
     */
    public static function calcWinAmount($bet, $betContent, $numbersArr, $sumValue, $oddsMap, $oddsMap2 = [])
    {
        $totalWin = 0;
        $playType = $bet['play_type'];
        $panelType = $bet['panel_type'];

        if ($panelType === 'shuangmian' && in_array($playType, ['erzi_dingwei', 'sanzi_dingwei', 'zusan', 'zuliu'])) {
            // 双面盘复杂玩法整单结算逻辑
            if ($playType === 'erzi_dingwei') {
                $pos0 = []; $pos1 = [];
                foreach ($betContent as $item) {
                    if (isset($item['pos'])) {
                        if (intval($item['pos']) === 0) $pos0[] = strval($item['key']);
                        if (intval($item['pos']) === 1) $pos1[] = strval($item['key']);
                    }
                }
                $sub = $bet['play_sub'];
                $map = ['baishi' => [0, 1], 'baige' => [0, 2], 'shige' => [1, 2]];
                if (isset($map[$sub])) {
                    $idx0 = $map[$sub][0];
                    $idx1 = $map[$sub][1];
                    $draw0 = strval($numbersArr[$idx0]);
                    $draw1 = strval($numbersArr[$idx1]);

                    if (in_array($draw0, $pos0) && in_array($draw1, $pos1)) {
                        $winKey = 'ed_' . $sub . '_' . $draw0 . $draw1;
                        $odds = isset($oddsMap[$winKey]) ? $oddsMap[$winKey] : (isset($oddsMap['erzi_dingwei']) ? $oddsMap['erzi_dingwei'] : 90.00);
                        $totalWin = $bet['bet_amount'] * $odds;
                    }
                }
            } elseif ($playType === 'sanzi_dingwei') {
                $pos0 = []; $pos1 = []; $pos2 = [];
                foreach ($betContent as $item) {
                    if (isset($item['pos'])) {
                        if (intval($item['pos']) === 0) $pos0[] = strval($item['key']);
                        if (intval($item['pos']) === 1) $pos1[] = strval($item['key']);
                        if (intval($item['pos']) === 2) $pos2[] = strval($item['key']);
                    }
                }
                $draw0 = strval($numbersArr[0]);
                $draw1 = strval($numbersArr[1]);
                $draw2 = strval($numbersArr[2]);
                if (in_array($draw0, $pos0) && in_array($draw1, $pos1) && in_array($draw2, $pos2)) {
                    $winKey = 'sz_dw_' . $draw0 . $draw1 . $draw2;
                    $odds = isset($oddsMap[$winKey]) ? $oddsMap[$winKey] : (isset($oddsMap['sd_default']) ? $oddsMap['sd_default'] : (isset($oddsMap['sanzi_dingwei']) ? $oddsMap['sanzi_dingwei'] : 900.00));
                    $totalWin = $bet['bet_amount'] * $odds;
                }
            } elseif ($playType === 'zusan') {
                if (count(array_unique($numbersArr)) === 2) {
                    $drawCounts = array_count_values($numbersArr);
                    $drawChong = ''; $drawSingle = '';
                    foreach ($drawCounts as $num => $count) {
                        if ($count === 2) $drawChong = strval($num);
                        if ($count === 1) $drawSingle = strval($num);
                    }
                    $chong = []; $buchong = [];
                    foreach ($betContent as $item) {
                        if (isset($item['pos'])) {
                            if (intval($item['pos']) === 0) $chong[] = strval($item['key']);
                            if (intval($item['pos']) === 1) $buchong[] = strval($item['key']);
                        }
                    }
                    if (in_array($drawChong, $chong) && in_array($drawSingle, $buchong)) {
                        $odds = isset($oddsMap['zusan']) ? $oddsMap['zusan'] : 300.00;
                        $totalWin = $bet['bet_amount'] * $odds;
                    }
                }
            } elseif ($playType === 'zuliu') {
                if (count(array_unique($numbersArr)) === 3) {
                    $selected = [];
                    foreach ($betContent as $item) {
                        $selected[] = strval($item['key']);
                    }
                    if (in_array(strval($numbersArr[0]), $selected) && 
                        in_array(strval($numbersArr[1]), $selected) && 
                        in_array(strval($numbersArr[2]), $selected)) {
                        $odds = isset($oddsMap['zuliu']) ? $oddsMap['zuliu'] : 150.00;
                        $totalWin = $bet['bet_amount'] * $odds;
                    }
                }
            }
        } else {
            // --- 不定胆 (一码/二码/三码) 整单组合结算 ---
            // 规则: 选N个号按组合数C(N,k)拆注; 开奖号(去重)中命中m个所选号,
            //       中奖注数 = C(m, k), 奖金 = 中奖注数 × 单注金额 × 赔率
            if (strpos($playType, 'mabuding') !== false) {
                // 确定 k 值
                $k = 1;
                if (strpos($playType, 'sanma') !== false) $k = 3;
                elseif (strpos($playType, 'erma') !== false) $k = 2;
                elseif (strpos($playType, 'yima') !== false) $k = 1;

                // 收集所选号码（从每个 item 提取胆码数字）
                $selected = [];
                foreach ($betContent as $item) {
                    $d = isset($item['num']) ? strval(intval($item['num'])) : null;
                    if ($d === null) {
                        $key = isset($item['key']) ? $item['key'] : '';
                        $dd = self::extractBudingDigit($key);
                        if ($dd !== null) $d = strval($dd);
                    }
                    if ($d !== null) $selected[$d] = true;
                }
                $selected = array_keys($selected);

                // 开奖号去重
                $drawUnique = array_values(array_unique(array_map('strval', $numbersArr)));

                // 命中的所选号码数量 m
                $hit = array_values(array_intersect($selected, $drawUnique));
                $m = count($hit);

                // 中奖注数 = C(m, k)
                $winUnits = self::combination($m, $k);

                // 二码不定胆: 规则为"组成1注，只算中一次"，命中后不按组合数累加，封顶1注
                if ($k === 2 && $winUnits > 1) {
                    $winUnits = 1;
                }

                if ($winUnits > 0) {
                    // 赔率: 优先 play_type 通用key
                    $odds = isset($oddsMap[$playType]) ? $oddsMap[$playType] : 0;
                    if ($odds <= 0) {
                        // 兜底：取首个 item 的 odds
                        $first = reset($betContent);
                        $odds = (is_array($first) && isset($first['odds'])) ? floatval($first['odds']) : 0;
                    }
                    // 单注金额 = 总投注额 / 注数 (兼容标准盘 bzpUnit×倍数 场景)
                    $betCountDb = intval($bet['bet_count']) > 0 ? intval($bet['bet_count']) : 1;
                    $unitAmt = floatval($bet['total_amount']) > 0
                        ? round(floatval($bet['total_amount']) / $betCountDb, 4)
                        : (floatval($bet['bet_amount']) > 0 ? floatval($bet['bet_amount']) : 1);
                    $totalWin = $winUnits * $unitAmt * $odds;
                }
                return $totalWin;
            }

            // === 标准盘复式: 按位选号(pos)整单匹配 ===
            // bet_content 格式: [{"key":"p0_4","pos":0,"num":4}, ...]
            $firstItem = reset($betContent);
            if (is_array($firstItem) && isset($firstItem['pos']) && $panelType === 'biaozhun') {
                $posSets = [];
                foreach ($betContent as $item) {
                    $pos = intval($item['pos']);
                    $num = isset($item['num']) ? strval(intval($item['num'])) : '';
                    if ($num === '' && isset($item['key'])) {
                        if (preg_match('/^p\d+_(\d+)$/', $item['key'], $pm)) $num = $pm[1];
                        else $num = strval($item['key']);
                    }
                    $posSets[$pos][] = $num;
                }

                // 三星直选复式/组合/通选复式
                if (preg_match('/^(sx|q3|z3|h3|rx3)_(zx_fushi|zx_zuhe|tx_fushi)$/', $playType)) {
                    if (isset($posSets[0], $posSets[1], $posSets[2]) &&
                        in_array(strval($numbersArr[0]), $posSets[0]) &&
                        in_array(strval($numbersArr[1]), $posSets[1]) &&
                        in_array(strval($numbersArr[2]), $posSets[2])) {
                        $odds = isset($oddsMap[$playType]) ? $oddsMap[$playType] : 900;
                        // 标准盘: 每注实际金额 = total_amount / bet_count（含倍数）
                        $unitAmt = ($panelType === 'biaozhun' && isset($bet['total_amount']) && isset($bet['bet_count']) && $bet['bet_count'] > 0)
                            ? floatval($bet['total_amount']) / intval($bet['bet_count'])
                            : floatval($bet['bet_amount']);
                        $totalWin = $unitAmt * $odds;
                    }
                    return $totalWin;
                }

                // 前二直选复式/组合
                if (preg_match('/^qe_zx_(fushi|zuhe)$/', $playType)) {
                    if (isset($posSets[0], $posSets[1]) &&
                        in_array(strval($numbersArr[0]), $posSets[0]) &&
                        in_array(strval($numbersArr[1]), $posSets[1])) {
                        $odds = isset($oddsMap[$playType]) ? $oddsMap[$playType] : 90;
                        $unitAmt = ($panelType === 'biaozhun' && isset($bet['total_amount']) && isset($bet['bet_count']) && $bet['bet_count'] > 0)
                            ? floatval($bet['total_amount']) / intval($bet['bet_count'])
                            : floatval($bet['bet_amount']);
                        $totalWin = $unitAmt * $odds;
                    }
                    return $totalWin;
                }

                // 后二直选复式/组合
                if (preg_match('/^he_zx_(fushi|zuhe)$/', $playType)) {
                    if (isset($posSets[0], $posSets[1]) &&
                        in_array(strval($numbersArr[1]), $posSets[0]) &&
                        in_array(strval($numbersArr[2]), $posSets[1])) {
                        $odds = isset($oddsMap[$playType]) ? $oddsMap[$playType] : 90;
                        $unitAmt = ($panelType === 'biaozhun' && isset($bet['total_amount']) && isset($bet['bet_count']) && $bet['bet_count'] > 0)
                            ? floatval($bet['total_amount']) / intval($bet['bet_count'])
                            : floatval($bet['bet_amount']);
                        $totalWin = $unitAmt * $odds;
                    }
                    return $totalWin;
                }

                // 其他有pos的未识别复式玩法，fallthrough到逐项checkWin
            }

            // === 标准盘组选三/六复式: 无位置整单匹配 ===
            // 用户选N个号码, 组三C(n,2)注, 组六C(n,3)注, 开奖号码的唯一数字全在选号中即中
            if ($panelType === 'biaozhun' && preg_match('/^(sx|q3|z3|h3|rx3)_(zx3|zx6)_fushi$/', $playType, $gm)) {
                $isZx3 = ($gm[2] === 'zx3');
                $drawUnique = array_values(array_unique(array_map('strval', $numbersArr)));
                // 组三要求恰好2种数字, 组六要求恰好3种
                $requiredUniq = $isZx3 ? 2 : 3;
                if (count($drawUnique) !== $requiredUniq) return $totalWin;

                // 收集用户选号
                $selected = [];
                foreach ($betContent as $item) {
                    $d = isset($item['num']) ? strval(intval($item['num'])) : null;
                    if ($d === null) {
                        $k = isset($item['key']) ? strval($item['key']) : '';
                        if (preg_match('/^p?\d*_?(\d+)$/', $k, $dm)) $d = $dm[1];
                        elseif (is_numeric($k)) $d = $k;
                    }
                    if ($d !== null) $selected[$d] = true;
                }
                $selected = array_keys($selected);

                // 开奖唯一数字全在选号中 → 中奖
                $hit = array_intersect($drawUnique, $selected);
                if (count($hit) === $requiredUniq) {
                    $odds = isset($oddsMap[$playType]) ? $oddsMap[$playType] : 0;
                    // 标准盘: 每注实际金额 = total_amount / bet_count（含倍数）
                    $unitAmt = ($panelType === 'biaozhun' && isset($bet['total_amount']) && isset($bet['bet_count']) && $bet['bet_count'] > 0)
                        ? floatval($bet['total_amount']) / intval($bet['bet_count'])
                        : (floatval($bet['bet_amount']) > 0 ? floatval($bet['bet_amount']) : 1);
                    $totalWin = $unitAmt * $odds;
                }
                return $totalWin;
            }

            // === 标准盘前二/后二组选复式: 无位置整单匹配 ===
            // 用户选N个号码, 取相关2位开奖号; 对子不中; 两个不同号都在选号中即中(不论顺序)
            if ($panelType === 'biaozhun' && preg_match('/^(qe|he)_zuxuan_fushi$/', $playType, $gm)) {
                if ($gm[1] === 'qe') {
                    $d1 = strval($numbersArr[0]); $d2 = strval($numbersArr[1]);
                } else {
                    $d1 = strval($numbersArr[1]); $d2 = strval($numbersArr[2]);
                }

                // 收集用户选号
                $selected = [];
                foreach ($betContent as $item) {
                    $d = isset($item['num']) ? strval(intval($item['num'])) : null;
                    if ($d === null) {
                        $k = isset($item['key']) ? strval($item['key']) : '';
                        if (preg_match('/^p?\d*_?(\d+)$/', $k, $dm)) $d = $dm[1];
                        elseif (is_numeric($k)) $d = $k;
                    }
                    if ($d !== null) $selected[$d] = true;
                }

                // 对子: 两位相同, 组选不中
                if ($d1 === $d2) {
                    $isWin = false;
                } else {
                    $isWin = isset($selected[$d1]) && isset($selected[$d2]);
                }
                if ($isWin) {
                    $odds = isset($oddsMap[$playType]) ? $oddsMap[$playType] : 0;
                    // 标准盘: 每注实际金额 = total_amount / bet_count（含倍数）
                    $unitAmt = ($panelType === 'biaozhun' && isset($bet['total_amount']) && isset($bet['bet_count']) && $bet['bet_count'] > 0)
                        ? floatval($bet['total_amount']) / intval($bet['bet_count'])
                        : (floatval($bet['bet_amount']) > 0 ? floatval($bet['bet_amount']) : 1);
                    $totalWin = $unitAmt * $odds;
                }
                return $totalWin;
            }

            // 标准玩法或普通双面玩法逐注结算
            // 组选单式去重：同一组合排序后相同的只算一次中奖
            $wonCombos = [];
            foreach ($betContent as $item) {
                // 快捷多选位置时，优先用item的sub字段作为位置
                $itemSub = isset($item['sub']) ? $item['sub'] : (isset($bet['play_sub']) ? $bet['play_sub'] : '');
                $isWin = self::checkWin($item, $numbersArr, $sumValue, $playType, $panelType, $itemSub);

                // 组选单式去重：排序后相同的key只计一次中奖
                if ($isWin && preg_match('/^(qe|he)_zuxuan_danshi$/', $playType)) {
                    $itemKey = isset($item['key']) ? $item['key'] : '';
                    $sortedKey = str_split($itemKey); sort($sortedKey); $sortedKey = implode('', $sortedKey);
                    if (isset($wonCombos[$sortedKey])) {
                        $isWin = false; // 已算过，跳过
                    } else {
                        $wonCombos[$sortedKey] = true;
                    }
                }
                // 三星组选三/六单式同理去重
                if ($isWin && preg_match('/^(sx|q3|z3|h3|rx3)_(zx3_danshi|zx6_danshi)$/', $playType)) {
                    $itemKey = isset($item['key']) ? $item['key'] : '';
                    $sortedKey = str_split($itemKey); sort($sortedKey); $sortedKey = implode('', $sortedKey);
                    if (isset($wonCombos[$sortedKey])) {
                        $isWin = false;
                    } else {
                        $wonCombos[$sortedKey] = true;
                    }
                }
                if ($isWin) {
                    $key = isset($item['key']) ? $item['key'] : '';
                    // 赔率查找：优先用二级映射 oddsMap2[play_type][bet_key] 精确匹配
                    $odds = 0;

                    // 组合类玩法key映射：num_X → yz_X (数据库赔率key是yz_0~yz_9)
                    $oddsKey = $key;
                    if ($playType === 'yizi_zuhe' && preg_match('/^num_(\d)$/', $key, $km)) {
                        $oddsKey = 'yz_' . $km[1];
                    } elseif ($playType === 'erzi_zuhe' && preg_match('/^num_(\d+)$/', $key, $km)) {
                        $oddsKey = 'ez_' . $km[1];
                    } elseif ($playType === 'sanzi_zuhe' && preg_match('/^num_(\d+)$/', $key, $km)) {
                        $oddsKey = 'sz_' . $km[1];
                    }

                    // 快捷玩法：根据位置(itemSub)确定数据库的play_type
                    // 如 baiwei/shiwei/gewei 对应数据库的 play_type
                    $dbPlayType = $playType;
                    if ($playType === 'kuaijie' && !empty($itemSub)) {
                        $dbPlayType = $itemSub; // baiwei / shiwei / gewei
                    }

                    // 三字和数尾数: 投注key=hzws_X, play_type=hezhi
                    // 赔率可能在 play_type=hezhi_ws bet_key=hzw_X，也可能在 play_type=hezhi bet_key=hzws_X
                    if ($playType === 'hezhi' && strpos($key, 'hzws_') === 0) {
                        // 尝试两种: 优先 hezhi_ws/hzw_X, 回退 hezhi/hzws_X
                        if (!empty($oddsMap2['hezhi_ws']) && isset($oddsMap2['hezhi_ws']['hzw_' . substr($key, 5)])) {
                            $oddsKey = 'hzw_' . substr($key, 5);
                            $dbPlayType = 'hezhi_ws';
                        } else {
                            $oddsKey = $key; // hzws_X
                            $dbPlayType = 'hezhi';
                        }
                    }
                    // 二字和数: 投注key=ehz_X, play_type=erzi_heshu → 赔率在 play_type=erzi_heshu_{sub}
                    if ($playType === 'erzi_heshu' && !empty($itemSub)) {
                        if (strpos($key, 'ehzws_') === 0) {
                            $dbPlayType = 'erzi_heshu_' . $itemSub . '_ws';
                            $oddsKey = 'ehsw_' . $itemSub . '_' . substr($key, 6);
                        } elseif (strpos($key, 'ehz_') === 0) {
                            $dbPlayType = 'erzi_heshu_' . $itemSub;
                            $val = substr($key, 4);
                            // 赔率key直接用原始值: ehs_{sub}_{bk}
                            // bk: '0-4'→0, 5~13→原值, '14-18'→14
                            if ($val === '0-4') {
                                $oddsKey = 'ehs_' . $itemSub . '_0';
                            } elseif ($val === '14-18') {
                                $oddsKey = 'ehs_' . $itemSub . '_14';
                            } else {
                                $oddsKey = 'ehs_' . $itemSub . '_' . intval($val);
                            }
                        }
                    }

                    // 标准盘玩法 → 数据库赔率表 play_type 映射
                    // 投注记录play_type与赔率表play_type命名不一致的映射
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
                        'sx_zx_kuadu'      => 'bzp_sanxing',
                        'sx_zx3_fushi'     => 'bzp_sanxing',
                        'sx_zx6_fushi'     => 'bzp_sanxing',
                        'sx_zx_baodan'     => 'bzp_sanxing',
                        'sx_yimabuding'    => 'bzp_budindan',
                        'sx_ermabuding'    => 'bzp_budindan',
                        'dxds'             => 'bzp_dxds',
                    ];
                    $dbPlayTypeBzp = isset($bzpPlayTypeMap[$dbPlayType]) ? $bzpPlayTypeMap[$dbPlayType] : '';

                    // 1. 二级精确匹配: oddsMap2[play_type][key]
                    if (!empty($oddsMap2[$dbPlayType]) && isset($oddsMap2[$dbPlayType][$oddsKey])) {
                        $odds = $oddsMap2[$dbPlayType][$oddsKey];
                    }
                    // 2. 二级精确匹配: oddsMap2[play_type][原始key]
                    elseif (!empty($oddsMap2[$dbPlayType]) && isset($oddsMap2[$dbPlayType][$key])) {
                        $odds = $oddsMap2[$dbPlayType][$key];
                    }
                    // 3. 标准盘映射: oddsMap2[bzp_xxx][key]
                    elseif ($dbPlayTypeBzp && !empty($oddsMap2[$dbPlayTypeBzp]) && isset($oddsMap2[$dbPlayTypeBzp][$oddsKey])) {
                        $odds = $oddsMap2[$dbPlayTypeBzp][$oddsKey];
                    }
                    // 4. 标准盘映射: oddsMap2[bzp_xxx][原始key]
                    elseif ($dbPlayTypeBzp && !empty($oddsMap2[$dbPlayTypeBzp]) && isset($oddsMap2[$dbPlayTypeBzp][$key])) {
                        $odds = $oddsMap2[$dbPlayTypeBzp][$key];
                    }
                    // 5. 标准盘映射: 用playType本身作为bet_key查找（定位胆/包胆等通用赔率）
                    //    数据库中: play_type='bzp_dingweidan', bet_key='dingweidan', odds=9
                    //    数据库中: play_type='bzp_houer', bet_key='he_zuxuan_baodan', odds=45
                    elseif ($dbPlayTypeBzp && !empty($oddsMap2[$dbPlayTypeBzp]) && isset($oddsMap2[$dbPlayTypeBzp][$playType])) {
                        $odds = $oddsMap2[$dbPlayTypeBzp][$playType];
                    }
                    // 6. 二级按play_type查找（通用赔率）
                    elseif (!empty($oddsMap2[$playType]) && isset($oddsMap2[$playType][$oddsKey])) {
                        $odds = $oddsMap2[$playType][$oddsKey];
                    }
                    // 7. 扁平fallback
                    elseif (isset($oddsMap[$oddsKey])) {
                        $odds = $oddsMap[$oddsKey];
                    }
                    // 8. 扁平fallback: 用playType名作为key
                    elseif (isset($oddsMap[$playType])) {
                        $odds = $oddsMap[$playType];
                    }
                    // 9. item自带odds（兜底：如果以上全未命中或命中但值为0）
                    if ($odds <= 0 && isset($item['odds']) && floatval($item['odds']) > 0) {
                        $odds = floatval($item['odds']);
                    }
                    // 行式投注用item自身amount，否则用实际单注金额
                    // 标准盘: bet_amount只存了单价(bzpUnit),实际每注=total_amount/bet_count(含倍数)
                    $itemAmt = 0;
                    if (isset($item['amount']) && floatval($item['amount']) > 0) {
                        $itemAmt = floatval($item['amount']);
                    } elseif ($panelType === 'biaozhun' && isset($bet['total_amount']) && isset($bet['bet_count']) && $bet['bet_count'] > 0) {
                        $itemAmt = floatval($bet['total_amount']) / intval($bet['bet_count']);
                    } else {
                        $itemAmt = floatval($bet['bet_amount']);
                    }
                    $totalWin += $itemAmt * $odds;
                }
            }
        }

        return $totalWin;
    }

/**
 * 判断单注是否中奖
 */
protected static function checkWin($betItem, $numbersArr, $sumValue, $playType, $panelType, $playSub = '')
{
    $key = isset($betItem['key']) ? strval($betItem['key']) : '';

    // 双面盘玩法
    if ($panelType === 'shuangmian') {
        $bai = intval($numbersArr[0]);
        $shi = intval($numbersArr[1]);
        $ge  = intval($numbersArr[2]);
        $sw  = $sumValue % 10; // 和尾

        // === 快捷玩法：da/xiao/dan/shuang 需按play_sub位置判断，不是总和 ===
        // 注意：只有playType是快捷(kuaijie)或明确位置玩法时才按位置判断，
        //       总和(zonghe/hezhi)玩法不能被拦截到这里
        $posSubMap = ['baiwei' => 0, 'bai' => 0, 'shiwei' => 1, 'shi' => 1, 'gewei' => 2, 'ge' => 2];
        $isTotalPlay = in_array($playType, ['hezhi', 'zonghe', 'kuadu']) || strpos($playSub, 'zonghe') !== false;
        if (in_array($key, ['da','xiao','dan','shuang','zhi','he']) && isset($posSubMap[$playSub]) && !$isTotalPlay) {
            $posVal = intval($numbersArr[$posSubMap[$playSub]]);
            if ($key === 'da') return $posVal >= 5;
            if ($key === 'xiao') return $posVal < 5;
            if ($key === 'dan') return $posVal % 2 === 1;
            if ($key === 'shuang') return $posVal % 2 === 0;
            if ($key === 'zhi') return in_array($posVal, [1,2,3,5,7]);
            if ($key === 'he') return in_array($posVal, [0,4,6,8,9]);
        }

        // === 总和大小单双质合 (拼音+中文) ===
        if ($key === 'da'  || $key === '大') return $sumValue >= 14;
        if ($key === 'xiao'|| $key === '小') return $sumValue < 14;
        if ($key === 'dan' || $key === '单') return $sumValue % 2 === 1;
        if ($key === 'shuang'|| $key === '双') return $sumValue % 2 === 0;
        if ($key === 'zhi') return in_array($sw, [1,2,3,5,7]);
        if ($key === 'he')  return in_array($sw, [0,4,6,8,9]);

        // === 总和(zonghe_heXX) ===
        if ($key === 'zonghe_heda')   return $sumValue >= 14;
        if ($key === 'zonghe_hexiao') return $sumValue < 14;
        if ($key === 'zonghe_hedan')  return $sumValue % 2 === 1;
        if ($key === 'zonghe_heshuang') return $sumValue % 2 === 0;
        if ($key === 'zonghe_hezhi') return in_array($sw, [1,2,3,5,7]);
        if ($key === 'zonghe_hehe')  return in_array($sw, [0,4,6,8,9]);
        // 总和尾
        if ($key === 'zonghe_heweida')   return $sw >= 5;
        if ($key === 'zonghe_heweixiao') return $sw < 5;
        if ($key === 'zonghe_heweidan')  return $sw % 2 === 1;
        if ($key === 'zonghe_heweishuang') return $sw % 2 === 0;
        if ($key === 'zonghe_heweizhi') return in_array($sw, [1,2,3,5,7]);
        if ($key === 'zonghe_heweihe')  return in_array($sw, [0,4,6,8,9]);

        // === 位置数字: bai_2, shi_8, ge_6 ===
        if (preg_match('/^bai(wei)?_(\d)$/', $key, $m)) return intval($m[2]) === $bai;
        if (preg_match('/^shi(wei)?_(\d)$/', $key, $m)) return intval($m[2]) === $shi;
        if (preg_match('/^ge(wei)?_(\d)$/', $key, $m))  return intval($m[2]) === $ge;

        // === 快捷玩法数字: num_0 ~ num_9，根据play_sub判断位置 ===
        if (preg_match('/^num_(\d)$/', $key, $m)) {
            $n = intval($m[1]);
            $subPosMap = ['baiwei' => 0, 'bai' => 0, 'shiwei' => 1, 'shi' => 1, 'gewei' => 2, 'ge' => 2];
            if (isset($subPosMap[$playSub])) {
                return $n === intval($numbersArr[$subPosMap[$playSub]]);
            }
            // 无play_sub时兜底：任意位置命中
            return in_array(strval($n), array_map('strval', $numbersArr));
        }

        // === 位置属性: bai_da, shi_xiao, ge_dan ===
        $pyPos = ['bai' => $bai, 'baiwei' => $bai, 'shi' => $shi, 'shiwei' => $shi, 'ge' => $ge, 'gewei' => $ge];
        if (preg_match('/^(bai|baiwei|shi|shiwei|ge|gewei)_(da|xiao|dan|shuang|zhi|he)$/', $key, $m)) {
            $v = $pyPos[$m[1]]; $a = $m[2];
            if ($a === 'da') return $v >= 5;
            if ($a === 'xiao') return $v < 5;
            if ($a === 'dan') return $v % 2 === 1;
            if ($a === 'shuang') return $v % 2 === 0;
            if ($a === 'zhi') return in_array($v, [1,2,3,5,7]);
            if ($a === 'he') return in_array($v, [0,4,6,8,9]);
        }
        // 中文位置
        $cnPos = ['百位' => $bai, '十位' => $shi, '个位' => $ge];
        foreach ($cnPos as $cn => $v) {
            if ($key === "{$cn}大") return $v >= 5;
            if ($key === "{$cn}小") return $v < 5;
            if ($key === "{$cn}单") return $v % 2 === 1;
            if ($key === "{$cn}双") return $v % 2 === 0;
        }

        // === 龙虎和 ===
        if ($key === 'long' || $key === '龙') return $bai > $ge;
        if ($key === 'hu'   || $key === '虎') return $bai < $ge;
        if ($key === 'longhu_he' || $key === '和') return $bai === $ge;

        // === 二字和: baishi_heXX, baige_heXX, shige_heXX ===
        $twoPosMap = ['baishi' => [$bai,$shi], 'baige' => [$bai,$ge], 'shige' => [$shi,$ge]];
        if (preg_match('/^(baishi|baige|shige)_he(da|xiao|dan|shuang|zhi|he)$/', $key, $m)) {
            $tp = $twoPosMap[$m[1]]; $ts = $tp[0] + $tp[1]; $a = $m[2];
            if ($a === 'da') return $ts >= 10;
            if ($a === 'xiao') return $ts < 10;
            if ($a === 'dan') return $ts % 2 === 1;
            if ($a === 'shuang') return $ts % 2 === 0;
            if ($a === 'zhi') return in_array($ts % 10, [1,2,3,5,7]);
            if ($a === 'he') return in_array($ts % 10, [0,4,6,8,9]);
        }
        // 二字和尾
        if (preg_match('/^(baishi|baige|shige)_hewei(da|xiao|dan|shuang|zhi|he)$/', $key, $m)) {
            $tp = $twoPosMap[$m[1]]; $tw = ($tp[0] + $tp[1]) % 10; $a = $m[2];
            if ($a === 'da') return $tw >= 5;
            if ($a === 'xiao') return $tw < 5;
            if ($a === 'dan') return $tw % 2 === 1;
            if ($a === 'shuang') return $tw % 2 === 0;
            if ($a === 'zhi') return in_array($tw, [1,2,3,5,7]);
            if ($a === 'he') return in_array($tw, [0,4,6,8,9]);
        }

        // === 形态 ===
        $uniq = count(array_unique($numbersArr));
        $sorted = $numbersArr; sort($sorted);
        if ($key === 'baozi')   return $uniq === 1;
        if ($key === 'duizi')   return $uniq === 2;
        if ($key === 'shunzi')  return $uniq === 3 && self::isShunzi($sorted);
        if ($key === 'banshun') return $uniq === 3 && self::isBanshun($sorted);
        if ($key === 'zaliu')   return $uniq === 3 && !self::isShunzi($sorted) && !self::isBanshun($sorted);

        // === 双面盘跨度 (playType='kuadu') ===
        if ($playType === 'kuadu') {
            $kd = max(array_map('intval', $numbersArr)) - min(array_map('intval', $numbersArr));
            $kv = $key;
            if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
            return intval($kv) === $kd;
        }

        // === 双面盘三字和数 (playType='hezhi') ===
        if ($playType === 'hezhi') {
            // 和数尾数: hzws_X 格式
            if (strpos($key, 'hzws_') === 0) {
                return intval(substr($key, 5)) === ($sumValue % 10);
            }
            // 和值范围: hz_0-6, hz_21-27
            if ($key === 'hz_0-6') return $sumValue >= 0 && $sumValue <= 6;
            if ($key === 'hz_21-27') return $sumValue >= 21 && $sumValue <= 27;
            // 总和大小单双质合
            if ($key === 'da'  || $key === '大') return $sumValue >= 14;
            if ($key === 'xiao'|| $key === '小') return $sumValue < 14;
            if ($key === 'dan' || $key === '单') return $sumValue % 2 === 1;
            if ($key === 'shuang'|| $key === '双') return $sumValue % 2 === 0;
            if ($key === 'zhi') return in_array($sumValue % 10, [1,2,3,5,7]);
            if ($key === 'he')  return in_array($sumValue % 10, [0,4,6,8,9]);
            $kv = $key;
            if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
            return intval($kv) === $sumValue;
        }

        // === 双面盘组三 (playType='zusan') ===
        if ($playType === 'zusan') {
            if (count(array_unique($numbersArr)) !== 2) return false;
            $keyArr = str_split($key);
            sort($keyArr);
            $drawSorted = $numbersArr; sort($drawSorted);
            return $keyArr === $drawSorted;
        }

        // === 双面盘组六 (playType='zuliu') ===
        if ($playType === 'zuliu') {
            if (count(array_unique($numbersArr)) !== 3) return false;
            $keyArr = str_split($key);
            sort($keyArr);
            $drawSorted = $numbersArr; sort($drawSorted);
            return $keyArr === $drawSorted;
        }

        // === 双面盘三字定位 (playType='sanzi_dingwei') ===
        if ($playType === 'sanzi_dingwei') {
            return $key === implode('', $numbersArr);
        }

        // === 双面盘二字定位 (playType='erzi_dingwei') ===
        if ($playType === 'erzi_dingwei') {
            // key格式: "28"(百十) / "86"(十个) / "26"(百个)，根据sub判断
            // 但bet里没有sub信息，只有key。需要看bet_content里是否有pos
            // 实际上二字定位的key就是2位数字，需要匹配对应位置
            $betPos = isset($betItem['pos']) ? strval($betItem['pos']) : '';
            if ($betPos === 'baishi' || $betPos === '0') return $key === ($numbersArr[0] . $numbersArr[1]);
            if ($betPos === 'baige'  || $betPos === '1') return $key === ($numbersArr[0] . $numbersArr[2]);
            if ($betPos === 'shige'  || $betPos === '2') return $key === ($numbersArr[1] . $numbersArr[2]);
            // 兜底：任意两位匹配
            $combos = [$numbersArr[0].$numbersArr[1], $numbersArr[0].$numbersArr[2], $numbersArr[1].$numbersArr[2]];
            return in_array($key, $combos);
        }
    }

    // 定位盘 - 猜指定位置的数字
    if ($panelType === 'dingwei') {
        // key 格式: "百位-3" 或 "0" (单数字)
        if (strpos($key, '百位-') === 0) return intval(substr($key, 7)) === intval($numbersArr[0]);
        if (strpos($key, '十位-') === 0) return intval(substr($key, 7)) === intval($numbersArr[1]);
        if (strpos($key, '个位-') === 0) return intval(substr($key, 7)) === intval($numbersArr[2]);
    }

    // 一字定位
    if ($playType === 'yizi_dingwei') {
        $pk = $key;
        if (preg_match('/bai(wei)?_(\d+)/', $pk, $m)) return intval($m[2]) === intval($numbersArr[0]);
        if (preg_match('/shi(wei)?_(\d+)/', $pk, $m)) return intval($m[2]) === intval($numbersArr[1]);
        if (preg_match('/ge(wei)?_(\d+)/', $pk, $m)) return intval($m[2]) === intval($numbersArr[2]);
        return false;
    }

    // 一字组合
    if ($playType === 'yizi_zuhe') {
        $num = $key;
        if (strpos($num, 'yz_') === 0) $num = substr($num, 3);
        if (strpos($num, 'num_') === 0) $num = substr($num, 4);
        return in_array($num, $numbersArr);
    }

    // 二字组合
    if ($playType === 'erzi_zuhe') {
        $comb = $key;
        if (strpos($comb, 'ez_') === 0) $comb = substr($comb, 3);
        if (strlen($comb) !== 2) return false;

        $betDigits = str_split($comb);
        $betCounts = array_count_values($betDigits);
        $drawCounts = array_count_values($numbersArr);

        foreach ($betCounts as $d => $count) {
            $drawCount = isset($drawCounts[$d]) ? $drawCounts[$d] : 0;
            if ($drawCount < $count) return false;
        }
        return true;
    }

    // 三字组合
    if ($playType === 'sanzi_zuhe') {
        $comb = $key;
        if (strpos($comb, 'sz_') === 0) $comb = substr($comb, 3);
        if (strlen($comb) !== 3) return false;

        $betDigits = str_split($comb);
        $betCounts = array_count_values($betDigits);
        $drawCounts = array_count_values($numbersArr);

        foreach ($betCounts as $d => $count) {
            $drawCount = isset($drawCounts[$d]) ? $drawCounts[$d] : 0;
            if ($drawCount < $count) return false;
        }
        return true;
    }

    // 二字和数 / 二字和数尾数
    if ($playType === 'erzi_heshu' || strpos($playType, 'erzi_heshu_') === 0) {
        // 位置优先从 $playSub 获取；兼容 play_type 带后缀旧格式
        $posKey = '';
        if (!empty($playSub)) {
            $posKey = $playSub;
        } elseif (strpos($playType, 'erzi_heshu_') === 0) {
            $parts = explode('_', $playType);
            $posKey = isset($parts[2]) ? $parts[2] : '';
        }

        $map = [
            'baishi' => [0, 1], 'baige' => [0, 2], 'shige' => [1, 2],
            'wanqian' => [0, 1], 'wanbai' => [0, 2], 'wanshi' => [0, 3], 'wange' => [0, 4],
            'qianbai' => [1, 2], 'qianshi' => [1, 3], 'qiange' => [1, 4]
        ];

        if (isset($map[$posKey])) {
            $idx1 = $map[$posKey][0];
            $idx2 = $map[$posKey][1];
            if (isset($numbersArr[$idx1]) && isset($numbersArr[$idx2])) {
                $sum = intval($numbersArr[$idx1]) + intval($numbersArr[$idx2]);

                // 判断 key 类型：ehzws_ = 和值尾数，ehz_ = 和值
                if (strpos($key, 'ehzws_') === 0) {
                    $ws = intval(substr($key, 6));
                    return $ws === ($sum % 10);
                }

                // 和值判定
                $val = $key;
                if (strpos($val, 'ehz_') === 0) {
                    $val = substr($val, 4);
                }
                if ($val === '0-4')   return $sum >= 0 && $sum <= 4;
                if ($val === '14-18') return $sum >= 14 && $sum <= 18;
                return intval($val) === $sum;
            }
        }
    }

    // 三字和数尾数
    if ($playType === 'hezhi_ws') {
        if (strpos($key, 'hzws_') === 0) {
            return intval(substr($key, 5)) === ($sumValue % 10);
        }
        return intval($key) === ($sumValue % 10);
    }

    // ========== 标准盘 biaozhun 玩法 ==========
    if ($panelType === 'biaozhun') {
        $drawStr = implode('', $numbersArr); // "286"
        $drawSorted = $numbersArr;
        sort($drawSorted);

        // --- 三星直选(单式/复式/组合) / 通选(单式/复式) ---
        if (preg_match('/^(sx|q3|z3|h3|rx3)_(zx_danshi|zx_fushi|zx_zuhe|tx_danshi|tx_fushi)$/', $playType)) {
            // key 是3位数字如 "286"
            return $key === $drawStr;
        }

        // --- 三星组选三(单式/复式) ---
        if (preg_match('/^(sx|q3|z3|h3|rx3)_(zx3_danshi|zx3_fushi)$/', $playType)) {
            // 开奖必须恰好2个相同
            if (count(array_unique($numbersArr)) !== 2) return false;
            $keyArr = str_split($key);
            sort($keyArr);
            return $keyArr === $drawSorted;
        }

        // --- 三星组选六(单式/复式) ---
        if (preg_match('/^(sx|q3|z3|h3|rx3)_(zx6_danshi|zx6_fushi)$/', $playType)) {
            // 开奖必须3个都不同
            if (count(array_unique($numbersArr)) !== 3) return false;
            $keyArr = str_split($key);
            sort($keyArr);
            return $keyArr === $drawSorted;
        }

        // --- 三星组选包胆 ---
        if (preg_match('/^(sx|q3|z3|h3|rx3)_zx_baodan$/', $playType)) {
            // 从 key(如 p0_3) 或 num 字段提取真正的胆码数字
            $digit = isset($betItem['num']) ? strval($betItem['num']) : null;
            if ($digit === null) $digit = self::extractBudingDigit($key);
            if ($digit === null) return false;
            return in_array(strval($digit), array_map('strval', $numbersArr));
        }

        // --- 三星混合组选 ---
        if (preg_match('/^(sx|q3|z3|h3|rx3)_(zx_)?hunhe$/', $playType)) {
            $keyArr = str_split($key);
            sort($keyArr);
            return $keyArr === $drawSorted;
        }

        // --- 前二/后二 直选(单式/复式) ---
        if (preg_match('/^(qe|he)_zx_(danshi|fushi)$/', $playType, $m)) {
            $prefix = $m[1];
            if ($prefix === 'qe') {
                $target = $numbersArr[0] . $numbersArr[1]; // 百十
            } else {
                $target = $numbersArr[1] . $numbersArr[2]; // 十个
            }
            return $key === $target;
        }

        // --- 前二/后二 组选(单式/复式) ---
        if (preg_match('/^(qe|he)_zuxuan_(danshi|fushi)$/', $playType, $m)) {
            $prefix = $m[1];
            if ($prefix === 'qe') {
                $target = [$numbersArr[0], $numbersArr[1]];
            } else {
                $target = [$numbersArr[1], $numbersArr[2]];
            }
            // 对子不中
            if ($target[0] === $target[1]) return false;
            sort($target);
            $keyArr = str_split($key);
            sort($keyArr);
            return $keyArr === $target;
        }

        // --- 前二/后二 组选包胆 ---
        if (preg_match('/^(qe|he)_zuxuan_baodan$/', $playType, $m)) {
            $prefix = $m[1];
            if ($prefix === 'qe') {
                $target = [$numbersArr[0], $numbersArr[1]];
            } else {
                $target = [$numbersArr[1], $numbersArr[2]];
            }
            // 对子不中
            if (strval($target[0]) === strval($target[1])) return false;
            // 从 key(如 p0_3) 或 num 字段提取真正的胆码数字
            $digit = isset($betItem['num']) ? strval($betItem['num']) : null;
            if ($digit === null) $digit = self::extractBudingDigit($key);
            if ($digit === null) return false;
            return in_array(strval($digit), array_map('strval', $target));
        }

        // --- 前二/后二 直选和值 ---
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

        // --- 前二/后二 组选和值 ---
        if (preg_match('/^(qe|he)_zuxuan_hezhi$/', $playType, $m)) {
            $prefix = $m[1];
            if ($prefix === 'qe') {
                $t = [$numbersArr[0], $numbersArr[1]];
                $hv = intval($numbersArr[0]) + intval($numbersArr[1]);
            } else {
                $t = [$numbersArr[1], $numbersArr[2]];
                $hv = intval($numbersArr[1]) + intval($numbersArr[2]);
            }
            // 对子不中
            if (strval($t[0]) === strval($t[1])) return false;
            $kv = $key;
            if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
            return intval($kv) === $hv;
        }

        // --- 前二/后二 直选跨度 ---
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

        // --- 定位胆 (dingweidan) ---
        if ($playType === 'dingweidan') {
            // key格式: "p0_3" (百位选3) / "p1_5" (十位选5) / "p2_8" (个位选8)
            if (preg_match('/^p(\d+)_(\d+)$/', $key, $m)) {
                $pos = intval($m[1]);
                $num = strval($m[2]);
                return isset($numbersArr[$pos]) && strval($numbersArr[$pos]) === $num;
            }
            return false;
        }

        // --- 不定胆 (一码/二码/三码) ---
        if (strpos($playType, 'mabuding') !== false || strpos($playType, 'budindan') !== false || strpos($playType, 'buding') !== false) {
            // key 可能为多种格式：p0_5 / p1_3 / num_5 / dan_5 / 纯数字5
            // 不定胆不看位置，只要开奖号中包含该数字即中，故统一提取末位数字再比对
            $digit = self::extractBudingDigit($key);
            if ($digit === null) return false;
            return in_array(strval($digit), array_map('strval', $numbersArr));
        }

        // --- 三星和值(直选和值) ---
        if (preg_match('/^(sx|q3|z3|h3|rx3)_zx_hezhi$/', $playType)) {
            $kv = $key;
            if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
            return intval($kv) === $sumValue;
        }

        // --- 三星和值(组选和值) ---
        if (preg_match('/^(sx|q3|z3|h3|rx3)_zx_hezhi2$/', $playType)) {
            if (count(array_unique($numbersArr)) < 2) return false; // 豹子不中组选和值
            $kv = $key;
            if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
            return intval($kv) === $sumValue;
        }

        // --- 三星跨度 ---
        if (preg_match('/^(sx|q3|z3|h3|rx3)_(zx_)?kuadu$/', $playType)) {
            $kd = max($numbersArr) - min($numbersArr);
            $kv = $key;
            if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
            return intval($kv) === $kd;
        }

        // --- 和值尾数 ---
        if (strpos($playType, 'hzweishu') !== false) {
            $kv = $key;
            if (strpos($kv, 'hz_') === 0) $kv = substr($kv, 3);
            return intval($kv) === ($sumValue % 10);
        }

        // --- 大小单双 ---
        if (strpos($playType, 'dxds') !== false) {
            // key格式: "p0_da"(百位大), "p1_xiao"(十位小), "p2_dan", "p0_shuang"
            if (preg_match('/^p?(\d+)_(da|xiao|dan|shuang)$/', $key, $m)) {
                $pos = intval($m[1]);
                $attr = $m[2];
                if (!isset($numbersArr[$pos])) return false;
                $v = intval($numbersArr[$pos]);
                if ($attr === 'da') return $v >= 5;
                if ($attr === 'xiao') return $v < 5;
                if ($attr === 'dan') return $v % 2 === 1;
                if ($attr === 'shuang') return $v % 2 === 0;
            }
            return false;
        }

        // --- 龙虎和 ---
        if (strpos($playType, 'longhu') !== false || strpos($playType, 'lhh') !== false) {
            $bai = intval($numbersArr[0]);
            $ge  = intval($numbersArr[2]);
            if ($key === 'long' || $key === '龙') return $bai > $ge;
            if ($key === 'hu'   || $key === '虎') return $bai < $ge;
            if ($key === 'he'   || $key === '和') return $bai === $ge;
            return false;
        }

        // --- 形态: 豹子/顺子/对子/半顺/杂六 ---
        if (strpos($playType, 'xingtai') !== false) {
            $sorted = $numbersArr;
            sort($sorted);
            $uniq = count(array_unique($numbersArr));
            if ($key === 'baozi')   return $uniq === 1;
            if ($key === 'duizi')   return $uniq === 2;
            if ($key === 'zaliu')   return $uniq === 3 && !self::isShunzi($sorted) && !self::isBanshun($sorted);
            if ($key === 'shunzi')  return $uniq === 3 && self::isShunzi($sorted);
            if ($key === 'banshun') return $uniq === 3 && self::isBanshun($sorted);
            return false;
        }
    }

    // 直选 - 猜完整3位数 (兜底兼容旧数据)
    if ($playType === '直选' || $panelType === 'zhixuan') {
        return $key === implode('', $numbersArr);
    }

    // 组三/组六 (兜底兼容旧数据)
    if ($playType === '组三') {
        $drawSorted = $numbersArr; sort($drawSorted);
        $keyArr = str_split($key); sort($keyArr);
        return $keyArr === $drawSorted && count(array_unique($numbersArr)) === 2;
    }
    if ($playType === '组六') {
        $drawSorted = $numbersArr; sort($drawSorted);
        $keyArr = str_split($key); sort($keyArr);
        return $keyArr === $drawSorted && count(array_unique($numbersArr)) === 3;
    }

    // 和值 (兜底兼容旧数据)
    if ($playType === '和值' || $panelType === 'hezhi') {
        if ($key === 'hz_0-6') return $sumValue >= 0 && $sumValue <= 6;
        if ($key === 'hz_21-27') return $sumValue >= 21 && $sumValue <= 27;
        if (strpos($key, 'hz_') === 0) {
            return intval(substr($key, 3)) === $sumValue;
        }
        return intval($key) === $sumValue;
    }

    return false;
    }

    /**
     * 从不定胆投注 key 中提取数字（0-9）
     * 支持格式: "p0_5" / "p1_3" / "num_5" / "dan_5" / "5" 等，统一返回末段数字
     * @return int|null 提取失败返回 null
     */
    protected static function extractBudingDigit($key)
    {
        $key = strval($key);
        // 优先匹配 xxx_数字 结构（如 p0_5、num_5、dan_5）
        if (preg_match('/_(\d+)$/', $key, $m)) {
            return intval($m[1]);
        }
        // 纯数字
        if (preg_match('/^\d+$/', $key)) {
            return intval($key);
        }
        // 末尾含数字兜底
        if (preg_match('/(\d+)$/', $key, $m)) {
            return intval($m[1]);
        }
        return null;
    }

    /**
     * 组合数 C(n, k)
     */
    protected static function combination($n, $k)
    {
        if ($k < 0 || $n < 0 || $n < $k) return 0;
        if ($k === 0 || $k === $n) return 1;
        $k = min($k, $n - $k);
        $result = 1;
        for ($i = 0; $i < $k; $i++) {
            $result = $result * ($n - $i) / ($i + 1);
        }
        return intval(round($result));
    }

    /**
     * 判断是否顺子（已排序数组，连续3个数字或含0-9-8这种跨尾顺子）
     */
    protected static function isShunzi($sorted)
    {
        if (count($sorted) !== 3) return false;
        $a = intval($sorted[0]);
        $b = intval($sorted[1]);
        $c = intval($sorted[2]);
        // 常规顺子: 1,2,3 / 3,4,5 等
        if ($c - $b === 1 && $b - $a === 1) return true;
        // 跨尾顺子: 0,8,9 / 0,1,9
        if ($a === 0 && $b === 1 && $c === 9) return true;
        if ($a === 0 && $b === 8 && $c === 9) return true;
        return false;
    }

    /**
     * 判断是否半顺（3个不同数字中有且仅有2个相邻）
     */
    protected static function isBanshun($sorted)
    {
        if (count($sorted) !== 3) return false;
        $a = intval($sorted[0]);
        $b = intval($sorted[1]);
        $c = intval($sorted[2]);
        $adj = 0;
        if (abs($b - $a) === 1 || ($a === 0 && $b === 9) || ($b === 0 && $a === 9)) $adj++;
        if (abs($c - $b) === 1 || ($b === 0 && $c === 9) || ($c === 0 && $b === 9)) $adj++;
        if (abs($c - $a) === 1 || ($a === 0 && $c === 9) || ($c === 0 && $a === 9)) $adj++;
        // 半顺=恰好1对相邻（不是0对也不是顺子的3对/2对）
        return $adj === 1;
    }

    /**
     * HTTP GET 请求
     */
    protected static function httpGet($url, $timeout = 10)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) return false;
        return $response;
    }
}
