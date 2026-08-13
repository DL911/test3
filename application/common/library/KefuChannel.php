<?php
namespace app\common\library;

use think\Db;
use think\Exception;

class KefuChannel
{
    const DEFAULT_CODE = 'general';

    public static function defaults()
    {
        return [
            ['code' => 'general', 'name' => '综合咨询', 'description' => '其他业务及综合问题', 'icon' => 'fa-comments', 'color' => '#18bc9c', 'weigh' => 100, 'status' => 1],
            ['code' => 'finance', 'name' => '充值提现', 'description' => '充值、提现及到账问题', 'icon' => 'fa-credit-card', 'color' => '#f59e0b', 'weigh' => 90, 'status' => 1],
            ['code' => 'lottery', 'name' => '投注咨询', 'description' => '投注、开奖及规则问题', 'icon' => 'fa-ticket', 'color' => '#3b82f6', 'weigh' => 80, 'status' => 1],
        ];
    }

    public static function all($enabledOnly = true)
    {
        try {
            $query = Db::name('lottery_kefu_channel')->order('weigh', 'desc')->order('id', 'asc');
            if ($enabledOnly) $query->where('status', 1);
            $rows = $query->select();
            return $rows ?: self::defaults();
        } catch (Exception $e) {
            return self::defaults();
        }
    }

    public static function normalize($code, $enabledOnly = true)
    {
        $code = strtolower(trim((string)$code));
        foreach (self::all($enabledOnly) as $item) {
            if ($item['code'] === $code) return $code;
        }
        return self::DEFAULT_CODE;
    }

    public static function map($enabledOnly = false)
    {
        $map = [];
        foreach (self::all($enabledOnly) as $item) $map[$item['code']] = $item;
        return $map;
    }
}
