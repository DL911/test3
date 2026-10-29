<?php
// Pure local regression; no database connection.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/application/common/service/QuickBetService.php';
use app\common\service\QuickBetService as Q;

function check($name, $actual, $expected) {
    if ($actual !== $expected) throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}
function bet($play, $number, $amount, $odds, $pos = '', $bomb = false) {
    $item = Q::validateItem(['play'=>$play,'number'=>$number,'amount'=>(string)$amount,'pos'=>$pos,'include_bomb'=>$bomb]);
    $item['odds']=$odds;
    return $item;
}
$sample = [
    bet('direct','518',100,900),bet('g6_3','518',10,150),
    bet('direct','581',100,900),bet('g6_3','581',10,150),
    bet('direct','185',100,900),bet('g6_3','185',10,150),
];
check('sample 518 award', Q::award($sample, '518'), 94500.0);
check('sample 581 award', Q::award($sample, '581'), 94500.0);
check('sample unrelated award', Q::award($sample, '123'), 0.0);
check('specific group 3', Q::award([bet('g3_3','007',2,300)], '070'), 600.0);
check('specific group 3 excludes different pair', Q::award([bet('g3_3','007',2,300)], '077'), 0.0);
try { bet('sum_0','27',10,860); throw new RuntimeException('retired sum bracket accepted'); }
catch (RuntimeException $e) { if ($e->getMessage() === 'retired sum bracket accepted') throw $e; }
check('historical sum bracket settlement', Q::award([['play'=>'sum_0','number'=>'27','amount'=>'10.00','odds'=>860,'pos'=>'','include_bomb'=>0]], '999'), 8600.0);
check('two-position', Q::award([bet('twopos','56',20,90,'01')], '564'), 1800.0);
check('two-position miss', Q::award([bet('twopos','56',20,90,'01')], '654'), 0.0);
echo "quick bet service checks passed\n";
