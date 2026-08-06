<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 平台收款码配置
 */
class PaymentConfig extends Backend
{
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 查看列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = Db::name('payment_config')->order('sort', 'asc')->select();
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
            $post = $_POST;
            $editId = isset($post['id']) ? intval($post['id']) : 0;

            $data = [
                'pay_type'   => isset($post['pay_type']) ? $post['pay_type'] : 'wechat',
                'title'      => isset($post['title']) ? $post['title'] : '',
                'qr_image'   => isset($post['qr_image']) ? $post['qr_image'] : '',
                'account'    => isset($post['account']) ? $post['account'] : '',
                'remark'     => isset($post['remark']) ? $post['remark'] : '',
                'min_amount' => isset($post['min_amount']) ? floatval($post['min_amount']) : 10,
                'max_amount' => isset($post['max_amount']) ? floatval($post['max_amount']) : 50000,
                'sort'       => isset($post['sort']) ? intval($post['sort']) : 0,
                'status'     => isset($post['status']) ? intval($post['status']) : 1,
                'updatetime' => time(),
            ];

            try {
                if ($editId > 0) {
                    $result = Db::name('payment_config')->where('id', $editId)->update($data);
                } else {
                    $data['createtime'] = time();
                    $result = Db::name('payment_config')->insert($data);
                }
                return json(['code' => 1, 'msg' => $editId > 0 ? '更新成功' : '添加成功', 'data' => $result]);
            } catch (\Exception $e) {
                return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
            } catch (\Throwable $e) {
                return json(['code' => 0, 'msg' => '系统错误: ' . $e->getMessage()]);
            }
        }

        return json(['code' => 0, 'msg' => '请求方式错误']);
    }

    /**
     * 删除
     */
    public function del($ids = null)
    {
        if (!$ids) {
            $ids = $_POST['ids'] ?? null;
        }
        if ($ids) {
            Db::name('payment_config')->where('id', 'in', $ids)->delete();
            return json(['code' => 1, 'msg' => '删除成功']);
        }
        return json(['code' => 0, 'msg' => '参数错误']);
    }
}
