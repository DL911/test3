<?php

require __DIR__ . '/../application/common/service/DrawService.php';

function parseFixture($method, $json, $period)
{
    $reflection = new ReflectionMethod('app\\common\\service\\DrawService', $method);
    $reflection->setAccessible(true);
    return $reflection->invoke(null, json_encode($json), $period);
}

function assertDraw($actual, $period, $numbers)
{
    if (!is_array($actual) || $actual['period'] !== $period || $actual['numbers'] !== $numbers) {
        throw new RuntimeException('开奖解析结果不匹配: ' . json_encode($actual));
    }
}

$fc = ['result' => [
    ['code' => '2026307', 'red' => '6,0,1', 'date' => '2026-11-17'],
    ['code' => '2026306', 'red' => '4,3,2', 'date' => '2026-11-16'],
]];
$pl = ['value' => ['list' => [
    ['lotteryDrawNum' => '2026307', 'lotteryDrawResult' => '0 6 7', 'lotteryDrawTime' => '2026-11-17 21:25:00'],
    ['lotteryDrawNum' => '2026306', 'lotteryDrawResult' => '0 3 2', 'lotteryDrawTime' => '2026-11-16 21:25:00'],
]]];
assertDraw(parseFixture('parseOfficialFc3d', $fc, '2026306'), '2026306', '4,3,2');
assertDraw(parseFixture('parseOfficialPl3', $pl, '26307'), '26307', '0,6,7');
if (parseFixture('parseOfficialFc3d', $fc, '2026308') !== false) throw new RuntimeException('缺失期号未拒绝');
$fc['result'][0]['red'] = '6,0,10';
if (parseFixture('parseOfficialFc3d', $fc, '2026307') !== false) throw new RuntimeException('无效号码未拒绝');
echo "开奖数据解析测试通过\n";
