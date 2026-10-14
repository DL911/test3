<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class FixCommission extends Command
{
    protected function configure()
    {
        $this->setName('FixCommission')
            ->setDescription('已停用：旧代理佣金修复命令');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->error('该命令已停用：系统已切换到新版逐笔投注、次日领取的代理返水模式。');
        $output->writeln('未读取数据库，未修改任何用户余额或历史佣金记录。');
        return 1;
    }
}
