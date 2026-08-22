<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\library\Auth;
use think\Db;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';
    protected $noNeedRight = ['profile', 'records', 'toggleStatus', 'saveRebateRates'];

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            // 实名信息存放在独立认证表；旧环境无该表时仍保持会员列表可用。
            $realNames = [];
            try {
                $userIds = [];
                foreach ($list as $item) $userIds[] = $item->id;
                if ($userIds) {
                    $realNames = Db::name('user_verify')
                        ->where('user_id', 'in', array_unique($userIds))
                        ->column('real_name', 'user_id');
                }
            } catch (\Exception $e) {
                $realNames = [];
            }
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->real_name = isset($realNames[$v->id]) ? $realNames[$v->id] : '';
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a', []);
            if (array_key_exists('money', $params)) {
                if (!is_numeric($params['money'])) {
                    $this->error('余额格式不正确');
                }
                $newMoney = round((float)$params['money'], 2);
                $currentMoney = round((float)$row['money'], 2);
                if ($newMoney < 0) {
                    $this->error('余额不能小于0');
                }
                if ($newMoney > $currentMoney) {
                    $this->error('编辑会员时余额只能减少，不能增加');
                }
            }
        }
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }

    /**
     * 启用/禁用会员
     */
    public function toggleStatus()
    {
        $id = $this->request->post('id/d', 0);
        $action = $this->request->post('action', '');
        if (!$id || !in_array($action, ['enable', 'disable'])) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        $user = $this->model->get($id);
        if (!$user) return json(['code' => 0, 'msg' => '用户不存在']);

        $newStatus = ($action === 'enable') ? 'normal' : 'hidden';
        $user->save(['status' => $newStatus]);
        return json(['code' => 1, 'msg' => ($action === 'enable' ? '已启用' : '已禁用')]);
    }

    /**
     * 会员详情（资料 + 各类统计）
     */
    public function profile()
    {
        $id = $this->request->param('ids/d', 0);
        if (!$id) $id = $this->request->param('id/d', 0);
        if (!$id) $this->error('缺少参数');

        $user = \think\Db::name('user')->where('id', $id)->find();
        if (!$user) $this->error('用户不存在');

        unset($user['password'], $user['salt'], $user['token']);
        $user['jointime_fmt'] = $user['jointime'] ? date('Y-m-d H:i:s', $user['jointime']) : '--';
        $user['logintime_fmt'] = $user['logintime'] ? date('Y-m-d H:i:s', $user['logintime']) : '--';

        // 统计汇总
        $stats = [];
        try { $stats['recharge_total'] = \think\Db::name('recharge_order')->where('user_id', $id)->where('status', 1)->sum('amount'); } catch (\Exception $e) { $stats['recharge_total'] = 0; }
        try { $stats['recharge_count'] = \think\Db::name('recharge_order')->where('user_id', $id)->where('status', 1)->count(); } catch (\Exception $e) { $stats['recharge_count'] = 0; }
        try { $stats['bet_total'] = \think\Db::name('lottery_bet')->where('user_id', $id)->where('status', '<>', 3)->sum('total_amount'); } catch (\Exception $e) { $stats['bet_total'] = 0; }
        try { $stats['bet_count'] = \think\Db::name('lottery_bet')->where('user_id', $id)->where('status', '<>', 3)->count(); } catch (\Exception $e) { $stats['bet_count'] = 0; }
        try { $stats['win_total'] = \think\Db::name('lottery_bet')->where('user_id', $id)->where('status', 1)->sum('win_amount'); } catch (\Exception $e) { $stats['win_total'] = 0; }
        try { $stats['withdraw_total'] = \think\Db::name('withdraw_order')->where('user_id', $id)->where('status', 1)->sum('amount'); } catch (\Exception $e) { $stats['withdraw_total'] = 0; }
        try { $stats['xima_total'] = \think\Db::name('xima_record')->where('user_id', $id)->sum('xima_amount'); } catch (\Exception $e) { $stats['xima_total'] = 0; }
        try { $stats['commission_total'] = \think\Db::name('lottery_commission')->where('user_id', $id)->sum('amount'); } catch (\Exception $e) { $stats['commission_total'] = 0; }
        try { $stats['rebate_total'] = \think\Db::name('rebate_record')->where('user_id', $id)->where('status', 1)->sum('rebate_amount'); } catch (\Exception $e) { $stats['rebate_total'] = 0; }
        try { $stats['transfer_out'] = \think\Db::name('transfer_order')->where('from_user_id', $id)->sum('amount'); } catch (\Exception $e) { $stats['transfer_out'] = 0; }
        try { $stats['transfer_in'] = \think\Db::name('transfer_order')->where('to_user_id', $id)->sum('amount'); } catch (\Exception $e) { $stats['transfer_in'] = 0; }

        // 邀请人信息
        $parent = null;
        if (!empty($user['pid']) && $user['pid'] > 0) {
            $parent = \think\Db::name('user')->where('id', $user['pid'])->field('id,username,nickname')->find();
        }

        // 下级数
        try { $stats['sub_count'] = \think\Db::name('user')->where('pid', $id)->count(); } catch (\Exception $e) { $stats['sub_count'] = 0; }

        $this->view->assign('user', $user);
        $this->view->assign('stats', $stats);
        $this->view->assign('parent', $parent);
        return $this->view->fetch();
    }

    /**
     * 保存返佣参数
     */
    public function saveRebateRates()
    {
        $id = $this->request->param('id/d', 0);
        if (!$id) return json(['code' => 0, 'msg' => '缺少参数']);

        $selfRate   = $this->request->param('self_rebate_rate/f', 0);
        $inviteRate = $this->request->param('invite_rebate_rate/f', 0);

        if ($selfRate < 0 || $selfRate > 1 || $inviteRate < 0 || $inviteRate > 1) {
            return json(['code' => 0, 'msg' => '比例必须在 0~1 之间']);
        }

        try {
            $user = \think\Db::name('user')->where('id', $id)->find();
            if (!$user) return json(['code' => 0, 'msg' => '用户不存在']);

            \think\Db::name('user')->where('id', $id)->update([
                'self_rebate_rate'   => round($selfRate, 4),
                'invite_rebate_rate' => round($inviteRate, 4),
            ]);

            return json(['code' => 1, 'msg' => '保存成功! 自身返佣 ' . round($selfRate * 100, 2) . '%, 邀请返佣 ' . round($inviteRate * 100, 2) . '%']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '保存失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 会员记录列表（AJAX）
     */
    public function records()
    {
        $userId = $this->request->param('user_id/d', 0);
        $type   = $this->request->param('type', 'bet');
        $page   = $this->request->param('page/d', 1);
        $limit  = $this->request->param('limit/d', 20);

        if (!$userId) return json(['code' => 0, 'msg' => '缺少参数']);

        $list = [];
        $total = 0;

        try {
            switch ($type) {
                case 'recharge':
                    $list = \think\Db::name('recharge_order')->where('user_id', $userId)->order('createtime', 'desc')->page($page, $limit)->select();
                    $total = \think\Db::name('recharge_order')->where('user_id', $userId)->count();
                    foreach ($list as &$item) {
                        $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                        $item['status_text'] = ['待审核', '已到账', '已拒绝'][$item['status']] ?? '未知';
                    }
                    break;

                case 'bet':
                    $list = \think\Db::name('lottery_bet')->where('user_id', $userId)->order('createtime', 'desc')->page($page, $limit)->select();
                    $total = \think\Db::name('lottery_bet')->where('user_id', $userId)->count();
                    $typeMap = [1 => '福彩3D', 2 => '排列三'];
                    $playMap = ['kuaijie'=>'快捷','shuangmian'=>'双面','daxiao'=>'大小','danshuang'=>'单双','longhu'=>'龙虎','zonghe_daxiao'=>'总和大小','zonghe_danshuang'=>'总和单双','biaozhun'=>'标准','dingwei'=>'定位','yizi'=>'一字组合','erzi'=>'二字组合','sanzi'=>'三字组合','zusan'=>'组三','zuliu'=>'组六','hezhi'=>'和值','zhixuan'=>'直选','danshi'=>'单式'];
                    $statusMap = [0 => '待开奖', 1 => '已中奖', 2 => '未中奖', 3 => '已取消'];
                    $betKeyMap = ['zhi'=>'质','da'=>'大','xiao'=>'小','dan'=>'单','shuang'=>'双','long'=>'龙','hu'=>'虎','he'=>'和','baozi'=>'豹子','shunzi'=>'顺子','duizi'=>'对子','banshun'=>'半顺','zaliu'=>'杂六','zonghe_da'=>'总和大','zonghe_xiao'=>'总和小','zonghe_dan'=>'总和单','zonghe_shuang'=>'总和双'];

                    foreach ($list as &$item) {
                        $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                        $item['lottery_name'] = $typeMap[$item['lottery_type']] ?? '未知';
                        $item['play_name'] = $playMap[$item['play_type']] ?? ($item['play_type'] ?? '-');
                        $item['status_text'] = $statusMap[$item['status']] ?? '未知';

                        // 解析投注内容为可读文本
                        $betDisplay = '-';
                        if (!empty($item['bet_content'])) {
                            $bets = json_decode($item['bet_content'], true);
                            if (is_array($bets)) {
                                $parts = [];
                                foreach ($bets as $b) {
                                    $k = is_array($b) ? ($b['key'] ?? '') : $b;
                                    if (preg_match('/^num_(\d)$/', $k, $m)) $parts[] = $m[1];
                                    elseif (preg_match('/^\d+$/', $k)) $parts[] = $k;
                                    else $parts[] = $betKeyMap[$k] ?? $k;
                                }
                                $betDisplay = implode(' ', $parts);
                            } else {
                                $betDisplay = mb_substr($item['bet_content'], 0, 30);
                            }
                        }
                        $item['bet_display'] = $betDisplay;

                        // 开奖号码
                        $item['draw_result'] = $item['draw_result'] ?? '-';
                    }
                    unset($item);

                    // 该用户全量汇总
                    $summary = [
                        'total_bet'     => \think\Db::name('lottery_bet')->where('user_id', $userId)->where('status', '<>', 3)->sum('total_amount'),
                        'total_win'     => \think\Db::name('lottery_bet')->where('user_id', $userId)->where('status', 1)->sum('win_amount'),
                        'total_count'   => \think\Db::name('lottery_bet')->where('user_id', $userId)->count(),
                        'win_count'     => \think\Db::name('lottery_bet')->where('user_id', $userId)->where('status', 1)->count(),
                        'pending_count' => \think\Db::name('lottery_bet')->where('user_id', $userId)->where('status', 0)->count(),
                    ];
                    // 该用户的反佣、返利、自身洗码
                    $userCommission = \think\Db::name('lottery_commission')->where('user_id', $userId)->sum('amount') ?: 0;
                    $userRebate = \think\Db::name('rebate_record')->where('user_id', $userId)->where('status', 1)->sum('rebate_amount') ?: 0;
                    $userXimaSelf = \think\Db::name('xima_record')->where('user_id', $userId)->where('type', 'self')->sum('xima_amount') ?: 0;
                    $summary['profit'] = round($summary['total_bet'] - $summary['total_win'] - $userCommission - $userRebate - $userXimaSelf, 2);
                    break;

                case 'withdraw':
                    $list = \think\Db::name('withdraw_order')->where('user_id', $userId)->order('createtime', 'desc')->page($page, $limit)->select();
                    $total = \think\Db::name('withdraw_order')->where('user_id', $userId)->count();
                    foreach ($list as &$item) {
                        $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                        $item['status_text'] = ['待审核', '已打款', '已拒绝'][$item['status']] ?? '未知';
                    }
                    break;

                case 'transfer':
                    $list = \think\Db::name('transfer_order')
                        ->where('from_user_id|to_user_id', $userId)
                        ->order('createtime', 'desc')->page($page, $limit)->select();
                    $total = \think\Db::name('transfer_order')->where('from_user_id|to_user_id', $userId)->count();
                    foreach ($list as &$item) {
                        $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                        $item['direction'] = ($item['from_user_id'] == $userId) ? '转出' : '转入';
                        $otherId = ($item['from_user_id'] == $userId) ? $item['to_user_id'] : $item['from_user_id'];
                        $item['other_name'] = \think\Db::name('user')->where('id', $otherId)->value('nickname') ?: 'UID:'.$otherId;
                    }
                    break;

                case 'commission':
                    $list = \think\Db::name('lottery_commission')->where('user_id', $userId)->order('createtime', 'desc')->page($page, $limit)->select();
                    $total = \think\Db::name('lottery_commission')->where('user_id', $userId)->count();
                    foreach ($list as &$item) {
                        $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                        $item['sub_name'] = \think\Db::name('user')->where('id', $item['sub_id'])->value('nickname') ?: 'UID:'.$item['sub_id'];
                    }
                    break;

                case 'xima':
                    $list = \think\Db::name('xima_record')->where('user_id', $userId)->order('createtime', 'desc')->page($page, $limit)->select();
                    $total = \think\Db::name('xima_record')->where('user_id', $userId)->count();
                    foreach ($list as &$item) {
                        $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                        $item['type_text'] = $item['type'] === 'self' ? '自身洗码' : '邀请人洗码';
                        $item['status_text'] = ['待领取', '已领取', '已过期'][$item['status']] ?? '未知';
                    }
                    break;

                case 'rebate':
                    $list = \think\Db::name('rebate_record')->where('user_id', $userId)->order('createtime', 'desc')->page($page, $limit)->select();
                    $total = \think\Db::name('rebate_record')->where('user_id', $userId)->count();
                    foreach ($list as &$item) {
                        $item['create_date'] = date('Y-m-d H:i:s', $item['createtime']);
                        $item['status_text'] = $item['status'] == 1 ? '已领取' : '待领取';
                    }
                    break;
            }
        } catch (\Exception $e) {
            // 表不存在则空
        }

        $result = ['list' => $list, 'total' => $total];
        if (isset($summary)) $result['summary'] = $summary;
        return json(['code' => 1, 'data' => $result]);
    }

}
