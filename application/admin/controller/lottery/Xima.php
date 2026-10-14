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
     * 逐笔返水管理主页（条件配置 + 记录）
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $tab = $this->request->param('tab', 'config');

            if ($tab === 'config') {
                return $this->configList();
            } else {
                return $this->recordList();
            }
        }

        return $this->view->fetch();
    }

    /**
     * 返水条件配置列表
     */
    private function configList()
    {
        $list = Db::name('xima_config')->order('id', 'asc')->select();
        $typeMap = [0 => '全部彩种', 1 => '福彩3D', 2 => '排列三'];
        foreach ($list as &$item) {
            $item['lottery_name'] = isset($typeMap[$item['lottery_type']]) ? $typeMap[$item['lottery_type']] : '未知';
            $item['self_rate_pct'] = round($item['self_rate'] * 100, 2) . '%';
            $item['parent_rate_pct'] = round($item['parent_rate'] * 100, 2) . '%';
        }
        $this->success('', '', ['list' => $list, 'total' => count($list)]);
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
        $id         = $this->request->post('id/d', 0);
        $name       = $this->request->post('name', '');
        $minBet     = $this->request->post('min_bet/f', 0);
        $lotteryType = $this->request->post('lottery_type/d', 0);
        $status     = $this->request->post('status/d', 1);

        if ($minBet < 0 || !in_array($lotteryType, [0, 1, 2], true) || !in_array($status, [0, 1], true)) {
            return json(['code' => 0, 'msg' => '配置参数不正确']);
        }

        $data = [
            'name'         => $name ?: '默认配置',
            'min_bet'      => $minBet,
            'lottery_type' => $lotteryType,
            'status'       => $status,
            'updatetime'   => time(),
        ];

        if ($id > 0) {
            Db::name('xima_config')->where('id', $id)->update($data);
            return json(['code' => 1, 'msg' => '更新成功']);
        } else {
            // 新业务比例来自用户层级，旧字段固定为零，仅保留表结构兼容。
            $data['self_rate'] = 0;
            $data['parent_rate'] = 0;
            $data['createtime'] = time();
            Db::name('xima_config')->insert($data);
            return json(['code' => 1, 'msg' => '添加成功']);
        }
    }

    /**
     * 删除返水条件配置
     */
    public function delConfig()
    {
        $id = $this->request->post('id/d', 0);
        if ($id) {
            Db::name('xima_config')->where('id', $id)->delete();
            return json(['code' => 1, 'msg' => '删除成功']);
        }
        return json(['code' => 0, 'msg' => '参数错误']);
    }
}
