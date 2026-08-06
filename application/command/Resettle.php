<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\Db;
use app\common\service\DrawService;

/**
 * 补结算指定期号的投注
 *
 * 用法:
 *   php think Resettle 26145         补结算排列三 26145 期
 *   php think Resettle 2026145       补结算福彩3D 2026145 期
 *   php think Resettle all           补结算所有已开奖但有未结算/误判订单的期号
 */
class Resettle extends Command
{
    protected function configure()
    {
        $this->setName('Resettle')
            ->setDescription('补结算指定期号（修正误判的中奖/未中奖订单）')
            ->addArgument('period', Argument::REQUIRED, '期号(如26145)或"all"');
    }

    protected function execute(Input $input, Output $output)
    {
        $period = $input->getArgument('period');
        $output->writeln('');
        $output->writeln('<info>' . date('Y-m-d H:i:s') . ' ===== 补结算任务启动 =====</info>');

        if ($period === 'all') {
            // 找所有已开奖(status=1)且仍有 status=2 投注的期号
            $draws = Db::name('lottery_draw')->where('status', 1)->select();
            foreach ($draws as $draw) {
                $this->resettlePeriod($draw, $output);
            }
        } else {
            $draws = Db::name('lottery_draw')
                ->where('period', $period)
                ->where('status', 1)
                ->select();
            if (empty($draws)) {
                $output->writeln("<error>找不到已开奖的期号: {$period}</error>");
                return;
            }
            foreach ($draws as $draw) {
                $this->resettlePeriod($draw, $output);
            }
        }

        $output->writeln('');
        $output->writeln('<info>' . date('Y-m-d H:i:s') . ' ===== 补结算完成 =====</info>');
    }

    protected function resettlePeriod($draw, Output $output)
    {
        $lotteryType = $draw['lottery_type'];
        $period = $draw['period'];
        $numbers = $draw['numbers'];
        $typeName = $lotteryType == 1 ? '福彩3D' : '排列三';

        $output->writeln('');
        $output->writeln(">>> [{$typeName}] 期号: {$period}, 开奖号码: {$numbers}");

        if (empty($numbers)) {
            $output->writeln('  <comment>跳过: 无开奖号码</comment>');
            return;
        }

        $numbersArr = explode(',', $numbers);
        $sumValue = array_sum(array_map('intval', $numbersArr));

        // 加载赔率
        $oddsMap = [];      // 扁平fallback
        $oddsMap2 = [];     // 二级: play_type => bet_key => odds
        $oddsRows = Db::name('lottery_odds')
            ->where('lottery_type', $lotteryType)
            ->where('status', 1)
            ->select();
        foreach ($oddsRows as $o) {
            $oddsMap[$o['bet_key']] = floatval($o['odds']);
            if (!isset($oddsMap2[$o['play_type']])) $oddsMap2[$o['play_type']] = [];
            $oddsMap2[$o['play_type']][$o['bet_key']] = floatval($o['odds']);
        }

        // 找该期 status=1(已中奖) 和 status=2(未中奖) 的订单，全部重新核算
        $bets = Db::name('lottery_bet')
            ->where('lottery_type', $lotteryType)
            ->where('period', $period)
            ->whereIn('status', [1, 2])
            ->select();

        $fixCount = 0;
        $fixAmount = 0;

        foreach ($bets as $bet) {
            $betContent = json_decode($bet['bet_content'], true);
            if (!$betContent) continue;

            // 用修复后的 calcWinAmount 重新计算正确奖金
            $correctWin = DrawService::calcWinAmount($bet, $betContent, $numbersArr, $sumValue, $oddsMap, $oddsMap2);
            $correctWin = round($correctWin, 2);
            $oldWin = round(floatval($bet['win_amount']), 2);
            $oldStatus = intval($bet['status']);

            // 计算需要补发的差额（正确金额 - 已发金额）
            $diff = round($correctWin - $oldWin, 2);

            // 仅当正确金额与现状不一致时才修正
            $needFix = false;
            if ($correctWin > 0 && ($oldStatus != 1 || abs($diff) > 0.001)) {
                $needFix = true;
            }

            if ($needFix) {
                Db::startTrans();
                try {
                    Db::name('lottery_bet')->where('id', $bet['id'])->update([
                        'status'     => 1,
                        'win_amount' => $correctWin,
                        'updatetime' => time(),
                    ]);
                    // 补差额（diff>0 少发了要补; diff<0 多发了要扣回）
                    if ($diff > 0) {
                        \app\common\model\User::where('id', $bet['user_id'])->setInc('money', $diff);
                    } elseif ($diff < 0) {
                        \app\common\model\User::where('id', $bet['user_id'])->setDec('money', abs($diff));
                    }
                    Db::commit();

                    $fixCount++;
                    $fixAmount += $diff;
                    $tag = $diff > 0 ? "补发 +¥{$diff}" : ($diff < 0 ? "扣回 ¥" . abs($diff) : "仅状态更新");
                    $output->writeln("  <info>✓ 修正 ID={$bet['id']} 用户{$bet['user_id']} play={$bet['play_type']} | 原={$oldWin} → 正确={$correctWin} | {$tag}</info>");
                } catch (\Exception $e) {
                    Db::rollback();
                    $output->writeln("  <error>✗ 修正失败 ID={$bet['id']}: {$e->getMessage()}</error>");
                }
            }
        }

        if ($fixCount > 0) {
            $output->writeln("  <info>本期修正: {$fixCount} 注, 累计补发: ¥" . number_format($fixAmount, 2) . "</info>");
        } else {
            $output->writeln('  <comment>本期无需修正</comment>');
        }
    }
}
