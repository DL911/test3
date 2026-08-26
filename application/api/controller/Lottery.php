<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 彩票API接口
 * 支持: 福彩3D(fc3d), 排列三(pl3)
 */
class Lottery extends Api
{
    protected $noNeedLogin = ['getDraws', 'getLatestDraw', 'getOdds', 'triggerAutoDraw', 'banners', 'reSettlePeriod', 'getUsdtRate', 'checkWinAmounts', 'check_win_amounts'];
    protected $noNeedRight = '*';

    // 彩种映射
    protected $typeMap = ['fc3d' => 1, 'pl3' => 2];

    /**
     * 获取USDT/CNY实时汇率（后端代理+缓存）
     * GET /api/lottery/getUsdtRate
     */
    public function getUsdtRate()
    {
        // 使用独立缓存键，避免上线后继续命中旧的单来源汇率。
        $cacheKey = 'usdt_cny_rate_min_v1';
        // 提现汇率上浮幅度（比充值汇率高 +0.07）
        $withdrawDiff = 0.07;
        $cached = \think\Cache::get($cacheKey);
        if ($cached && isset($cached['rate']) && $cached['rate'] > 0) {
            // 兼容旧缓存：若无 withdraw_rate 则实时补上
            if (!isset($cached['withdraw_rate'])) {
                $cached['withdraw_rate'] = round($cached['rate'] + $withdrawDiff, 4);
                $cached['withdraw_diff'] = $withdrawDiff;
            }
            $this->success('', $cached);
        }

        $rate = 0;
        $source = '';
        $urls = [
            'OKX(欧意)' => 'https://www.okx.com/api/v5/market/exchange-rate',
            'CoinGecko' => 'https://api.coingecko.com/api/v3/simple/price?ids=tether&vs_currencies=cny',
            'Coinbase' => 'https://api.coinbase.com/v2/exchange-rates?currency=USDT',
        ];
        $responses = $this->httpGetMultiple($urls);
        $quotes = [];

        $j = isset($responses['OKX(欧意)']) ? json_decode($responses['OKX(欧意)'], true) : [];
        if (isset($j['data'][0]['usdCny'])) $quotes['OKX(欧意)'] = floatval($j['data'][0]['usdCny']);

        $j = isset($responses['CoinGecko']) ? json_decode($responses['CoinGecko'], true) : [];
        if (isset($j['tether']['cny'])) $quotes['CoinGecko'] = floatval($j['tether']['cny']);

        $j = isset($responses['Coinbase']) ? json_decode($responses['Coinbase'], true) : [];
        if (isset($j['data']['rates']['CNY'])) $quotes['Coinbase'] = floatval($j['data']['rates']['CNY']);

        // USDT/CNY 正常报价限定在4~10，防止某一来源异常值被当成最低价。
        $quotes = array_filter($quotes, function ($value) {
            return is_finite($value) && $value >= 4 && $value <= 10;
        });
        if ($quotes) {
            $rate = min($quotes);
            $source = array_search($rate, $quotes, true);
        }

        // 兜底：使用上次缓存或默认值
        if ($rate <= 0) {
            $last = \think\Cache::get('usdt_cny_rate_last');
            if ($last && $last > 0) {
                $rate = floatval($last);
                $source = 'cache';
            } else {
                $rate = 7.20;
                $source = 'default';
            }
        }

        $data = [
            'rate'          => round($rate, 4),
            'withdraw_rate' => round($rate + $withdrawDiff, 4),
            'withdraw_diff' => $withdrawDiff,
            'source'        => $source,
            'quotes'        => array_map(function ($value) { return round($value, 4); }, $quotes),
            'updatetime'    => date('Y-m-d H:i:s'),
        ];

        // 成功获取的真实汇率缓存5分钟，并长期保留最后一次有效值
        if ($source !== 'default') {
            \think\Cache::set($cacheKey, $data, 300);
            \think\Cache::set('usdt_cny_rate_last', $rate, 0);
        }

        $this->success('', $data);
    }

    /**
     * 并发请求多个汇率来源；无curl_multi时退化为顺序请求。
     */
    private function httpGetMultiple(array $urls, $timeout = 5)
    {
        if (!function_exists('curl_multi_init')) {
            $responses = [];
            foreach ($urls as $name => $url) $responses[$name] = $this->httpGet($url, $timeout);
            return $responses;
        }

        $multi = curl_multi_init();
        $handles = [];
        foreach ($urls as $name => $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            curl_multi_add_handle($multi, $ch);
            $handles[$name] = $ch;
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                $selected = curl_multi_select($multi, 1.0);
                if ($selected === -1) usleep(10000);
            }
        } while ($running && $status === CURLM_OK);

        $responses = [];
        foreach ($handles as $name => $ch) {
            $body = curl_multi_getcontent($ch);
            $responses[$name] = $body ?: '';
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
        return $responses;
    }

    /**
     * 简单HTTP GET（带超时）
     */
    private function httpGet($url, $timeout = 5)
    {
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                $res = curl_exec($ch);
                curl_close($ch);
                return $res ?: '';
            }
            $ctx = stream_context_create(['http' => ['timeout' => $timeout]]);
            return @file_get_contents($url, false, $ctx) ?: '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * 获取开奖历史记录
     * GET /api/lottery/getDraws?type=fc3d&limit=10
     */
    public function getDraws()
    {
        $type = $this->request->param('type', 'fc3d');
        $limit = $this->request->param('limit/d', 10);
        $lotteryType = isset($this->typeMap[$type]) ? $this->typeMap[$type] : 1;

        $list = Db::name('lottery_draw')
            ->where('lottery_type', $lotteryType)
            ->where('status', 1)
            ->order('period', 'desc')
            ->limit($limit)
            ->select();

        foreach ($list as &$item) {
            $item['numbers_arr'] = $item['numbers'] ? explode(',', $item['numbers']) : [];
            $item['da_xiao'] = $item['sum_value'] >= 14 ? '大' : '小';
            $item['dan_shuang'] = $item['sum_value'] % 2 === 1 ? '单' : '双';
        }

        $this->success('OK', $list);
    }

