<?php
define('IN_SYSTEM', true);
define('APP_PATH', __DIR__ . '/application/');
define('BIND_MODULE', 'api');
require __DIR__ . '/thinkphp/base.php';
\think\Db::setConfig(\think\Config::get('database'));

$data = [
    ['period' => '2026106', 'numbers' => '9,2,8', 'draw_time' => '2026-04-26 21:15:00'],
    ['period' => '2026105', 'numbers' => '6,3,1', 'draw_time' => '2026-04-25 21:15:00'],
];

foreach ($data as $item) {
    $exists = \think\Db::name('lottery_draw')->where('lottery_type', 1)->where('period', $item['period'])->find();
    if (!$exists) {
        $numbersArr = explode(',', $item['numbers']);
        \think\Db::name('lottery_draw')->insert([
            'lottery_type' => 1,
            'period' => $item['period'],
            'numbers' => $item['numbers'],
            'sum_value' => array_sum($numbersArr),
            'draw_time' => $item['draw_time'],
            'status' => 1,
            'createtime' => time(),
            'updatetime' => time()
        ]);
        echo "Inserted FC3D: {$item['period']}\n";
    } else {
        if ($exists['status'] == 0 || empty($exists['numbers'])) {
            $numbersArr = explode(',', $item['numbers']);
            \think\Db::name('lottery_draw')->where('id', $exists['id'])->update([
                'numbers' => $item['numbers'],
                'sum_value' => array_sum($numbersArr),
                'status' => 1,
                'updatetime' => time()
            ]);
            echo "Updated FC3D: {$item['period']}\n";
        } else {
            echo "Exists FC3D: {$item['period']}\n";
        }
    }
}
