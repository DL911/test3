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
        
        // 累计已领取的邀请奖励
        $total_commission = Db::name('xima_record')->where('user_id', $user_id)
            ->where('type', 'parent')->where('status', 1)->sum('xima_amount');
        
        // 邀请奖励比例以后台启用的洗码配置为准
        $config = Db::name('xima_config')->where('status', 1)
            ->order('lottery_type', 'asc')->order('id', 'asc')->field('parent_rate')->find();
        $rate = $config ? floatval($config['parent_rate']) : 0;
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
        
        $list = Db::name('xima_record')
            ->where('user_id', $user_id)
            ->where('type', 'parent')
            ->order('createtime desc')
            ->limit(50)
            ->select();
            
        foreach ($list as &$v) {
            $v['date'] = date('Y-m-d H:i:s', $v['createtime']);
            $v['amount'] = $v['xima_amount'];
            $v['remark'] = $v['status'] == 1 ? '邀请奖励（已领取）' : '邀请奖励（次日可领）';
            $sub = Db::name('user')->where('id', $v['source_user_id'])->field('username')->find();
            $v['sub_name'] = $sub ? $sub['username'] : '--';
        }
        
        $this->success('success', $list);
    }
}
