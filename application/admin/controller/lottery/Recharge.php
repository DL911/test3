<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 充值订单管理
 */
class Recharge extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 充值订单列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page  = $this->request->param('page/d', 1);
            $limit = $this->request->param('limit/d', 20);
            $status = $this->request->param('status', '');
            $keyword = $this->request->param('keyword', '');

            $where = [];
            if ($status !== '') $where['r.status'] = intval($status);
            if ($keyword) {
                $where['r.order_no|u.username|u.nickname'] = ['like', "%{$keyword}%"];
            }

            $list = Db::name('recharge_order')
                ->alias('r')
                ->join('user u', 'u.id = r.user_id', 'LEFT')
                ->where($where)
                ->field('r.*, u.username, u.nickname, u.avatar')
                ->order('r.createtime', 'desc')
                ->page($page, $limit)
                ->select();

            $total = Db::name('recharge_order')
                ->alias('r')
                ->join('user u', 'u.id = r.user_id', 'LEFT')
                ->where($where)
                ->count();

            foreach ($list as &$item) {
                $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                $item['status_text'] = ['待审核', '已到账', '已拒绝'][$item['status']] ?? '未知';
                $item['pay_type_text'] = ['wechat'=>'微信','alipay'=>'支付宝','usdt'=>'USDT'][$item['pay_type']] ?? $item['pay_type'];
            }

            $this->success('', '', ['list' => $list, 'total' => $total]);
        }

        return $this->view->fetch();
    }

    /**
     * 审核充值
     */
    public function audit()
    {
        $id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $remark = isset($_POST['remark']) ? $_POST['remark'] : '';

        if (!$id) return json(['code' => 0, 'msg' => '缺少订单ID']);

        $order = Db::name('recharge_order')->where('id', $id)->where('status', 0)->find();
        if (!$order) return json(['code' => 0, 'msg' => '订单不存在或已处理']);

        if ($action === 'approve') {
            try {
                Db::name('recharge_order')->where('id', $id)->update([
                    'status' => 1, 'admin_remark' => $remark, 'updatetime' => time()
                ]);
                // 直接用 Db 操作余额，避免 User::money() 内部异常被吞
                $result = Db::name('user')->where('id', $order['user_id'])->setInc('money', $order['amount']);
                return json(['code' => 1, 'msg' => '已确认到账 ¥' . $order['amount'] . ' (affected:' . $result . ')']);
            } catch (\Exception $e) {
                return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
            }
        } elseif ($action === 'reject') {
            Db::name('recharge_order')->where('id', $id)->update([
                'status' => 2, 'admin_remark' => $remark, 'updatetime' => time()
            ]);
            return json(['code' => 1, 'msg' => '已拒绝']);
        } else {
            return json(['code' => 0, 'msg' => '无效操作']);
        }
    }
}
