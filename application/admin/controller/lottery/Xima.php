<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 洗码管理
 */
class Xima extends Backend
{
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 洗码管理主页 (配置 + 记录)
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
     * 洗码配置列表
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
     * 洗码记录列表
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
            ->where($where)
            ->field('r.*, u.username, u.nickname, su.username as source_username, su.nickname as source_nickname')
            ->order('r.createtime', 'desc')
            ->page($page, $limit)
            ->select();

        $total = Db::name('xima_record')
            ->alias('r')
            ->join('user u', 'u.id = r.user_id', 'LEFT')
            ->join('user su', 'su.id = r.source_user_id', 'LEFT')
            ->where($where)
            ->count();

        // 统计
        $totalXima = Db::name('xima_record')
            ->alias('r')
            ->join('user u', 'u.id = r.user_id', 'LEFT')
            ->join('user su', 'su.id = r.source_user_id', 'LEFT')
            ->where($where)
            ->sum('r.xima_amount');

        $claimedXima = Db::name('xima_record')
            ->alias('r')
            ->join('user u', 'u.id = r.user_id', 'LEFT')
            ->join('user su', 'su.id = r.source_user_id', 'LEFT')
            ->where($where)
            ->where('r.status', 1)
            ->sum('r.xima_amount');

        foreach ($list as &$item) {
            $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
            $item['claim_date'] = $item['claim_time'] ? date('Y-m-d H:i:s', $item['claim_time']) : '--';
            $item['type_text'] = $item['type'] === 'self' ? '自身洗码' : '邀请人洗码';
            $item['status_text'] = ['待领取', '已领取', '已过期'][$item['status']] ?? '未知';
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
     * 保存洗码配置
     */
    public function saveConfig()
    {
        $id         = $this->request->post('id/d', 0);
        $name       = $this->request->post('name', '');
        $selfRate   = $this->request->post('self_rate/f', 0);
        $parentRate = $this->request->post('parent_rate/f', 0);
        $minBet     = $this->request->post('min_bet/f', 0);
        $lotteryType = $this->request->post('lottery_type/d', 0);
        $status     = $this->request->post('status/d', 1);

        $data = [
            'name'         => $name ?: '默认配置',
            'self_rate'    => $selfRate,
            'parent_rate'  => $parentRate,
            'min_bet'      => $minBet,
            'lottery_type' => $lotteryType,
            'status'       => $status,
            'updatetime'   => time(),
        ];

        if ($id > 0) {
            Db::name('xima_config')->where('id', $id)->update($data);
            return json(['code' => 1, 'msg' => '更新成功']);
        } else {
            $data['createtime'] = time();
            Db::name('xima_config')->insert($data);
            return json(['code' => 1, 'msg' => '添加成功']);
        }
    }

    /**
     * 删除洗码配置
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