    /**
     * 获取最新一期开奖 + 下期信息 + 倒计时
     * GET /api/lottery/getLatestDraw?type=fc3d
     */
    public function getLatestDraw()
    {
        $type = $this->request->param('type', 'fc3d');
        $lotteryType = isset($this->typeMap[$type]) ? $this->typeMap[$type] : 1;

        // 最新已开奖
        $latest = Db::name('lottery_draw')
            ->where('lottery_type', $lotteryType)
            ->where('status', 1)
            ->order('period', 'desc')
            ->find();

        // 下一期（待开奖）
        $next = Db::name('lottery_draw')
            ->where('lottery_type', $lotteryType)
            ->where('status', 0)
            ->order('draw_time', 'asc')
            ->find();

        // 如果没有下一期，自动触发开奖+创建下一期
        if (!$next) {
            try {
                \app\common\service\DrawService::processAutoDraw($type);
            } catch (\Exception $e) {
                // 采集失败不影响页面加载
            }
            // 重新查询
            $latest = Db::name('lottery_draw')
                ->where('lottery_type', $lotteryType)
                ->where('status', 1)
                ->order('period', 'desc')
                ->find();
            $next = Db::name('lottery_draw')
                ->where('lottery_type', $lotteryType)
                ->where('status', 0)
                ->order('draw_time', 'asc')
                ->find();

            // 兜底: 如果采集失败仍无下一期，直接根据最新期号创建
            if (!$next && $latest) {
                $drawTimeMap = ['fc3d' => '21:15:00', 'pl3' => '21:25:00'];
                $drawHour = isset($drawTimeMap[$type]) ? $drawTimeMap[$type] : '21:15:00';
                $nextPeriod = strval(intval($latest['period']) + 1);
                $nextDrawTime = date('Y-m-d', strtotime('+1 day')) . ' ' . $drawHour;

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

                $next = Db::name('lottery_draw')
                    ->where('lottery_type', $lotteryType)
                    ->where('status', 0)
                    ->order('draw_time', 'asc')
                    ->find();
            }
        }

        // 彩种配置
        $config = Db::name('lottery_config')
            ->where('code', $type)
            ->find();

        $data = [
            'latest'      => $latest,
            'next'        => $next,
            'config'      => $config,
            'server_time' => date('Y-m-d H:i:s')
        ];

        if ($latest) {
            $data['latest']['numbers_arr'] = explode(',', $latest['numbers']);
        }

        // 倒计时计算
        if ($next && $next['draw_time']) {
            $countdown = strtotime($next['draw_time']) - time();
            $betClose = $config ? $config['bet_close_seconds'] : 60;
            $data['countdown'] = max(0, $countdown - $betClose);
            $data['is_open'] = $data['countdown'] > 0;
        } else {
            $data['countdown'] = 0;
            $data['is_open'] = false;
        }

        $this->success('OK', $data);
    }

    /**
     * 获取赔率配置
     * GET /api/lottery/getOdds?type=fc3d
     */
    public function getOdds()
    {
        $type = $this->request->param('type', 'fc3d');
        $lotteryType = isset($this->typeMap[$type]) ? $this->typeMap[$type] : 1;

        $odds = Db::name('lottery_odds')
            ->where('lottery_type', $lotteryType)
            ->where('status', 1)
            ->select();

        $grouped = [];
        foreach ($odds as $item) {
            $grouped[$item['play_type']][] = [
                'key'      => $item['bet_key'],
                'name'     => $item['bet_name'],
                'odds'     => floatval($item['odds']),
                'max_odds' => floatval($item['max_odds'])
            ];
        }

        $this->success('OK', $grouped);
    }

    /**
     * 计算真实注数（与前端 calcRealBetCount 保持一致）
     */
    private function calcBetCount($betsArr, $playType, $panelType)
    {
        // 行式输入模式：各行amount之和
        $hasAmount = false;
        foreach ($betsArr as $bet) {
            if (isset($bet['amount']) && $bet['amount'] > 0) {
                $hasAmount = true;
                break;
            }
        }
        if ($hasAmount) {
            $sum = 0;
            foreach ($betsArr as $bet) {
                $sum += isset($bet['amount']) ? intval($bet['amount']) : 0;
            }
            return max($sum, 1);
        }

        // 标准盘玩法
        if ($panelType === 'biaozhun') {
            return $this->calcBzpBetCount($betsArr, $playType);
        }

        // 双面盘 - 定位类玩法：按位相乘
        if ($playType === 'sanzi_dingwei' || $playType === 'erzi_dingwei') {
            $positions = [];
            foreach ($betsArr as $bet) {
                if (isset($bet['pos'])) {
                    $p = $bet['pos'];
                    if (!isset($positions[$p])) $positions[$p] = 0;
                    $positions[$p]++;
                }
            }
            $counts = array_values($positions);
            if (empty($counts)) return count($betsArr);
            $result = 1;
            foreach ($counts as $c) $result *= $c;
            return $result;
        }

        // 双面盘 - 组三: 重号数 × 不重号数 - 重叠数
        if ($playType === 'zusan') {
            $pos0 = []; $pos1 = [];
            foreach ($betsArr as $bet) {
                $p = isset($bet['pos']) ? strval($bet['pos']) : '';
                $num = isset($bet['key']) ? $bet['key'] : '';
                if ($p === '0') $pos0[] = $num;
                else $pos1[] = $num;
            }
            $overlap = count(array_intersect($pos0, $pos1));
            return count($pos0) * count($pos1) - $overlap;
        }
        // 双面盘 - 组六: C(n,3)
        if ($playType === 'zuliu') {
            return $this->comb(count($betsArr), 3);
        }

        return count($betsArr);
    }

