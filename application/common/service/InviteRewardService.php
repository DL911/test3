<?php
namespace app\common\service;

use think\Db;

/** Independent, direct-invite rewards. Caller owns the bet transaction. */
class InviteRewardService
{
    public static function createForBet($userId, $orderNo, $amount, $lotteryType)
    {
        $user = Db::name('user')->where('id', $userId)->field('id,pid,account_source')->find();
        if (!$user || $user['account_source'] !== 'invite') return [];
        $config = Db::name('invite_reward_config')->where('id', 1)->where('status', 1)->find();
        if (!$config || $amount < floatval($config['min_bet']) || $amount <= 0) return [];
        $now = time();
        $unlock = date('Y-m-d', strtotime('tomorrow', $now));
        $rows = [];
        $rates = [['id' => $userId, 'type' => 'self', 'reward_type' => 'invite_self', 'rate' => $config['self_rate']]];
        if (intval($user['pid']) > 0) {
            $parent = Db::name('user')->where('id', $user['pid'])->field('id,account_source,invite_rebate_rate')->find();
            if ($parent && $parent['account_source'] === 'invite') {
                $rates[] = ['id' => $parent['id'], 'type' => 'parent', 'reward_type' => 'invite_parent',
                    'rate' => floatval($parent['invite_rebate_rate']) > 0 ? $parent['invite_rebate_rate'] : $config['parent_rate']];
            }
        }
        foreach ($rates as $item) {
            $rate = floatval($item['rate']);
            $reward = round($amount * $rate, 2);
            if ($rate <= 0 || $reward <= 0) continue;
            $rows[] = ['user_id' => $item['id'], 'type' => $item['type'], 'reward_type' => $item['reward_type'],
                'source_user_id' => $userId, 'level_distance' => $item['type'] === 'self' ? 0 : 1,
                'bet_order_no' => $orderNo, 'bet_amount' => $amount, 'xima_rate' => $rate,
                'beneficiary_rate' => $rate, 'child_rate' => 0, 'xima_amount' => $reward,
                'unlock_date' => $unlock, 'status' => 0, 'createtime' => $now, 'updatetime' => $now];
        }
        if ($rows && Db::name('xima_record')->insertAll($rows) !== count($rows)) throw new \RuntimeException('邀请奖励生成失败');
        return $rows;
    }
}
