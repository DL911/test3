<?php
namespace app\common\service;

use think\Db;

/** 快译首批支持玩法。赔率只从本服务的后台配置读取。 */
class QuickBetService
{
    public static function catalog()
    {
        return [
            'direct' => '三码直选', 'onepos' => '一码定位', 'twopos' => '两码定位',
            'sumodd' => '和值单', 'sumeven' => '和值双', 'sumbig' => '和值大', 'sumsmall' => '和值小',
            'posodd' => '位置单', 'poseven' => '位置双', 'posbig' => '位置大', 'possmall' => '位置小',
        ];
    }

    public static function settings($lotteryType)
    {
        return Db::name('quick_bet_odds')->where('lottery_type', $lotteryType)->order('id')->select();
    }

    public static function validateItem($item)
    {
        if (!is_array($item) || !isset(self::catalog()[$item['play'] ?? ''])) throw new \RuntimeException('存在暂不支持的快译玩法');
        $play = $item['play'];
        $number = isset($item['number']) ? (string)$item['number'] : '';
        $pos = isset($item['pos']) ? (string)$item['pos'] : '';
        $amount = isset($item['amount']) ? (string)$item['amount'] : '';
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount) || floatval($amount) <= 0) throw new \RuntimeException('快译金额无效');
        if ($play === 'direct' && !preg_match('/^\d{3}$/', $number)) throw new \RuntimeException('直选号码必须是三位数字');
        if ($play === 'onepos' && (!preg_match('/^\d$/', $number) || !preg_match('/^[012]$/', $pos))) throw new \RuntimeException('一码定位号码或位置无效');
        if ($play === 'twopos' && (!preg_match('/^\d{2}$/', $number) || !in_array($pos, ['01','02','12'], true))) throw new \RuntimeException('两码定位号码或位置无效');
        if (strpos($play, 'pos') === 0 && !preg_match('/^[012]$/', $pos)) throw new \RuntimeException('位置大小单双须指定一个位置');
        if (strpos($play, 'sum') === 0 || strpos($play, 'pos') === 0) $number = '';
        return ['play' => $play, 'number' => $number, 'pos' => $pos, 'amount' => number_format(floatval($amount), 2, '.', '')];
    }

    public static function award($items, $draw)
    {
        if (!preg_match('/^\d{3}$/', $draw)) return 0;
        $d = array_map('intval', str_split($draw));
        $sum = array_sum($d);
        $win = 0;
        foreach ($items as $item) {
            $p = $item['play']; $n = $item['number']; $pos = $item['pos']; $hit = false;
            if ($p === 'direct') $hit = $n === $draw;
            elseif ($p === 'onepos') $hit = $d[intval($pos)] === intval($n);
            elseif ($p === 'twopos') $hit = $d[intval($pos[0])] === intval($n[0]) && $d[intval($pos[1])] === intval($n[1]);
            elseif ($p === 'sumodd') $hit = $sum % 2 === 1;
            elseif ($p === 'sumeven') $hit = $sum % 2 === 0;
            elseif ($p === 'sumbig') $hit = $sum >= 14;
            elseif ($p === 'sumsmall') $hit = $sum <= 13;
            elseif ($p === 'posodd') $hit = $d[intval($pos)] % 2 === 1;
            elseif ($p === 'poseven') $hit = $d[intval($pos)] % 2 === 0;
            elseif ($p === 'posbig') $hit = $d[intval($pos)] >= 5;
            elseif ($p === 'possmall') $hit = $d[intval($pos)] < 5;
            if ($hit) $win += round(floatval($item['amount']) * floatval($item['odds']), 2);
        }
        return round($win, 2);
    }
}
