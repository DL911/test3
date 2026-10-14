<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 逐笔返水管理
 */
class Xima extends Backend
{
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 逐笔返水记录主页
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $tab = $this->request->param('tab', 'record');

            if ($tab === 'config') {
                $this->success('旧洗码条件配置已停用', '', ['list' => [], 'total' => 0]);
            } else {
                return $this->recordList();
            }
        }

        return $this->view->fetch();
    }

    /**
     * 逐笔返水记录列表
     */
    private function recordList()
    {
        $page    = $this->request->param('page/d', 1);
        $limit   = $this->request->param('limit/d', 20);
        $keyword = $this->request->param('keyword', '');
        $type    = $this->request->param('type', '');
        $status  = $this->request->param('status', '');

        $where = [];
        if ($type !== '') $where['r.type'] = $type;
        if ($status !== '') $where['r.status'] = intval($status);
        if ($keyword) {
            $where['u.username|u.nickname'] = ['like', "%{$keyword}%"];
        }

        $list = Db::name('xima_record')
            ->alias('r')
            ->join('user u', 'u.id = r.user_id', 'LEFT')
            ->join('user su', 'su.id = r.source_user_id', 'LEFT')
            ->where('u.agent_level', '>', 0)
            ->where($where)
            ->field('r.*, u.username, u.nickname, su.username as source_username, su.nickname as source_nickname')
            ->order('r.createtime', 'desc')
            ->page($page, $limit)
            ->select();

        $total = Db::name('xima_record')
            ->alias('r')
            ->join('user u', 'u.id = r.user_id', 'LEFT')
            ->join('user su', 'su.id = r.source_user_id', 'LEFT')
            ->where('u.agent_level', '>', 0)
            ->where($where)
            ->count();

        // 统计
        $totalXima = Db::name('xima_record')
            ->alias('r')
            ->join('user u', 'u.id = r.user_id', 'LEFT')
            ->join('user su', 'su.id = r.source_user_id', 'LEFT')
            ->where('u.agent_level', '>', 0)
            ->where($where)
            ->sum('r.xima_amount');

        $claimedXima = Db::name('xima_record')
            ->alias('r')
            ->join('user u', 'u.id = r.user_id', 'LEFT')
            ->join('user su', 'su.id = r.source_user_id', 'LEFT')
            ->where('u.agent_level', '>', 0)
            ->where($where)
            ->where('r.status', 1)
            ->sum('r.xima_amount');

        foreach ($list as &$item) {
            $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
            $item['claim_date'] = $item['claim_time'] ? date('Y-m-d H:i:s', $item['claim_time']) : '--';
            $item['type_text'] = $item['type'] === 'self' ? '自身返水' : '团队级差';
            $item['status_text'] = $item['status'] == 1 ? '已领取' : ($item['status'] == 2 ? '已作废' : ($item['unlock_date'] <= date('Y-m-d') ? '可领取' : '今日待解锁'));
            $item['source_name'] = $item['source_nickname'] ?: $item['source_username'] ?: '--';
        }

        $this->success('', '', [
            'list'          => $list,
            'total'         => $total,
            'total_xima'    => round($totalXima, 2),
            'claimed_xima'  => round($claimedXima, 2),
        ]);
    }

    /**
     * 保存返水条件配置
     */
    public function saveConfig()
    {
        return json(['code' => 0, 'msg' => '新代理模式每笔投注自动返水，旧洗码条件配置已停用']);
    }

    /**
     * 删除返水条件配置
     */
    public function delConfig()
    {
        return json(['code' => 0, 'msg' => '新代理模式已停用旧洗码条件配置']);
    }
}
