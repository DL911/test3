<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Token;
use app\common\model\User as UserModel;
use fast\Random;
use think\Db;

/** 代理开户、团队与级差收益 API。 */
class Agent extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function summary()
    {
        $userId = $this->auth->id;
        $user = $this->currentUser();
        $todayStart = strtotime('today');
        $directCount = Db::name('user')->where('pid', $userId)->count();
        $claimed = Db::name('xima_record')->where('user_id', $userId)->where('type', 'parent')->where('status', 1)->sum('xima_amount');
        $todayPending = Db::name('xima_record')->where('user_id', $userId)->where('type', 'parent')->where('status', 0)->where('createtime', '>=', $todayStart)->sum('xima_amount');
        $claimable = Db::name('xima_record')->where('user_id', $userId)->where('type', 'parent')->where('status', 0)->where('unlock_date', '<=', date('Y-m-d'))->sum('xima_amount');
        $rate = floatval($user['self_rebate_rate']);
        $this->success('success', [
            'is_agent' => intval($user['is_agent']),
            'can_open_account' => intval($user['can_open_account']),
            'my_rate' => $rate,
            'my_rate_text' => $this->rateText($rate),
            'team_size' => $directCount,
            'direct_count' => $directCount,
            'team_count' => $this->countTeam($userId),
            'total_commission' => number_format($claimed, 2, '.', ''),
            'today_pending' => number_format($todayPending, 2, '.', ''),
            'claimable' => number_format($claimable, 2, '.', ''),
            'commission_rate' => $this->rateText($rate),
            'commission_rate_raw' => $rate,
        ]);
    }

    public function subordinates()
    {
        $userId = $this->auth->id;
        $parent = $this->currentUser();
        $list = Db::name('user')->where('pid', $userId)
            ->field('id,username,nickname,mobile,money,is_agent,can_open_account,self_rebate_rate,status,createtime')
            ->order('createtime', 'desc')->select();
        foreach ($list as &$item) {
            $item['name'] = $item['nickname'] ?: $item['username'];
            $item['date'] = date('Y-m-d H:i', $item['createtime']);
            $item['account_type'] = intval($item['is_agent']) === 1 ? 'agent' : 'user';
            $item['account_type_text'] = intval($item['is_agent']) === 1 ? '代理用户' : '普通用户';
            $item['status_text'] = $item['status'] === 'normal' ? '正常' : '已禁用';
            $item['status_action_text'] = $item['status'] === 'normal' ? '禁用' : '启用';
            $item['rebate_rate'] = floatval($item['self_rebate_rate']);
            $item['rebate_rate_text'] = $this->rateText($item['rebate_rate']);
            $item['rate_gap'] = max(0, floatval($parent['self_rebate_rate']) - $item['rebate_rate']);
            $item['rate_gap_text'] = $this->rateText($item['rate_gap']);
            $item['sub_count'] = Db::name('user')->where('pid', $item['id'])->count();
        }
        unset($item);
        $this->success('success', $list);
    }

    /** 代理与直属账号之间划转余额，充值仍由后台客服向代理账户办理。 */
    public function transferBalance()
    {
        $agent = $this->currentUser(true);
        $subId = $this->request->post('user_id/d', 0);
        $direction = $this->request->post('direction', '');
        $amountText = trim((string)$this->request->post('amount', ''));
        $remark = trim((string)$this->request->post('remark', ''));
        $requestId = trim((string)$this->request->post('request_id', ''));
        if (!in_array($direction, ['give', 'take'], true)) $this->error('划转方向不正确');
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amountText) || floatval($amountText) <= 0) $this->error('金额须为正数，最多两位小数');
        if ($direction === 'take' && ($remark === '' || mb_strlen($remark) > 200)) $this->error('扣款原因必填，最多200字');
        if (mb_strlen($remark) > 200) $this->error('备注最多200字');
        if (!preg_match('/^[A-Za-z0-9-]{16,64}$/', $requestId)) $this->error('请求标识不正确，请刷新页面重试');
        $cents = intval(round(floatval($amountText) * 100));
        if ($cents <= 0) $this->error('金额不正确');
        $amount = number_format($cents / 100, 2, '.', '');
        Db::startTrans();
        try {
            $previous = Db::name('agent_balance_transfer')->where('agent_id', $agent['id'])->where('request_id', $requestId)->find();
            if ($previous) throw new \RuntimeException('该操作已提交，请查看划转记录');
            // 统一按 ID 顺序锁定账户，避免相反方向并发划转死锁。
            $ids = [intval($agent['id']), intval($subId)];
            if ($subId <= 0 || $subId === intval($agent['id'])) throw new \RuntimeException('直属账号不正确');
            sort($ids, SORT_NUMERIC);
            $users = [];
            foreach ($ids as $id) $users[$id] = Db::name('user')->where('id', $id)->lock(true)->field('id,pid,agent_level,status,money')->find();
            $parent = $users[intval($agent['id'])];
            $child = $users[intval($subId)];
            if (!$parent || $parent['status'] !== 'normal' || !$child || intval($child['pid']) !== intval($agent['id']) || intval($child['agent_level']) !== intval($parent['agent_level']) + 1) throw new \RuntimeException('只能操作新代理体系的直属账号');
            $fromId = $direction === 'give' ? intval($agent['id']) : intval($subId);
            $toId = $direction === 'give' ? intval($subId) : intval($agent['id']);
            $fromBefore = intval(round(floatval($users[$fromId]['money']) * 100));
            $toBefore = intval(round(floatval($users[$toId]['money']) * 100));
            if ($fromBefore < $cents) throw new \RuntimeException('转出账户余额不足');
            $fromAfter = number_format(($fromBefore - $cents) / 100, 2, '.', '');
            $toAfter = number_format(($toBefore + $cents) / 100, 2, '.', '');
            $now = time();
            $orderNo = 'AT' . date('YmdHis') . str_pad($agent['id'], 6, '0', STR_PAD_LEFT) . mt_rand(100000, 999999);
            Db::name('user')->where('id', $fromId)->update(['money' => $fromAfter, 'updatetime' => $now]);
            Db::name('user')->where('id', $toId)->update(['money' => $toAfter, 'updatetime' => $now]);
            Db::name('agent_balance_transfer')->insert(['order_no' => $orderNo, 'request_id' => $requestId, 'agent_id' => $agent['id'], 'sub_id' => $subId, 'direction' => $direction, 'amount' => $amount, 'remark' => $remark, 'from_user_id' => $fromId, 'to_user_id' => $toId, 'from_before' => $fromBefore / 100, 'from_after' => $fromAfter, 'to_before' => $toBefore / 100, 'to_after' => $toAfter, 'operator_ip' => $this->request->ip(), 'createtime' => $now]);
            Db::name('user_money_log')->insert(['user_id' => $fromId, 'money' => -floatval($amount), 'before' => $fromBefore / 100, 'after' => $fromAfter, 'memo' => '代理余额划转转出：' . $orderNo, 'createtime' => $now]);
            Db::name('user_money_log')->insert(['user_id' => $toId, 'money' => floatval($amount), 'before' => $toBefore / 100, 'after' => $toAfter, 'memo' => '代理余额划转转入：' . $orderNo, 'createtime' => $now]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('划转失败：' . $e->getMessage());
        }
        $this->success('划转成功', ['order_no' => $orderNo, 'agent_balance' => $direction === 'give' ? $fromAfter : $toAfter, 'sub_balance' => $direction === 'give' ? $toAfter : $fromAfter]);
    }

    public function transferHistory()
    {
        $agent = $this->currentUser(true);
        $page = max(1, $this->request->get('page/d', 1));
        $limit = min(50, max(1, $this->request->get('limit/d', 20)));
        $query = Db::name('agent_balance_transfer')->alias('t')->join('user u', 'u.id=t.sub_id')->where('t.agent_id', $agent['id']);
        $total = (clone $query)->count();
        $list = $query->field('t.*,u.username as sub_name')->order('t.id', 'desc')->page($page, $limit)->select();
        foreach ($list as &$row) $row['date'] = date('Y-m-d H:i:s', $row['createtime']);
        $this->success('success', ['list' => $list, 'total' => $total]);
    }

    public function teamBets()
    {
        $agent = $this->currentUser(true);
        $rootId = intval($agent['id']);
        $directId = $this->request->get('direct_id/d', 0);
        $keyword = trim((string)$this->request->get('keyword', ''));
        $type = $this->request->get('lottery_type/d', 0);
        $status = $this->request->get('status', '');
        $start = $this->request->get('date_start', date('Y-m-d', strtotime('-6 days')));
        $end = $this->request->get('date_end', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || strtotime($start) > strtotime($end) || strtotime($end) - strtotime($start) > 31 * 86400) $this->error('请选择不超过31天的有效日期范围');
        $page = max(1, $this->request->get('page/d', 1));
        $limit = min(50, max(1, $this->request->get('limit/d', 20)));
        $frontier = [$rootId]; $seen = [$rootId => true]; $branches = [];
        for ($depth = 1; $depth <= 100 && $frontier; $depth++) {
            $children = Db::name('user')->where('pid', 'in', $frontier)->where('agent_level', '>', 0)->field('id,pid')->select();
            $frontier = [];
            foreach ($children as $child) {
                $id = intval($child['id']); $pid = intval($child['pid']);
                if (isset($seen[$id])) continue;
                $seen[$id] = true; $frontier[] = $id;
                $branches[$id] = ['direct_id' => $pid === $rootId ? $id : $branches[$pid]['direct_id'], 'distance' => $depth];
            }
        }
        $ids = array_keys($branches);
        if ($directId) $ids = array_values(array_filter($ids, function ($id) use ($branches, $directId) { return $branches[$id]['direct_id'] === $directId; }));
        if (!$ids) $this->success('success', ['list' => [], 'total' => 0, 'bet_amount' => 0, 'win_amount' => 0]);
        $query = Db::name('lottery_bet')->alias('b')->join('user u', 'u.id=b.user_id')->where('b.user_id', 'in', $ids)->where('b.createtime', 'between', [strtotime($start), strtotime($end) + 86399]);
        if ($type) $query->where('b.lottery_type', $type);
        if ($status !== '' && in_array((string)$status, ['0','1','2','3'], true)) $query->where('b.status', intval($status));
        if ($keyword !== '') $query->where('b.order_no|u.username', 'like', '%' . addcslashes($keyword, '%_\\') . '%');
        $total = (clone $query)->count();
        $betAmount = (clone $query)->where('b.status', '<>', 3)->sum('b.total_amount');
        $winAmount = (clone $query)->where('b.status', 1)->sum('b.win_amount');
        $list = $query->field('b.id,b.order_no,b.user_id,b.lottery_type,b.period,b.play_type,b.panel_type,b.bet_content,b.total_amount,b.win_amount,b.status,b.createtime,u.username')->order('b.createtime desc,b.id desc')->page($page, $limit)->select();
        foreach ($list as &$row) { $row['date'] = date('Y-m-d H:i:s', $row['createtime']); $row['direct_id'] = $branches[intval($row['user_id'])]['direct_id']; $row['distance'] = $branches[intval($row['user_id'])]['distance']; }
        $this->success('success', ['list' => $list, 'total' => $total, 'bet_amount' => round($betAmount, 2), 'win_amount' => round($winAmount, 2)]);
    }

    public function createAccount()
    {
        $agent = $this->currentUser(true);
        $username = trim($this->request->post('username', ''));
        $mobile = trim($this->request->post('mobile', ''));
        $password = (string)$this->request->post('password', '');
        $accountType = $this->request->post('account_type', 'user');
        $ratePercent = $this->request->post('rebate_rate/f', -1);
        if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $username)) $this->error('用户名需为4-20位字母、数字或下划线');
        if ($mobile !== '' && !preg_match('/^1[3-9]\d{9}$/', $mobile)) $this->error('手机号格式不正确');
        if (strlen($password) < 6 || strlen($password) > 30) $this->error('密码长度需为6-30位');
        if (!in_array($accountType, ['user', 'agent'], true)) $this->error('账户类型不正确');

        $rate = round($ratePercent / 100, 6);
        $parentRate = floatval($agent['self_rebate_rate']);
        $minGap = floatval(Db::name('agent_config')->where('id', 1)->value('min_rate_gap'));
        if ($rate < 0 || $rate > $parentRate + 0.0000001) $this->error('下级返水比例必须在0%～' . $this->rateText($parentRate) . '之间');
        if ($minGap > 0 && $rate > 0 && $parentRate - $rate + 0.0000001 < $minGap) $this->error('与下级的比例差不能低于' . $this->rateText($minGap));
        if (UserModel::getByUsername($username) || ($mobile && UserModel::getByMobile($mobile))) $this->error('用户名或手机号已存在');

        $now = time();
        $salt = Random::alnum();
        Db::startTrans();
        try {
            $user = UserModel::create([
                'pid' => intval($agent['id']),
                'is_agent' => $accountType === 'agent' ? 1 : 0,
                'can_open_account' => $accountType === 'agent' ? 1 : 0,
                'agent_level' => intval($agent['agent_level']) + 1,
                'username' => $username,
                'nickname' => $username,
                'password' => $this->auth->getEncryptPassword($password, $salt),
                'salt' => $salt,
                'email' => '',
                'mobile' => $mobile,
                'avatar' => '',
                'level' => 1,
                'score' => 0,
                'money' => 0,
                'self_rebate_rate' => $rate,
                'invite_rebate_rate' => 0,
                'jointime' => $now,
                'joinip' => $this->request->ip(),
                'logintime' => 0,
                'loginip' => '',
                'prevtime' => 0,
                'status' => 'normal',
                'createtime' => $now,
                'updatetime' => $now,
            ], true);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('开户失败：' . $e->getMessage());
        }
        $this->success('开户成功', [
            'id' => $user->id,
            'username' => $username,
            'account_type' => $accountType,
            'rebate_rate' => $rate,
            'rebate_rate_text' => $this->rateText($rate),
            'rate_gap_text' => $this->rateText(max(0, $parentRate - $rate)),
        ]);
    }

    public function updateRate()
    {
        $agent = $this->currentUser(true);
        $subId = $this->request->post('user_id/d', 0);
        $rate = round($this->request->post('rebate_rate/f', -1) / 100, 6);
        $sub = Db::name('user')->where('id', $subId)->where('pid', $agent['id'])->find();
        if (!$sub) $this->error('直属用户不存在');
        $maxChildRate = floatval(Db::name('user')->where('pid', $subId)->max('self_rebate_rate'));
        $parentRate = floatval($agent['self_rebate_rate']);
        if ($rate < $maxChildRate - 0.0000001 || $rate > $parentRate + 0.0000001) {
            $this->error('合法调整范围为' . $this->rateText($maxChildRate) . '～' . $this->rateText($parentRate));
        }
        Db::name('user')->where('id', $subId)->update(['self_rebate_rate' => $rate, 'updatetime' => time()]);
        $this->success('比例调整成功，新比例仅影响之后的投注');
    }

    public function resetPassword()
    {
        $agent = $this->currentUser(true);
        $subId = $this->request->post('user_id/d', 0);
        $password = (string)$this->request->post('password', '');
        if (strlen($password) < 6 || strlen($password) > 30) $this->error('密码长度需为6-30位');
        $sub = Db::name('user')->where('id', $subId)->where('pid', $agent['id'])->find();
        if (!$sub) $this->error('直属用户不存在');
        $salt = Random::alnum();
        Db::startTrans();
        try {
            $updated = Db::name('user')->where('id', $subId)->update([
                'salt' => $salt,
                'password' => $this->auth->getEncryptPassword($password, $salt),
                'updatetime' => time(),
            ]);
            if ($updated !== 1) throw new \RuntimeException('密码更新失败');
            // 通过框架 Token 驱动清理，兼容 MySQL、Redis 等配置。
            Token::clear($subId);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('密码重置失败：' . $e->getMessage());
        }
        $this->success('密码已重置，该用户需要重新登录');
    }

    public function toggleStatus()
    {
        $agent = $this->currentUser(true);
        $subId = $this->request->post('user_id/d', 0);
        $sub = Db::name('user')->where('id', $subId)->where('pid', $agent['id'])->find();
        if (!$sub) $this->error('直属用户不存在');
        $status = $sub['status'] === 'normal' ? 'hidden' : 'normal';
        Db::name('user')->where('id', $subId)->update(['status' => $status, 'updatetime' => time()]);
        if ($status !== 'normal') Token::clear($subId);
        $this->success($status === 'normal' ? '账号已启用' : '账号已禁用');
    }

    public function commission_logs()
    {
        $userId = $this->auth->id;
        $list = Db::name('xima_record')->where('user_id', $userId)->where('type', 'parent')->order('createtime', 'desc')->limit(100)->select();
        foreach ($list as &$item) {
            $item['date'] = date('Y-m-d H:i:s', $item['createtime']);
            $item['amount'] = $item['xima_amount'];
            $item['rate_text'] = $this->rateText(floatval($item['xima_rate']));
            $item['remark'] = $item['status'] == 1 ? '级差奖励（已领取）' : ($item['status'] == 2 ? '级差奖励（已作废）' : ($item['unlock_date'] <= date('Y-m-d') ? '级差奖励（可领取）' : '级差奖励（次日可领）'));
            $sub = Db::name('user')->where('id', $item['source_user_id'])->field('username,nickname')->find();
            $item['sub_name'] = $sub ? ($sub['nickname'] ?: $sub['username']) : '--';
        }
        unset($item);
        $this->success('success', $list);
    }

    private function currentUser($requireAgent = false)
    {
        $user = Db::name('user')->where('id', $this->auth->id)->field('id,pid,is_agent,can_open_account,agent_level,self_rebate_rate,status')->find();
        if (!$user || $user['status'] !== 'normal') $this->error('账号不可用');
        if (intval($user['agent_level']) <= 0) $this->error('历史账号不属于新代理体系');
        if ($requireAgent && (intval($user['is_agent']) !== 1 || intval($user['can_open_account']) !== 1)) $this->error('当前账号没有代理开户权限');
        return $user;
    }

    private function countTeam($rootId)
    {
        $frontier = [intval($rootId)];
        $seen = [];
        $count = 0;
        for ($depth = 0; $depth < 100 && $frontier; $depth++) {
            $ids = Db::name('user')->where('pid', 'in', $frontier)->column('id');
            $frontier = [];
            foreach ($ids as $id) {
                $id = intval($id);
                if (!isset($seen[$id])) {
                    $seen[$id] = true;
                    $frontier[] = $id;
                    $count++;
                }
            }
        }
        return $count;
    }

    private function rateText($rate)
    {
        return rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.') . '%';
    }
}
