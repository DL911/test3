<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 实名认证审核
 */
class Verify extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 认证列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $status = $this->request->get('status', '');
            $query = Db::name('user_verify')
                ->alias('v')
                ->join('user u', 'u.id = v.user_id', 'LEFT')
                ->field('v.*, u.username, u.nickname, u.mobile');

            if ($status !== '') {
                $query->where('v.status', $status);
            }

            $list = $query->order('v.id', 'desc')->paginate(15);
            $data = $list->toArray();

            foreach ($data['data'] as &$item) {
                $statusMap = [0 => '<span class="label label-warning">待审核</span>', 1 => '<span class="label label-success">已通过</span>', 2 => '<span class="label label-danger">已拒绝</span>'];
                $item['status_html'] = isset($statusMap[$item['status']]) ? $statusMap[$item['status']] : '未知';
                $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                $item['front_url'] = $item['front_image'] ? '<a href="'.$item['front_image'].'" target="_blank">查看</a>' : '-';
                $item['back_url']  = $item['back_image'] ? '<a href="'.$item['back_image'].'" target="_blank">查看</a>' : '-';
            }

            $result = ['total' => $data['total'], 'rows' => $data['data']];
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 通过认证
     */
    public function approve()
    {
        $id = $this->request->post('id/d', 0);
        if (!$id) $this->error('参数错误');

        $verify = Db::name('user_verify')->where('id', $id)->find();
        if (!$verify) $this->error('记录不存在');
        if ($verify['status'] == 1) $this->error('已通过，无需重复操作');

        Db::name('user_verify')->where('id', $id)->update([
            'status' => 1,
            'reject_reason' => '',
            'updatetime' => time()
        ]);
        $this->success('已通过认证');
    }

    /**
     * 拒绝认证
     */
    public function reject()
    {
        $id = $this->request->post('id/d', 0);
        $reason = $this->request->post('reason', '信息不符');
        if (!$id) $this->error('参数错误');

        Db::name('user_verify')->where('id', $id)->update([
            'status' => 2,
            'reject_reason' => $reason,
            'updatetime' => time()
        ]);
        $this->success('已拒绝');
    }
}
