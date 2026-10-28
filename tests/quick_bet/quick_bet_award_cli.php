<?php
// Test bridge only. Pure computation; never connects to a database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/application/common/service/QuickBetService.php';
$cases = json_decode(stream_get_contents(STDIN), true);
if (!is_array($cases)) { fwrite(STDERR, "invalid cases\n"); exit(2); }
$results = [];
foreach ($cases as $case) {
    $item = \app\common\service\QuickBetService::validateItem($case['item']);
    $item['odds'] = $case['item']['odds'];
    $results[] = \app\common\service\QuickBetService::award([$item], $case['draw']);
}
echo json_encode($results);
