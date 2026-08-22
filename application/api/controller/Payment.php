<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 充值提现API
 */
class Payment extends Api
{
    protected $noNeedLogin = ['getPaymentConfig'];
    protected $noNeedRight = '*';

    /**
     * 获取平台收款码配置
     * GET /api/payment/getPaymentConfig
     */
    public function getPaymentConfig()
    {
        $list = Db::name('payment_config')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->select();

        $this->success('OK', $list);
    }

    /**
     * 提交充值申请
     * POST /api/payment/recharge
     */
    public function recharge()
    {
        $userId  = $this->auth->id;
        $amount  = $this->request->post('amount/f', 0);
        $payType = $this->request->post('pay_type', '');

        if ($amount <= 0) $this->error('请输入正确的充值金额');
        if (!in_array($payType, ['wechat', 'alipay', 'usdt', 'bank'])) $this->error('请选择支付方式');

        // 验证金额范围
        $config = Db::name('payment_config')
            ->where('pay_type', $payType)
            ->where('status', 1)
            ->find();
        if (!$config) $this->error('该支付方式暂不可用');
        if ($amount < $config['min_amount']) $this->error('最低充值 ¥' . $config['min_amount']);
        if ($amount > $config['max_amount']) $this->error('最高充值 ¥' . $config['max_amount']);

        // 处理凭证上传
        $payProof = '';
        $file = $this->request->file('pay_proof');
        if ($file) {
            $info = $file->validate(['size' => 5 * 1024 * 1024, 'ext' => 'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'recharge');
            if ($info) {
                $payProof = '/uploads/recharge/' . $info->getSaveName();
            }
        }

        // USDT 钱包地址
        $usdtAddress = $this->request->post('usdt_address', '');

        $orderNo = 'RC' . date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . mt_rand(100, 999);

        $insertData = [
            'order_no'   => $orderNo,
            'user_id'    => $userId,
            'amount'     => $amount,
            'pay_type'   => $payType,
            'pay_proof'  => $payProof,
            'status'     => 0,
            'createtime' => time(),
            'updatetime' => time(),
        ];
        if ($usdtAddress) $insertData['usdt_address'] = $usdtAddress;

        Db::name('recharge_order')->insert($insertData);

        $this->success('充值申请已提交，请等待后台审核', ['order_no' => $orderNo]);
    }

    /**
     * 上传/补传充值凭证
     * POST /api/payment/uploadProof
     */
    public function uploadProof()
    {
        $userId  = $this->auth->id;
        $orderNo = $this->request->post('order_no', '');

        $order = Db::name('recharge_order')
            ->where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->where('status', 0)
            ->find();
        if (!$order) $this->error('订单不存在或已处理');

        $file = $this->request->file('pay_proof');
        if (!$file) $this->error('请上传转账凭证截图');

        $info = $file->validate(['size' => 5 * 1024 * 1024, 'ext' => 'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'recharge');
        if (!$info) $this->error('上传失败: ' . $file->getError());

        $payProof = '/uploads/recharge/' . $info->getSaveName();

        Db::name('recharge_order')
            ->where('id', $order['id'])
            ->update(['pay_proof' => $payProof, 'updatetime' => time()]);

        $this->success('凭证上传成功', ['pay_proof' => $payProof]);
    }

    /**
     * 提交提现申请
     * POST /api/payment/withdraw
     */
    public function withdraw()
    {
        $userId       = $this->auth->id;
        $amount       = $this->request->post('amount/f', 0);
        $withdrawType = $this->request->post('withdraw_type', '');
        $accountName  = $this->request->post('account_name', '');
        $accountNo    = $this->request->post('account_no', '');
        $usdtAddress  = $this->request->post('usdt_address', '');

        if ($amount <= 0) $this->error('请输入正确的提现金额');
        if ($amount < 10) $this->error('最低提现 ¥10');
        if (!in_array($withdrawType, ['wechat', 'alipay', 'usdt', 'bank'])) $this->error('请选择提现方式');

        // 检查余额
        $user = \app\common\model\User::get($userId);
        if (!$user || $user->money < $amount) {
            $this->error('余额不足，当前余额: ¥' . ($user ? $user->money : 0));
        }

        // 处理收款码上传
        $qrImage = '';
        $file = $this->request->file('qr_image');
        if ($file) {
            $info = $file->validate(['size' => 5 * 1024 * 1024, 'ext' => 'jpg,jpeg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'withdraw');
            if ($info) {
                $qrImage = '/uploads/withdraw/' . $info->getSaveName();
            }
        }

        $accountInfo = json_encode([
            'type'         => $withdrawType,
            'name'         => $accountName,
            'account_no'   => $accountNo,
            'usdt_address' => $usdtAddress,
        ], JSON_UNESCAPED_UNICODE);

        $orderNo = 'WD' . date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . mt_rand(100, 999);

        Db::startTrans();
        try {
            // 冻结余额
            \app\common\model\User::where('id', $userId)->setDec('money', $amount);

            $wdData = [
                'order_no'      => $orderNo,
                'user_id'       => $userId,
                'amount'        => $amount,
                'withdraw_type' => $withdrawType,
                'account_info'  => $accountInfo,
                'qr_image'      => $qrImage,
                'status'        => 0,
                'createtime'    => time(),
                'updatetime'    => time(),
            ];
            if ($usdtAddress) $wdData['usdt_address'] = $usdtAddress;

            Db::name('withdraw_order')->insert($wdData);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('提交失败: ' . $e->getMessage());
        }
        $this->success('提现申请已提交，请等待后台审核', ['order_no' => $orderNo]);
    }

    /**
     * 获取充值/提现记录
     * GET /api/payment/getOrders?type=recharge&page=1
     */
    public function getOrders()
    {
        $userId = $this->auth->id;
        $type   = $this->request->param('type', 'recharge'); // recharge / withdraw
        $page   = $this->request->param('page/d', 1);
        $limit  = $this->request->param('limit/d', 20);

        $table = $type === 'withdraw' ? 'withdraw_order' : 'recharge_order';

        $list = Db::name($table)
            ->where('user_id', $userId)
            ->order('createtime', 'desc')
            ->page($page, $limit)
            ->select();

        $total = Db::name($table)
            ->where('user_id', $userId)
            ->count();

        // 格式化状态
        $statusMap = [0 => '待审核', 1 => ($type === 'withdraw' ? '已打款' : '已到账'), 2 => '已拒绝'];
        foreach ($list as &$item) {
            $item['status_text'] = isset($statusMap[$item['status']]) ? $statusMap[$item['status']] : '未知';
            $item['create_date'] = date('Y-m-d H:i', $item['createtime']);
        }

        $this->success('OK', ['list' => $list, 'total' => $total]);
    }

    /**
     * 后台审核充值（管理员用）
     * POST /api/payment/approveRecharge
     */
    public function approveRecharge()
    {
        $userId = $this->auth->id;
        $user = \app\common\model\User::get($userId);
        if (!$user || !in_array($user->group_id, [1])) $this->error('无权限');

        $orderId = $this->request->post('id/d', 0);
        $action  = $this->request->post('action', ''); // approve / reject
        $remark  = $this->request->post('remark', '');

        $order = Db::name('recharge_order')->where('id', $orderId)->where('status', 0)->find();
        if (!$order) $this->error('订单不存在或已处理');

        if ($action === 'approve') {
            Db::startTrans();
            try {
                Db::name('recharge_order')->where('id', $orderId)->update([
                    'status' => 1, 'admin_remark' => $remark, 'updatetime' => time()
                ]);
                \app\common\model\User::where('id', $order['user_id'])->setInc('money', $order['amount']);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('操作失败');
            }
            $this->success('充值已确认，已到账 ¥' . $order['amount']);
        } elseif ($action === 'reject') {
            Db::name('recharge_order')->where('id', $orderId)->update([
                'status' => 2, 'admin_remark' => $remark, 'updatetime' => time()
            ]);
            $this->success('已拒绝');
        } else {
            $this->error('无效操作');
        }
    }

    /**
     * 后台审核提现（管理员用）
     * POST /api/payment/approveWithdraw
     */
    public function approveWithdraw()
    {
        $userId = $this->auth->id;
        $user = \app\common\model\User::get($userId);
        if (!$user || !in_array($user->group_id, [1])) $this->error('无权限');

        $orderId = $this->request->post('id/d', 0);
        $action  = $this->request->post('action', '');
        $remark  = $this->request->post('remark', '');

        $order = Db::name('withdraw_order')->where('id', $orderId)->where('status', 0)->find();
        if (!$order) $this->error('订单不存在或已处理');

        if ($action === 'approve') {
            Db::name('withdraw_order')->where('id', $orderId)->update([
                'status' => 1, 'admin_remark' => $remark, 'updatetime' => time()
            ]);
            $this->success('提现已确认打款');
        } elseif ($action === 'reject') {
            Db::startTrans();
            try {
                Db::name('withdraw_order')->where('id', $orderId)->update([
                    'status' => 2, 'admin_remark' => $remark, 'updatetime' => time()
                ]);
                // 退回余额
                \app\common\model\User::where('id', $order['user_id'])->setInc('money', $order['amount']);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('操作失败');
            }
            $this->success('已拒绝，余额已退回');
        } else {
            $this->error('无效操作');
        }
    }

    /**
     * 站内转账
     * POST /api/payment/transfer
     */
    public function transfer()
    {
        $userId  = $this->auth->id;
        $toAccount = $this->request->post('to_account', '');
        $amount  = $this->request->post('amount/f', 0);
        $remark  = $this->request->post('remark', '');

        if (empty($toAccount)) $this->error('请输入对方账号');
        if ($amount <= 0) $this->error('请输入正确的转账金额');
        if ($amount < 1) $this->error('最低转账 ¥1');

        // 查找对方用户
        $toUser = Db::name('user')
            ->where('username|mobile|nickname', $toAccount)
            ->find();
        if (!$toUser) $this->error('找不到该用户');
        if ($toUser['id'] == $userId) $this->error('不能给自己转账');

        // 检查发起方余额
        $fromUser = \app\common\model\User::get($userId);
        if (!$fromUser || $fromUser->money < $amount) {
            $this->error('余额不足，当前余额: ¥' . ($fromUser ? $fromUser->money : 0));
        }

        $orderNo = 'TF' . date('YmdHis') . str_pad($userId, 6, '0', STR_PAD_LEFT) . mt_rand(100, 999);

        Db::startTrans();
        try {
            // 扣发起方余额
            \app\common\model\User::where('id', $userId)->setDec('money', $amount);
            // 加收款方余额
            \app\common\model\User::where('id', $toUser['id'])->setInc('money', $amount);

            // 记录
            Db::name('transfer_order')->insert([
                'order_no'     => $orderNo,
                'from_user_id' => $userId,
                'to_user_id'   => $toUser['id'],
                'amount'       => $amount,
                'remark'       => $remark,
                'status'       => 1,
                'createtime'   => time(),
                'updatetime'   => time(),
            ]);

            // 重新查询最新余额
            $newBalance = Db::name('user')->where('id', $userId)->value('money');
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('转账失败: ' . $e->getMessage());
        }
        $this->success('转账成功', [
            'order_no' => $orderNo,
            'to_user'  => $toUser['nickname'] ?: $toUser['username'],
            'amount'   => $amount,
            'balance'  => $newBalance,
        ]);
    }

    /**
     * 获取转账记录
     * GET /api/payment/getTransfers?page=1
     */
    public function getTransfers()
    {
        $userId = $this->auth->id;
        $page   = $this->request->param('page/d', 1);
        $limit  = $this->request->param('limit/d', 20);

        $list = Db::name('transfer_order')
            ->alias('t')
            ->join('user fu', 'fu.id = t.from_user_id', 'LEFT')
            ->join('user tu', 'tu.id = t.to_user_id', 'LEFT')
            ->where('t.from_user_id|t.to_user_id', $userId)
            ->field('t.*, fu.username as from_username, fu.nickname as from_nickname, tu.username as to_username, tu.nickname as to_nickname')
            ->order('t.createtime', 'desc')
            ->page($page, $limit)
            ->select();

        foreach ($list as &$item) {
            $item['direction'] = $item['from_user_id'] == $userId ? 'out' : 'in';
            $item['other_name'] = $item['direction'] === 'out'
                ? ($item['to_nickname'] ?: $item['to_username'])
                : ($item['from_nickname'] ?: $item['from_username']);
            $item['create_date'] = date('Y-m-d H:i', $item['createtime']);
        }

        $total = Db::name('transfer_order')
            ->where('from_user_id|to_user_id', $userId)
            ->count();

        $this->success('OK', ['list' => $list, 'total' => $total]);
    }
}
