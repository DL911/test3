<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use app\common\service\DrawService;

/**
 * 自动开奖定时任务 (带轮询重试)
 *
 * 用法:
 *   php think AutoDraw                    一次性执行（适合cron每5分钟调一次）
 *   php think AutoDraw --poll             轮询模式：失败自动重试，直到成功或超时
 *   php think AutoDraw --poll --max=10    最多重试10次
 *   php think AutoDraw --poll --wait=120  每次重试间隔120秒
 *
 * crontab建议 (每天21:20启动，轮询模式自动重试):
 *   20 21 * * * cd /path/to/project && php think AutoDraw --poll --max=8 --wait=180
 */
class AutoDraw extends Command
{
    protected function configure()
    {
        $this->setName('AutoDraw')
            ->setDescription('自动获取开奖结果并结算投注（支持轮询重试）')
            ->addOption('poll', 'p', Option::VALUE_NONE, '启用轮询模式，失败自动重试')
            ->addOption('max', 'm', Option::VALUE_OPTIONAL, '最大重试次数', 6)
            ->addOption('wait', 'w', Option::VALUE_OPTIONAL, '重试间隔秒数', 180);
    }

    protected function execute(Input $input, Output $output)
    {
        $poll     = $input->getOption('poll');
        $maxRetry = intval($input->getOption('max'));
        $waitSecs = intval($input->getOption('wait'));

        $output->writeln('');
        $output->writeln('<info>' . date('Y-m-d H:i:s') . ' ===== 自动开奖任务启动 =====</info>');
        if ($poll) {
            $output->writeln("<comment>轮询模式: 最多重试 {$maxRetry} 次, 间隔 {$waitSecs} 秒</comment>");
        }

        $types = ['fc3d', 'pl3'];
        $pending = $types; // 待处理的彩种
        $results = [];
        $attempt = 0;

        do {
            $attempt++;
            $stillPending = [];

            foreach ($pending as $type) {
                $name = $type === 'fc3d' ? '福彩3D' : '排列三';
                $output->writeln('');
                $output->writeln(">>> [{$name}] 第{$attempt}次尝试...");

                $result = DrawService::processAutoDraw($type);

                if ($result['code'] === 1) {
                    // 成功
                    $output->writeln("<info>  ✓ {$result['msg']}</info>");
                    $results[$type] = $result;
                } else {
                    // 失败
                    $output->writeln("<error>  ✗ {$result['msg']}</error>");
                    $stillPending[] = $type;
                    $results[$type] = $result;
                }
            }

            $pending = $stillPending;

            // 判断是否需要继续重试
            if (empty($pending)) {
                $output->writeln('');
                $output->writeln('<info>所有彩种处理完成!</info>');
                break;
            }

            if (!$poll) {
                // 非轮询模式，直接退出
                $failNames = array_map(function($t) { return $t === 'fc3d' ? '福彩3D' : '排列三'; }, $pending);
                $output->writeln('');
                $output->writeln('<error>以下彩种获取失败: ' . implode(', ', $failNames) . '</error>');
                $output->writeln('<comment>提示: 使用 --poll 参数启用自动重试</comment>');
                break;
            }

            if ($attempt >= $maxRetry) {
                $failNames = array_map(function($t) { return $t === 'fc3d' ? '福彩3D' : '排列三'; }, $pending);
                $output->writeln('');
                $output->writeln("<error>已达最大重试次数({$maxRetry})，以下彩种仍失败: " . implode(', ', $failNames) . '</error>');
                $output->writeln('<comment>请检查数据源或手动开奖</comment>');
                break;
            }

            // 等待后重试
            $failNames = array_map(function($t) { return $t === 'fc3d' ? '福彩3D' : '排列三'; }, $pending);
            $output->writeln('');
            $output->writeln("<comment>等待 {$waitSecs} 秒后重试 [" . implode(', ', $failNames) . "] ... (第{$attempt}/{$maxRetry}次)</comment>");
            sleep($waitSecs);

        } while (true);

        // 汇总结果
        $output->writeln('');
        $output->writeln('<info>' . date('Y-m-d H:i:s') . ' ===== 开奖任务结束 =====</info>');
        $output->writeln('结果汇总:');
        foreach ($results as $type => $r) {
            $name = $type === 'fc3d' ? '福彩3D' : '排列三';
            $status = $r['code'] === 1 ? '<info>✓ 成功</info>' : '<error>✗ 失败</error>';
            $output->writeln("  {$name}: {$status} - {$r['msg']}");
        }
    }
}