    /**
     * 标准盘注数计算
     */
    private function calcBzpBetCount($betsArr, $playType)
    {
        // 按位分组统计
        $positions = [];
        $nonPosCount = 0;
        foreach ($betsArr as $bet) {
            if (isset($bet['pos']) && $bet['pos'] !== null && $bet['pos'] !== '') {
                $p = $bet['pos'];
                if (!isset($positions[$p])) $positions[$p] = 0;
                $positions[$p]++;
            } else {
                $nonPosCount++;
            }
        }
        $counts = array_values($positions);
        sort($counts);
        $n = $nonPosCount > 0 ? $nonPosCount : (count($counts) > 0 ? $counts[0] : count($betsArr));

        // === 单式 / 混合组选（text输入，前端已拆为多条）===
        if (strpos($playType, 'danshi') !== false || strpos($playType, 'hunhe') !== false) {
            return count($betsArr);
        }

        // === 直选复式 row3（三位相乘）===
        if (preg_match('/^(sx|q3|z3|h3|rx3)_(zx|tx)_fushi$/', $playType) ||
            preg_match('/^(sx|q3|z3|h3|rx3)_zx_zuhe$/', $playType)) {
            if (count($counts) >= 3) return $counts[0] * $counts[1] * $counts[2];
            return 0;
        }

        // === 直选复式 row2（两位相乘）===
        if (preg_match('/^(qe|he|rx2)_zx_fushi$/', $playType)) {
            if (count($counts) >= 2) return $counts[0] * $counts[1];
            return 0;
        }

        // === 四星直选复式（四位相乘）===
        if (preg_match('/^[qh]4_zx_(fushi|zuhe)$/', $playType) || preg_match('/^rx4_zx_fushi$/', $playType)) {
            if (count($counts) >= 4) return $counts[0] * $counts[1] * $counts[2] * $counts[3];
            return 0;
        }

        // === 五星直选复式（五位相乘）===
        if (preg_match('/^wx_zx_(fushi|zuhe)$/', $playType)) {
            if (count($counts) >= 5) return $counts[0] * $counts[1] * $counts[2] * $counts[3] * $counts[4];
            return 0;
        }

        // === 组选三复式: n × (n-1) ===
        if (preg_match('/^(sx|q3|z3|h3|rx3)_zx3_fushi$/', $playType)) {
            return $n * ($n - 1);
        }

        // === 二星组选复式: C(n, 2) ===
        if (preg_match('/^(qe|he|rx2)_zuxuan_fushi$/', $playType)) {
            return $this->comb($n, 2);
        }

        // === 组选六复式: C(n,3) ===
        if (preg_match('/^(sx|q3|z3|h3|rx3)_zx6_fushi$/', $playType)) {
            return $this->comb($n, 3);
        }

        // === 四星组选 ===
        if (preg_match('/^([qh]4|rx4)_zx24$/', $playType)) return $this->comb($n, 4);
        if (preg_match('/^([qh]4|rx4)_zx12$/', $playType)) return count($betsArr);
        if (preg_match('/^([qh]4|rx4)_zx6$/', $playType))  return count($betsArr);
        if (preg_match('/^([qh]4|rx4)_zx4$/', $playType))  return count($betsArr);

        // === 五星组选 ===
        if ($playType === 'wx_zx120') return $this->comb($n, 5);
        if ($playType === 'wx_zx60')  return count($betsArr);
        if ($playType === 'wx_zx30')  return count($betsArr);
        if ($playType === 'wx_zx20')  return count($betsArr);
        if ($playType === 'wx_zx10')  return count($betsArr);
        if ($playType === 'wx_zx5')   return count($betsArr);

        // === 三星组选包胆: 54注 (组三18 + 组六36) ===
        if (preg_match('/^(sx|q3|z3|h3)_zx_baodan$/', $playType)) {
            return $n * 54;
        }

        // === 二星组选包胆: 9 ===
        if (preg_match('/^(qe|he|rx2)_zuxuan_baodan$/', $playType)) {
            return $n * 9;
        }

        // === 双面盘组三: 重号数 × 不重号数 - 重叠数 ===
        if ($playType === 'zusan') {
            $pos0 = []; $pos1 = [];
            foreach ($betsArr as $bet) {
                $p = isset($bet['pos']) ? strval($bet['pos']) : '';
                $num = isset($bet['num']) ? $bet['num'] : (isset($bet['key']) ? $bet['key'] : '');
                if ($p === '0') $pos0[] = $num;
                else $pos1[] = $num;
            }
            $overlap = count(array_intersect($pos0, $pos1));
            return count($pos0) * count($pos1) - $overlap;
        }

        // === 双面盘组六: C(n, 3) ===
        if ($playType === 'zuliu') {
            return $this->comb(count($betsArr), 3);
        }

        // === 双面盘三字定位: 各位相乘 ===
        if ($playType === 'sanzi_dingwei') {
            $posCounts = [];
            foreach ($betsArr as $bet) {
                $p = isset($bet['pos']) ? strval($bet['pos']) : '0';
                if (!isset($posCounts[$p])) $posCounts[$p] = 0;
                $posCounts[$p]++;
            }
            $cts = array_values($posCounts);
            sort($cts);
            return count($cts) >= 3 ? $cts[0] * $cts[1] * $cts[2] : 0;
        }

        // === 双面盘二字定位: 两位相乘 ===
        if ($playType === 'erzi_dingwei') {
            $posCounts = [];
            foreach ($betsArr as $bet) {
                $p = isset($bet['pos']) ? strval($bet['pos']) : '0';
                if (!isset($posCounts[$p])) $posCounts[$p] = 0;
                $posCounts[$p]++;
            }
            $cts = array_values($posCounts);
            sort($cts);
            return count($cts) >= 2 ? $cts[0] * $cts[1] : 0;
        }

        // === 定位胆: 各位选中数之和 ===
        if ($playType === 'dingweidan') {
            $total = 0;
            foreach ($counts as $c) $total += $c;
            return $total > 0 ? $total : count($betsArr);
        }

        // === 不定胆 ===
        if (strpos($playType, 'buding') !== false || strpos($playType, 'mabuding') !== false) {
            if (strpos($playType, 'sanma') !== false) return $this->comb($n, 3);
            if (strpos($playType, 'erma') !== false)  return $this->comb($n, 2);
            return $n;
        }

        // === 和值 / 跨度 / 和值尾数: 查表累加 ===
        if (strpos($playType, 'hezhi') !== false || strpos($playType, 'kuadu') !== false || strpos($playType, 'hzweishu') !== false) {
            // 和值尾数: 每选一个 = 1注
            if (strpos($playType, 'hzweishu') !== false) {
                return count($betsArr);
            }

            // 查表数组 (与前端完全一致)
            $HZ27_ZX  = [1,3,6,10,15,21,28,36,45,55,63,69,73,75,75,73,69,63,55,45,36,28,21,15,10,6,3,1];
            $HZ27_ZUX = [0,1,2,2,4,5,6,8,10,11,13,14,14,15,15,14,14,13,11,10,8,6,5,4,2,2,1,0];
            $HZ18     = [1,2,3,4,5,6,7,8,9,10,9,8,7,6,5,4,3,2,1];
            $HZ18_ZUX = [0,1,1,2,2,3,3,4,4,5,4,4,3,3,2,2,1,1,0];
            $KD3      = [10,54,96,126,144,150,144,126,96,54];
            $KD2      = [10,18,16,14,12,10,8,6,4,2];

            $table = null;
            // 二星优先匹配（避免被三星的模糊匹配抢走）
            if (preg_match('/^(qe|he|rx2)_zuxuan_hezhi$/', $playType)) {
                $table = $HZ18_ZUX; // 二星组选和值
            } elseif (preg_match('/^(qe|he|rx2)_zx_hezhi$/', $playType)) {
                $table = $HZ18; // 二星直选和值
            } elseif (preg_match('/^(qe|he|rx2)_zx_kuadu$/', $playType)) {
                $table = $KD2; // 二星跨度
            } elseif (strpos($playType, 'hezhi2') !== false || preg_match('/^(sx|q3|z3|h3|rx3)_(zx_)?hezhi2$/', $playType)) {
                $table = $HZ27_ZUX; // 三星组选和值
            } elseif (preg_match('/^(sx|q3|z3|h3|rx3)_(zx_)?hezhi$/', $playType)) {
                $table = $HZ27_ZX; // 三星直选和值
            } elseif (preg_match('/^(sx|q3|z3|h3|rx3)_(zx_)?kuadu$/', $playType)) {
                $table = $KD3; // 三星跨度
            }

            if ($table) {
                $total = 0;
                foreach ($betsArr as $bet) {
                    $k = isset($bet['key']) ? $bet['key'] : '';
                    $idx = intval(str_replace('hz_', '', $k));
                    if ($idx >= 0 && $idx < count($table)) {
                        $total += $table[$idx];
                    }
                }
                return $total > 0 ? $total : count($betsArr);
            }

            // 双面盘和值/跨度: 每选一个=1注
            return count($betsArr);
        }

        // === 大小单双: 各位相乘 ===
        if (strpos($playType, 'dxds') !== false) {
            if (count($counts) > 0) {
                $result = 1;
                foreach ($counts as $c) $result *= $c;
                return $result;
            }
            return count($betsArr);
        }

        // === 趣味 / 梭哈 / 炸金花 / 牛牛等: 选中数 ===
        if (in_array($playType, ['yffs','hscs','sxbx','sjfc','suoha','zhajinhua','niuniu'])) {
            return count($betsArr);
        }

        // === 兜底 ===
        return $nonPosCount > 0 ? $nonPosCount : count($betsArr);
    }

