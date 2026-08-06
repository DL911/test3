<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 走势分析API
 */
class Trend extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = '*';

    /**
     * 获取开奖历史记录（走势数据源）
     * GET /api/trend/history?type=1&limit=50
     * type: 1=福彩3D, 2=排列三
     */
    public function history()
    {
        $type  = $this->request->param('type/d', 1);
        $limit = $this->request->param('limit/d', 50);
        if ($limit > 200) $limit = 200;

        $list = Db::name('lottery_draw')
            ->where('lottery_type', $type)
            ->where('status', 1)
            ->where('numbers', '<>', '')
            ->order('period', 'desc')
            ->limit($limit)
            ->select();

        // 按期号升序（走势图从左到右）
        $list = array_reverse($list);

        $result = [];
        foreach ($list as $row) {
            $nums = explode(',', $row['numbers']);
            $b = intval($nums[0] ?? 0);
            $s = intval($nums[1] ?? 0);
            $g = intval($nums[2] ?? 0);
            $sum = $b + $s + $g;

            $result[] = [
                'period'  => $row['period'],
                'numbers' => $row['numbers'],
                'b' => $b, 's' => $s, 'g' => $g,
                'sum'     => $sum,
                'big_small' => $sum >= 14 ? '大' : '小',
                'odd_even'  => $sum % 2 === 1 ? '单' : '双',
                'b_bs' => $b >= 5 ? '大' : '小',
                's_bs' => $s >= 5 ? '大' : '小',
                'g_bs' => $g >= 5 ? '大' : '小',
                'b_oe' => $b % 2 === 1 ? '单' : '双',
                's_oe' => $s % 2 === 1 ? '单' : '双',
                'g_oe' => $g % 2 === 1 ? '单' : '双',
                'draw_time' => $row['draw_time'],
            ];
        }

        $this->success('OK', $result);
    }

    /**
     * 长龙排行
     * GET /api/trend/streaks?type=1
     */
    public function streaks()
    {
        $type = $this->request->param('type/d', 1);

        $list = Db::name('lottery_draw')
            ->where('lottery_type', $type)
            ->where('status', 1)
            ->where('numbers', '<>', '')
            ->order('period', 'desc')
            ->limit(100)
            ->select();

        // 计算各项连续出现次数
        $items = [
            '总和大' => function($r) { $n = explode(',', $r['numbers']); return array_sum(array_map('intval',$n)) >= 14; },
            '总和小' => function($r) { $n = explode(',', $r['numbers']); return array_sum(array_map('intval',$n)) < 14; },
            '总和单' => function($r) { $n = explode(',', $r['numbers']); return array_sum(array_map('intval',$n)) % 2 === 1; },
            '总和双' => function($r) { $n = explode(',', $r['numbers']); return array_sum(array_map('intval',$n)) % 2 === 0; },
            '百位大' => function($r) { $n = explode(',', $r['numbers']); return intval($n[0]) >= 5; },
            '百位小' => function($r) { $n = explode(',', $r['numbers']); return intval($n[0]) < 5; },
            '十位大' => function($r) { $n = explode(',', $r['numbers']); return intval($n[1]) >= 5; },
            '十位小' => function($r) { $n = explode(',', $r['numbers']); return intval($n[1]) < 5; },
            '个位大' => function($r) { $n = explode(',', $r['numbers']); return intval($n[2]) >= 5; },
            '个位小' => function($r) { $n = explode(',', $r['numbers']); return intval($n[2]) < 5; },
            '百位单' => function($r) { $n = explode(',', $r['numbers']); return intval($n[0]) % 2 === 1; },
            '百位双' => function($r) { $n = explode(',', $r['numbers']); return intval($n[0]) % 2 === 0; },
            '十位单' => function($r) { $n = explode(',', $r['numbers']); return intval($n[1]) % 2 === 1; },
            '十位双' => function($r) { $n = explode(',', $r['numbers']); return intval($n[1]) % 2 === 0; },
            '个位单' => function($r) { $n = explode(',', $r['numbers']); return intval($n[2]) % 2 === 1; },
            '个位双' => function($r) { $n = explode(',', $r['numbers']); return intval($n[2]) % 2 === 0; },
        ];

        $streaks = [];
        foreach ($items as $name => $fn) {
            $count = 0;
            foreach ($list as $row) {
                if ($fn($row)) {
                    $count++;
                } else {
                    break;
                }
            }
            if ($count >= 2) {
                $streaks[] = ['name' => $name, 'count' => $count];
            }
        }

        // 按连续次数降序
        usort($streaks, function($a, $b) { return $b['count'] - $a['count']; });

        $this->success('OK', $streaks);
    }

    /**
     * 对奖查询
     * GET /api/trend/check?type=1&numbers=1,2,3
     */
    public function check()
    {
        $type    = $this->request->param('type/d', 1);
        $numbers = $this->request->param('numbers', '');

        if (empty($numbers)) $this->error('请输入号码');
        $myNums = explode(',', $numbers);
        if (count($myNums) !== 3) $this->error('请输入3位号码');

        // 获取最近30期
        $list = Db::name('lottery_draw')
            ->where('lottery_type', $type)
            ->where('status', 1)
            ->where('numbers', '<>', '')
            ->order('period', 'desc')
            ->limit(30)
            ->select();

        $results = [];
        foreach ($list as $row) {
            $drawNums = explode(',', $row['numbers']);
            $match = 0;
            $matchDetail = [];

            // 直选匹配
            for ($i = 0; $i < 3; $i++) {
                if (isset($myNums[$i]) && isset($drawNums[$i]) && trim($myNums[$i]) === trim($drawNums[$i])) {
                    $match++;
                    $matchDetail[] = ['百位','十位','个位'][$i];
                }
            }

            // 组选匹配
            $mySort = $myNums; sort($mySort);
            $drawSort = $drawNums; sort($drawSort);
            $groupMatch = ($mySort === $drawSort);

            $results[] = [
                'period'       => $row['period'],
                'numbers'      => $row['numbers'],
                'draw_time'    => $row['draw_time'],
                'direct_match' => $match,
                'match_detail' => $matchDetail,
                'group_match'  => $groupMatch,
                'is_win'       => $match === 3,
            ];
        }

        $this->success('OK', $results);
    }
}
