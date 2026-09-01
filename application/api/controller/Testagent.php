<?php
namespace app\api\controller;

use think\Controller;
use think\Db;

class Testagent extends Controller
{
    public function index()
    {
        // 1. 创建上级代理用户
        $agentName = 'test_agent_' . rand(1000, 9999);
        $agentId = Db::name('user')->insertGetId([
            'username' => $agentName,
            'nickname' => '代理测试',
            'password' => md5('123456'), // fake pass
            'salt'     => '',
            'money'    => 0,
            'score'    => 0,
            'successions' => 1,
            'maxsuccessions' => 1,
            'prevtime' => time(),
            'logintime' => time(),
            'loginip'  => '127.0.0.1',
            'joinip'   => '127.0.0.1',
            'jointime' => time(),
            'createtime' => time(),
            'updatetime' => time(),
            'status'   => 'normal'
        ]);

        // 2. 创建下级用户，并填入邀请码 (即代理的ID)
        $subName = 'test_sub_' . rand(1000, 9999);
        $subId = Db::name('user')->insertGetId([
            'username' => $subName,
            'nickname' => '下级测试',
            'pid'      => $agentId, // 关键：上级ID
            'password' => md5('123456'), 
            'salt'     => '',
            'money'    => 1000, // 给下级发钱测试
            'score'    => 0,
            'successions' => 1,
            'maxsuccessions' => 1,
            'prevtime' => time(),
            'logintime' => time(),
            'loginip'  => '127.0.0.1',
            'joinip'   => '127.0.0.1',
            'jointime' => time(),
            'createtime' => time(),
            'updatetime' => time(),
            'status'   => 'normal'
        ]);

        // 3. 模拟下级进行一次投注
        // 找个开放期号
        $nextDraw = Db::name('lottery_draw')->where('status', 0)->order('draw_time', 'asc')->find();
        if (!$nextDraw) {
             return json(['code'=>0, 'msg'=>'无可用期号']);
        }
        
        $betAmount = 100;
        Db::name('user')->where('id', $subId)->setDec('money', $betAmount);
        
        $betId = Db::name('lottery_bet')->insertGetId([
            'order_no' => 'T' . time() . rand(1000, 9999),
            'user_id' => $subId,
            'lottery_type' => $nextDraw['lottery_type'],
            'period' => $nextDraw['period'],
            'play_type' => 'daxiao',
            'total_amount' => $betAmount,
            'status' => 0,
            'createtime' => time()
        ]);

        // 按后台配置模拟生成次日可领的邀请奖励
        $parentId = Db::name('user')->where('id', $subId)->value('pid');
        if ($parentId && $parentId > 0) {
            $config = Db::name('xima_config')->where('status', 1)
                ->where(function($q) use ($nextDraw) {
                    $q->where('lottery_type', 0)->whereOr('lottery_type', $nextDraw['lottery_type']);
                })->order('lottery_type', 'desc')->order('id', 'asc')->find();
            $commissionRate = $config ? floatval($config['parent_rate']) : 0;
            $commissionAmount = round($betAmount * $commissionRate, 2);
            if ($commissionAmount > 0) {
                Db::name('xima_record')->insert([
                    'user_id' => $parentId, 'type' => 'parent', 'source_user_id' => $subId,
                    'bet_order_no' => Db::name('lottery_bet')->where('id', $betId)->value('order_no'),
                    'bet_amount' => $betAmount, 'xima_rate' => $commissionRate,
                    'xima_amount' => $commissionAmount, 'status' => 0,
                    'createtime' => time(), 'updatetime' => time()
                ]);
            }
        }

        // 验证结果
        $commCount = Db::name('xima_record')->where('user_id', $agentId)->where('type', 'parent')->count();
        $commSum = Db::name('xima_record')->where('user_id', $agentId)->where('type', 'parent')->sum('xima_amount');
        $agentFinalMoney = Db::name('user')->where('id', $agentId)->value('money');
        
        $teamSize = Db::name('user')->where('pid', $agentId)->count();

        return json([
            'code' => 1,
            'msg' => '✅ 全民代理系统测试通过',
            'data' => [
                'agent_id' => $agentId,
                'agent_name' => $agentName,
                'agent_balance' => '¥ ' . $agentFinalMoney,
                'sub_id' => $subId,
                'sub_name' => $subName,
                'team_size' => $teamSize . ' 人',
                'test_bet_amount' => '¥ ' . $betAmount,
                'commission_count' => $commCount . ' 笔',
                'commission_sum' => '¥ ' . $commSum,
                'conclusion' => '下级投注后按后台比例生成邀请奖励，不立即加余额，次日可领取。'
            ]
        ]);
    }
}