    /**
     * 组合数 C(n, r)
     */
    private function comb($n, $r)
    {
        if ($n < $r || $r < 0) return 0;
        if ($r == 0 || $r == $n) return 1;
        $result = 1;
        for ($i = 0; $i < $r; $i++) {
            $result = $result * ($n - $i) / ($i + 1);
        }
        return intval(round($result));
    }

    /**
     * 提交投注
     * POST /api/lottery/placeBet
     */
    public function placeBet()
    {
        $userId = $this->auth->id;

        $type      = strtolower(trim($this->request->post('type', 'fc3d')));
        $period    = trim($this->request->post('period', ''));
        $playType  = trim($this->request->post('play_type', ''));
        $playSub   = $this->request->post('play_sub', '');
        $panelType = $this->request->post('panel_type', 'shuangmian');
        $bets      = isset($_POST['bets']) ? $_POST['bets'] : '';
        $amount    = $this->request->post('amount/f', 1);

        // 标准盘: 单价和倍数
        $bzpUnit     = $this->request->post('bzp_unit/f', 0);
        $bzpMultiple = $this->request->post('bzp_multiple/d', 0);

        if (empty($period) || empty($playType) || empty($bets)) {
            $this->error('参数不完整');
        }

        $betsArr = json_decode($bets, true);
        if (empty($betsArr) || !is_array($betsArr)) {
            $this->error('投注内容格式错误, raw=' . mb_substr($bets, 0, 100));
        }

        $lotteryType = isset($this->typeMap[$type]) ? $this->typeMap[$type] : 1;

        // 检查彩种是否启用
        $config = Db::name('lottery_config')
            ->where('code', $type)
            ->where('status', 1)
            ->find();

        if (!$config) {
            $this->error('该彩种暂未开放');
        }

        // 检查是否在投注时间内
        $nextDraw = Db::name('lottery_draw')
            ->where('lottery_type', $lotteryType)
            ->where('period', $period)
            ->where('status', 0)
            ->find();

        if (!$nextDraw) {
            $this->error('该期号暂不可投注');
        }

        $countdown = strtotime($nextDraw['draw_time']) - time();
        if ($countdown <= $config['bet_close_seconds']) {
            $this->error('已封盘，请等待下一期');
        }

        // 注数计算（与前端 calcRealBetCount 保持一致）
        $betCount = $this->calcBetCount($betsArr, $playType, $panelType);
        if ($betCount <= 0) {
            $this->error('未识别到有效投注注数');
        }

        // 总金额计算
        // 检测是否为行式amount（每个bet自带amount字段）
        $isLineAmount = false;
        $lineTotal = 0;
        foreach ($betsArr as $bi) {
            if (isset($bi['amount']) && floatval($bi['amount']) > 0) {
                $isLineAmount = true;
                $lineTotal += floatval($bi['amount']);
            }
        }

        if ($isLineAmount) {
            // 行式投注: 总金额 = 各行金额之和
            $totalAmount = $lineTotal;
            $amount = $lineTotal / max(count($betsArr), 1); // 记录平均每注
        } elseif ($panelType === 'biaozhun' && $bzpUnit > 0 && $bzpMultiple > 0) {
            // 标准盘: 总金额 = 注数 × 单价 × 倍数
            $totalAmount = $betCount * $bzpUnit * $bzpMultiple;
            $amount = $bzpUnit;
        } else {
            $totalAmount = $betCount * $amount;
        }

        // 福彩3D/排列三标准盘的三星直选复式/单式按用户、彩种、期号、玩法分别累计校验。
        // 具体累计校验放在事务和用户行锁内执行，防止通过拆单或并发请求绕过。
        $needsSanxingAverageLimit = in_array($type, ['fc3d', 'pl3'])
            && $panelType === 'biaozhun'
            && in_array($playType, ['sx_zx_fushi', 'sx_zx_danshi']);
        $periodLimitData = null;
        if ($panelType === 'biaozhun' && ($bzpUnit <= 0 || $bzpMultiple <= 0)) {
            $this->error('标准盘投注金额或倍数无效');
        }

        // 标准盘验证总金额，双面盘/行式验证单注金额
        $checkAmount = ($panelType === 'biaozhun' && $bzpUnit > 0 && $bzpMultiple > 0) ? $totalAmount : $amount;
        if ($checkAmount < $config['min_bet'] || $checkAmount > $config['max_bet']) {
            $this->error('投注金额不在允许范围内 (¥' . $config['min_bet'] . ' - ¥' . $config['max_bet'] . ')');
        }

        // 检查用户余额
        $user = \app\common\model\User::get($userId);
        if (!$user || $user->money < $totalAmount) {
            $this->error('余额不足，当前余额: ¥' . ($user ? $user->money : 0));
        }

        // 从数据库获取赔率（防止前端篡改）
        $oddsMap = [];
        $oddsRows = Db::name('lottery_odds')
            ->where('lottery_type', $lotteryType)
            ->where('status', 1)
            ->select();
        foreach ($oddsRows as $o) {
            $oddsMap[$o['bet_key']] = floatval($o['odds']);
        }

        // 用数据库赔率覆盖前端传入的赔率，确保金额校验与防篡改一致
        foreach ($betsArr as &$betItem) {
            $betKey = isset($betItem['key']) ? strval($betItem['key']) : '';
            
            // 1. 一字定位 (yizi_dingwei)
            if ($playType === 'yizi_dingwei') {
                $mappedKey = $betKey;
                if (preg_match('/^(bai|shi|ge)_(\d+)$/', $betKey, $m)) {
                    $pos = $m[1] . 'wei';
                    $mappedKey = 'yz_dw_' . $pos . '_' . $m[2];
                }
                if (isset($oddsMap[$mappedKey])) {
                    $betItem['odds'] = $oddsMap[$mappedKey];
                }
                continue;
            }
            
            // 2. 二字和数 / 二字和数尾数 (erzi_heshu)
            if (strpos($playType, 'erzi_heshu_') === 0) {
                $parts = explode('_', $playType);
                $sub = end($parts);
                $isWs = ($sub === 'ws');
                $posKey = $isWs ? $parts[2] : $parts[2];
                
                $mappedKey = '';
                if ($isWs) {
                    $digit = $betKey;
                    if (strpos($digit, 'ehzws_') === 0) $digit = substr($digit, 6);
                    $mappedKey = 'ehsw_' . $posKey . '_' . $digit;
                } else {
                    $val = $betKey;
                    if (strpos($val, 'ehz_') === 0) $val = substr($val, 4);
                    
                    $idx = -1;
                    if ($val === '0-4') $idx = 0;
                    elseif ($val === '14-18') $idx = 10;
                    else {
                        $num = intval($val);
                        if ($num >= 5 && $num <= 13) {
                            $idx = $num - 4;
                        }
                    }
                    if ($idx !== -1) {
                        $mappedKey = 'ehs_' . $posKey . '_' . $idx;
                    }
                }
                
                if ($mappedKey && isset($oddsMap[$mappedKey])) {
                    $betItem['odds'] = $oddsMap[$mappedKey];
                }
                continue;
            }
            
            // 3. 三字和数范围 (hezhi)
            if ($playType === '和值' || $panelType === 'hezhi') {
                if ($betKey === 'hz_0-6' || $betKey === 'hz_21-27' || $betKey === '0-6' || $betKey === '21-27') {
                    $hz10 = isset($oddsMap['hz_10']) ? floatval($oddsMap['hz_10']) : 9.85;
                    $ratio = $hz10 / 9.85;
                    $fallback = 11.726; // 前端默认值
                    $betItem['odds'] = round($fallback * $ratio, 3);
                    continue;
                }
            }
            
            // 4. 三字组合 (sanzi_zuhe)
            if ($playType === 'sanzi_zuhe') {
                // 前端提交的键可能是 sz_zuliu / sz_zusan / sz_baozi
                $map = [
                    'sz_zuliu' => 'sanzi_zuliu',
                    'sz_zusan' => 'sanzi_zusan',
                    'sz_baozi' => 'sanzi_baozi',
                    'sz_zhixuan' => 'sanzi_zhixuan'
                ];
                if (isset($map[$betKey]) && isset($oddsMap[$map[$betKey]])) {
                    $betItem['odds'] = $oddsMap[$map[$betKey]];
                    continue;
                }
            }

            // 5. 默认直接匹配
            if (isset($oddsMap[$betKey])) {
                $betItem['odds'] = $oddsMap[$betKey];
            }
        }
        unset($betItem);

        // 创建投注记录
        Db::startTrans();
        try {
            // 串行化同一用户的投注，确保累计均注金额与余额校验都不会被并发绕过。
            $lockedUser = Db::name('user')->where('id', $userId)->lock(true)->field('money')->find();
            if (!$lockedUser || floatval($lockedUser['money']) < $totalAmount) {
                Db::rollback();
                $this->error('余额不足，当前余额: ¥' . ($lockedUser ? $lockedUser['money'] : 0));
            }

            if ($needsSanxingAverageLimit) {
                // 两项汇总必须使用独立Query对象；旧版ThinkPHP/PDO克隆查询会丢失预处理绑定参数。
                $historyBetCount = intval(Db::name('lottery_bet')
                    ->where('user_id', $userId)
                    ->where('lottery_type', $lotteryType)
                    ->where('period', $period)
                    ->where('panel_type', 'biaozhun')
                    ->where('play_type', $playType)
                    ->where('status', '<>', 3)
                    ->sum('bet_count'));
                $historyTotalAmount = floatval(Db::name('lottery_bet')
                    ->where('user_id', $userId)
                    ->where('lottery_type', $lotteryType)
                    ->where('period', $period)
                    ->where('panel_type', 'biaozhun')
                    ->where('play_type', $playType)
                    ->where('status', '<>', 3)
                    ->sum('total_amount'));
                $periodBetCount = $historyBetCount + $betCount;
                $periodTotalAmount = $historyTotalAmount + $totalAmount;
                $periodMaxAmount = $periodBetCount * 500;
                if ($periodTotalAmount > $periodMaxAmount + 0.000001) {
                    Db::rollback();
                    $limitPlayName = $playType === 'sx_zx_danshi' ? '三星直选单式' : '三星直选复式';
                    $this->error('本期' . $limitPlayName . '最多可投注¥' . number_format($periodMaxAmount, 2)
                        . '（累计' . $periodBetCount . '注 × 500元），当前累计提交将达到¥' . number_format($periodTotalAmount, 2));
                }
                $periodLimitData = [
                    'bet_count' => $periodBetCount,
                    'total_amount' => round($periodTotalAmount, 2),
                    'max_amount' => round($periodMaxAmount, 2),
                    'remaining_amount' => round($periodMaxAmount - $periodTotalAmount, 2),
                ];
            }

            $orderNo = date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . mt_rand(1000, 9999);

            Db::name('lottery_bet')->insert([
                'order_no'     => $orderNo,
                'user_id'      => $userId,
                'lottery_type' => $lotteryType,
                'period'       => $period,
                'play_type'    => $playType,
                'play_sub'     => $playSub,
                'panel_type'   => $panelType,
                'bet_content'  => json_encode($betsArr, JSON_UNESCAPED_UNICODE),
                'bet_count'    => $betCount,
                'bet_amount'   => $amount,
                'total_amount' => $totalAmount,
                'odds'         => isset($betsArr[0]['odds']) ? $betsArr[0]['odds'] : 0,
                'status'       => 0,
                'createtime'   => time(),
                'updatetime'   => time()
            ]);

            // 扣减余额
            Db::name('user')->where('id', $userId)->setDec('money', $totalAmount);

            // 代理佣金（不影响主流程）
            try {
                $betId = Db::name('lottery_bet')->getLastInsID();
                $userRow = Db::name('user')->where('id', $userId)->field('pid, invite_rebate_rate')->find();

                // 邀请人返佣（洗码已处理自身返水，这里只处理邀请人佣金）
                $parentId = isset($userRow['pid']) ? intval($userRow['pid']) : 0;
                if ($parentId && $parentId > 0) {
                    $parentRow = Db::name('user')->where('id', $parentId)->field('invite_rebate_rate')->find();
                    $commissionRate = isset($parentRow['invite_rebate_rate']) ? floatval($parentRow['invite_rebate_rate']) : 0.02;
                    $commissionAmount = round($totalAmount * $commissionRate, 2);
                    if ($commissionAmount > 0) {
                        Db::name('lottery_commission')->insert([
                            'user_id'    => $parentId,
                            'sub_id'     => $userId,
                            'bet_id'     => $betId,
                            'type'       => 'bet_commission',
                            'amount'     => $commissionAmount,
                            'remark'     => "下级投注返佣 (订单: {$orderNo})",
                            'createtime' => time()
                        ]);
                        Db::name('user')->where('id', $parentId)->setInc('money', $commissionAmount);
                    }
                }
            } catch (\Exception $ce) {
                // 佣金失败不中断投注
            }

            // 洗码处理（不影响主流程）
            try {
                $ximaConfig = Db::name('xima_config')
                    ->where('status', 1)
                    ->where(function($q) use ($lotteryType) {
                        $q->where('lottery_type', 0)->whereOr('lottery_type', $lotteryType);
                    })
                    ->order('id', 'asc')
                    ->find();

                if ($ximaConfig && $totalAmount >= $ximaConfig['min_bet']) {
                    // 读取用户个人费率用于自身洗码，邀请人洗码用后台配置
                    $userRates = Db::name('user')->where('id', $userId)
                        ->field('self_rebate_rate, pid')
                        ->find();
                    $selfRate = (!empty($userRates['self_rebate_rate']) && floatval($userRates['self_rebate_rate']) > 0)
                        ? floatval($userRates['self_rebate_rate'])
                        : floatval($ximaConfig['self_rate']);

                    // 1. 自身洗码
                    if ($selfRate > 0) {
                        $selfAmount = round($totalAmount * $selfRate, 2);
                        if ($selfAmount > 0) {
                            Db::name('xima_record')->insert([
                                'user_id'        => $userId,
                                'type'           => 'self',
                                'source_user_id' => $userId,
                                'bet_order_no'   => $orderNo,
                                'bet_amount'     => $totalAmount,
                                'xima_rate'      => $selfRate,
                                'xima_amount'    => $selfAmount,
                                'status'         => 0,
                                'createtime'     => time(),
                                'updatetime'     => time(),
                            ]);
                        }
                    }

                    // 2. 邀请人洗码（给上级）
                    $pId = isset($userRates['pid']) ? intval($userRates['pid']) : 0;
                    if ($pId > 0) {
                        // 邀请人洗码费率：优先上级个人“下级洗码”费率(invite_rebate_rate)，否则用后台配置 parent_rate
                        $parentUser = Db::name('user')->where('id', $pId)->field('invite_rebate_rate')->find();
                        $parentRate = (!empty($parentUser['invite_rebate_rate']) && floatval($parentUser['invite_rebate_rate']) > 0)
                            ? floatval($parentUser['invite_rebate_rate'])
                            : floatval($ximaConfig['parent_rate']);
                        if ($parentRate > 0) {
                            $parentAmount = round($totalAmount * $parentRate, 2);
                            if ($parentAmount > 0) {
                                Db::name('xima_record')->insert([
                                    'user_id'        => $pId,
                                    'type'           => 'parent',
                                    'source_user_id' => $userId,
                                    'bet_order_no'   => $orderNo,
                                    'bet_amount'     => $totalAmount,
                                    'xima_rate'      => $parentRate,
                                    'xima_amount'    => $parentAmount,
                                    'status'         => 0,
                                    'createtime'     => time(),
                                    'updatetime'     => time(),
                                ]);
                            }
                        }
                    }
                }
            } catch (\Exception $xe) {
                // 洗码失败不中断投注
            }

            $newBalance = Db::name('user')->where('id', $userId)->value('money');
            Db::commit();
        } catch (\think\exception\HttpResponseException $e) {
            // $this->success / $this->error 内部抛的，必须原样抛出
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('投注失败: ' . $e->getMessage());
        }
        $this->success('投注成功', [
            'order_no'     => $orderNo,
            'bet_count'    => $betCount,
            'total_amount' => $totalAmount,
            'balance'      => $newBalance,
            'period_limit' => $periodLimitData
        ]);
    }

