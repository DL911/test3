<?php

require __DIR__ . '/../application/common/service/DrawService.php';

function parseFixture($method, $json, $period)
{
    $reflection = new ReflectionMethod('app\\common\\service\\DrawService', $method);
    $reflection->setAccessible(true);
    return $reflection->invoke(null, json_encode($json), $period);
}

function parseHtmlFixture($method, $html, $type, $period)
{
    $reflection = new ReflectionMethod('app\\common\\service\\DrawService', $method);
    $reflection->setAccessible(true);
    return $reflection->invoke(null, $html, $type, $period);
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

$html17500 = '<html><body><table>'
    . '<tr><td>福彩3D</td><td>2026307</td><td>11-17</td><td>601 307期模拟试机号 160</td></tr>'
    . '<tr><td>排列3</td><td>2026307</td><td>11-17</td><td>067 307期模拟试机号 059</td></tr>'
    . '</table></body></html>';
assertDraw(parseHtmlFixture('parse17500', $html17500, 'fc3d', '2026307'), '2026307', '6,0,1');
assertDraw(parseHtmlFixture('parse17500', $html17500, 'pl3', '26307'), '26307', '0,6,7');
if (parseHtmlFixture('parse17500', $html17500, 'pl3', '26308') !== false) throw new RuntimeException('乐彩网缺失期号未拒绝');

$htmlEastmoney = '<html><body><div>福彩3D 第2026307期 2026-11-17 星期二 21:05 '
    . '6 0 1 试机号：3 7 2</div><div>排列3 / 排列5 第26307期 2026-11-17 星期二 21:05 '
    . '0 6 7 4 7 排列3直选：1,040元</div></body></html>';
assertDraw(parseHtmlFixture('parseEastmoney', $htmlEastmoney, 'fc3d', '2026307'), '2026307', '6,0,1');
assertDraw(parseHtmlFixture('parseEastmoney', $htmlEastmoney, 'pl3', '26307'), '26307', '0,6,7');
if (parseHtmlFixture('parseEastmoney', $htmlEastmoney, 'fc3d', '2026308') !== false) throw new RuntimeException('东方财富缺失期号未拒绝');
echo "开奖数据解析测试通过\n";
