<?php
/**
 * 修复历史重复代理佣金
 * 
 * 用法: php think FixCommission [dry]
 *       dry - 只统计不执行
 */

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

// 不走 think 命令模式，直接独立运行
// 手动初始化 ThinkPHP 环境
define('APP_PATH', dirname(__DIR__) . '/application/');
define('DS', DIRECTORY_SEPARATOR);

// 加载基础文件
require dirname(__DIR__) . '/thinkphp/base.php';

// 初始化应用但不执行路由
\think\App::initCommon();

$dryRun = isset($argv[1]) && $argv[1] === 'dry';

echo "===== 修复重复代理佣金 (win_commission) =====\n";
echo $dryRun ? "[DRY-RUN 模式，不实际执行]\n\n" : "[执行模式]\n\n";

// 查出所有 win_commission 记录，按 user_id 汇总
$records = Db::name('lottery_commission')
    ->where('type', 'win_commission')
    ->field('id, user_id, amount, createtime, remark')
    ->select();

if (empty($records)) {
    echo "未找到任何 win_commission 记录，无需修复。\n";
    exit(0);
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

echo "发现 {$totalRecords} 条重复佣金记录\n";
echo "涉及 " . count($userSums) . " 个用户\n";
echo "总金额: ¥" . round($totalAmount, 2) . "\n\n";

echo "--- 各用户明细 ---\n";
foreach ($userSums as $uid => $sum) {
    $user = Db::name('user')->where('id', $uid)->field('id, username, nickname, money')->find();
    $name = $user ? ($user['nickname'] ?: $user['username']) : "未知用户";
    $balance = $user ? $user['money'] : 0;
    echo "  用户#{$uid} ({$name}) 余额:¥{$balance} → 扣回:¥" . round($sum, 2) . "\n";
}
echo "\n";

if ($dryRun) {
    echo "[DRY-RUN] 以上为预览，未执行任何修改。去掉 dry 参数即可执行。\n";
    exit(0);
}

// 确认执行
echo "是否确认执行扣款？(输入 yes 确认): ";
$confirm = trim(fgets(STDIN));
if ($confirm !== 'yes') {
    echo "已取消。\n";
    exit(0);
}

echo "\n开始执行...\n";

Db::startTrans();
try {
    // 1. 从每个用户余额中扣回
    foreach ($userSums as $uid => $sum) {
        $sum = round($sum, 2);
        Db::name('user')->where('id', $uid)->setDec('money', $sum);
        echo "  用户#{$uid} 余额扣回 ¥{$sum}\n";
    }

    // 2. 删除所有 win_commission 记录
    $deleted = Db::name('lottery_commission')->where('type', 'win_commission')->delete();
    echo "\n  删除 {$deleted} 条 win_commission 记录\n";

    Db::commit();
    echo "\n===== 修复完成 =====\n";
    echo "共扣回 ¥" . round($totalAmount, 2) . "，删除 {$deleted} 条重复记录\n";
} catch (\Exception $e) {
    Db::rollback();
    echo "\n[错误] 执行失败，已回滚: " . $e->getMessage() . "\n";
    exit(1);
}
