<?php
namespace app\common\library;

use think\Db;

class KefuChannel
{
    const DEFAULT_CODE = 'general';

    public static function defaults()
    {
        return [
            ['id'=>0, 'code'=>'general', 'name'=>'综合咨询', 'description'=>'其他业务及综合问题', 'announcement'=>'欢迎使用在线客服', 'image'=>'', 'intro'=>'官方人工客服为您服务', 'icon'=>'fa-comments', 'color'=>'#18bc9c', 'weigh'=>100, 'status'=>1],
            ['id'=>0, 'code'=>'wanli', 'name'=>'万丽百家乐开户窗口', 'description'=>'万丽百家乐开户咨询', 'announcement'=>'欢迎咨询万丽百家乐开户业务', 'image'=>'', 'intro'=>'请在聊天窗口提交您的开户需求，客服将为您详细介绍开户流程。', 'icon'=>'fa-gem', 'color'=>'#ef4444', 'weigh'=>90, 'status'=>1],
            ['id'=>0, 'code'=>'dongfang', 'name'=>'东方汇百家乐', 'description'=>'东方汇百家乐开户咨询', 'announcement'=>'欢迎咨询东方汇百家乐业务', 'image'=>'', 'intro'=>'请在聊天窗口说明您的咨询内容，客服将尽快为您服务。', 'icon'=>'fa-chess-queen', 'color'=>'#8b5cf6', 'weigh'=>80, 'status'=>1],
            ['id'=>0, 'code'=>'crown', 'name'=>'皇冠足球开户窗口', 'description'=>'皇冠足球开户咨询', 'announcement'=>'欢迎咨询皇冠足球开户业务', 'image'=>'', 'intro'=>'请联系在线客服了解开户流程、玩法及相关注意事项。', 'icon'=>'fa-futbol', 'color'=>'#0ea5e9', 'weigh'=>70, 'status'=>1],
        ];
    }

    public static function all($enabledOnly = true)
    {
        try {
            $query = Db::name('lottery_kefu_channel')->order('weigh', 'desc')->order('id', 'asc');
            $rows = $query->select();
            $merged = [];
            foreach (self::defaults() as $item) $merged[$item['code']] = $item;
            foreach ($rows as $item) $merged[$item['code']] = array_merge(isset($merged[$item['code']]) ? $merged[$item['code']] : [], $item);
            $result = array_values($merged);
            if ($enabledOnly) $result = array_values(array_filter($result, function ($item) { return intval($item['status']) === 1; }));
            usort($result, function ($a, $b) { return intval($b['weigh']) - intval($a['weigh']); });
            return $result;
        } catch (\Throwable $e) {
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
