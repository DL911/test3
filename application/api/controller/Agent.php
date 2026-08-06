<?php
namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 全民代理API
 */
class Agent extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 代理中心总览
     */
    public function summary()
    {
        $user_id = $this->auth->id;
        
        // 直推人数
        $team_size = Db::name('user')->where('pid', $user_id)->count();
        
        // 累计佣金
        $total_commission = Db::name('lottery_commission')->where('user_id', $user_id)->sum('amount');
        
        // 用户邀请佣金比例
        $user = Db::name('user')->where('id', $user_id)->field('invite_rebate_rate')->find();
        $rate = isset($user['invite_rebate_rate']) ? floatval($user['invite_rebate_rate']) : 0.015;
        $rateStr = rtrim(rtrim(number_format($rate * 100, 2), '0'), '.') . '%';
        
        $this->success('success', [
            'team_size' => $team_size,
            'total_commission' => number_format($total_commission, 2),
            'commission_rate' => $rateStr,
            'commission_rate_raw' => $rate,
            'invite_code' => $user_id,
            'invite_url' => request()->domain() . '/app.html?invite=' . $user_id
        ]);
    }

    /**
     * 下级列表
     */
    public function subordinates()
    {
        $user_id = $this->auth->id;
        
        $list = Db::name('user')
            ->where('pid', $user_id)
            ->field('id, username, nickname, createtime')
            ->order('createtime desc')
            ->select();
            
        foreach ($list as &$v) {
            $v['name'] = $v['nickname'] ?: $v['username'];
            $v['date'] = date('Y-m-d H:i', $v['createtime']);
        }
        
        $this->success('success', $list);
    }

    /**
     * 佣金记录
     */
    public function commission_logs()
    {
        $user_id = $this->auth->id;
        
        $list = Db::name('lottery_commission')
            ->where('user_id', $user_id)
            ->order('createtime desc')
            ->limit(50)
            ->select();
            
        foreach ($list as &$v) {
            $v['date'] = date('Y-m-d H:i:s', $v['createtime']);
            $sub = Db::name('user')->where('id', $v['sub_id'])->field('username')->find();
            $v['sub_name'] = $sub ? $sub['username'] : '--';
        }
        
        $this->success('success', $list);
    }
}
