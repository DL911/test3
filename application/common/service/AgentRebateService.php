<?php

namespace app\common\service;

use think\Db;

/**
 * 每笔投注的代理层级返水服务。
 * 调用方必须已经开启数据库事务。
 */
class AgentRebateService
{
    const TYPE_SELF = 'self';
    const TYPE_LEVEL_DIFF = 'level_diff';

    /**
     * 根据投注时的用户层级比例快照生成奖励记录。
     */
    public static function createForBet($userId, $orderNo, $betAmount, $lotteryType)
    {
        // 历史用户 agent_level=0，与新代理体系完全隔离。
        $bettorLevel = intval(Db::name('user')->where('id', $userId)->value('agent_level'));
        if ($bettorLevel <= 0) {
            return [];
        }
        $config = Db::name('agent_config')->where('id', 1)->where('status', 1)->find();
        if (!$config || $config['registration_mode'] !== 'agent_only') {
            return [];
        }

        // 新模式明确为每笔投注产生返水，不再依赖旧洗码配置、阶梯或最低流水。
        if ($betAmount <= 0) {
            return [];
        }

        $maxRate = floatval($config['max_rebate_rate']);
        $chain = self::loadChain($userId, $maxRate);
        if (!$chain) {
            return [];
        }

        $now = time();
        $unlockDate = date('Y-m-d', strtotime('tomorrow', $now));
        $records = [];

        $bettor = $chain[0];
        $selfRate = floatval($bettor['rate']);
        self::appendRecord($records, [
            'user_id' => intval($bettor['id']),
            'type' => 'self',
            'reward_type' => self::TYPE_SELF,
            'source_user_id' => intval($bettor['id']),
            'level_distance' => 0,
            'bet_order_no' => $orderNo,
            'bet_amount' => $betAmount,
            'xima_rate' => $selfRate,
            'beneficiary_rate' => $selfRate,
            'child_rate' => 0,
            'xima_amount' => round($betAmount * $selfRate, 2),
            'unlock_date' => $unlockDate,
            'status' => 0,
            'createtime' => $now,
            'updatetime' => $now,
        ]);

        for ($i = 1, $count = count($chain); $i < $count; $i++) {
            $parent = $chain[$i];
            $child = $chain[$i - 1];
            $parentRate = floatval($parent['rate']);
            $childRate = floatval($child['rate']);
            if ($parentRate + 0.0000001 < $childRate) {
                throw new \RuntimeException('代理链比例倒挂，无法生成返水');
            }
            $diffRate = round($parentRate - $childRate, 6);
            self::appendRecord($records, [
                'user_id' => intval($parent['id']),
                'type' => 'parent',
                'reward_type' => self::TYPE_LEVEL_DIFF,
                'source_user_id' => intval($bettor['id']),
                'level_distance' => $i,
                'bet_order_no' => $orderNo,
                'bet_amount' => $betAmount,
                'xima_rate' => $diffRate,
                'beneficiary_rate' => $parentRate,
                'child_rate' => $childRate,
                'xima_amount' => round($betAmount * $diffRate, 2),
                'unlock_date' => $unlockDate,
                'status' => 0,
                'createtime' => $now,
                'updatetime' => $now,
            ]);
        }

        if ($records) {
            $inserted = Db::name('xima_record')->insertAll($records);
            if ($inserted !== count($records)) {
                throw new \RuntimeException('返水奖励记录生成失败');
            }
        }
        return $records;
    }

    private static function appendRecord(&$records, $record)
    {
        if ($record['xima_rate'] > 0 && $record['xima_amount'] > 0) {
            $records[] = $record;
        }
    }

    /**
     * 返回从投注人到顶级代理的链。self_rebate_rate 在 v2 中代表层级总返水比例。
     */
    private static function loadChain($userId, $maxRate)
    {
        $chain = [];
        $seen = [];
        $currentId = intval($userId);
        $depth = 0;
        while ($currentId > 0 && $depth < 100) {
            if (isset($seen[$currentId])) {
                throw new \RuntimeException('检测到循环代理关系');
            }
            $seen[$currentId] = true;
            $row = Db::name('user')->where('id', $currentId)
                ->lock(true)
                ->field('id,pid,agent_level,self_rebate_rate,status')->find();
            // 上级被禁用只限制登录和操作，不应导致整个下级投注失败；奖励继续冻结在其账户，启用后可领取。
            if (!$row || intval($row['agent_level']) <= 0) {
                break;
            }
            $rate = min($maxRate, max(0, floatval($row['self_rebate_rate'])));
            $level = intval($row['agent_level']);
            if ($chain) {
                $child = $chain[count($chain) - 1];
                if ($level !== intval($child['level']) - 1) {
                    throw new \RuntimeException('代理层级关系不连续，无法生成返水');
                }
            }
            $chain[] = ['id' => intval($row['id']), 'pid' => intval($row['pid']), 'level' => $level, 'rate' => $rate];
            $currentId = intval($row['pid']);
            $depth++;
        }
        if ($depth >= 100 && $currentId > 0) {
            throw new \RuntimeException('代理层级超过安全上限');
        }
        if ($chain) {
            $top = $chain[count($chain) - 1];
            if (intval($top['level']) !== 1 || intval($top['pid']) !== 0) {
                throw new \RuntimeException('代理链未连接到新模式顶级代理');
            }
        }
        return $chain;
    }
}