    /**
     * 获取投注记录
     * GET /api/lottery/getBetHistory?type=fc3d&page=1&limit=20
     */
    public function getBetHistory()
    {
        $userId = $this->auth->id;
        $type   = $this->request->param('type', '');
        $page   = $this->request->param('page/d', 1);
        $limit  = $this->request->param('limit/d', 20);
        $time   = $this->request->param('time', '');
        $status = $this->request->param('status', '');

        // 构建筛选条件数组（同时用于计数与列表查询，避免 clone Query 对象）
        $map = [];
        $map['user_id'] = $userId;

        // 彩种筛选
        if ($type && isset($this->typeMap[$type])) {
            $map['lottery_type'] = $this->typeMap[$type];
        }

        // 状态筛选: 0=待开奖, 1=已中奖, 2=未中奖, 3=已撤单
        if ($status !== '' && $status !== 'all') {
            $map['status'] = intval($status);
        }

        // 时间范围筛选
        if ($time) {
            $todayStart = strtotime(date('Y-m-d'));
            switch ($time) {
                case 'today':
                    $map['createtime'] = ['>=', $todayStart];
                    break;
                case 'yesterday':
                    $map['createtime'] = ['between', [$todayStart - 86400, $todayStart - 1]];
                    break;
                case 'before_yesterday':
                    $map['createtime'] = ['between', [$todayStart - 86400 * 2, $todayStart - 86400 - 1]];
                    break;
                case '7days':
                    $map['createtime'] = ['>=', $todayStart - 86400 * 7];
                    break;
                case '20days':
                    $map['createtime'] = ['>=', $todayStart - 86400 * 20];
                    break;
            }
        }

        $total = Db::name('lottery_bet')->where($map)->count();
        $sumAmount = Db::name('lottery_bet')->where($map)->sum('total_amount');

        // 中奖注数：独立统计(不受 status 筛选影响)，避免对 status 字段二次 where 导致条件冲突
        $winMap = $map;
        unset($winMap['status']);
        $winMap['status'] = 1;
        $winCount = Db::name('lottery_bet')->where($winMap)->count();
        $winAmount = Db::name('lottery_bet')->where($winMap)->sum('win_amount');

        $list = Db::name('lottery_bet')
            ->where($map)
            ->order('createtime', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('OK', [
            'list'       => $list,
            'total'      => $total,
            'sum_amount' => round($sumAmount, 2),
            'win_count'  => $winCount,
            'win_amount' => round($winAmount, 2),
            'page'       => $page,
            'limit'      => $limit
        ]);
    }

    /**
     * 撤单（取消投注）
     * POST /api/lottery/cancelBet
     * @param bet_id 投注ID
     */
    public function cancelBet()
    {
        $userId = $this->auth->id;
        $betId  = $this->request->post('bet_id/d', 0);

        if (!$betId) {
            $this->error('参数错误');
        }

        $bet = Db::name('lottery_bet')->where('id', $betId)->where('user_id', $userId)->find();
        if (!$bet) {
            $this->error('订单不存在');
        }
        if ($bet['status'] != 0) {
            $this->error('该订单已开奖，无法撤单');
        }

        // 检查是否已过截止时间（开奖前5分钟不允许撤单）
        $draw = Db::name('lottery_draw')
            ->where('lottery_type', $bet['lottery_type'])
            ->where('period', $bet['period'])
            ->find();
        if ($draw && !empty($draw['numbers'])) {
            $this->error('该期已开奖，无法撤单');
        }

        Db::startTrans();
        try {
            // 更新订单状态为已撤单
            Db::name('lottery_bet')->where('id', $betId)->update([
                'status'     => 3,
                'updatetime' => time()
            ]);

            // 退还金额
            $refund = floatval($bet['total_amount']);
            Db::name('user')->where('id', $userId)->setInc('money', $refund);

            // 如有代理佣金记录，也需撤销（忽略表不存在）
            try { Db::name('lottery_commission')->where('bet_id', $betId)->delete(); } catch (\Exception $ce) {}

            // 撤销关联洗码记录
            try {
                $ximaRecords = Db::name('xima_record')->where('bet_order_no', $bet['order_no'])->select();
                foreach ($ximaRecords as $xr) {
                    // 已领取的扣回余额
                    if ($xr['status'] == 1 && $xr['xima_amount'] > 0) {
                        Db::name('user')->where('id', $xr['user_id'])->setDec('money', $xr['xima_amount']);
                    }
                }
                Db::name('xima_record')->where('bet_order_no', $bet['order_no'])->delete();
            } catch (\Exception $xe) {}

            Db::commit();
        } catch (\think\exception\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('撤单失败: ' . $e->getMessage());
        }
        $this->success('撤单成功，已退还 ¥' . number_format($refund, 2));
    }

    /**
     * 手动开奖（后台使用）
     * POST /api/lottery/manualDraw
     * @param type    fc3d/pl3
     * @param period  期号
     * @param numbers 开奖号码 如 "4,2,0"
     */
    public function manualDraw()
    {
        // 简单权限检查: 只允许管理员
        $userId = $this->auth->id;
        $user = \app\common\model\User::get($userId);
        if (!$user || !in_array($user->group_id, [1])) {
            $this->error('无权限操作');
        }

        $type    = $this->request->post('type', 'fc3d');
        $period  = $this->request->post('period', '');
        $numbers = $this->request->post('numbers', '');

        if (empty($period) || empty($numbers)) {
            $this->error('请提供期号和开奖号码');
        }

        // 验证号码格式
        $numArr = explode(',', $numbers);
        if (count($numArr) !== 3) {
            $this->error('开奖号码格式错误，应为3个数字用逗号分隔，如: 4,2,0');
        }

        $lotteryType = isset($this->typeMap[$type]) ? $this->typeMap[$type] : 1;
        $sumValue = array_sum($numArr);

        // 更新或创建开奖记录
        $draw = Db::name('lottery_draw')
            ->where('lottery_type', $lotteryType)
            ->where('period', $period)
            ->find();

        if ($draw) {
            Db::name('lottery_draw')->where('id', $draw['id'])->update([
                'numbers'    => $numbers,
                'sum_value'  => $sumValue,
                'status'     => 1,
                'updatetime' => time(),
            ]);
        } else {
            Db::name('lottery_draw')->insert([
                'lottery_type' => $lotteryType,
                'period'       => $period,
                'numbers'      => $numbers,
                'sum_value'    => $sumValue,
                'draw_time'    => date('Y-m-d H:i:s'),
                'status'       => 1,
                'createtime'   => time(),
                'updatetime'   => time(),
            ]);
        }

        // 结算该期投注
        $result = \app\common\service\DrawService::settleBets($lotteryType, $period, $numbers);

        $this->success('手动开奖成功', [
            'period'     => $period,
            'numbers'    => $numbers,
            'win_count'  => $result['win_count'],
            'lose_count' => $result['lose_count'],
        ]);
    }

    /**
     * 触发自动开奖（可通过URL调用，替代cron）
     * GET /api/lottery/triggerAutoDraw?key=YOUR_SECRET_KEY
     */
    public function triggerAutoDraw()
    {
        // 安全密钥验证（防止外部随意调用）
        $key = $this->request->param('key', '');
        $secretKey = 'db_lottery_auto_draw_2026';
        if ($key !== $secretKey) {
            $this->error('无效的密钥');
        }

        $fc3d = \app\common\service\DrawService::processAutoDraw('fc3d');
        $pl3  = \app\common\service\DrawService::processAutoDraw('pl3');

        $this->success('自动开奖执行完成', [
            'fc3d' => $fc3d,
            'pl3'  => $pl3,
        ]);
    }

    /**
     * 获取轮播图列表
     * GET /api/lottery/banners
     */
    public function banners()
    {
        $list = Db::name('lottery_banner')
            ->where('status', 1)
            ->order('weigh', 'desc')
            ->order('id', 'desc')
            ->field('id, title, image, url')
            ->select();
        $this->success('ok', $list);
    }

    /**
     * 重新结算指定期号（修复错误结算）
     * GET /api/lottery/reSettlePeriod?key=db_lottery_auto_draw_2026&type=fc3d&period=2026139
     */
    public function reSettlePeriod()
    {
        $key = $this->request->param('key', '');
        if ($key !== 'db_lottery_auto_draw_2026') {
            $this->error('无效的密钥');
        }

        $type   = $this->request->param('type', 'fc3d');
        $period = $this->request->param('period', '');
        if (!$period) {
            $this->error('缺少期号参数');
        }

        $lotteryType = isset($this->typeMap[$type]) ? $this->typeMap[$type] : 1;

        // 获取该期开奖号码
        $draw = Db::name('lottery_draw')
            ->where('lottery_type', $lotteryType)
            ->where('period', $period)
            ->where('status', 1)
            ->find();

        if (!$draw || !$draw['numbers']) {
            $this->error('该期号未开奖或不存在');
        }

        // 将所有 status=1(已中奖) 和 status=2(未中奖) 的投注重置为 status=0(待结算)
        // 先扣回已中奖订单的奖金
        $wonBets = Db::name('lottery_bet')
            ->where('lottery_type', $lotteryType)
            ->where('period', $period)
            ->where('status', 1)
            ->where('win_amount', '>', 0)
            ->select();

        foreach ($wonBets as $wb) {
            // 扣回用户余额
            Db::name('user')->where('id', $wb['user_id'])->setDec('money', floatval($wb['win_amount']));
        }

        // 重置所有该期订单
        $resetCount = Db::name('lottery_bet')
            ->where('lottery_type', $lotteryType)
            ->where('period', $period)
            ->whereIn('status', [1, 2])
            ->update(['status' => 0, 'win_amount' => 0, 'updatetime' => time()]);

        // 重新结算
        $result = \app\common\service\DrawService::settleBets($lotteryType, $period, $draw['numbers']);

        $this->success('重新结算完成', [
            'period'      => $period,
            'numbers'     => $draw['numbers'],
            'reset_count' => $resetCount,
            'win_count'   => $result['win_count'],
            'lose_count'  => $result['lose_count'],
        ]);
    }

    /**
     * 批量检查已中奖订单奖金是否正确
     * GET /api/lottery/checkWinAmounts?limit=200
     * 用完请删除此方法!
     */
    public function checkWinAmounts()
    {
        $key = $this->request->param('key', '');
        if ($key !== 'db_lottery_auto_draw_2026') {
            $this->error('无效的密钥');
        }

        $limit = $this->request->param('limit/d', 200);

        // 获取所有已中奖的标准盘订单
        $bets = Db::name('lottery_bet')
            ->where('status', 1)
            ->where('panel_type', 'biaozhun')
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        // 加载赔率
        $oddsRows = Db::name('lottery_odds')->where('status', 1)->select();
        $oddsMap2 = [];
        foreach ($oddsRows as $o) {
            $oddsMap2[$o['play_type']][$o['bet_key']] = floatval($o['odds']);
        }

        // 默认赔率
        $defaultOdds = [
            'sx_zx_fushi' => 900, 'sx_zx_danshi' => 900, 'sx_zx_hezhi' => 900, 'sx_zx_kuadu' => 900,
            'sx_zx3_fushi' => 300, 'sx_zx3_danshi' => 300, 'sx_zx6_fushi' => 150, 'sx_zx6_danshi' => 150,
            'sx_hunhe' => 150, 'sx_zx_hezhi2' => 150, 'sx_zx_baodan' => 150,
            'sx_tx_fushi' => 900, 'sx_tx_danshi' => 900,
            'qe_zx_fushi' => 90, 'qe_zx_danshi' => 90, 'qe_zx_hezhi' => 90, 'qe_zx_kuadu' => 90,
            'qe_zuxuan_fushi' => 45, 'qe_zuxuan_danshi' => 45, 'qe_zuxuan_hezhi' => 45, 'qe_zuxuan_baodan' => 45,
            'he_zx_fushi' => 90, 'he_zx_danshi' => 90, 'he_zx_hezhi' => 90, 'he_zx_kuadu' => 90,
            'he_zuxuan_fushi' => 45, 'he_zuxuan_danshi' => 45, 'he_zuxuan_hezhi' => 45, 'he_zuxuan_baodan' => 45,
            'dingweidan' => 9, 'sx_yimabuding' => 3.3, 'sx_ermabuding' => 16.6,
            'dxds' => 3.94,
            // PL3 三星
            'q3_zx_fushi' => 900, 'q3_zx_danshi' => 900, 'q3_zx3_fushi' => 300, 'q3_zx6_fushi' => 150,
            'z3_zx_fushi' => 900, 'z3_zx_danshi' => 900, 'z3_zx3_fushi' => 300, 'z3_zx6_fushi' => 150,
            'h3_zx_fushi' => 900, 'h3_zx_danshi' => 900, 'h3_zx3_fushi' => 300, 'h3_zx6_fushi' => 150,
        ];

        // bzp_* 分类映射
        $bzpCatMap = [
            'sx_zx_fushi' => 'bzp_sanxing', 'sx_zx_danshi' => 'bzp_sanxing', 'sx_zx_hezhi' => 'bzp_sanxing',
            'sx_zx_kuadu' => 'bzp_sanxing', 'sx_zx3_fushi' => 'bzp_sanxing', 'sx_zx3_danshi' => 'bzp_sanxing',
            'sx_zx6_fushi' => 'bzp_sanxing', 'sx_zx6_danshi' => 'bzp_sanxing', 'sx_hunhe' => 'bzp_sanxing',
            'sx_zx_hezhi2' => 'bzp_sanxing', 'sx_zx_baodan' => 'bzp_sanxing', 'sx_tx_fushi' => 'bzp_sanxing',
            'sx_tx_danshi' => 'bzp_sanxing', 'sx_hzweishu' => 'bzp_sanxing',
            'qe_zx_fushi' => 'bzp_qianer', 'qe_zx_danshi' => 'bzp_qianer', 'qe_zx_hezhi' => 'bzp_qianer',
            'qe_zx_kuadu' => 'bzp_qianer', 'qe_zuxuan_fushi' => 'bzp_qianer', 'qe_zuxuan_danshi' => 'bzp_qianer',
            'qe_zuxuan_hezhi' => 'bzp_qianer', 'qe_zuxuan_baodan' => 'bzp_qianer',
            'he_zx_fushi' => 'bzp_houer', 'he_zx_danshi' => 'bzp_houer', 'he_zx_hezhi' => 'bzp_houer',
            'he_zx_kuadu' => 'bzp_houer', 'he_zuxuan_fushi' => 'bzp_houer', 'he_zuxuan_danshi' => 'bzp_houer',
            'he_zuxuan_hezhi' => 'bzp_houer', 'he_zuxuan_baodan' => 'bzp_houer',
            'dingweidan' => 'bzp_dingweidan',
            'sx_yimabuding' => 'bzp_budindan', 'sx_ermabuding' => 'bzp_budindan',
            'dxds' => 'bzp_dxds',
            // PL3
            'q3_zx_fushi' => 'bzp_sanxing', 'q3_zx3_fushi' => 'bzp_sanxing', 'q3_zx6_fushi' => 'bzp_sanxing',
            'z3_zx_fushi' => 'bzp_sanxing', 'z3_zx3_fushi' => 'bzp_sanxing', 'z3_zx6_fushi' => 'bzp_sanxing',
            'h3_zx_fushi' => 'bzp_sanxing', 'h3_zx3_fushi' => 'bzp_sanxing', 'h3_zx6_fushi' => 'bzp_sanxing',
        ];

        $errors = [];
        $okCount = 0;

        foreach ($bets as $bet) {
            $playType = $bet['play_type'];
            $betCount = intval($bet['bet_count']);
            $betAmount = floatval($bet['bet_amount']);
            $totalAmount = floatval($bet['total_amount']);
            $currentWin = floatval($bet['win_amount']);

            // 正确的每注金额
            $unitAmt = ($betCount > 0 && $totalAmount > 0) ? ($totalAmount / $betCount) : $betAmount;

            // 查赔率
            $odds = 0;
            $cat = isset($bzpCatMap[$playType]) ? $bzpCatMap[$playType] : '';
            if ($cat && isset($oddsMap2[$cat][$playType])) {
                $odds = $oddsMap2[$cat][$playType];
            }
            if ($odds <= 0 && isset($defaultOdds[$playType])) {
                $odds = $defaultOdds[$playType];
            }
            if ($odds <= 0) {
                $odds = floatval($bet['odds']);
            }

            // 应得奖金
            $correctWin = $unitAmt * $odds;

            $diff = $correctWin - $currentWin;
            if (abs($diff) > 0.01) {
                $errors[] = [
                    'id'            => $bet['id'],
                    'period'        => $bet['period'],
                    'play_type'     => $playType,
                    'lottery_type'  => $bet['lottery_type'],
                    'bet_count'     => $betCount,
                    'bet_amount'    => $betAmount,
                    'total_amount'  => $totalAmount,
                    'unit_amt'      => round($unitAmt, 4),
                    'odds'          => $odds,
                    'current_win'   => $currentWin,
                    'correct_win'   => round($correctWin, 2),
                    'diff'          => round($diff, 2),
                ];
            } else {
                $okCount++;
            }
        }

        $this->success('检查完成', [
            'total_checked' => count($bets),
            'ok_count'      => $okCount,
            'error_count'   => count($errors),
            'errors'        => $errors,
        ]);
    }
}
