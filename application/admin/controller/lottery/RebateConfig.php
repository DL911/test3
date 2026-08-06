<?php
namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 流水返利配置管理
 */
class RebateConfig extends Backend
{
    protected $noNeedRight = ['*'];

    /**
     * 列表 + 编辑
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = Db::name('rebate_config')->order('min_amount', 'asc')->select();
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
                Db::name('rebate_config')->where('id', $data['id'])->update($data);
                $this->success('更新成功');
            } else {
                $data['createtime'] = time();
                Db::name('rebate_config')->insert($data);
                $this->success('添加成功');
            }
        }

        $row = $id ? Db::name('rebate_config')->where('id', $id)->find() : [];
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = null)
    {
        if ($ids) {
            Db::name('rebate_config')->where('id', 'in', $ids)->delete();
            $this->success('删除成功');
        }
        $this->error('参数错误');
    }
}
