<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use think\Db;

/**
 * 提现订单管理
 */
class Withdraw extends Backend
{
    protected $model = null;
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 提现订单列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page  = $this->request->param('page/d', 1);
            $limit = $this->request->param('limit/d', 20);
            $status = $this->request->param('status', '');
            $keyword = $this->request->param('keyword', '');

            $where = [];
            if ($status !== '') $where['w.status'] = intval($status);
            if ($keyword) {
                $where['w.order_no|u.username|u.nickname'] = ['like', "%{$keyword}%"];
            }

            $list = Db::name('withdraw_order')
                ->alias('w')
                ->join('user u', 'u.id = w.user_id', 'LEFT')
                ->where($where)
                ->field('w.*, u.username, u.nickname, u.avatar, u.money as user_balance')
                ->order('w.createtime', 'desc')
                ->page($page, $limit)
                ->select();

            $this->appendRealNames($list);

            $total = Db::name('withdraw_order')
                ->alias('w')
                ->join('user u', 'u.id = w.user_id', 'LEFT')
                ->where($where)
                ->count();

            foreach ($list as &$item) {
                $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                $item['status_text'] = ['待审核', '已打款', '已拒绝'][$item['status']] ?? '未知';
                $item['withdraw_type_text'] = ['wechat'=>'微信','alipay'=>'支付宝','usdt'=>'USDT','bank'=>'银行卡'][$item['withdraw_type']] ?? $item['withdraw_type'];
            }

            $this->success('', '', ['list' => $list, 'total' => $total]);
        }

        return $this->view->fetch();
    }

    /** 兼容尚未安装实名认证升级表的旧环境。 */
    private function appendRealNames(&$list)
    {
        foreach ($list as &$item) $item['real_name'] = '';
        if (!$list) return;
        try {
            $userIds = array_unique(array_column($list, 'user_id'));
            $names = Db::name('user_verify')->where('user_id', 'in', $userIds)->column('real_name', 'user_id');
            foreach ($list as &$item) $item['real_name'] = isset($names[$item['user_id']]) ? $names[$item['user_id']] : '';
        } catch (\Exception $e) {
            // 旧库没有 user_verify 时保持原列表可用
        }
    }

    /**
     * 审核提现
     */
    public function audit()
    {
        $id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $remark = isset($_POST['remark']) ? $_POST['remark'] : '';

        if (!$id) return json(['code' => 0, 'msg' => '缺少订单ID']);

        $order = Db::name('withdraw_order')->where('id', $id)->where('status', 0)->find();
        if (!$order) return json(['code' => 0, 'msg' => '订单不存在或已处理']);

        if ($action === 'approve') {
            Db::name('withdraw_order')->where('id', $id)->update([
                'status' => 1, 'admin_remark' => $remark, 'updatetime' => time()
            ]);
            return json(['code' => 1, 'msg' => '已确认打款']);
        } elseif ($action === 'reject') {
            Db::startTrans();
            try {
                Db::name('withdraw_order')->where('id', $id)->update([
                    'status' => 2, 'admin_remark' => $remark, 'updatetime' => time()
                ]);
                // 退回余额
                \app\common\model\User::where('id', $order['user_id'])->setInc('money', $order['amount']);
                Db::commit();
                return json(['code' => 1, 'msg' => '已拒绝，余额已退回']);
            } catch (\Exception $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => '操作失败: ' . $e->getMessage()]);
            }
        } else {
            return json(['code' => 0, 'msg' => '无效操作']);
        }
    }
}
