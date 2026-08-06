<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class FixCommission extends Command
{
    protected function configure()
    {
        $this->setName('FixCommission')
            ->setDescription('修复重复代理佣金(删除win_commission并扣回余额)');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("===== 修复重复代理佣金 (win_commission) =====\n");

        $records = Db::name('lottery_commission')
            ->where('type', 'win_commission')
            ->field('id, user_id, amount, createtime, remark')
            ->select();

        if (empty($records)) {
            $output->writeln("未找到任何 win_commission 记录，无需修复。");
            return;
        }

        $totalRecords = count($records);
        $totalAmount = 0;
        $userSums = [];

        foreach ($records as $r) {
            $uid = $r['user_id'];
            $amt = floatval($r['amount']);
            $totalAmount += $amt;
            if (!isset($userSums[$uid])) $userSums[$uid] = 0;
            $userSums[$uid] += $amt;
        }

        $output->writeln("发现 {$totalRecords} 条重复佣金记录");
        $output->writeln("涉及 " . count($userSums) . " 个用户");
        $output->writeln("总金额: ¥" . round($totalAmount, 2) . "\n");

        $output->writeln("--- 各用户明细 ---");
        foreach ($userSums as $uid => $sum) {
            $user = Db::name('user')->where('id', $uid)->field('id, username, nickname, money')->find();
            $name = $user ? ($user['nickname'] ?: $user['username']) : "未知用户";
            $balance = $user ? $user['money'] : 0;
            $output->writeln("  用户#{$uid} ({$name}) 余额:¥{$balance} → 扣回:¥" . round($sum, 2));
        }

        $output->writeln("\n开始执行扣款...");

        Db::startTrans();
        try {
            foreach ($userSums as $uid => $sum) {
                $sum = round($sum, 2);
                Db::name('user')->where('id', $uid)->setDec('money', $sum);
                $output->writeln("  用户#{$uid} 余额扣回 ¥{$sum}");
            }

            $deleted = Db::name('lottery_commission')->where('type', 'win_commission')->delete();
            $output->writeln("\n  删除 {$deleted} 条 win_commission 记录");

            Db::commit();
            $output->writeln("\n===== 修复完成 =====");
            $output->writeln("共扣回 ¥" . round($totalAmount, 2) . "，删除 {$deleted} 条重复记录");
        } catch (\Exception $e) {
            Db::rollback();
            $output->error("执行失败，已回滚: " . $e->getMessage());
        }
    }
}
