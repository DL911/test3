<?php
/**
 * 独立部署到国内 PHP 站点的开奖采集节点；不连接主站数据库。
 * 必须在 PHP-FPM 环境中配置 DRAW_RELAY_SECRET，主站使用同一密钥验签。
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function failResult($code, $message)
{
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function getJson($url, $referer)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Referer: ' . $referer],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200 || !$body) return null;
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

$secret = (string)getenv('DRAW_RELAY_SECRET');
$type = isset($_GET['type']) ? (string)$_GET['type'] : '';
$period = isset($_GET['period']) ? (string)$_GET['period'] : '';
if (strlen($secret) < 32) failResult(503, '采集节点未配置');
if (!in_array($type, ['fc3d', 'pl3'], true)) failResult(400, '彩种无效');
if (!preg_match($type === 'fc3d' ? '/^\d{7}$/' : '/^\d{5}$/', $period)) failResult(400, '期号无效');

if ($type === 'fc3d') {
    $data = getJson('https://www.cwl.gov.cn/cwl_admin/front/cwlkj/search/kjxx/findDrawNotice?name=3d&issueCount=20', 'https://www.cwl.gov.cn/');
    $rows = isset($data['result']) && is_array($data['result']) ? $data['result']
        : (isset($data['data']) && is_array($data['data']) ? $data['data'] : []);
} else {
    $data = getJson('https://webapi.sporttery.cn/gateway/lottery/getHistoryPageListV1.qry?gameNo=35&provinceId=0&pageSize=20&isVerify=1&pageNo=1', 'https://m.lottery.gov.cn/');
    $rows = isset($data['value']['list']) && is_array($data['value']['list']) ? $data['value']['list'] : [];
}

foreach ($rows as $row) {
    if (!is_array($row)) continue;
    if ($type === 'fc3d') {
        $itemPeriod = isset($row['code']) ? (string)$row['code'] : (isset($row['issue']) ? (string)$row['issue'] : '');
        $rawNumbers = isset($row['red']) ? (string)$row['red'] : (isset($row['number']) ? (string)$row['number'] : '');
        $date = isset($row['date']) ? substr((string)$row['date'], 0, 10)
            : (isset($row['openTime']) ? substr((string)$row['openTime'], 0, 10) : '');
        $drawHour = '21:15:00';
    } else {
        $itemPeriod = isset($row['lotteryDrawNum']) ? (string)$row['lotteryDrawNum'] : '';
        if (preg_match('/^\d{7}$/', $itemPeriod)) $itemPeriod = substr($itemPeriod, 2);
        $rawNumbers = isset($row['lotteryDrawResult']) ? (string)$row['lotteryDrawResult'] : '';
        $date = isset($row['lotteryDrawTime']) ? substr((string)$row['lotteryDrawTime'], 0, 10) : '';
        $drawHour = '21:25:00';
    }
    if ($itemPeriod !== $period || !preg_match('/^\s*([0-9])[,\s]+([0-9])[,\s]+([0-9])\s*$/', $rawNumbers, $m)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
    $numbers = "{$m[1]},{$m[2]},{$m[3]}";
    $drawTime = $date . ' ' . $drawHour;
    if (!strtotime($drawTime) || strtotime($drawTime) > time()) continue;
    $timestamp = time();
    $payload = implode('|', [$type, $period, $numbers, $drawTime, $timestamp]);
    echo json_encode([
        'type' => $type, 'period' => $period, 'numbers' => $numbers,
        'draw_time' => $drawTime, 'timestamp' => $timestamp,
        'signature' => hash_hmac('sha256', $payload, $secret),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

failResult(404, '官方接口未返回指定期号');
