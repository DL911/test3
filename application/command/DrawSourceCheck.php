<?php

namespace app\command;

use app\common\service\DrawService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;

/** 只读检查开奖来源；不连接数据库，不写开奖记录或余额。 */
class DrawSourceCheck extends Command
{
    protected function configure()
    {
        $this->setName('DrawSourceCheck')
            ->setDescription('只读检查官方、国内采集节点及旧数据源')
            ->addArgument('type', Argument::REQUIRED, 'fc3d 或 pl3')
            ->addArgument('period', Argument::OPTIONAL, '期号，可留空查看来源最新一期');
    }

    protected function execute(Input $input, Output $output)
    {
        $type = (string)$input->getArgument('type');
        $period = (string)$input->getArgument('period');
        if (!in_array($type, ['fc3d', 'pl3'], true)) {
            $output->writeln('<error>彩种只能为 fc3d 或 pl3</error>');
            return 1;
        }
        $methods = [
            '官方接口' => ['fetchFromOfficial', $period ?: null],
            '国内签名节点' => ['fetchFromRelay', $period ?: null],
            '旧 huiniao' => ['fetchFromHuiniao', null],
            '旧 cz89' => ['fetchFromCz89', null],
        ];
        foreach ($methods as $label => $entry) {
            try {
                $method = $entry[0];
                $result = in_array($method, ['fetchFromOfficial', 'fetchFromRelay'], true)
                    ? DrawService::$method($type, $entry[1]) : DrawService::$method($type);
                if (!$result) {
                    $output->writeln("{$label}: 无有效结果");
                    continue;
                }
                $match = $period && (string)$result['period'] !== $period ? '（与指定期号不符）' : '';
                $output->writeln("{$label}: 期号={$result['period']} 号码={$result['numbers']} 开奖时间={$result['draw_time']}{$match}");
            } catch (\Exception $e) {
                $output->writeln("{$label}: 请求失败：" . $e->getMessage());
            }
        }
    }
}
