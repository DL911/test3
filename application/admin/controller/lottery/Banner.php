<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 轮播图管理
 */
class Banner extends Backend
{
    protected $noNeedRight = ['*'];

    /**
     * 列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = Db::name('lottery_banner')->order('weigh', 'desc')->order('id', 'desc')->select();
            $total = count($list);
            return json(['total' => $total, 'rows' => $list]);
        }
        return $this->view->fetch();
    }

    /**
     * 添加/编辑
     */
    public function edit($id = null)
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data['updatetime'] = time();

            if (!empty($data['id'])) {
                Db::name('lottery_banner')->where('id', $data['id'])->update($data);
                $this->success('更新成功');
            } else {
                unset($data['id']);
                $data['createtime'] = time();
                Db::name('lottery_banner')->insert($data);
                $this->success('添加成功');
            }
        }

        $row = $id ? Db::name('lottery_banner')->where('id', $id)->find() : [];
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = null)
    {
        if ($ids) {
            Db::name('lottery_banner')->where('id', 'in', $ids)->delete();
            $this->success('删除成功');
        }
        $this->error('参数错误');
    }
}
