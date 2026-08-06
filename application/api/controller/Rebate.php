<?php
namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 流水返利 API
 */
class Rebate extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 获取返利配置（阶梯比例展示）
     * GET /api/rebate/config
     */
    public function config()
    {
        $list = Db::name('rebate_config')
            ->where('status', 1)
            ->order('min_amount', 'asc')
            ->select();

        $this->success('OK', $list);
    }

    /**
     * 查询我的返利概况
     * GET /api/rebate/summary
     * 返回：今日流水、可领取返利、历史已领
     */
    public function summary()
    {
        $userId = $this->auth->id;
        $today = date('Y-m-d');

        // 今日投注流水
        $todayStart = strtotime($today);
        $todayEnd = $todayStart + 86400;
        $todayBet = Db::name('lottery_bet')
            ->where('user_id', $userId)
            ->where('createtime', '>=', $todayStart)
            ->where('createtime', '<', $todayEnd)
            ->sum('total_amount');

        // 昨日流水（用于计算昨日可领返利）
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yStart = strtotime($yesterday);
        $yEnd = $yStart + 86400;
        $yesterdayBet = Db::name('lottery_bet')
            ->where('user_id', $userId)
            ->where('createtime', '>=', $yStart)
            ->where('createtime', '<', $yEnd)
            ->sum('total_amount');

        // 计算昨日应得返利
        $yesterdayRate = $this->getRate($yesterdayBet);
        $yesterdayRebate = round($yesterdayBet * $yesterdayRate, 2);

        // 检查昨日是否已领取
        $claimed = Db::name('rebate_record')
            ->where('user_id', $userId)
            ->where('period_start', $yesterday)
            ->where('period_end', $yesterday)
            ->find();

        // 历史已领取总额
        $totalClaimed = Db::name('rebate_record')
            ->where('user_id', $userId)
            ->where('status', 1)
            ->sum('rebate_amount');

        // 返利阶梯
        $tiers = Db::name('rebate_config')
            ->where('status', 1)
            ->order('min_amount', 'asc')
            ->select();

        $this->success('OK', [
            'today_bet'        => round($todayBet, 2),
            'today_rate'       => $this->getRate($todayBet),
            'yesterday_bet'    => round($yesterdayBet, 2),
            'yesterday_rebate' => $yesterdayRebate,
            'yesterday_rate'   => $yesterdayRate,
            'can_claim'        => $yesterdayRebate > 0 && !$claimed,
            'already_claimed'  => !!$claimed,
            'total_claimed'    => round($totalClaimed, 2),
            'tiers'            => $tiers,
        ]);
    }

    /**
     * 领取昨日返利
     * POST /api/rebate/claim
     */
    public function claim()
    {
        $userId = $this->auth->id;
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yStart = strtotime($yesterday);
        $yEnd = $yStart + 86400;

        // 检查是否已领取
        $exists = Db::name('rebate_record')
            ->where('user_id', $userId)
            ->where('period_start', $yesterday)
            ->where('period_end', $yesterday)
            ->find();
        if ($exists) {
            $this->error('昨日返利已领取，请勿重复操作');
        }

        // 计算昨日流水
        $betAmount = Db::name('lottery_bet')
            ->where('user_id', $userId)
            ->where('createtime', '>=', $yStart)
            ->where('createtime', '<', $yEnd)
            ->sum('total_amount');

        $rate = $this->getRate($betAmount);
        $rebateAmount = round($betAmount * $rate, 2);

        if ($rebateAmount <= 0) {
            $this->error('昨日流水不足，暂无可领取返利');
        }

        Db::startTrans();
        try {
            // 写入记录
            Db::name('rebate_record')->insert([
                'user_id'      => $userId,
                'period_start' => $yesterday,
                'period_end'   => $yesterday,
                'bet_amount'   => $betAmount,
                'rebate_rate'  => $rate,
                'rebate_amount'=> $rebateAmount,
                'status'       => 1,
                'claim_time'   => time(),
                'createtime'   => time(),
                'updatetime'   => time(),
            ]);

            // 加余额
            \app\common\model\User::where('id', $userId)->setInc('money', $rebateAmount);

            Db::commit();
            $this->success('领取成功，¥' . $rebateAmount . ' 已到账', [
                'rebate_amount' => $rebateAmount,
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('领取失败: ' . $e->getMessage());
        }
    }

    /**
     * 返利领取历史
     * GET /api/rebate/history?page=1
     */
    public function history()
    {
        $userId = $this->auth->id;
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 20);

        $list = Db::name('rebate_record')
            ->where('user_id', $userId)
            ->order('createtime', 'desc')
            ->page($page, $limit)
            ->select();

        foreach ($list as &$item) {
            $item['claim_date'] = $item['claim_time'] ? date('Y-m-d H:i', $item['claim_time']) : '--';
            $item['period'] = $item['period_start'];
        }

        $total = Db::name('rebate_record')
            ->where('user_id', $userId)
            ->count();

        $this->success('OK', ['list' => $list, 'total' => $total]);
    }

    /**
     * 根据投注金额匹配返利比例
     */
    private function getRate($amount)
    {
        if ($amount <= 0) return 0;

        $tiers = Db::name('rebate_config')
            ->where('status', 1)
            ->order('min_amount', 'desc')
            ->select();

        foreach ($tiers as $tier) {
            if ($amount >= $tier['min_amount']) {
                if ($tier['max_amount'] <= 0 || $amount <= $tier['max_amount']) {
                    return floatval($tier['rebate_rate']);
                }
            }
        }
        return 0;
    }
}
